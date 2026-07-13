<?php
/**
 * Plugin Name: WPAgent
 * Plugin URI: https://example.com/wpagent
 * Description: Personalized AI companion for WordPress 7.0 with local knowledge, user memory, WordPress AI connectors, and OpenRouter fallback.
 * Version: 0.4.15
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: WPAgent
 * License: GPL-2.0-or-later
 * Text Domain: wpagent
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAGENT_VERSION', '0.4.15' );
define( 'WPAGENT_FILE', __FILE__ );
define( 'WPAGENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPAGENT_URL', plugin_dir_url( __FILE__ ) );

require_once WPAGENT_PATH . 'includes/class-wpagent-i18n.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-activator.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-repository.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-document-indexer.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-settings.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-wordpress-ai-integration.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-agents.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-reports.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-embeddings.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-periodic-tasks.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-admin-abilities.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-email-actions.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-prompt-builder.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-ai-client.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-rest-controller.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-shortcode.php';
require_once WPAGENT_PATH . 'includes/class-wpagent-plugin.php';

register_activation_hook( __FILE__, array( 'WPAgent_Activator', 'activate' ) );

WPAgent_I18n::register();

add_action(
	'plugins_loaded',
	static function () {
		WPAgent_Plugin::instance()->boot();
	}
);
