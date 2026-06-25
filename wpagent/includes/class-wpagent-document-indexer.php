<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Document_Indexer {
	private $repository;

	public function __construct( WPAgent_Repository $repository ) {
		$this->repository = $repository;
	}

	public function index_attachment( $post_id, $agent_slug, $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		$mime = get_post_mime_type( $attachment_id );

		$source_id = $this->repository->upsert_training_source(
			array(
				'agent_slug'    => $agent_slug,
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
				'source_type'   => 'upload',
				'title'         => get_the_title( $attachment_id ),
				'filename'      => $path ? basename( $path ) : '',
				'url'           => wp_get_attachment_url( $attachment_id ),
				'mime'          => $mime,
				'status'        => 'processing',
				'status_message'=> __( 'Extraindo texto e criando indice.', 'wpagent' ),
			)
		);

		if ( ! $source_id ) {
			return new WP_Error( 'wpagent_source_not_created', __( 'Nao foi possivel registrar a fonte de treinamento.', 'wpagent' ) );
		}

		$extracted = $this->extract_text( $path, $mime );
		if ( is_wp_error( $extracted ) ) {
			$this->repository->replace_source_chunks( $source_id, $agent_slug, array() );
			$this->repository->mark_training_source(
				$source_id,
				'needs_extraction',
				$extracted->get_error_message(),
				array(
					'chunk_count' => 0,
					'char_count'  => 0,
				)
			);

			return $extracted;
		}

		$text = trim( (string) $extracted );
		if ( '' === $text ) {
			$this->repository->replace_source_chunks( $source_id, $agent_slug, array() );
			$this->repository->mark_training_source(
				$source_id,
				'empty',
				__( 'Nenhum texto util foi encontrado neste documento.', 'wpagent' ),
				array(
					'chunk_count' => 0,
					'char_count'  => 0,
				)
			);

			return new WP_Error( 'wpagent_empty_document', __( 'Nenhum texto util foi encontrado neste documento.', 'wpagent' ) );
		}

		$quality = 'application/pdf' === $mime ? $this->assess_text_quality( $text ) : array( 'acceptable' => true );
		if ( empty( $quality['acceptable'] ) ) {
			$this->repository->replace_source_chunks( $source_id, $agent_slug, array() );
			$this->repository->mark_training_source(
				$source_id,
				'extraction_insufficient',
				$quality['message'],
				array(
					'chunk_count' => 0,
					'char_count'  => strlen( $text ),
				)
			);

			return new WP_Error( 'wpagent_extraction_insufficient', $quality['message'] );
		}

		$chunks = $this->chunk_text( $text, $mime );
		$this->repository->replace_source_chunks( $source_id, $agent_slug, $chunks );
		$this->repository->mark_training_source(
			$source_id,
			'indexed',
			sprintf(
				/* translators: %d: number of indexed chunks. */
				__( '%d trechos indexados.', 'wpagent' ),
				count( $chunks )
			),
			array(
				'chunk_count' => count( $chunks ),
				'char_count'  => strlen( $text ),
			)
		);

		return $source_id;
	}

	public function index_manual_text( $post_id, $agent_slug, $title, $text ) {
		$text = $this->clean_text( $text );
		if ( '' === $text ) {
			return 0;
		}

		$source_id = $this->repository->upsert_training_source(
			array(
				'agent_slug'     => $agent_slug,
				'post_id'        => $post_id,
				'attachment_id'  => 0,
				'source_type'    => 'manual',
				'title'          => $title ?: __( 'Texto manual de treinamento', 'wpagent' ),
				'filename'       => '',
				'url'            => '',
				'mime'           => 'text/markdown',
				'status'         => 'processing',
				'status_message' => __( 'Indexando texto manual.', 'wpagent' ),
			)
		);

		if ( ! $source_id ) {
			return 0;
		}

		$chunks = $this->chunk_text( $text, 'text/markdown' );
		$this->repository->replace_source_chunks( $source_id, $agent_slug, $chunks );
		$this->repository->mark_training_source(
			$source_id,
			'indexed',
			sprintf(
				/* translators: %d: number of indexed chunks. */
				__( '%d trechos indexados a partir do texto manual.', 'wpagent' ),
				count( $chunks )
			),
			array(
				'chunk_count' => count( $chunks ),
				'char_count'  => strlen( $text ),
			)
		);

		return $source_id;
	}

	public function extract_text( $path, $mime ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return new WP_Error( 'wpagent_missing_file', __( 'Arquivo nao encontrado para indexacao.', 'wpagent' ) );
		}

		if ( 'application/pdf' === $mime ) {
			return $this->extract_pdf_text( $path );
		}

		if ( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' === $mime ) {
			return $this->extract_docx_text( $path );
		}

		$allowed_mimes = array(
			'text/plain',
			'text/markdown',
			'text/csv',
			'text/html',
			'application/json',
		);

		if ( ! in_array( $mime, $allowed_mimes, true ) ) {
			return new WP_Error( 'wpagent_unsupported_mime', __( 'Tipo de arquivo ainda nao suportado para extracao automatica.', 'wpagent' ) );
		}

		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return new WP_Error( 'wpagent_read_failed', __( 'Nao foi possivel ler o arquivo enviado.', 'wpagent' ) );
		}

		return $this->clean_text( $contents );
	}

	private function extract_pdf_text( $path ) {
		$filtered = apply_filters( 'wpagent_extract_pdf_text', '', $path );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return $this->clean_text( $filtered );
		}

		$php_text = $this->extract_pdf_text_in_php( $path );
		if ( is_string( $php_text ) && '' !== trim( $php_text ) ) {
			return $this->clean_text( $php_text );
		}

		if ( $this->can_shell_exec() ) {
			foreach ( $this->pdftotext_candidates() as $binary ) {
				$command = escapeshellarg( $binary ) . ' -layout -enc UTF-8 ' . escapeshellarg( $path ) . ' - 2>&1';
				$output  = shell_exec( $command );

				if ( is_string( $output ) && '' !== trim( $output ) && false === stripos( $output, 'command not found' ) && false === stripos( $output, 'not recognized' ) ) {
					return $this->clean_text( $output );
				}
			}
		}

		return new WP_Error(
			'wpagent_pdf_extractor_missing',
			__( 'PDF recebido, mas nao foi possivel extrair texto dele. Instale pdftotext no servidor, use um PDF com texto selecionavel ou conecte um extrator/OCR pelo filtro wpagent_extract_pdf_text.', 'wpagent' )
		);
	}

	private function extract_pdf_text_in_php( $path ) {
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return '';
		}

		$text_parts = array();
		if ( preg_match_all( '/<<(.*?)>>\s*stream\s*\r?\n?(.*?)\r?\n?endstream/s', $raw, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$dictionary = $match[1];
				$stream     = $match[2];

				if ( false !== strpos( $dictionary, '/FlateDecode' ) ) {
					$stream = $this->inflate_pdf_stream( $stream );
				}

				if ( ! is_string( $stream ) || '' === $stream ) {
					continue;
				}

				$stream_text = $this->extract_text_from_pdf_stream( $stream );
				if ( '' !== trim( $stream_text ) ) {
					$text_parts[] = $stream_text;
				}
			}
		}

		if ( empty( $text_parts ) ) {
			$text_parts[] = $this->extract_text_from_pdf_stream( $raw );
		}

		return trim( implode( "\n\n", $text_parts ) );
	}

	private function inflate_pdf_stream( $stream ) {
		$stream = ltrim( $stream, "\r\n" );
		$stream = rtrim( $stream, "\r\n" );

		$attempts = array(
			static function ( $value ) {
				return @gzuncompress( $value );
			},
			static function ( $value ) {
				return @gzdecode( $value );
			},
			static function ( $value ) {
				return @gzinflate( $value );
			},
			static function ( $value ) {
				return @gzinflate( substr( $value, 2 ) );
			},
		);

		foreach ( $attempts as $attempt ) {
			$decoded = $attempt( $stream );
			if ( is_string( $decoded ) && '' !== $decoded ) {
				return $decoded;
			}
		}

		return '';
	}

	private function extract_text_from_pdf_stream( $stream ) {
		$parts = array();

		if ( preg_match_all( '/\[(.*?)\]\s*TJ/s', $stream, $matches ) ) {
			foreach ( $matches[1] as $array_text ) {
				$parts[] = $this->decode_pdf_text_operands( $array_text );
			}
		}

		if ( preg_match_all( '/(\((?:\\\\.|[^\\\\)])*\)|<[\da-fA-F\s]+>)\s*(?:Tj|\'|")/s', $stream, $matches ) ) {
			foreach ( $matches[1] as $operand ) {
				$parts[] = $this->decode_pdf_operand( $operand );
			}
		}

		return trim( implode( "\n", array_filter( $parts ) ) );
	}

	private function decode_pdf_text_operands( $text ) {
		$parts = array();
		if ( preg_match_all( '/\((?:\\\\.|[^\\\\)])*\)|<[\da-fA-F\s]+>/', $text, $matches ) ) {
			foreach ( $matches[0] as $operand ) {
				$parts[] = $this->decode_pdf_operand( $operand );
			}
		}

		return trim( implode( ' ', array_filter( $parts ) ) );
	}

	private function decode_pdf_operand( $operand ) {
		$operand = trim( $operand );

		if ( 0 === strpos( $operand, '<' ) && '>' === substr( $operand, -1 ) ) {
			return $this->decode_pdf_hex_string( substr( $operand, 1, -1 ) );
		}

		if ( 0 === strpos( $operand, '(' ) && ')' === substr( $operand, -1 ) ) {
			return $this->decode_pdf_literal_string( substr( $operand, 1, -1 ) );
		}

		return '';
	}

	private function decode_pdf_literal_string( $text ) {
		$output = '';
		$length = strlen( $text );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $text[ $i ];
			if ( '\\' !== $char ) {
				$output .= $char;
				continue;
			}

			$i++;
			if ( $i >= $length ) {
				break;
			}

			$escaped = $text[ $i ];
			$map = array(
				'n'  => "\n",
				'r'  => "\r",
				't'  => "\t",
				'b'  => "\b",
				'f'  => "\f",
				'('  => '(',
				')'  => ')',
				'\\' => '\\',
			);

			if ( isset( $map[ $escaped ] ) ) {
				$output .= $map[ $escaped ];
				continue;
			}

			if ( preg_match( '/[0-7]/', $escaped ) ) {
				$octal = $escaped;
				for ( $j = 0; $j < 2 && $i + 1 < $length && preg_match( '/[0-7]/', $text[ $i + 1 ] ); $j++ ) {
					$i++;
					$octal .= $text[ $i ];
				}
				$output .= chr( octdec( $octal ) );
				continue;
			}

			$output .= $escaped;
		}

		return $this->normalize_pdf_text( $output );
	}

	private function decode_pdf_hex_string( $hex ) {
		$hex = preg_replace( '/\s+/', '', $hex );
		if ( '' === $hex ) {
			return '';
		}

		if ( 1 === strlen( $hex ) % 2 ) {
			$hex .= '0';
		}

		$binary = hex2bin( $hex );
		if ( false === $binary ) {
			return '';
		}

		if ( 0 === strpos( $binary, "\xFE\xFF" ) && function_exists( 'mb_convert_encoding' ) ) {
			$binary = mb_convert_encoding( substr( $binary, 2 ), 'UTF-8', 'UTF-16BE' );
		}

		return $this->normalize_pdf_text( $binary );
	}

	private function normalize_pdf_text( $text ) {
		$text = str_replace( "\0", '', $text );
		$text = preg_replace( '/[^\P{C}\n\r\t]+/u', '', $text );
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	private function extract_docx_text( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'wpagent_docx_zip_missing', __( 'ZipArchive nao esta disponivel para extrair arquivos DOCX.', 'wpagent' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'wpagent_docx_open_failed', __( 'Nao foi possivel abrir o arquivo DOCX.', 'wpagent' ) );
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml ) {
			return new WP_Error( 'wpagent_docx_text_missing', __( 'Nao foi possivel localizar o texto principal do DOCX.', 'wpagent' ) );
		}

		$text = preg_replace( '/<\/w:p>/', "\n\n", $xml );
		$text = preg_replace( '/<[^>]+>/', ' ', $text );

		return $this->clean_text( html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
	}

	private function chunk_text( $text, $mime ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return array();
		}

		$pages = ( 'application/pdf' === $mime && false !== strpos( $text, "\f" ) )
			? preg_split( "/\f+/", $text )
			: array( $text );

		$chunks = array();
		foreach ( $pages as $page_index => $page_text ) {
			$page_chunks = $this->chunk_plain_text( $page_text, $page_index + 1 );
			foreach ( $page_chunks as $chunk ) {
				$chunks[] = $chunk;
			}
		}

		return $chunks;
	}

	private function chunk_plain_text( $text, $page_number ) {
		$max_chars     = (int) apply_filters( 'wpagent_chunk_max_chars', 3200 );
		$overlap_chars = (int) apply_filters( 'wpagent_chunk_overlap_chars', 450 );
		$text          = trim( preg_replace( "/[ \t]+/", ' ', $text ) );
		$paragraphs    = preg_split( "/\n{2,}/", $text );
		$chunks        = array();
		$current       = '';

		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( '' === $paragraph ) {
				continue;
			}

			if ( strlen( $paragraph ) > $max_chars ) {
				if ( '' !== $current ) {
					$chunks[] = $this->make_chunk( $current, $page_number );
					$current  = $this->tail_text( $current, $overlap_chars );
				}

				$offset = 0;
				$length = strlen( $paragraph );
				while ( $offset < $length ) {
					$piece = substr( $paragraph, $offset, $max_chars );
					$chunks[] = $this->make_chunk( $piece, $page_number );
					$offset += max( 1, $max_chars - $overlap_chars );
				}
				continue;
			}

			$next = '' === $current ? $paragraph : $current . "\n\n" . $paragraph;
			if ( strlen( $next ) > $max_chars && '' !== $current ) {
				$chunks[] = $this->make_chunk( $current, $page_number );
				$current = trim( $this->tail_text( $current, $overlap_chars ) . "\n\n" . $paragraph );
			} else {
				$current = $next;
			}
		}

		if ( '' !== trim( $current ) ) {
			$chunks[] = $this->make_chunk( $current, $page_number );
		}

		return $chunks;
	}

	private function make_chunk( $content, $page_number ) {
		$content = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $content ) ) );

		return array(
			'page_number'    => absint( $page_number ),
			'heading'        => '',
			'content'        => $content,
			'content_hash'   => md5( $content ),
			'token_estimate' => max( 1, (int) ceil( strlen( $content ) / 4 ) ),
			'keywords'       => implode( ' ', $this->extract_keywords( $content ) ),
			'metadata'       => array(),
		);
	}

	private function extract_keywords( $text ) {
		$words = preg_split( '/[^a-zA-Z0-9\x{00C0}-\x{017F}]+/u', strtolower( $text ) );
		$stopwords = array_flip(
			array(
				'para', 'como', 'mais', 'pela', 'pelo', 'com', 'uma', 'que', 'dos', 'das', 'por', 'sem',
				'este', 'esta', 'isso', 'aqui', 'sobre', 'entre', 'tambem', 'quando', 'onde', 'voce',
				'the', 'and', 'for', 'that', 'this', 'with', 'from',
			)
		);
		$counts = array();

		foreach ( $words as $word ) {
			if ( strlen( $word ) < 4 || isset( $stopwords[ $word ] ) ) {
				continue;
			}
			$counts[ $word ] = ( $counts[ $word ] ?? 0 ) + 1;
		}

		arsort( $counts );

		return array_slice( array_keys( $counts ), 0, 24 );
	}

	private function tail_text( $text, $limit ) {
		$text = trim( $text );
		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		return trim( substr( $text, -1 * $limit ) );
	}

	private function clean_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( "/\r\n|\r/", "\n", $text );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	private function assess_text_quality( $text ) {
		$sample = substr( (string) $text, 0, 25000 );
		preg_match_all( '/\S/u', $sample, $visible_matches );
		preg_match_all( '/\p{L}/u', $sample, $letter_matches );
		preg_match_all( '/\p{L}{3,}/u', $sample, $word_matches );

		$visible_count = count( $visible_matches[0] );
		$letter_count  = count( $letter_matches[0] );
		$word_count    = count( $word_matches[0] );

		if ( $visible_count < 1 ) {
			return array(
				'acceptable' => false,
				'message'    => __( 'A extracao nao encontrou texto legivel suficiente.', 'wpagent' ),
			);
		}

		$letter_ratio = $letter_count / $visible_count;
		if ( $word_count < 25 || $letter_ratio < 0.45 ) {
			return array(
				'acceptable' => false,
				'message'    => __( 'A extracao encontrou muitos simbolos e poucas palavras legiveis. Converta o documento para TXT/Markdown ou use um extrator/OCR externo.', 'wpagent' ),
			);
		}

		return array(
			'acceptable' => true,
			'message'    => '',
		);
	}

	private function can_shell_exec() {
		$allowed = defined( 'WPAGENT_ALLOW_PDFTOTEXT_EXEC' ) && WPAGENT_ALLOW_PDFTOTEXT_EXEC;
		$allowed = (bool) apply_filters( 'wpagent_allow_pdftotext_exec', $allowed );

		if ( ! $allowed ) {
			return false;
		}

		if ( ! function_exists( 'shell_exec' ) ) {
			return false;
		}

		$disabled = ini_get( 'disable_functions' );
		if ( ! $disabled ) {
			return true;
		}

		$disabled_functions = array_map( 'trim', explode( ',', $disabled ) );

		return ! in_array( 'shell_exec', $disabled_functions, true );
	}

	private function pdftotext_candidates() {
		$candidates = array();

		if ( defined( 'WPAGENT_PDFTOTEXT_PATH' ) && WPAGENT_PDFTOTEXT_PATH ) {
			$candidates[] = WPAGENT_PDFTOTEXT_PATH;
		}

		$candidates[] = 'pdftotext';
		$candidates[] = 'C:\\Program Files\\Git\\mingw64\\bin\\pdftotext.exe';
		$candidates[] = 'C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe';
		$candidates[] = 'C:\\Program Files\\poppler\\bin\\pdftotext.exe';

		$candidates = apply_filters( 'wpagent_pdftotext_candidates', $candidates );
		$candidates = array_filter( array_unique( array_map( 'trim', (array) $candidates ) ) );

		return $candidates;
	}
}
