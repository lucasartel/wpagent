<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_AI_Client {
	private $repository;
	private $settings;
	private $agents;
	private $prompt_builder;

	public function __construct( WPAgent_Repository $repository, WPAgent_Settings $settings, WPAgent_Agents $agents, WPAgent_Embeddings $embeddings = null, WPAgent_Admin_Abilities $admin_abilities = null, WPAgent_Email_Actions $email_actions = null ) {
		$this->repository     = $repository;
		$this->settings       = $settings;
		$this->agents         = $agents;
		$this->prompt_builder = new WPAgent_Prompt_Builder( $repository, $settings, $agents, $embeddings, $admin_abilities, $email_actions );
	}

	public function chat( $message, $user_id, $agent_slug, $conversation_id = '', $session_id = '' ) {
		$prompt = $this->prompt_builder->build( $message, $user_id, $agent_slug, $conversation_id, $session_id );
		$agent  = $prompt['agent'];

		if ( 'wordpress_ai' === ( $agent['provider_mode'] ?? '' ) ) {
			try {
				$core_response = $this->chat_with_wordpress_ai( $prompt['messages'], $agent );
			} catch ( Throwable $throwable ) {
				$core_response = $this->provider_exception_error( 'wpagent_wordpress_ai_exception', $throwable );
			}
			if ( ! is_wp_error( $core_response ) ) {
				return $this->with_prompt_metadata( $core_response, $prompt );
			}

			if ( empty( $this->settings->get( 'openrouter_api_key', '' ) ) ) {
				return $core_response;
			}
		}

		try {
			$response = $this->chat_with_openrouter( $prompt['messages'], $agent );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->with_prompt_metadata( $response, $prompt );
		} catch ( Throwable $throwable ) {
			return $this->provider_exception_error( 'wpagent_openrouter_exception', $throwable );
		}
	}

	private function chat_with_wordpress_ai( $messages, $agent ) {
		$system_instruction = $this->system_instruction_from_messages( $messages );
		$text               = $this->conversation_text_from_messages( $messages );

		if ( function_exists( 'WordPress\\AI\\get_ai_service' ) ) {
			$service = call_user_func( 'WordPress\\AI\\get_ai_service' );
			$builder = $service->create_textgen_prompt(
				$text,
				array(
					'system_instruction' => $system_instruction,
					'temperature'        => 0.4,
				)
			);
		} elseif ( function_exists( 'wp_ai_client_prompt' ) ) {
			$builder = wp_ai_client_prompt( $text )
				->using_system_instruction( $system_instruction )
				->using_temperature( 0.4 );
		} else {
			return new WP_Error( 'wpagent_wordpress_ai_unavailable', __( 'O WordPress AI Client nao esta disponivel.', 'wpagent' ), array( 'status' => 500 ) );
		}

		if ( ! empty( $agent['wordpress_ai_provider'] ) && ! empty( $agent['wordpress_ai_model'] ) && method_exists( $builder, 'using_model_preference' ) ) {
			$builder = $builder->using_model_preference(
				array(
					$agent['wordpress_ai_provider'],
					$agent['wordpress_ai_model'],
				)
			);
		}

		$result = $builder->generate_text();

		if ( is_wp_error( $result ) ) {
			return $this->normalize_provider_error( $result );
		}

		return array(
			'reply'        => (string) $result,
			'provider'     => $agent['wordpress_ai_provider'] ?: 'wordpress-ai',
			'model'        => $agent['wordpress_ai_model'] ?: '',
			'token_input'  => 0,
			'token_output' => 0,
			'raw'          => array(),
		);
	}

	private function chat_with_openrouter( $messages, $agent ) {
		$api_key = $this->settings->get( 'openrouter_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpagent_missing_api_key', __( 'Configure a chave da OpenRouter nas opcoes do WPAgent.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$model = $agent['openrouter_model'] ?: $this->settings->get( 'openrouter_model', 'openai/gpt-4.1-mini' );

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'timeout' => $this->request_timeout( 'chat' ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => get_bloginfo( 'name' ),
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => $messages,
						'temperature' => 0.4,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->normalize_provider_error( $response );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpagent_openrouter_error', $body['error']['message'] ?? __( 'Erro ao chamar a OpenRouter.', 'wpagent' ), array( 'status' => $code ) );
		}

		$reply = $body['choices'][0]['message']['content'] ?? '';

		if ( '' === trim( (string) $reply ) ) {
			return new WP_Error( 'wpagent_empty_ai_reply', __( 'O fornecedor de IA respondeu sem conteudo.', 'wpagent' ), array( 'status' => 502 ) );
		}

		return array(
			'reply'        => $reply,
			'provider'     => 'openrouter',
			'model'        => $body['model'] ?? $model,
			'token_input'  => absint( $body['usage']['prompt_tokens'] ?? 0 ),
			'token_output' => absint( $body['usage']['completion_tokens'] ?? 0 ),
			'raw'          => $body,
		);
	}

	public function maybe_extract_memory( $message, $reply, $interaction_id, $user_id, $agent_slug ) {
		if ( empty( $user_id ) ) {
			return;
		}

		$text = strtolower( wp_strip_all_tags( $message ) );
		$markers = array( 'meu ', 'minha ', 'gosto ', 'prefiro ', 'sou ', 'estou ', 'preciso ', 'quero ', 'tenho ' );

		foreach ( $markers as $marker ) {
			if ( false !== strpos( $text, $marker ) ) {
				$content = 'O usuario disse: ' . $this->compact_memory_sentence( $message );
				$this->repository->add_memory( $user_id, $agent_slug, $content, 'profile', $interaction_id, 3 );
				return;
			}
		}
	}

	private function system_instruction_from_messages( $messages ) {
		$lines = array();

		foreach ( $messages as $message ) {
			if ( 'system' === ( $message['role'] ?? '' ) ) {
				$lines[] = $message['content'] ?? '';
			}
		}

		return implode( "\n\n", array_filter( $lines ) );
	}

	private function conversation_text_from_messages( $messages ) {
		$lines = array();

		foreach ( $messages as $message ) {
			$role    = strtoupper( $message['role'] ?? 'user' );
			$content = $message['content'] ?? '';
			if ( 'SYSTEM' === $role ) {
				continue;
			}
			$lines[] = $role . ":\n" . $content;
		}

		return implode( "\n\n", $lines );
	}

	private function compact_memory_sentence( $message ) {
		$message = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $message ) ) );

		if ( strlen( $message ) > 260 ) {
			return substr( $message, 0, 257 ) . '...';
		}

		return $message;
	}

	private function request_timeout( $context ) {
		$timeout = (int) apply_filters( 'wpagent_ai_request_timeout', 75, $context );

		return max( 15, min( 120, $timeout ) );
	}

	private function provider_exception_error( $code, Throwable $throwable ) {
		$message = $throwable->getMessage();

		if ( $this->is_timeout_message( $message ) ) {
			return $this->timeout_error( $code, $message );
		}

		return new WP_Error(
			$code,
			__( 'Nao foi possivel concluir a resposta do provedor de IA. Tente novamente em instantes.', 'wpagent' ),
			array(
				'status'         => 502,
				'provider_error' => $code,
			)
		);
	}

	private function normalize_provider_error( WP_Error $error ) {
		$message = $error->get_error_message();

		if ( $this->is_timeout_message( $message ) ) {
			return $this->timeout_error( $error->get_error_code(), $message );
		}

		$data = $error->get_error_data();
		$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 502;

		return new WP_Error(
			$error->get_error_code(),
			$message ?: __( 'Nao foi possivel concluir a resposta do provedor de IA. Tente novamente em instantes.', 'wpagent' ),
			array( 'status' => $status )
		);
	}

	private function timeout_error( $code, $original_message = '' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $original_message ) {
			error_log( 'WPAgent AI timeout: ' . $original_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new WP_Error(
			'wpagent_ai_timeout',
			__( 'A resposta demorou demais. Tente novamente em instantes ou use uma pergunta mais curta.', 'wpagent' ),
			array(
				'status'         => 504,
				'provider_error' => $code,
			)
		);
	}

	private function is_timeout_message( $message ) {
		$message = strtolower( (string) $message );

		return false !== strpos( $message, 'timed out' )
			|| false !== strpos( $message, 'operation timed out' )
			|| false !== strpos( $message, 'curl error 28' )
			|| false !== strpos( $message, 'timeout' );
	}

	private function with_prompt_metadata( $response, $prompt ) {
		$response['knowledge'] = $prompt['knowledge'] ?? array();
		$response['memories']  = $prompt['memories'] ?? array();
		$response['context']   = array(
			'knowledge_count' => count( $response['knowledge'] ),
			'memory_count'    => count( $response['memories'] ),
		);

		return $response;
	}
}
