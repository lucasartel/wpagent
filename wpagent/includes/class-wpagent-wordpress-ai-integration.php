<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_WordPress_AI_Integration' ) ) {
class WPAgent_WordPress_AI_Integration {
	private $settings;

	public function __construct( WPAgent_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_action( 'init', array( $this, 'sync_openrouter_key_to_connectors' ), 30 );
		add_action( 'update_option_' . WPAgent_Settings::OPTION_NAME, array( $this, 'sync_openrouter_key_to_connectors' ), 10, 0 );
		add_filter( 'wpai_preferred_text_models', array( $this, 'prefer_wpagent_openrouter_model' ) );
		add_filter( 'wpai_has_ai_credentials', array( $this, 'report_wpagent_openrouter_credentials' ), 10, 2 );
	}

	public function sync_openrouter_key_to_connectors() {
		$key = $this->settings->get( 'openrouter_api_key', '' );

		if ( empty( $key ) || '1' !== $this->settings->get( 'use_wp_ai_client', '1' ) || ! function_exists( 'wp_get_connectors' ) ) {
			return;
		}

		foreach ( wp_get_connectors() as $connector_id => $connector ) {
			if ( ! $this->is_openrouter_connector( $connector_id, $connector ) ) {
				continue;
			}

			$setting_name = $connector['authentication']['setting_name'] ?? '';
			if ( $setting_name ) {
				update_option( $setting_name, $key );
			}
		}
	}

	public function prefer_wpagent_openrouter_model( $models ) {
		$key   = $this->settings->get( 'openrouter_api_key', '' );
		$model = $this->settings->get( 'openrouter_model', '' );

		if ( empty( $key ) || empty( $model ) || '1' !== $this->settings->get( 'use_wp_ai_client', '1' ) ) {
			return $models;
		}

		return array_merge(
			array(
				array( 'openrouter', $model ),
			),
			is_array( $models ) ? $models : array()
		);
	}

	public function report_wpagent_openrouter_credentials( $has_credentials, $connectors ) {
		if ( $has_credentials || empty( $this->settings->get( 'openrouter_api_key', '' ) ) || '1' !== $this->settings->get( 'use_wp_ai_client', '1' ) ) {
			return $has_credentials;
		}

		foreach ( $connectors as $connector_id => $connector ) {
			if ( $this->is_openrouter_connector( $connector_id, $connector ) ) {
				return true;
			}
		}

		return $has_credentials;
	}

	public function get_text_generation_providers() {
		if ( ! function_exists( 'rest_do_request' ) || ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$request = new WP_REST_Request( 'GET', '/ai/v1/providers' );
		$request->set_param( 'capability', 'text_generation' );
		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return array();
		}

		$data = $response->get_data();

		return is_array( $data ) ? $data : array();
	}

	private function is_openrouter_connector( $connector_id, $connector ) {
		$name = strtolower( (string) ( $connector['name'] ?? '' ) );
		$id   = strtolower( (string) $connector_id );

		return false !== strpos( $id, 'openrouter' ) || false !== strpos( $name, 'openrouter' );
	}
}
}
