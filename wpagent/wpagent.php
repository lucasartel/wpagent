<?php
/**
 * Plugin Name: WPAgent
 * Plugin URI: https://github.com/lucasartel/wpagent
 * Description: Personalized AI companion for WordPress 7.0 with local knowledge, user memory, WordPress AI connectors, and OpenRouter fallback.
 * Version: 0.5.4
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Tested up to: 7.0.1
 * Author: WPAgent
 * Author URI: https://github.com/lucasartel
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/lucasartel/wpagent
 * Text Domain: wpagent
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAGENT_VERSION', '0.5.4' );
define( 'WPAGENT_FILE', __FILE__ );
define( 'WPAGENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPAGENT_URL', plugin_dir_url( __FILE__ ) );

if ( ! class_exists( 'WPAgent_I18n' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-i18n.php';
}
if ( ! class_exists( 'WPAgent_Activator' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-activator.php';
}
if ( ! class_exists( 'WPAgent_Repository' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-repository.php';
}
if ( ! class_exists( 'WPAgent_Document_Indexer' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-document-indexer.php';
}
if ( ! class_exists( 'WPAgent_Settings' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-settings.php';
}
if ( ! class_exists( 'WPAgent_WordPress_AI_Integration' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-wordpress-ai-integration.php';
}
if ( ! class_exists( 'WPAgent_Agents' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-agents.php';
}
if ( ! class_exists( 'WPAgent_Reports' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-reports.php';
}
if ( ! class_exists( 'WPAgent_Embeddings' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-embeddings.php';
}
if ( ! class_exists( 'WPAgent_Periodic_Tasks' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-periodic-tasks.php';
}
if ( ! class_exists( 'WPAgent_Admin_Abilities' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-admin-abilities.php';
}
if ( ! class_exists( 'WPAgent_Email_Actions' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-email-actions.php';
}
if ( ! class_exists( 'WPAgent_Email_Schedules' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-email-schedules.php';
}
if ( ! class_exists( 'WPAgent_Conversation_Summaries' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-conversation-summaries.php';
}
if ( ! class_exists( 'WPAgent_Prompt_Builder' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-prompt-builder.php';
}
if ( ! class_exists( 'WPAgent_AI_Client' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-ai-client.php';
}
if ( ! class_exists( 'WPAgent_REST_Controller' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-rest-controller.php';
}
if ( ! class_exists( 'WPAgent_Shortcode' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-shortcode.php';
}
if ( ! class_exists( 'WPAgent_Plugin' ) ) {
	require_once WPAGENT_PATH . 'includes/class-wpagent-plugin.php';
}

register_activation_hook( __FILE__, array( 'WPAgent_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPAgent_Activator', 'deactivate' ) );

WPAgent_I18n::register();

add_action(
	'plugins_loaded',
	static function () {
		WPAgent_Plugin::instance()->boot();
	}
);
