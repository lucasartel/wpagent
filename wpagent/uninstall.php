<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	'wpagent_interactions',
	'wpagent_conversations',
	'wpagent_memories',
	'wpagent_sources',
	'wpagent_source_chunks',
	'wpagent_chunk_embeddings',
	'wpagent_email_events',
	'wpagent_email_schedules',
	'wpagent_email_subscriptions',
	'wpagent_user_tokens_usage',
	'wpagent_conversation_summaries',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

$options = array(
	'wpagent_settings',
	'wpagent_schema_version',
	'wpagent_embedding_status',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$meta_keys = array(
	'_wpagent_agent_slug',
	'_wpagent_provider_mode',
	'_wpagent_openrouter_model',
	'_wpagent_wordpress_ai_provider',
	'_wpagent_wordpress_ai_model',
	'_wpagent_use_wp_ai_client',
	'_wpagent_allow_guest_chat',
	'_wpagent_public_site_assistant',
	'_wpagent_admin_assistant',
	'_wpagent_user_profile_enabled',
	'_wpagent_user_profile_label',
	'_wpagent_user_profile_description',
	'_wpagent_user_profile_fields',
	'_wpagent_email_actions_enabled',
	'_wpagent_email_actions_instructions',
	'_wpagent_system_prompt',
	'_wpagent_max_knowledge_items',
	'_wpagent_max_memory_items',
	'_wpagent_max_recent_interactions',
	'_wpagent_token_limit_day',
	'_wpagent_token_limit_week',
	'_wpagent_token_limit_month',
	'_wpagent_show_token_usage',
	'_wpagent_email_schedules_enabled',
	'_wpagent_conversation_summary_enabled',
	'_wpagent_conversation_summary_delay',
	'_wpagent_default_theme',
);

foreach ( $meta_keys as $key ) {
	delete_post_meta_by_key( $key );
}

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type = %s", 'wpagent_agent' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})" ) );
