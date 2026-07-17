<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Repository' ) ) {
class WPAgent_Repository {
	public function record_interaction( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_interactions';

		$wpdb->insert(
			$table,
			array(
				'user_id'      => absint( $data['user_id'] ?? 0 ),
				'agent_slug'   => sanitize_key( $data['agent_slug'] ?? 'default' ),
				'conversation_id' => sanitize_text_field( $data['conversation_id'] ?? '' ),
				'session_id'   => sanitize_text_field( $data['session_id'] ?? '' ),
				'message'      => wp_kses_post( $data['message'] ?? '' ),
				'reply'        => wp_kses_post( $data['reply'] ?? '' ),
				'model'        => sanitize_text_field( $data['model'] ?? '' ),
				'provider'     => sanitize_text_field( $data['provider'] ?? '' ),
				'token_input'  => absint( $data['token_input'] ?? 0 ),
				'token_output' => absint( $data['token_output'] ?? 0 ),
				'metadata'     => wp_json_encode( $data['metadata'] ?? array() ),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function record_email_event( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_email_events';
		$status = sanitize_key( $data['status'] ?? 'pending' );

		$wpdb->insert(
			$table,
			array(
				'user_id'              => absint( $data['user_id'] ?? 0 ),
				'agent_slug'           => sanitize_key( $data['agent_slug'] ?? 'default' ),
				'conversation_id'      => sanitize_text_field( $data['conversation_id'] ?? '' ),
				'session_id'           => sanitize_text_field( $data['session_id'] ?? '' ),
				'interaction_id'       => absint( $data['interaction_id'] ?? 0 ),
				'recipient_email'      => sanitize_email( $data['recipient_email'] ?? '' ),
				'recipient_email_hash' => hash( 'sha256', strtolower( sanitize_email( $data['recipient_email'] ?? '' ) ) ),
				'recipient_name'       => sanitize_text_field( $data['recipient_name'] ?? '' ),
				'subject'              => sanitize_text_field( $data['subject'] ?? '' ),
				'body'                 => sanitize_textarea_field( $data['body'] ?? '' ),
				'purpose'              => sanitize_text_field( $data['purpose'] ?? '' ),
				'status'               => $status ?: 'pending',
				'error_message'        => sanitize_textarea_field( $data['error_message'] ?? '' ),
				'metadata'             => wp_json_encode( $data['metadata'] ?? array() ),
				'created_at'           => current_time( 'mysql' ),
				'sent_at'              => 'sent' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function get_email_event( $event_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_email_events';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $event_id )
			),
			ARRAY_A
		);
	}

	public function update_email_event_status( $event_id, $status, $error_message = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_email_events';
		$status = sanitize_key( $status );
		$payload = array(
			'status'        => $status,
			'error_message' => sanitize_textarea_field( $error_message ),
		);
		$formats = array( '%s', '%s' );

		if ( 'sent' === $status ) {
			$payload['sent_at'] = current_time( 'mysql' );
			$formats[] = '%s';
		}

		return false !== $wpdb->update(
			$table,
			$payload,
			array( 'id' => absint( $event_id ) ),
			$formats,
			array( '%d' )
		);
	}

	public function create_conversation( $user_id, $agent_slug, $title = '' ) {
		global $wpdb;

		$conversation_id = wp_generate_uuid4();
		$title = $title ? sanitize_text_field( $title ) : __( 'Nova conversa', 'wpagent' );
		$table = $wpdb->prefix . 'wpagent_conversations';

		$wpdb->insert(
			$table,
			array(
				'conversation_id' => $conversation_id,
				'user_id'         => absint( $user_id ),
				'agent_slug'      => sanitize_key( $agent_slug ),
				'title'           => $title,
				'status'          => 'active',
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $this->get_conversation( $conversation_id, $user_id, $agent_slug );
	}

	public function get_conversation( $conversation_id, $user_id, $agent_slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_conversations';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %s AND user_id = %d AND agent_slug = %s AND status = 'active'",
				sanitize_text_field( $conversation_id ),
				absint( $user_id ),
				sanitize_key( $agent_slug )
			),
			ARRAY_A
		);
	}

	public function list_conversations( $user_id, $agent_slug, $limit = 50 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_conversations';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT conversation_id, title, created_at, updated_at FROM {$table} WHERE user_id = %d AND agent_slug = %s AND status = 'active' ORDER BY updated_at DESC LIMIT %d",
				absint( $user_id ),
				sanitize_key( $agent_slug ),
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function update_conversation_title( $conversation_id, $user_id, $agent_slug, $title ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_conversations';

		$wpdb->update(
			$table,
			array(
				'title'      => sanitize_text_field( $title ),
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'conversation_id' => sanitize_text_field( $conversation_id ),
				'user_id'         => absint( $user_id ),
				'agent_slug'      => sanitize_key( $agent_slug ),
			),
			array( '%s', '%s' ),
			array( '%s', '%d', '%s' )
		);

		return $this->get_conversation( $conversation_id, $user_id, $agent_slug );
	}

	public function touch_conversation( $conversation_id, $user_id, $agent_slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_conversations';

		$wpdb->update(
			$table,
			array( 'updated_at' => current_time( 'mysql' ) ),
			array(
				'conversation_id' => sanitize_text_field( $conversation_id ),
				'user_id'         => absint( $user_id ),
				'agent_slug'      => sanitize_key( $agent_slug ),
			),
			array( '%s' ),
			array( '%s', '%d', '%s' )
		);
	}

	public function delete_conversation( $conversation_id, $user_id, $agent_slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_conversations';

		return (bool) $wpdb->update(
			$table,
			array(
				'status'     => 'deleted',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'conversation_id' => sanitize_text_field( $conversation_id ),
				'user_id'         => absint( $user_id ),
				'agent_slug'      => sanitize_key( $agent_slug ),
				'status'          => 'active',
			),
			array( '%s', '%s' ),
			array( '%s', '%d', '%s', '%s' )
		);
	}

	public function get_conversation_messages( $conversation_id, $user_id, $agent_slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_interactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message, reply, created_at FROM {$table} WHERE conversation_id = %s AND user_id = %d AND agent_slug = %s ORDER BY created_at ASC",
				sanitize_text_field( $conversation_id ),
				absint( $user_id ),
				sanitize_key( $agent_slug )
			),
			ARRAY_A
		);
	}

	public function add_memory( $user_id, $agent_slug, $content, $type = 'profile', $source_interaction_id = null, $salience = 3 ) {
		global $wpdb;

		if ( empty( $user_id ) || empty( trim( wp_strip_all_tags( $content ) ) ) ) {
			return 0;
		}

		$table = $wpdb->prefix . 'wpagent_memories';

		$wpdb->insert(
			$table,
			array(
				'user_id'               => absint( $user_id ),
				'agent_slug'            => sanitize_key( $agent_slug ),
				'memory_type'           => sanitize_key( $type ),
				'content'               => wp_kses_post( $content ),
				'source_interaction_id' => $source_interaction_id ? absint( $source_interaction_id ) : null,
				'salience'              => min( 5, max( 1, absint( $salience ) ) ),
				'metadata'              => wp_json_encode( array() ),
				'created_at'            => current_time( 'mysql' ),
				'updated_at'            => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function get_memories( $user_id, $agent_slug, $limit = 8 ) {
		global $wpdb;

		if ( empty( $user_id ) || $limit < 1 ) {
			return array();
		}

		$table = $wpdb->prefix . 'wpagent_memories';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND agent_slug = %s AND memory_type <> 'profile_note' ORDER BY salience DESC, updated_at DESC LIMIT %d",
				absint( $user_id ),
				sanitize_key( $agent_slug ),
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function get_user_profile_memory( $user_id, $agent_slug ) {
		$profile = $this->get_user_profile_data( $user_id, $agent_slug );

		return $profile['content'];
	}

	public function get_user_profile_data( $user_id, $agent_slug ) {
		global $wpdb;

		if ( empty( $user_id ) ) {
			return array(
				'content'    => '',
				'free_text'  => '',
				'structured' => array(),
			);
		}

		$table = $wpdb->prefix . 'wpagent_memories';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT content, metadata FROM {$table} WHERE user_id = %d AND agent_slug = %s AND memory_type = 'profile_note' ORDER BY updated_at DESC LIMIT 1",
				absint( $user_id ),
				sanitize_key( $agent_slug )
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return array(
				'content'    => '',
				'free_text'  => '',
				'structured' => array(),
			);
		}

		$metadata = json_decode( $row['metadata'] ?? '', true );
		$metadata = is_array( $metadata ) ? $metadata : array();

		return array(
			'content'    => (string) ( $row['content'] ?? '' ),
			'free_text'  => (string) ( $metadata['free_text'] ?? $row['content'] ?? '' ),
			'structured' => is_array( $metadata['structured'] ?? null ) ? $metadata['structured'] : array(),
		);
	}

	public function save_user_profile_memory( $user_id, $agent_slug, $content, $structured = array(), $fields = array() ) {
		global $wpdb;

		if ( empty( $user_id ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpagent_memories';
		$free_text = trim( sanitize_textarea_field( $content ) );
		$structured = $this->sanitize_structured_profile_values( $structured, $fields );
		$content = $this->compose_profile_memory_content( $free_text, $structured, $fields );
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND agent_slug = %s AND memory_type = 'profile_note' ORDER BY updated_at DESC LIMIT 1",
				absint( $user_id ),
				sanitize_key( $agent_slug )
			)
		);

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			if ( $existing_id ) {
				return false !== $wpdb->delete( $table, array( 'id' => $existing_id ), array( '%d' ) );
			}

			return true;
		}

		if ( $existing_id ) {
			return false !== $wpdb->update(
				$table,
				array(
					'content'    => $content,
					'salience'   => 5,
					'metadata'   => wp_json_encode(
						array(
							'source'     => 'user_declared_profile',
							'free_text'  => $free_text,
							'structured' => $structured,
						)
					),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $existing_id ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		return (bool) $wpdb->insert(
			$table,
			array(
				'user_id'               => absint( $user_id ),
				'agent_slug'            => sanitize_key( $agent_slug ),
				'memory_type'           => 'profile_note',
				'content'               => $content,
				'source_interaction_id' => null,
				'salience'              => 5,
				'metadata'              => wp_json_encode(
					array(
						'source'     => 'user_declared_profile',
						'free_text'  => $free_text,
						'structured' => $structured,
					)
				),
				'created_at'            => current_time( 'mysql' ),
				'updated_at'            => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	private function sanitize_structured_profile_values( $values, $fields ) {
		$output = array();
		$allowed = array();

		foreach ( (array) $fields as $field ) {
			$key = sanitize_key( $field['key'] ?? '' );
			if ( $key ) {
				$allowed[ $key ] = true;
			}
		}

		foreach ( (array) $values as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! $key || ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$output[ $key ] = substr( sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' ), 0, 1000 );
		}

		return $output;
	}

	private function compose_profile_memory_content( $free_text, $structured, $fields ) {
		$lines = array();

		foreach ( (array) $fields as $field ) {
			$key = sanitize_key( $field['key'] ?? '' );
			$value = trim( (string) ( $structured[ $key ] ?? '' ) );

			if ( '' === $key || '' === $value ) {
				continue;
			}

			$lines[] = sanitize_text_field( $field['label'] ?? $key ) . ': ' . $value;
		}

		if ( '' !== trim( $free_text ) ) {
			$lines[] = __( 'Observacoes adicionais', 'wpagent' ) . ': ' . $free_text;
		}

		return trim( implode( "\n", $lines ) );
	}

	public function get_recent_interactions( $user_id, $agent_slug, $limit = 4, $conversation_id = '' ) {
		global $wpdb;

		if ( empty( $user_id ) || $limit < 1 ) {
			return array();
		}

		$table = $wpdb->prefix . 'wpagent_interactions';

		if ( $conversation_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT message, reply, created_at FROM {$table} WHERE user_id = %d AND agent_slug = %s AND conversation_id = %s ORDER BY created_at DESC LIMIT %d",
					absint( $user_id ),
					sanitize_key( $agent_slug ),
					sanitize_text_field( $conversation_id ),
					absint( $limit )
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message, reply, created_at FROM {$table} WHERE user_id = %d AND agent_slug = %s ORDER BY created_at DESC LIMIT %d",
				absint( $user_id ),
				sanitize_key( $agent_slug ),
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function get_recent_interactions_by_session( $session_id, $agent_slug, $limit = 4 ) {
		global $wpdb;

		$session_id = sanitize_text_field( $session_id );
		if ( empty( $session_id ) || $limit < 1 ) {
			return array();
		}

		$table = $wpdb->prefix . 'wpagent_interactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message, reply, created_at FROM {$table} WHERE user_id = 0 AND agent_slug = %s AND session_id = %s ORDER BY created_at DESC LIMIT %d",
				sanitize_key( $agent_slug ),
				$session_id,
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function get_agent_token_usage_since( $agent_slug, $since ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_interactions';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(token_input + token_output), 0) FROM {$table} WHERE agent_slug = %s AND created_at >= %s",
				sanitize_key( $agent_slug ),
				sanitize_text_field( $since )
			)
		);
	}

	public function get_token_usage_for_agent( $agent_slug ) {
		$starts = $this->token_period_starts();

		return array(
			'day'   => $this->get_agent_token_usage_since( $agent_slug, $starts['day'] ),
			'week'  => $this->get_agent_token_usage_since( $agent_slug, $starts['week'] ),
			'month' => $this->get_agent_token_usage_since( $agent_slug, $starts['month'] ),
		);
	}

	public function get_token_period_starts() {
		return $this->token_period_starts();
	}

	public function get_token_usage_report_by_agent() {
		global $wpdb;

		$table  = $wpdb->prefix . 'wpagent_interactions';
		$starts = $this->token_period_starts();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					agent_slug,
					COUNT(*) AS interactions,
					COUNT(DISTINCT user_id) AS users,
					COALESCE(SUM(token_input), 0) AS input_tokens,
					COALESCE(SUM(token_output), 0) AS output_tokens,
					COALESCE(SUM(token_input + token_output), 0) AS total_tokens,
					COALESCE(SUM(CASE WHEN created_at >= %s THEN token_input + token_output ELSE 0 END), 0) AS day_tokens,
					COALESCE(SUM(CASE WHEN created_at >= %s THEN token_input + token_output ELSE 0 END), 0) AS week_tokens,
					COALESCE(SUM(CASE WHEN created_at >= %s THEN token_input + token_output ELSE 0 END), 0) AS month_tokens
				FROM {$table}
				GROUP BY agent_slug
				ORDER BY total_tokens DESC",
				$starts['day'],
				$starts['week'],
				$starts['month']
			),
			ARRAY_A
		);
	}

	public function get_token_usage_report_by_user( $limit = 100 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_interactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					agent_slug,
					user_id,
					COUNT(*) AS interactions,
					COALESCE(SUM(token_input), 0) AS input_tokens,
					COALESCE(SUM(token_output), 0) AS output_tokens,
					COALESCE(SUM(token_input + token_output), 0) AS total_tokens,
					MAX(created_at) AS last_used_at
				FROM {$table}
				GROUP BY agent_slug, user_id
				ORDER BY total_tokens DESC
				LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function get_email_lead_summary_by_agent() {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_email_events';

		return $wpdb->get_results(
			"SELECT
				agent_slug,
				COUNT(DISTINCT recipient_email_hash) AS leads,
				COUNT(*) AS email_events,
				COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) AS sent_count,
				COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) AS failed_count,
				MAX(created_at) AS last_email_at
			FROM {$table}
			WHERE recipient_email_hash <> ''
			GROUP BY agent_slug
			ORDER BY last_email_at DESC",
			ARRAY_A
		);
	}

	public function get_email_leads_report( $limit = 200 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_email_events';
		$limit = min( 500, max( 1, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					lead.agent_slug,
					lead.recipient_email_hash,
					MAX(lead.recipient_email) AS recipient_email,
					MAX(lead.recipient_name) AS recipient_name,
					COUNT(*) AS email_events,
					COALESCE(SUM(CASE WHEN lead.status = 'sent' THEN 1 ELSE 0 END), 0) AS sent_count,
					COALESCE(SUM(CASE WHEN lead.status = 'failed' THEN 1 ELSE 0 END), 0) AS failed_count,
					MIN(lead.created_at) AS first_seen_at,
					MAX(lead.created_at) AS last_email_at,
					(
						SELECT recent.subject
						FROM {$table} recent
						WHERE recent.agent_slug = lead.agent_slug
							AND recent.recipient_email_hash = lead.recipient_email_hash
						ORDER BY recent.created_at DESC, recent.id DESC
						LIMIT 1
					) AS last_subject,
					(
						SELECT recent.purpose
						FROM {$table} recent
						WHERE recent.agent_slug = lead.agent_slug
							AND recent.recipient_email_hash = lead.recipient_email_hash
						ORDER BY recent.created_at DESC, recent.id DESC
						LIMIT 1
					) AS last_purpose,
					(
						SELECT recent.status
						FROM {$table} recent
						WHERE recent.agent_slug = lead.agent_slug
							AND recent.recipient_email_hash = lead.recipient_email_hash
						ORDER BY recent.created_at DESC, recent.id DESC
						LIMIT 1
					) AS last_status
				FROM {$table} lead
				WHERE lead.recipient_email_hash <> ''
				GROUP BY lead.agent_slug, lead.recipient_email_hash
				ORDER BY last_email_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public function upsert_training_source( $data ) {
		global $wpdb;

		$table         = $wpdb->prefix . 'wpagent_sources';
		$attachment_id = absint( $data['attachment_id'] ?? 0 );
		$agent_slug    = sanitize_key( $data['agent_slug'] ?? 'default' );
		$existing_id   = 0;

		if ( $attachment_id ) {
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE attachment_id = %d AND agent_slug = %s LIMIT 1",
					$attachment_id,
					$agent_slug
				)
			);
		} elseif ( 'manual' === ( $data['source_type'] ?? '' ) ) {
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE post_id = %d AND agent_slug = %s AND source_type = 'manual' AND title = %s LIMIT 1",
					absint( $data['post_id'] ?? 0 ),
					$agent_slug,
					sanitize_text_field( $data['title'] ?? '' )
				)
			);
		}

		$payload = array(
			'agent_slug'     => $agent_slug,
			'post_id'        => absint( $data['post_id'] ?? 0 ),
			'attachment_id'  => $attachment_id,
			'source_type'    => sanitize_key( $data['source_type'] ?? 'upload' ),
			'title'          => sanitize_text_field( $data['title'] ?? '' ),
			'filename'       => sanitize_file_name( $data['filename'] ?? '' ),
			'url'            => esc_url_raw( $data['url'] ?? '' ),
			'mime'           => sanitize_mime_type( $data['mime'] ?? '' ),
			'status'         => sanitize_key( $data['status'] ?? 'processing' ),
			'status_message' => sanitize_text_field( $data['status_message'] ?? '' ),
			'metadata'       => wp_json_encode( $data['metadata'] ?? array() ),
			'updated_at'     => current_time( 'mysql' ),
		);

		if ( $existing_id ) {
			$wpdb->update(
				$table,
				$payload,
				array( 'id' => $existing_id ),
				array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return $existing_id;
		}

		$payload['created_at'] = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			$payload,
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function mark_training_source( $source_id, $status, $message = '', $stats = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_sources';

		$payload = array(
			'status'         => sanitize_key( $status ),
			'status_message' => sanitize_text_field( $message ),
			'updated_at'     => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s', '%s' );

		if ( isset( $stats['chunk_count'] ) ) {
			$payload['chunk_count'] = absint( $stats['chunk_count'] );
			$formats[] = '%d';
		}

		if ( isset( $stats['char_count'] ) ) {
			$payload['char_count'] = absint( $stats['char_count'] );
			$formats[] = '%d';
		}

		return $wpdb->update(
			$table,
			$payload,
			array( 'id' => absint( $source_id ) ),
			$formats,
			array( '%d' )
		);
	}

	public function replace_source_chunks( $source_id, $agent_slug, $chunks ) {
		global $wpdb;

		$source_id = absint( $source_id );
		$agent_slug = sanitize_key( $agent_slug );
		$table = $wpdb->prefix . 'wpagent_source_chunks';
		$embeddings = $wpdb->prefix . 'wpagent_chunk_embeddings';

		$wpdb->delete( $embeddings, array( 'source_id' => $source_id ), array( '%d' ) );
		$wpdb->delete( $table, array( 'source_id' => $source_id ), array( '%d' ) );

		foreach ( array_values( $chunks ) as $index => $chunk ) {
			$wpdb->insert(
				$table,
				array(
					'source_id'      => $source_id,
					'agent_slug'     => $agent_slug,
					'chunk_index'    => absint( $index ),
					'page_number'    => absint( $chunk['page_number'] ?? 0 ),
					'heading'        => sanitize_text_field( $chunk['heading'] ?? '' ),
					'content'        => wp_kses_post( $chunk['content'] ?? '' ),
					'content_hash'   => sanitize_text_field( $chunk['content_hash'] ?? '' ),
					'token_estimate' => absint( $chunk['token_estimate'] ?? 0 ),
					'keywords'       => sanitize_text_field( $chunk['keywords'] ?? '' ),
					'metadata'       => wp_json_encode( $chunk['metadata'] ?? array() ),
					'created_at'     => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
		}
	}

	public function get_chunks_without_embeddings( $provider, $model, $limit = 10 ) {
		global $wpdb;

		$chunks = $wpdb->prefix . 'wpagent_source_chunks';
		$sources = $wpdb->prefix . 'wpagent_sources';
		$embeddings = $wpdb->prefix . 'wpagent_chunk_embeddings';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*
				FROM {$chunks} c
				INNER JOIN {$sources} s ON s.id = c.source_id AND s.status = 'indexed'
				LEFT JOIN {$embeddings} e ON e.chunk_id = c.id AND e.provider = %s AND e.model = %s
				WHERE e.id IS NULL
				ORDER BY c.id ASC
				LIMIT %d",
				sanitize_key( $provider ),
				sanitize_text_field( $model ),
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function upsert_chunk_embedding( $chunk, $provider, $model, $embedding ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_chunk_embeddings';
		$provider = sanitize_key( $provider );
		$model = sanitize_text_field( $model );
		$chunk_id = absint( $chunk['id'] ?? 0 );
		$embedding_json = wp_json_encode( array_values( $embedding ) );
		$dimensions = is_array( $embedding ) ? count( $embedding ) : 0;
		$hash = md5( $embedding_json );

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE chunk_id = %d AND provider = %s AND model = %s LIMIT 1",
				$chunk_id,
				$provider,
				$model
			)
		);

		$payload = array(
			'chunk_id'       => $chunk_id,
			'source_id'      => absint( $chunk['source_id'] ?? 0 ),
			'agent_slug'     => sanitize_key( $chunk['agent_slug'] ?? 'default' ),
			'provider'       => $provider,
			'model'          => $model,
			'dimensions'     => absint( $dimensions ),
			'embedding'      => $embedding_json,
			'embedding_hash' => $hash,
			'status'         => 'ready',
			'error_message'  => '',
			'updated_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

		if ( $existing_id ) {
			$wpdb->update( $table, $payload, array( 'id' => $existing_id ), $formats, array( '%d' ) );
			return $existing_id;
		}

		$payload['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $payload, array_merge( $formats, array( '%s' ) ) );

		return (int) $wpdb->insert_id;
	}

	public function get_ready_embeddings_for_agent( $agent_slug, $provider, $model, $limit = 500 ) {
		global $wpdb;

		$chunks = $wpdb->prefix . 'wpagent_source_chunks';
		$sources = $wpdb->prefix . 'wpagent_sources';
		$embeddings = $wpdb->prefix . 'wpagent_chunk_embeddings';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, s.title AS source_title, s.filename, s.url, s.mime, e.embedding, e.dimensions
				FROM {$embeddings} e
				INNER JOIN {$chunks} c ON c.id = e.chunk_id
				INNER JOIN {$sources} s ON s.id = c.source_id AND s.status = 'indexed'
				WHERE e.agent_slug = %s AND e.provider = %s AND e.model = %s AND e.status = 'ready'
				ORDER BY c.id DESC
				LIMIT %d",
				sanitize_key( $agent_slug ),
				sanitize_key( $provider ),
				sanitize_text_field( $model ),
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function get_embedding_count_for_source( $source_id, $provider, $model ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_chunk_embeddings';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE source_id = %d AND provider = %s AND model = %s AND status = 'ready'",
				absint( $source_id ),
				sanitize_key( $provider ),
				sanitize_text_field( $model )
			)
		);
	}

	public function list_training_sources( $agent_slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_sources';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE agent_slug = %s ORDER BY updated_at DESC, id DESC",
				sanitize_key( $agent_slug )
			),
			ARRAY_A
		);
	}

	public function get_training_source( $source_id, $agent_slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpagent_sources';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND agent_slug = %s",
				absint( $source_id ),
				sanitize_key( $agent_slug )
			),
			ARRAY_A
		);
	}

	public function delete_training_source( $source_id, $agent_slug ) {
		global $wpdb;

		$source_id  = absint( $source_id );
		$agent_slug = sanitize_key( $agent_slug );
		$sources    = $wpdb->prefix . 'wpagent_sources';
		$chunks     = $wpdb->prefix . 'wpagent_source_chunks';

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$sources} WHERE id = %d AND agent_slug = %s",
				$source_id,
				$agent_slug
			),
			ARRAY_A
		);

		if ( ! $source ) {
			return false;
		}

		$wpdb->delete( $chunks, array( 'source_id' => $source_id ), array( '%d' ) );
		$wpdb->delete( $sources, array( 'id' => $source_id ), array( '%d' ) );

		return true;
	}

	public function search_training_chunks( $agent_slug, $query, $limit = 4 ) {
		global $wpdb;

		$limit = absint( $limit );
		if ( $limit < 1 ) {
			return array();
		}

		$chunks  = $wpdb->prefix . 'wpagent_source_chunks';
		$sources = $wpdb->prefix . 'wpagent_sources';
		$terms   = $this->query_terms( $query );

		if ( empty( $terms ) ) {
			return array();
		}

		$where = array( 'c.agent_slug = %s', "s.status = 'indexed'" );
		$args  = array( sanitize_key( $agent_slug ) );

		$like_parts = array();
		foreach ( array_slice( $terms, 0, 8 ) as $term ) {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			$like_parts[] = '(c.content LIKE %s OR c.keywords LIKE %s OR s.title LIKE %s)';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		if ( ! empty( $like_parts ) ) {
			$where[] = '(' . implode( ' OR ', $like_parts ) . ')';
		}

		$args[] = max( 60, $limit * 20 );
		$sql = $wpdb->prepare(
			"SELECT c.*, s.title AS source_title, s.filename, s.url, s.mime
			FROM {$chunks} c
			INNER JOIN {$sources} s ON s.id = c.source_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY c.id DESC
			LIMIT %d',
			$args
		);

		$candidates = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $candidates ) ) {
			$candidates = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.*, s.title AS source_title, s.filename, s.url, s.mime
					FROM {$chunks} c
					INNER JOIN {$sources} s ON s.id = c.source_id
					WHERE c.agent_slug = %s AND s.status = 'indexed'
					ORDER BY c.id DESC
					LIMIT %d",
					sanitize_key( $agent_slug ),
					max( 250, $limit * 80 )
				),
				ARRAY_A
			);
		}

		$ranked = array();

		foreach ( $candidates as $candidate ) {
			$haystack = $this->normalize_search_text( $candidate['source_title'] . ' ' . $candidate['keywords'] . ' ' . $candidate['content'] );
			$score    = 0;

			foreach ( $terms as $term ) {
				if ( false !== strpos( $haystack, $term ) ) {
					$score += 3;
					continue;
				}

				if ( strlen( $term ) > 5 && false !== strpos( $haystack, substr( $term, 0, 5 ) ) ) {
					$score += 1;
				}
			}

			if ( $score < 1 ) {
				continue;
			}

			$score += min( 5, substr_count( $this->normalize_search_text( $candidate['keywords'] ), ' ' ) );
			$candidate['score'] = $score;
			$ranked[] = $candidate;
		}

		usort(
			$ranked,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return (int) $a['chunk_index'] <=> (int) $b['chunk_index'];
				}

				return $b['score'] <=> $a['score'];
			}
		);

		if ( ! empty( $ranked ) ) {
			return array_slice( $ranked, 0, $limit );
		}

		return array();
	}

	private function query_terms( $query ) {
		$words = preg_split( '/[^a-zA-Z0-9\x{00C0}-\x{017F}]+/u', $this->normalize_search_text( $query ) );
		$terms = array();

		foreach ( $words as $word ) {
			if ( strlen( $word ) < 3 ) {
				continue;
			}
			$terms[ $word ] = true;

			foreach ( $this->expanded_query_terms( $word ) as $expanded ) {
				$terms[ $expanded ] = true;
			}
		}

		return array_keys( $terms );
	}

	private function normalize_search_text( $text ) {
		$text = strtolower( wp_strip_all_tags( (string) $text ) );
		if ( function_exists( 'remove_accents' ) ) {
			$text = remove_accents( $text );
		}
		$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );

		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	private function expanded_query_terms( $term ) {
		$map = array(
			'ansiedade'      => array( 'ansioso', 'ansiosa', 'preocupacao', 'medo', 'temor', 'inquietacao', 'aflicao' ),
			'ansioso'        => array( 'ansiedade', 'preocupacao', 'medo', 'temor' ),
			'ansiosa'        => array( 'ansiedade', 'preocupacao', 'medo', 'temor' ),
			'culpa'          => array( 'pecado', 'vergonha', 'condenacao', 'arrependimento', 'confissao' ),
			'culpado'        => array( 'culpa', 'pecado', 'vergonha', 'condenacao' ),
			'culpada'        => array( 'culpa', 'pecado', 'vergonha', 'condenacao' ),
			'alivio'         => array( 'consolo', 'descanso', 'paz', 'esperanca', 'encorajamento' ),
			'medo'           => array( 'temor', 'ansiedade', 'preocupacao', 'aflicao' ),
			'cansaco'        => array( 'cansado', 'fadiga', 'desanimo', 'descanso' ),
			'sofrimento'     => array( 'dor', 'aflicao', 'tribulacao', 'consolo' ),
			'perdao'         => array( 'confissao', 'arrependimento', 'pecado', 'graca' ),
			'perdoar'        => array( 'perdao', 'confissao', 'arrependimento', 'graca' ),
			'conselho'       => array( 'aconselhamento', 'orientacao', 'cuidado' ),
			'aconselhamento' => array( 'conselho', 'orientacao', 'cuidado' ),
		);

		return $map[ $term ] ?? array();
	}

	private function token_period_starts() {
		$now = current_time( 'timestamp' );

		return array(
			'day'   => wp_date( 'Y-m-d 00:00:00', $now ),
			'week'  => wp_date( 'Y-m-d 00:00:00', strtotime( 'monday this week', $now ) ),
			'month' => wp_date( 'Y-m-01 00:00:00', $now ),
		);
	}

	public function get_conversation_interactions( $conversation_id, $agent_slug, $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_interactions';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %s AND agent_slug = %s ORDER BY id ASC LIMIT %d",
				$conversation_id,
				$agent_slug,
				$limit
			),
			ARRAY_A
		);
	}

