<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Activator' ) ) {
class WPAgent_Activator {
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$interactions    = $wpdb->prefix . 'wpagent_interactions';
		$memories        = $wpdb->prefix . 'wpagent_memories';
		$conversations   = $wpdb->prefix . 'wpagent_conversations';
		$sources         = $wpdb->prefix . 'wpagent_sources';
		$source_chunks   = $wpdb->prefix . 'wpagent_source_chunks';
		$embeddings      = $wpdb->prefix . 'wpagent_chunk_embeddings';
		$email_events    = $wpdb->prefix . 'wpagent_email_events';

		$sql = array();

		$sql[] = "CREATE TABLE {$interactions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			conversation_id varchar(120) NOT NULL DEFAULT '',
			session_id varchar(120) NOT NULL DEFAULT '',
			message longtext NOT NULL,
			reply longtext NOT NULL,
			model varchar(190) NOT NULL DEFAULT '',
			provider varchar(190) NOT NULL DEFAULT '',
			token_input int unsigned NOT NULL DEFAULT 0,
			token_output int unsigned NOT NULL DEFAULT 0,
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_agent_created (user_id, agent_slug, created_at),
			KEY conversation_created (conversation_id, created_at),
			KEY session_created (session_id, created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id varchar(120) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			title varchar(190) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY conversation_id (conversation_id),
			KEY user_agent_updated (user_id, agent_slug, updated_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$memories} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			memory_type varchar(80) NOT NULL DEFAULT 'profile',
			content longtext NOT NULL,
			source_interaction_id bigint(20) unsigned NULL,
			salience tinyint unsigned NOT NULL DEFAULT 3,
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_agent_type (user_id, agent_slug, memory_type),
			KEY user_agent_updated (user_id, agent_slug, updated_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$sources} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_type varchar(40) NOT NULL DEFAULT 'upload',
			title varchar(255) NOT NULL DEFAULT '',
			filename varchar(255) NOT NULL DEFAULT '',
			url text NULL,
			mime varchar(120) NOT NULL DEFAULT '',
			status varchar(40) NOT NULL DEFAULT 'processing',
			status_message text NULL,
			chunk_count int unsigned NOT NULL DEFAULT 0,
			char_count int unsigned NOT NULL DEFAULT 0,
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY agent_status_updated (agent_slug, status, updated_at),
			KEY attachment_agent (attachment_id, agent_slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$source_chunks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			chunk_index int unsigned NOT NULL DEFAULT 0,
			page_number int unsigned NOT NULL DEFAULT 0,
			heading varchar(255) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			content_hash varchar(64) NOT NULL DEFAULT '',
			token_estimate int unsigned NOT NULL DEFAULT 0,
			keywords text NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY source_chunk (source_id, chunk_index),
			KEY agent_created (agent_slug, created_at),
			KEY content_hash (content_hash)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$embeddings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			chunk_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			provider varchar(80) NOT NULL DEFAULT 'openrouter',
			model varchar(190) NOT NULL DEFAULT '',
			dimensions int unsigned NOT NULL DEFAULT 0,
			embedding longtext NULL,
			embedding_hash varchar(64) NOT NULL DEFAULT '',
			status varchar(40) NOT NULL DEFAULT 'ready',
			error_message text NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY chunk_provider_model (chunk_id, provider, model),
			KEY agent_status (agent_slug, status),
			KEY source_id (source_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$email_events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			conversation_id varchar(120) NOT NULL DEFAULT '',
			session_id varchar(120) NOT NULL DEFAULT '',
			interaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
			recipient_email varchar(190) NOT NULL DEFAULT '',
			recipient_email_hash varchar(64) NOT NULL DEFAULT '',
			recipient_name varchar(190) NOT NULL DEFAULT '',
			subject varchar(255) NOT NULL DEFAULT '',
			body longtext NOT NULL,
			purpose varchar(255) NOT NULL DEFAULT '',
			status varchar(40) NOT NULL DEFAULT 'pending',
			error_message text NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			KEY user_agent_created (user_id, agent_slug, created_at),
			KEY agent_status_created (agent_slug, status, created_at),
			KEY recipient_hash_created (recipient_email_hash, created_at)
		) {$charset_collate};";

		$email_schedules = $wpdb->prefix . 'wpagent_email_schedules';
		$sql[] = "CREATE TABLE {$email_schedules} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			name varchar(255) NOT NULL DEFAULT '',
			schedule_type varchar(40) NOT NULL DEFAULT 'recurring',
			frequency varchar(20) NOT NULL DEFAULT 'weekly',
			sequence_steps longtext NULL,
			subject_template varchar(500) NOT NULL DEFAULT '',
			content_prompt longtext NOT NULL,
			consent_required tinyint unsigned NOT NULL DEFAULT 0,
			max_occurrences int unsigned NOT NULL DEFAULT 0,
			status varchar(40) NOT NULL DEFAULT 'draft',
			origin varchar(20) NOT NULL DEFAULT '',
			signature varchar(64) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			last_send_at datetime NULL,
			next_send_at datetime NULL,
			total_sends int unsigned NOT NULL DEFAULT 0,
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY agent_status (agent_slug, status),
			KEY next_send (status, next_send_at),
			UNIQUE KEY signature (agent_slug, signature)
		) {$charset_collate};";

		$email_subscriptions = $wpdb->prefix . 'wpagent_email_subscriptions';
		$sql[] = "CREATE TABLE {$email_subscriptions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			schedule_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_slug varchar(120) NOT NULL DEFAULT '',
			recipient_email varchar(190) NOT NULL DEFAULT '',
			recipient_name varchar(190) NOT NULL DEFAULT '',
			conversation_id varchar(120) NOT NULL DEFAULT '',
			session_id varchar(120) NOT NULL DEFAULT '',
			interaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
			consent_note text NULL,
			consent_status varchar(40) NOT NULL DEFAULT 'subscribed',
			consented_at datetime NULL,
			current_step int unsigned NOT NULL DEFAULT 0,
			next_send_at datetime NULL,
			occurrences_sent int unsigned NOT NULL DEFAULT 0,
			last_sent_at datetime NULL,
			status varchar(40) NOT NULL DEFAULT 'subscribed',
			unsubscribe_token varchar(64) NOT NULL DEFAULT '',
			unsubscribed_at datetime NULL,
			metadata longtext NULL,
			subscribed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY schedule_status (schedule_id, status),
			KEY next_send (status, next_send_at),
			KEY unsubscribe_token (unsubscribe_token),
			KEY schedule_email (schedule_id, recipient_email)
		) {$charset_collate};";

