<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Email_Actions' ) ) {
class WPAgent_Email_Actions {
	private $repository;

	public function __construct( WPAgent_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register() {
		add_action( 'wpagent_send_queued_email', array( $this, 'send_queued_email' ), 10, 1 );
	}

	public function prompt_context( $agent ) {
		if ( '1' !== ( $agent['email_actions_enabled'] ?? '0' ) ) {
			return '';
		}

		$lines = array(
			'Envio de email autorizado pelo usuario:',
			'- Voce pode preparar um email para o usuario receber uma informacao, documento, resumo, plano, mensagem ou dados de produto/servico.',
			'- Antes de propor envio, colete somente os dados necessarios: email do destinatario, nome se util, assunto e conteudo final.',
			'- Nunca diga que enviou. A execucao real depende de o usuario clicar no botao de confirmacao do WPAgent.',
			'- Se faltar email valido, assunto ou conteudo, faca uma pergunta antes.',
			'- Use este recurso somente quando o usuario pedir ou autorizar claramente o envio por email.',
			'- Ao preparar o envio, explique brevemente o que sera enviado e termine a resposta com um bloco de JSON valido exatamente neste formato. Se o corpo tiver quebras de linha, use \\n dentro do campo body:',
			'```wpagent-email',
			'{"to_email":"email@exemplo.com","to_name":"Nome opcional","subject":"Assunto do email","body":"Conteudo completo do email","purpose":"Finalidade curta","data":{"campo":"valor"}}',
			'```',
			'- O campo body deve conter o texto final que sera enviado. O campo data deve conter apenas dados consentidos e relevantes coletados na conversa.',
		);

		if ( ! empty( $agent['email_actions_instructions'] ) ) {
			$lines[] = 'Instrucoes adicionais do administrador para emails deste agente:';
			$lines[] = $this->compact_text( $agent['email_actions_instructions'], 800 );
		}

		return implode( "\n", $lines );
	}

	public function extract_proposal( $reply, $agent ) {
		if ( '1' !== ( $agent['email_actions_enabled'] ?? '0' ) ) {
			return null;
		}

		if ( ! preg_match( '/```wpagent-email\s*([\s\S]*?)\s*```/s', (string) $reply, $matches ) ) {
			return null;
		}

		$data = json_decode( trim( $matches[1] ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$proposal = $this->prepare_proposal( $data, $agent );
		if ( is_wp_error( $proposal ) ) {
			return null;
		}

		$clean_reply = trim( preg_replace( '/```wpagent-email\s*[\s\S]*?\s*```/s', '', (string) $reply ) );
		if ( '' === $clean_reply ) {
			$clean_reply = __( 'Preparei um email para sua revisao.', 'wpagent' );
		}

		return array(
			'reply'    => $clean_reply,
			'proposal' => $proposal,
		);
	}

	public function prepare_proposal( $data, $agent ) {
		$payload = array(
			'agent_slug' => sanitize_key( $agent['slug'] ?? 'default' ),
			'to_email'   => sanitize_email( $data['to_email'] ?? '' ),
			'to_name'    => sanitize_text_field( $data['to_name'] ?? '' ),
			'subject'    => substr( sanitize_text_field( $data['subject'] ?? '' ), 0, 180 ),
			'body'       => substr( sanitize_textarea_field( $data['body'] ?? '' ), 0, (int) apply_filters( 'wpagent_email_body_max_length', 20000, $agent ) ),
			'purpose'    => substr( sanitize_text_field( $data['purpose'] ?? '' ), 0, 180 ),
			'data'       => $this->sanitize_data( $data['data'] ?? array() ),
		);

		if ( ! is_email( $payload['to_email'] ) ) {
			return new WP_Error( 'wpagent_email_invalid_recipient', __( 'Informe um email valido para o envio.', 'wpagent' ), array( 'status' => 400 ) );
		}

		if ( '' === trim( $payload['subject'] ) || '' === trim( $payload['body'] ) ) {
			return new WP_Error( 'wpagent_email_missing_content', __( 'O email precisa de assunto e conteudo antes do envio.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$payload['signature'] = $this->signature( $payload );

		return $payload;
	}

	public function send( $proposal, $agent, $context = array() ) {
		if ( '1' !== ( $agent['email_actions_enabled'] ?? '0' ) ) {
			return new WP_Error( 'wpagent_email_disabled', __( 'Este agente nao esta habilitado para enviar emails.', 'wpagent' ), array( 'status' => 403 ) );
		}

		$prepared = $this->prepare_proposal( (array) $proposal, $agent );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		if ( ! hash_equals( (string) ( $proposal['signature'] ?? '' ), $prepared['signature'] ) ) {
			return new WP_Error( 'wpagent_email_signature_invalid', __( 'A proposta de email expirou ou foi alterada. Peca ao agente para preparar o envio novamente.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$rate_limit = $this->check_rate_limit( $agent['slug'] );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$event_id = $this->repository->record_email_event(
			array(
				'user_id'         => get_current_user_id(),
				'agent_slug'      => $agent['slug'],
				'conversation_id' => sanitize_text_field( $context['conversation_id'] ?? '' ),
				'session_id'      => sanitize_text_field( $context['session_id'] ?? '' ),
				'interaction_id'  => absint( $context['interaction_id'] ?? 0 ),
				'recipient_email' => $prepared['to_email'],
				'recipient_name'  => $prepared['to_name'],
				'subject'         => $prepared['subject'],
				'body'            => $prepared['body'],
				'purpose'         => $prepared['purpose'],
				'status'          => 'queued',
				'error_message'   => '',
				'metadata'        => array(
					'data'    => $prepared['data'],
					'ip_hash' => $this->client_ip_hash(),
				),
			)
		);

		if ( ! $event_id ) {
			return new WP_Error( 'wpagent_email_not_queued', __( 'Nao foi possivel registrar o email para envio.', 'wpagent' ), array( 'status' => 500 ) );
		}

		$scheduled = wp_schedule_single_event( time() + 5, 'wpagent_send_queued_email', array( $event_id ) );
		if ( ! $scheduled ) {
			$this->repository->update_email_event_status( $event_id, 'failed', __( 'Nao foi possivel agendar o envio do email.', 'wpagent' ) );

			return new WP_Error( 'wpagent_email_not_scheduled', __( 'Nao foi possivel agendar o envio do email.', 'wpagent' ), array( 'status' => 500, 'event_id' => $event_id ) );
		}

		$this->spawn_cron();

		return array(
			'queued'   => true,
			'event_id' => $event_id,
			'to_email' => $prepared['to_email'],
			'subject'  => $prepared['subject'],
			'message'  => __( 'Email agendado para envio.', 'wpagent' ),
		);
	}

	public function send_queued_email( $event_id ) {
		$event = $this->repository->get_email_event( $event_id );
		if ( ! $event || ! in_array( $event['status'], array( 'queued', 'pending' ), true ) ) {
			return;
		}

		$to = sanitize_email( $event['recipient_email'] ?? '' );
		$subject = sanitize_text_field( $event['subject'] ?? '' );
		$body = sanitize_textarea_field( $event['body'] ?? '' );

		if ( ! is_email( $to ) || '' === trim( $subject ) || '' === trim( $body ) ) {
			$this->repository->update_email_event_status( $event_id, 'failed', __( 'O email agendado perdeu destinatario, assunto ou conteudo valido.', 'wpagent' ) );
			return;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			$this->repository->update_email_event_status( $event_id, 'failed', __( 'O WordPress nao conseguiu enviar o email. Verifique a configuracao SMTP/hospedagem.', 'wpagent' ) );
			return;
		}

		$this->repository->update_email_event_status( $event_id, 'sent', '' );
	}

	private function sanitize_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$output = array();
		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_scalar( $value ) ) {
				$output[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 500 );
			}
		}

		return $output;
	}

	private function signature( $payload ) {
		$copy = $payload;
		unset( $copy['signature'] );
		if ( isset( $copy['data'] ) && is_array( $copy['data'] ) ) {
			ksort( $copy['data'] );
		}
		ksort( $copy );

		return wp_hash( wp_json_encode( $copy ), 'wpagent_email_action' );
	}

	private function check_rate_limit( $agent_slug ) {
		$limit = (int) apply_filters( 'wpagent_email_rate_limit_per_hour', 5, $agent_slug );
		if ( $limit < 1 ) {
			return true;
		}

		$key = 'wpagent_email_rate_' . md5( sanitize_key( $agent_slug ) . '|' . $this->client_ip_hash() . '|' . gmdate( 'YmdH' ) );
		$count = absint( get_transient( $key ) );
		if ( $count >= $limit ) {
			return new WP_Error( 'wpagent_email_rate_limited', __( 'Muitos emails solicitados em pouco tempo. Aguarde e tente novamente.', 'wpagent' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS + MINUTE_IN_SECONDS );

		return true;
	}

	private function spawn_cron() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return;
		}

		wp_remote_post(
			site_url( 'wp-cron.php?doing_wp_cron=' . rawurlencode( microtime( true ) ) ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	private function client_ip_hash() {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		return wp_hash( $ip );
	}

	private function compact_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );

		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		return substr( $text, 0, $limit - 3 ) . '...';
	}
}
}
