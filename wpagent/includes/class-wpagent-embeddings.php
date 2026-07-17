<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Embeddings' ) ) {
class WPAgent_Embeddings {
	const CRON_HOOK = 'wpagent_process_embedding_batch';

	private $repository;
	private $settings;

	public function __construct( WPAgent_Repository $repository, WPAgent_Settings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	public function register() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'process_batch' ) );
		add_action( 'admin_post_wpagent_process_embeddings_now', array( $this, 'handle_process_now' ) );
	}

	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['wpagent_five_minutes'] ) ) {
			$schedules['wpagent_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every five minutes', 'wpagent' ),
			);
		}

		return $schedules;
	}

	public function maybe_schedule() {
		if ( ! $this->enabled() ) {
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'wpagent_five_minutes', self::CRON_HOOK );
		}
	}

	public function enabled() {
		return '1' === $this->settings->get( 'embedding_enabled', '0' );
	}

	public function provider() {
		return sanitize_key( $this->settings->get( 'embedding_provider', 'openrouter' ) );
	}

	public function model() {
		return sanitize_text_field( $this->settings->get( 'embedding_model', 'openai/text-embedding-3-small' ) );
	}

	public function process_batch() {
		if ( ! $this->enabled() ) {
			$this->set_status( 'disabled', __( 'Embeddings desativados.', 'wpagent' ), 0 );
			return array( 'processed' => 0 );
		}

		$provider = $this->provider();
		$model    = $this->model();
		$limit    = max( 1, min( 50, absint( $this->settings->get( 'embedding_batch_size', 10 ) ) ) );
		$chunks   = $this->repository->get_chunks_without_embeddings( $provider, $model, $limit );

		if ( empty( $chunks ) ) {
			$this->set_status( 'idle', __( 'Nenhum chunk pendente para embeddings.', 'wpagent' ), 0 );
			return array( 'processed' => 0 );
		}

		$inputs = array();
		foreach ( $chunks as $chunk ) {
			$inputs[] = trim( wp_strip_all_tags( $chunk['content'] ?? '' ) );
		}

		$result = $this->create_embeddings( $inputs );
		if ( is_wp_error( $result ) ) {
			$this->set_status( 'error', $result->get_error_message(), 0 );
			return array( 'processed' => 0, 'error' => $result->get_error_message() );
		}

		$processed = 0;
		foreach ( $chunks as $index => $chunk ) {
			if ( empty( $result[ $index ] ) || ! is_array( $result[ $index ] ) ) {
				continue;
			}
			$this->repository->upsert_chunk_embedding( $chunk, $provider, $model, $result[ $index ] );
			$processed++;
		}

		$this->set_status(
			'success',
			sprintf(
				/* translators: %d: processed chunks. */
				__( '%d embeddings processados no ultimo lote.', 'wpagent' ),
				$processed
			),
			$processed
		);

		return array( 'processed' => $processed );
	}

	public function handle_process_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		check_admin_referer( 'wpagent_process_embeddings_now' );
		$total = 0;

		for ( $i = 0; $i < 10; $i++ ) {
			$result = $this->process_batch();
			$total += absint( $result['processed'] ?? 0 );

			if ( ! empty( $result['error'] ) || empty( $result['processed'] ) ) {
				break;
			}
		}

		if ( $total > 0 ) {
			$this->set_status(
				'success',
				sprintf(
					/* translators: %d: processed chunks. */
					__( '%d embeddings processados manualmente.', 'wpagent' ),
					$total
				),
				$total
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpagent' ) );
		exit;
	}

	public function status() {
		$status = get_option( 'wpagent_embedding_status', array() );

		return is_array( $status ) ? $status : array();
	}

	public function create_query_embedding( $text ) {
		if ( ! $this->enabled() ) {
			return new WP_Error( 'wpagent_embeddings_disabled', __( 'Embeddings desativados.', 'wpagent' ) );
		}

		$result = $this->create_embeddings( array( $text ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result[0] ?? new WP_Error( 'wpagent_empty_embedding', __( 'O provedor nao retornou embedding.', 'wpagent' ) );
	}

	public function semantic_search( $agent_slug, $query, $limit = 4 ) {
		$query_embedding = $this->create_query_embedding( $query );
		if ( is_wp_error( $query_embedding ) ) {
			return array();
		}

		$candidates = $this->repository->get_ready_embeddings_for_agent( $agent_slug, $this->provider(), $this->model(), 800 );
		$ranked = array();

		foreach ( $candidates as $candidate ) {
			$embedding = json_decode( $candidate['embedding'] ?? '', true );
			if ( ! is_array( $embedding ) ) {
				continue;
			}

			$score = $this->cosine_similarity( $query_embedding, $embedding );
			$candidate['score'] = $score;
			$ranked[] = $candidate;
		}

		usort(
			$ranked,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $ranked, 0, absint( $limit ) );
	}

	private function create_embeddings( $inputs ) {
		if ( 'openrouter' !== $this->provider() ) {
			return new WP_Error( 'wpagent_embedding_provider_unsupported', __( 'Provider de embeddings ainda nao suportado.', 'wpagent' ) );
		}

		$api_key = $this->settings->get( 'openrouter_api_key', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpagent_missing_api_key', __( 'Configure a chave da OpenRouter para gerar embeddings.', 'wpagent' ) );
		}

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/embeddings',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => get_bloginfo( 'name' ),
				),
				'body'    => wp_json_encode(
					array(
						'model' => $this->model(),
						'input' => array_values( $inputs ),
						'encoding_format' => 'float',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpagent_embedding_error', $body['error']['message'] ?? __( 'Erro ao gerar embeddings.', 'wpagent' ), array( 'status' => $code ) );
		}

		$items = $body['data'] ?? array();
		$embeddings = array();
		foreach ( $items as $item ) {
			$embeddings[] = $item['embedding'] ?? array();
		}

		return $embeddings;
	}

	private function set_status( $status, $message, $processed ) {
		update_option(
			'wpagent_embedding_status',
			array(
				'status'       => sanitize_key( $status ),
				'message'      => sanitize_text_field( $message ),
				'processed'    => absint( $processed ),
				'provider'     => $this->provider(),
				'model'        => $this->model(),
				'updated_at'   => current_time( 'mysql' ),
			),
			false
		);
	}

	private function cosine_similarity( $a, $b ) {
		$length = min( count( $a ), count( $b ) );
		if ( $length < 1 ) {
			return 0;
		}

		$dot = 0;
		$norm_a = 0;
		$norm_b = 0;

		for ( $i = 0; $i < $length; $i++ ) {
			$av = (float) $a[ $i ];
			$bv = (float) $b[ $i ];
			$dot += $av * $bv;
			$norm_a += $av * $av;
			$norm_b += $bv * $bv;
		}

		if ( $norm_a <= 0 || $norm_b <= 0 ) {
			return 0;
		}

		return $dot / ( sqrt( $norm_a ) * sqrt( $norm_b ) );
	}
}
}