		$user_tokens_usage = $wpdb->prefix . 'wpagent_user_tokens_usage';
		$sql[] = "CREATE TABLE {$user_tokens_usage} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			year int unsigned NOT NULL DEFAULT 0,
			month int unsigned NOT NULL DEFAULT 0,
			total_tokens int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_month (user_id, year, month)
		) {$charset_collate};";

		$conversation_summaries = $wpdb->prefix . 'wpagent_conversation_summaries';
		$sql[] = "CREATE TABLE {$conversation_summaries} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id varchar(120) NOT NULL DEFAULT '',
			agent_slug varchar(120) NOT NULL DEFAULT 'default',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			conversation_title varchar(255) NOT NULL DEFAULT '',
			summary longtext NULL,
			data_points longtext NULL,
			interaction_count int unsigned NOT NULL DEFAULT 0,
			last_interaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
			last_interaction_at datetime NULL,
			model varchar(190) NOT NULL DEFAULT '',
			provider varchar(190) NOT NULL DEFAULT '',
			generated_at datetime NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY conversation_agent (conversation_id, agent_slug),
			KEY agent_user (agent_slug, user_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::seed_current_month_user_token_usage( $user_tokens_usage );

		$defaults = array(
			'agent_name'             => 'WPAgent',
			'agent_slug'             => 'default',
			'openrouter_api_key'     => '',
			'openrouter_model'       => 'openai/gpt-4.1-mini',
			'wordpress_ai_provider'  => '',
			'wordpress_ai_model'     => '',
			'use_wp_ai_client'       => '1',
			'allow_guest_chat'       => '0',
			'public_site_assistant'  => '0',
			'admin_assistant'        => '0',
			'user_profile_enabled'   => '0',
			'user_profile_label'     => 'Sobre voce',
			'user_profile_description' => 'Compartilhe informacoes que ajudam este agente a personalizar as respostas.',
			'user_profile_fields'    => array(),
			'email_actions_enabled'  => '0',
			'email_actions_instructions' => '',
			'max_knowledge_items'    => 4,
			'max_memory_items'       => 8,
			'max_recent_interactions'=> 4,
			'embedding_enabled'      => '0',
			'embedding_provider'     => 'openrouter',
			'embedding_model'        => 'openai/text-embedding-3-small',
			'embedding_batch_size'   => 10,
			'periodic_tasks_enabled' => '0',
			'periodic_task_mode'     => 'report_only',
			'periodic_tasks'         => array(),
			'system_prompt'          => 'Voce e um assistente pessoal cuidadoso, util e contextual. Responda em portugues do Brasil, use a base de conhecimento quando ela for relevante e trate a memoria do usuario como contexto privado.',
		);

		add_option( 'wpagent_settings', $defaults );
		update_option( 'wpagent_schema_version', WPAGENT_VERSION );
		self::ensure_default_agent( $defaults );
	}

	public static function deactivate() {
		$hooks = array(
			'wpagent_process_embedding_batch',
			'wpagent_process_periodic_tasks',
			'wpagent_send_email_events',
			'wpagent_process_email_schedules',
			'wpagent_process_conversation_summaries',
		);
		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	private static function ensure_default_agent( $defaults ) {
		$existing = get_posts(
			array(
				'post_type'      => 'wpagent_agent',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $existing ) ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'wpagent_agent',
				'post_status' => 'publish',
				'post_title'  => $defaults['agent_name'],
			)
		);

		if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_wpagent_agent_slug', $defaults['agent_slug'] );
		update_post_meta( $post_id, '_wpagent_provider_mode', 'wordpress_ai' );
		update_post_meta( $post_id, '_wpagent_openrouter_model', $defaults['openrouter_model'] );
		update_post_meta( $post_id, '_wpagent_wordpress_ai_provider', $defaults['wordpress_ai_provider'] );
		update_post_meta( $post_id, '_wpagent_wordpress_ai_model', $defaults['wordpress_ai_model'] );
		update_post_meta( $post_id, '_wpagent_use_wp_ai_client', $defaults['use_wp_ai_client'] );
		update_post_meta( $post_id, '_wpagent_allow_guest_chat', $defaults['allow_guest_chat'] );
		update_post_meta( $post_id, '_wpagent_public_site_assistant', $defaults['public_site_assistant'] );
		update_post_meta( $post_id, '_wpagent_admin_assistant', $defaults['admin_assistant'] );
		update_post_meta( $post_id, '_wpagent_user_profile_enabled', $defaults['user_profile_enabled'] );
		update_post_meta( $post_id, '_wpagent_user_profile_label', $defaults['user_profile_label'] );
		update_post_meta( $post_id, '_wpagent_user_profile_description', $defaults['user_profile_description'] );
		update_post_meta( $post_id, '_wpagent_user_profile_fields', $defaults['user_profile_fields'] );
		update_post_meta( $post_id, '_wpagent_email_actions_enabled', $defaults['email_actions_enabled'] );
		update_post_meta( $post_id, '_wpagent_email_actions_instructions', $defaults['email_actions_instructions'] );
		update_post_meta( $post_id, '_wpagent_system_prompt', $defaults['system_prompt'] );
		update_post_meta( $post_id, '_wpagent_max_knowledge_items', $defaults['max_knowledge_items'] );
		update_post_meta( $post_id, '_wpagent_max_memory_items', $defaults['max_memory_items'] );
		update_post_meta( $post_id, '_wpagent_max_recent_interactions', $defaults['max_recent_interactions'] );
		update_post_meta( $post_id, '_wpagent_token_limit_day', 0 );
		update_post_meta( $post_id, '_wpagent_token_limit_week', 0 );
		update_post_meta( $post_id, '_wpagent_token_limit_month', 0 );
		update_post_meta( $post_id, '_wpagent_show_token_usage', '0' );
		update_post_meta( $post_id, '_wpagent_site_context_enabled', '1' );
		update_post_meta( $post_id, '_wpagent_conversation_summary_enabled', '0' );
		update_post_meta( $post_id, '_wpagent_conversation_summary_delay', 4 );
	}

	private static function seed_current_month_user_token_usage( $usage_table ) {
		global $wpdb;

		$interactions_table = $wpdb->prefix . 'wpagent_interactions';
		$now                = current_time( 'mysql' );
		$year               = (int) current_time( 'Y' );
		$month              = (int) current_time( 'm' );
		$month_start        = current_time( 'Y-m-01 00:00:00' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$usage_table} (user_id, year, month, total_tokens, created_at, updated_at)
				SELECT user_id, %d, %d, SUM(token_input + token_output), %s, %s
				FROM {$interactions_table}
				WHERE user_id > 0 AND created_at >= %s
				GROUP BY user_id
				ON DUPLICATE KEY UPDATE
					total_tokens = VALUES(total_tokens),
					updated_at = VALUES(updated_at)",
				$year,
				$month,
				$now,
				$now,
				$month_start
			)
		);
	}
}
}