	public function create_email_schedule( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_schedules';
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	public function get_email_schedule( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_schedules';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	public function get_email_schedule_by_signature( $agent_slug, $signature ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_schedules';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE agent_slug = %s AND signature = %s",
				$agent_slug,
				$signature
			),
			ARRAY_A
		);
	}

	public function update_email_schedule( $id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_schedules';
		return $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	public function delete_email_schedule( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_schedules';
		return $wpdb->delete( $table, array( 'id' => $id ) );
	}

	public function list_email_schedules( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_schedules';
		$where = '1=1';
		if ( ! empty( $args['agent_slug'] ) ) {
			$where .= $wpdb->prepare( ' AND agent_slug = %s', $args['agent_slug'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}
		$order = 'ORDER BY updated_at DESC';
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} {$order}", ARRAY_A );
	}

	public function create_email_subscription( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_subscriptions';
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	public function get_email_subscription_by_email( $schedule_id, $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_subscriptions';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE schedule_id = %d AND recipient_email = %s",
				$schedule_id,
				$email
			),
			ARRAY_A
		);
	}

	public function get_email_subscription_by_token( $token ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_subscriptions';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE unsubscribe_token = %s",
				$token
			),
			ARRAY_A
		);
	}

	public function update_email_subscription( $id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_subscriptions';
		return $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	public function get_due_email_subscriptions( $per_run = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_subscriptions';
		$schedules_table = $wpdb->prefix . 'wpagent_email_schedules';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*
				FROM {$table} s
				INNER JOIN {$schedules_table} sch ON s.schedule_id = sch.id
				WHERE s.consent_status = 'subscribed'
					AND s.status = 'active'
					AND sch.status = 'active'
					AND (s.next_send_at IS NULL OR s.next_send_at <= NOW())
				ORDER BY s.next_send_at ASC
				LIMIT %d",
				$per_run
			),
			ARRAY_A
		);
	}

	public function list_email_subscriptions( $schedule_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_subscriptions';
		$where = $wpdb->prepare( 'schedule_id = %d', $schedule_id );
		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}
		$limit = '';
		if ( ! empty( $args['limit'] ) ) {
			$limit = $wpdb->prepare( ' LIMIT %d', $args['limit'] );
		}
		$order = 'ORDER BY subscribed_at DESC';
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} {$order} {$limit}", ARRAY_A );
	}

	public function count_email_subscriptions( $schedule_id, $status = 'subscribed' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_email_subscriptions';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE schedule_id = %d AND status = %s",
				$schedule_id,
				$status
			)
		);
	}

	public function upsert_conversation_summary( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_conversation_summaries';
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE conversation_id = %s AND agent_slug = %s",
				$data['conversation_id'],
				$data['agent_slug']
			)
		);
		if ( isset( $data['data_points'] ) && is_array( $data['data_points'] ) ) {
			$data['data_points'] = wp_json_encode( $data['data_points'], JSON_UNESCAPED_UNICODE );
		}
		if ( $existing ) {
			$data['updated_at'] = current_time( 'mysql' );
			$wpdb->update( $table, $data, array( 'id' => $existing ) );
			return $existing;
		}
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	public function get_conversation_summary( $conversation_id, $agent_slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_conversation_summaries';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %s AND agent_slug = %s",
				$conversation_id,
				$agent_slug
			),
			ARRAY_A
		);
		if ( $row ) {
			$row = $this->decode_summary_data_points( $row );
		}
		return $row;
	}

	public function list_conversation_summaries( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_conversation_summaries';
		$where = '1=1';
		if ( ! empty( $args['agent_slug'] ) ) {
			$where .= $wpdb->prepare( ' AND cs.agent_slug = %s', $args['agent_slug'] );
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where .= $wpdb->prepare( ' AND cs.user_id = %d', $args['user_id'] );
		}
		$join = '';
		$select_extra = '';
		if ( ! empty( $args['with_conversation'] ) ) {
			$conv_table = $wpdb->prefix . 'wpagent_conversations';
			$join = "LEFT JOIN {$conv_table} c ON cs.conversation_id = c.conversation_id";
			$select_extra = ', c.title as conversation_title, c.updated_at as conversation_updated';
		}
		$limit = '';
		if ( ! empty( $args['limit'] ) ) {
			$limit = $wpdb->prepare( ' LIMIT %d', $args['limit'] );
		}
		$order = 'ORDER BY cs.updated_at DESC';
		$rows = $wpdb->get_results(
			"SELECT cs.* {$select_extra} FROM {$table} cs {$join} WHERE {$where} {$order} {$limit}",
			ARRAY_A
		);
		if ( $rows ) {
			foreach ( $rows as &$row ) {
				$row = $this->decode_summary_data_points( $row );
			}
		}
		return $rows;
	}

	private function decode_summary_data_points( $row ) {
		if ( isset( $row['data_points'] ) && is_string( $row['data_points'] ) ) {
			$decoded = json_decode( $row['data_points'], true );
			if ( is_array( $decoded ) ) {
				$row['data_points'] = $decoded;
			}
		} elseif ( ! isset( $row['data_points'] ) ) {
			$row['data_points'] = array();
		}
		return $row;
	}

	public function get_conversations_needing_summary( $agent_slug, $delay_hours = 4, $limit = 10 ) {
		global $wpdb;
		$conv_table   = $wpdb->prefix . 'wpagent_conversations';
		$int_table    = $wpdb->prefix . 'wpagent_interactions';
		$summ_table   = $wpdb->prefix . 'wpagent_conversation_summaries';
		$gmt_offset   = (float) get_option( 'gmt_offset', 0 );
		$cutoff       = gmdate( 'Y-m-d H:i:s', time() + $gmt_offset * 3600 - $delay_hours * 3600 );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*,
					COUNT(DISTINCT i.id) AS interaction_count,
					MAX(i.id) AS last_interaction_id,
					MAX(i.created_at) AS last_interaction_at
				FROM {$conv_table} c
				LEFT JOIN {$int_table} i ON c.conversation_id = i.conversation_id AND c.agent_slug = i.agent_slug
				LEFT JOIN {$summ_table} s ON c.conversation_id = s.conversation_id AND c.agent_slug = s.agent_slug
				WHERE c.agent_slug = %s
					AND c.updated_at <= %s
					AND s.id IS NULL
				GROUP BY c.id
				ORDER BY c.updated_at ASC
				LIMIT %d",
				$agent_slug,
				$cutoff,
				$limit
			),
			ARRAY_A
		);
	}

	public function track_tokens_usage( $user_id, $tokens ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_user_tokens_usage';
		$user_id = absint( $user_id );
		$tokens  = absint( $tokens );

		if ( 0 === $user_id || 0 === $tokens ) {
			return false;
		}

		$now   = current_time( 'mysql' );
		$year  = (int) current_time( 'Y' );
		$month = (int) current_time( 'm' );

		return false !== $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, year, month, total_tokens, created_at, updated_at)
				VALUES (%d, %d, %d, %d, %s, %s)
				ON DUPLICATE KEY UPDATE
					total_tokens = total_tokens + %d,
					updated_at = %s",
				$user_id,
				$year,
				$month,
				$tokens,
				$now,
				$now,
				$tokens,
				$now
			)
		);
	}

	public function get_user_monthly_usage( $user_id, $year = null, $month = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_user_tokens_usage';

		if ( null === $year || null === $month ) {
			$year = (int) current_time( 'Y' );
			$month = (int) current_time( 'm' );
		}

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND year = %d AND month = %d",
				absint( $user_id ),
				absint( $year ),
				absint( $month )
			),
			ARRAY_A
		);

		return $result ?: array(
			'user_id' => absint( $user_id ),
			'year' => absint( $year ),
			'month' => absint( $month ),
			'total_tokens' => 0,
		);
	}

	public function can_user_use_tokens( $user_id, $tokens_needed = 1 ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$settings = get_option( 'wpagent_settings', array() );
		$enable_global_limit = ! empty( $settings['enable_global_token_limit'] );
		if ( ! $enable_global_limit ) {
			return true;
		}

		$global_limit = (int) ( $settings['global_token_limit'] ?? 100000 );
		if ( 0 === $global_limit ) {
			return true;
		}

		$usage = $this->get_user_monthly_usage( $user_id );
		$current_usage = (int) ( $usage['total_tokens'] ?? 0 );

		if ( ( $current_usage + $tokens_needed ) > $global_limit ) {
			return false;
		}

		return true;
	}

	public function reset_monthly_usage( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_user_tokens_usage';

		return $wpdb->update(
			$table,
			array(
				'total_tokens' => 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'user_id' => absint( $user_id ),
				'year'    => (int) current_time( 'Y' ),
				'month'   => (int) current_time( 'm' ),
			),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public function get_all_users_token_usage( $limit = 100, $year = null, $month = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpagent_user_tokens_usage';

		if ( null === $year ) {
			$year = (int) current_time( 'Y' );
		}
		if ( null === $month ) {
			$month = (int) current_time( 'm' );
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, year, month, total_tokens, created_at, updated_at
				FROM {$table}
				WHERE user_id > 0 AND year = %d AND month = %d
				ORDER BY total_tokens DESC
				LIMIT %d",
				absint( $year ),
				absint( $month ),
				absint( $limit )
			),
			ARRAY_A
		);

		return $results ?: array();
	}
}
}
