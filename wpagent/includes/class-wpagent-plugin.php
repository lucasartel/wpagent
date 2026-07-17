<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Plugin' ) ) {
final class WPAgent_Plugin {
	private static $instance = null;

	private $repository;
	private $settings;
	private $wordpress_ai_integration;
	private $agents;
	private $reports;
	private $embeddings;
	private $periodic_tasks;
	private $admin_abilities;
	private $email_actions;
	private $email_schedules;
	private $conversation_summaries;
	private $ai_client;
	private $rest_controller;
	private $shortcode;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->repository      = new WPAgent_Repository();
		$this->settings        = new WPAgent_Settings();
		$this->wordpress_ai_integration = new WPAgent_WordPress_AI_Integration( $this->settings );
		$this->agents          = new WPAgent_Agents( $this->settings, $this->repository );
		$this->reports         = new WPAgent_Reports( $this->repository, $this->agents );
		$this->embeddings      = new WPAgent_Embeddings( $this->repository, $this->settings );
		$this->periodic_tasks  = new WPAgent_Periodic_Tasks( $this->settings );
		$this->admin_abilities = new WPAgent_Admin_Abilities( $this->repository, $this->settings );
		$this->email_actions          = new WPAgent_Email_Actions( $this->repository );
		$this->email_schedules        = new WPAgent_Email_Schedules( $this->repository, $this->settings, $this->agents );
		$this->conversation_summaries = new WPAgent_Conversation_Summaries( $this->repository, $this->settings, $this->agents );
		$this->ai_client              = new WPAgent_AI_Client( $this->repository, $this->settings, $this->agents, $this->embeddings, $this->admin_abilities, $this->email_actions );
		$this->rest_controller = new WPAgent_REST_Controller( $this->repository, $this->settings, $this->agents, $this->ai_client, $this->admin_abilities, $this->email_actions );
		$this->shortcode       = new WPAgent_Shortcode( $this->settings, $this->agents );
	}

	public function boot() {
		if ( get_option( 'wpagent_schema_version' ) !== WPAGENT_VERSION ) {
			WPAgent_Activator::activate();
		}
		$this->wordpress_ai_integration->register();
		$this->settings->register();
		$this->agents->register();
		$this->reports->register();
		$this->embeddings->register();
		$this->periodic_tasks->register();
		$this->admin_abilities->register();
		$this->email_actions->register();
		$this->email_schedules->register();
		$this->conversation_summaries->register();
		$this->shortcode->register();

		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
	}
}
}
