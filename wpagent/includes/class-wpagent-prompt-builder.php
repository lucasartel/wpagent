<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Prompt_Builder' ) ) {
class WPAgent_Prompt_Builder {
	private $repository;
	private $settings;
	private $agents;
	private $embeddings;
	private $admin_abilities;
	private $email_actions;

	public function __construct( WPAgent_Repository $repository, WPAgent_Settings $settings, WPAgent_Agents $agents, ?WPAgent_Embeddings $embeddings = null, ?WPAgent_Admin_Abilities $admin_abilities = null, ?WPAgent_Email_Actions $email_actions = null ) {
		$this->repository      = $repository;
		$this->settings        = $settings;
		$this->agents          = $agents;
		$this->embeddings      = $embeddings;
		$this->admin_abilities = $admin_abilities;
		$this->email_actions   = $email_actions;
	}

	public function build( $message, $user_id, $agent_slug, $conversation_id = '', $session_id = '' ) {
		$agent         = $this->agents->get_agent( $agent_slug );
		$agent_slug    = $agent['slug'];
		$system_prompt = trim( wp_strip_all_tags( $agent['system_prompt'] ) );
		$knowledge     = $this->search_knowledge( $message, $agent, absint( $agent['max_knowledge_items'] ) );
		$user_profile  = '1' === ( $agent['user_profile_enabled'] ?? '0' ) ? $this->repository->get_user_profile_memory( $user_id, $agent_slug ) : '';
		$memories      = $this->repository->get_memories( $user_id, $agent_slug, absint( $agent['max_memory_items'] ) );
		$recent_limit  = absint( $agent['max_recent_interactions'] );
		$recent        = $user_id
			? $this->repository->get_recent_interactions( $user_id, $agent_slug, $recent_limit, $conversation_id )
			: $this->repository->get_recent_interactions_by_session( $session_id, $agent_slug, $recent_limit );

		$context_parts = array();

		if ( '' !== trim( wp_strip_all_tags( $user_profile ) ) ) {
			$context_parts[] = "Perfil declarado pelo usuario para este agente:\n" . $this->compact_text( $user_profile, 1200 );
		}

		if ( ! empty( $memories ) ) {
			$lines = array();
			foreach ( $memories as $memory ) {
				$lines[] = '- ' . trim( wp_strip_all_tags( $memory['content'] ) );
			}
			$context_parts[] = "Memoria persistente do usuario:\n" . implode( "\n", $lines );
		}

		if ( ! empty( $recent ) ) {
			$lines = array();
			foreach ( array_reverse( $recent ) as $item ) {
				$lines[] = 'Usuario: ' . $this->compact_text( $item['message'], 350 );
				$lines[] = 'Agente: ' . $this->compact_text( $item['reply'], 350 );
			}
			$context_parts[] = "Interacoes recentes:\n" . implode( "\n", $lines );
		}

		if ( ! empty( $knowledge ) ) {
			$lines = array();
			foreach ( $knowledge as $item ) {
				$lines[] = 'Fonte: ' . $item['title'] . "\n" . $this->compact_text( $item['content'], 1200 );
			}
			$context_parts[] = "Base de conhecimento relevante:\n" . implode( "\n\n", $lines );
		}

		if ( ! empty( $agent['admin_assistant'] ) && '1' === $agent['admin_assistant'] && $this->admin_abilities ) {
			$abilities_context = $this->admin_abilities->prompt_context();
			if ( $abilities_context ) {
				$context_parts[] = $abilities_context;
			}
		}

		if ( $this->email_actions ) {
			$email_context = $this->email_actions->prompt_context( $agent );
			if ( $email_context ) {
				$context_parts[] = $email_context;
			}
		}

		$context = implode( "\n\n---\n\n", $context_parts );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
		);

		if ( $context ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => "Use o contexto abaixo apenas quando for relevante. Nao invente informacoes ausentes.\n\n" . $context,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		return array(
			'agent'     => $agent,
			'messages'  => $messages,
			'knowledge' => $knowledge,
			'memories'  => $memories,
		);
	}

	private function search_knowledge( $message, $agent, $limit ) {
		if ( $limit < 1 ) {
			return array();
		}

		if ( $this->embeddings && $this->embeddings->enabled() ) {
			$semantic = $this->embeddings->semantic_search( $agent['slug'], $message, $limit );
			if ( ! empty( $semantic ) ) {
				return $this->knowledge_from_chunks( $semantic, 'embedding' );
			}
		}

		$chunks = $this->repository->search_training_chunks( $agent['slug'], $message, $limit );
		if ( ! empty( $chunks ) ) {
			return $this->knowledge_from_chunks( $chunks, 'keyword' );
		}

		$files = is_array( $agent['training_files'] ?? null ) ? $agent['training_files'] : array();
		$terms = preg_split( '/\s+/', strtolower( wp_strip_all_tags( $message ) ) );
		$ranked = array();

		foreach ( $files as $file ) {
			$content = trim( wp_strip_all_tags( $file['content'] ?? '' ) );
			if ( '' === $content ) {
				continue;
			}

			$haystack = strtolower( ( $file['title'] ?? '' ) . ' ' . $content );
			$score = 0;
			foreach ( $terms as $term ) {
				if ( strlen( $term ) > 2 && false !== strpos( $haystack, $term ) ) {
					$score++;
				}
			}

			$ranked[] = array(
				'score'   => $score,
				'id'      => absint( $file['attachment_id'] ?? 0 ),
				'title'   => $file['title'] ?? $file['filename'] ?? __( 'Arquivo de treinamento', 'wpagent' ),
				'content' => $content,
			);
		}

		usort(
			$ranked,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $ranked, 0, $limit );
	}

	private function knowledge_from_chunks( $chunks, $strategy ) {
		$knowledge = array();
		foreach ( $chunks as $chunk ) {
			$page = absint( $chunk['page_number'] ?? 0 );
			$title = $chunk['source_title'] ?? $chunk['filename'] ?? __( 'Documento de treinamento', 'wpagent' );
			if ( $page ) {
				$title .= sprintf(
					/* translators: %d: page number. */
					__( ' (pag. %d)', 'wpagent' ),
					$page
				);
			}

			$knowledge[] = array(
				'score'    => $chunk['score'] ?? 0,
				'id'       => absint( $chunk['id'] ?? 0 ),
				'title'    => $title,
				'strategy' => $strategy,
				'content'  => trim( wp_strip_all_tags( $chunk['content'] ?? '' ) ),
			);
		}

		return $knowledge;
	}

	private function compact_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );

		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		return substr( $text, 0, $limit - 3 ) . '...';
	}
}
}
