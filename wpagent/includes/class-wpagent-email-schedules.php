<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Email_Schedules' ) ) {
class WPAgent_Email_Schedules {
	const CRON_HOOK = 'wpagent_process_email_schedules';
	const RECURRING_FREQUENCIES = array( 'daily', 'weekly', 'monthly' );

	private $repository;
	private $settings;
	private $agents;

	public function __construct( WPAgent_Repository $repository, WPAgent_Settings $settings, WPAgent_Agents $agents ) {
		$this->repository = $repository;
		$this->settings   = $settings;
		$this->agents     = $agents;
	}

	public function register() {
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'process_due' ) );
		add_action( 'init', array( $this, 'maybe_handle_unsubscribe' ), 5 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_post_wpagent_save_schedule', array( $this, 'handle_save_schedule' ) );
			add_action( 'admin_post_wpagent_schedule_action', array( $this, 'handle_schedule_action' ) );
			add_action( 'admin_post_wpagent_export_subscribers', array( $this, 'handle_export_subscribers' ) );
		}
	}

	public function maybe_schedule_cron() {
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $next ) {
			wp_schedule_event( time() + 120, 'hourly', self::CRON_HOOK );
		}
	}

	public function enabled_for_agent( $agent ) {
		return '1' === ( $agent['email_schedules_enabled'] ?? '0' );
	}

	public function prompt_context( $agent ) {
		if ( ! $this->enabled_for_agent( $agent ) ) {
			return '';
		}

		$lines = array(
			'E-mails recorrentes e programados autorizados pelo administrador:',
			'- Quando as instrucoes deste agente definirem envios recorrentes (ex.: semanal, mensal) ou uma sequencia (ex.: um e-mail hoje e outro daqui a 3 dias), voce pode propor um agendamento.',
			'- So proponha o agendamento depois de coletar o e-mail do destinatario e o consentimento explicito para receber os envios programados.',
			'- Para envios recorrentes, defina o tipo como "recurring" e a frequencia (daily, weekly ou monthly). Para sequencias, use o tipo "sequence" com a lista de passos em horas a partir da inscricao.',
			'- O campo content_prompt descreve como cada e-mail deve ser gerado na hora do envio. O campo subject_template pode usar {{nome}} e {{email}}.',
			'- Nunca diga que enviou ou agendou sozinho. O sistema so agenda apos o usuario confirmar no botao do WPAgent.',
			'- Se faltar e-mail valido ou consentimento, pergunte antes. Se o usuario nao quiser envios recorrentes, nao proponha o agendamento.',
			'- Ao propor, termine a resposta com um bloco de JSON valido exatamente neste formato:',
			'```wpagent-email-schedule',
			'{"name":"Resumo semanal","type":"recurring","frequency":"weekly","subject_template":"Seu resumo, {{nome}}","content_prompt":"Escreva um resumo util...","to_email":"email@exemplo.com","to_name":"Nome","consent_note":"Usuario autorizou envios semanais","max_occurrences":0}',
			'```',
			'- Para sequencia (drip), use "type":"sequence" e "steps":[{"offset_hours":0,"label":"Boas-vindas"},{"offset_hours":72,"label":"Follow-up"}].',
		);

		return implode( "\n", $lines );
	}

	public function extract_schedule_proposal( $reply, $agent ) {
		if ( ! $this->enabled_for_agent( $agent ) ) {
			return null;
		}

		if ( ! preg_match( '/```wpagent-email-schedule\s*([\s\S]*?)\s*```/s', (string) $reply, $matches ) ) {
			return null;
		}

		$data = json_decode( trim( $matches[1] ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$proposal = $this->prepare_schedule_proposal( $data, $agent );
		if ( is_wp_error( $proposal ) ) {
			return null;
		}

		$clean_reply = trim( preg_replace( '/```wpagent-email-schedule\s*[\s\S]*?\s*```/s', '', (string) $reply ) );
		if ( '' === $clean_reply ) {
			$clean_reply = __( 'Preparei um agendamento de e-mails para sua confirmacao.', 'wpagent' );
		}

		return array(
			'reply'    => $clean_reply,
			'proposal' => $proposal,
		);
	}

	public function prepare_schedule_proposal( $data, $agent ) {
		$type = $data['type'] ?? $data['schedule_type'] ?? '';
		$type = in_array( $type, array( 'recurring', 'sequence' ), true ) ? $type : 'recurring';
		$frequency = $data['frequency'] ?? '';
		$frequency = in_array( $frequency, self::RECURRING_FREQUENCIES, true ) ? $frequency : 'weekly';

		$steps = array();
		$raw_steps = $data['steps'] ?? $data['sequence_steps'] ?? array();
		if ( is_array( $raw_steps ) && ! empty( $raw_steps ) ) {
			foreach ( $raw_steps as $step ) {
				if ( ! is_array( $step ) ) {
					continue;
				}
				$offset = absint( $step['offset_hours'] ?? 0 );
				$steps[] = array(
					'offset_hours' => $offset,
					'label'        => substr( sanitize_text_field( $step['label'] ?? '' ), 0, 120 ),
				);
			}
			$steps = array_slice( $steps, 0, 10 );
			usort( $steps, static function ( $a, $b ) {
				return $a['offset_hours'] <=> $b['offset_hours'];
			} );
		}

		$to_email = sanitize_email( $data['to_email'] ?? '' );
		if ( ! is_email( $to_email ) ) {
			return new WP_Error( 'wpagent_schedule_invalid_recipient', __( 'Informe um e-mail valido para o agendamento.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$content_prompt = sanitize_textarea_field( $data['content_prompt'] ?? '' );
		if ( '' === trim( $content_prompt ) ) {
			return new WP_Error( 'wpagent_schedule_missing_prompt', __( 'O agendamento precisa de instrucoes de conteudo (content_prompt).', 'wpagent' ), array( 'status' => 400 ) );
		}

		if ( 'sequence' === $type && empty( $steps ) ) {
			return new WP_Error( 'wpagent_schedule_missing_steps', __( 'A sequencia precisa de pelo menos um passo.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$payload = array(
			'agent_slug'       => sanitize_key( $agent['slug'] ?? 'default' ),
			'name'             => substr( sanitize_text_field( $data['name'] ?? '' ), 0, 190 ),
			'schedule_type'    => $type,
			'frequency'        => $frequency,
			'sequence_steps'   => $steps,
			'subject_template' => substr( sanitize_text_field( $data['subject_template'] ?? '' ), 0, 500 ),
			'content_prompt'   => $content_prompt,
			'to_email'         => $to_email,
			'to_name'          => sanitize_text_field( $data['to_name'] ?? '' ),
			'consent_note'     => substr( sanitize_textarea_field( $data['consent_note'] ?? '' ), 0, 500 ),
			'max_occurrences'  => absint( $data['max_occurrences'] ?? 0 ),
		);

		$payload['signature'] = $this->proposal_signature( $payload );

		return $payload;
	}

	public function confirm( $proposal, $agent, $context = array() ) {
		if ( ! $this->enabled_for_agent( $agent ) ) {
			return new WP_Error( 'wpagent_schedules_disabled', __( 'Este agente nao esta habilitado para agendamentos de e-mail.', 'wpagent' ), array( 'status' => 403 ) );
		}

		$prepared = $this->prepare_schedule_proposal( (array) $proposal, $agent );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		if ( ! hash_equals( (string) ( $proposal['signature'] ?? '' ), $prepared['signature'] ) ) {
			return new WP_Error( 'wpagent_schedule_signature_invalid', __( 'A proposta de agendamento expirou ou foi alterada.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$schedule = $this->repository->get_email_schedule_by_signature( $prepared['agent_slug'], $prepared['signature'] );
		if ( ! $schedule ) {
			$schedule_id = $this->repository->create_email_schedule(
				array(
					'agent_slug'       => $prepared['agent_slug'],
					'name'             => $prepared['name'] ?: $this->default_schedule_name( $prepared ),
					'schedule_type'    => $prepared['schedule_type'],
					'frequency'        => $prepared['frequency'],
					'sequence_steps'   => $prepared['sequence_steps'],
					'subject_template' => $prepared['subject_template'],
					'content_prompt'   => $prepared['content_prompt'],
					'consent_required' => 1,
					'max_occurrences'  => $prepared['max_occurrences'],
					'status'           => 'active',
					'origin'           => 'agent',
					'signature'        => $prepared['signature'],
					'metadata'         => array( 'first_to_name' => $prepared['to_name'] ),
				)
			);
		} else {
			$schedule_id = (int) $schedule['id'];
		}

		$existing = $this->repository->get_email_subscription_by_email( $schedule_id, $prepared['to_email'] );
		if ( $existing && 'subscribed' === ( $existing['consent_status'] ?? '' ) ) {
			return array(
				'confirmed'    => true,
				'already'      => true,
				'schedule_id'  => $schedule_id,
				'to_email'     => $prepared['to_email'],
				'next_send_at' => $existing['next_send_at'] ?? '',
				'message'      => __( 'Este e-mail ja esta inscrito neste agendamento.', 'wpagent' ),
			);
		}

		$next_send_at = $this->compute_first_send_at( $prepared );
		$subscription_id = $this->repository->create_email_subscription(
			array(
				'schedule_id'      => $schedule_id,
				'agent_slug'       => $prepared['agent_slug'],
				'user_id'          => get_current_user_id(),
				'recipient_email'  => $prepared['to_email'],
				'recipient_name'   => $prepared['to_name'],
				'conversation_id'  => sanitize_text_field( $context['conversation_id'] ?? '' ),
				'session_id'       => sanitize_text_field( $context['session_id'] ?? '' ),
				'interaction_id'   => absint( $context['interaction_id'] ?? 0 ),
				'consent_note'     => $prepared['consent_note'],
				'current_step'     => 'sequence' === $prepared['schedule_type'] ? 0 : 0,
				'next_send_at'     => $next_send_at,
				'metadata'         => array( 'origin' => 'chat' ),
			)
		);

		if ( ! $subscription_id ) {
			return new WP_Error( 'wpagent_subscription_not_created', __( 'Nao foi possivel registrar a inscricao.', 'wpagent' ), array( 'status' => 500 ) );
		}

		return array(
			'confirmed'      => true,
			'schedule_id'    => $schedule_id,
			'subscription_id'=> $subscription_id,
			'to_email'       => $prepared['to_email'],
			'next_send_at'   => $next_send_at,
			'schedule_type'  => $prepared['schedule_type'],
			'frequency'      => $prepared['frequency'],
			'message'        => __( 'Inscricao confirmada. O proximo envio esta agendado.', 'wpagent' ),
		);
	}

	private function compute_first_send_at( $proposal ) {
		if ( 'sequence' === $proposal['schedule_type'] && ! empty( $proposal['sequence_steps'] ) ) {
			$offset = absint( $proposal['sequence_steps'][0]['offset_hours'] ) * HOUR_IN_SECONDS;
		} else {
			$offset = $this->frequency_seconds( $proposal['frequency'] );
		}

		return gmdate( 'Y-m-d H:i:s', time() + $offset );
	}

	public function frequency_seconds( $frequency ) {
		$map = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => 30 * DAY_IN_SECONDS,
		);

		return $map[ $frequency ] ?? WEEK_IN_SECONDS;
	}

	private function default_schedule_name( $proposal ) {
		if ( 'sequence' === $proposal['schedule_type'] ) {
			return __( 'Sequencia de e-mails', 'wpagent' );
		}

		$labels = array(
			'daily'   => __( 'Diario', 'wpagent' ),
			'weekly'  => __( 'Semanal', 'wpagent' ),
			'monthly' => __( 'Mensal', 'wpagent' ),
		);

		return ( $labels[ $proposal['frequency'] ] ?? __( 'Recorrente', 'wpagent' ) ) . ' - ' . $proposal['agent_slug'];
	}

	private function proposal_signature( $payload ) {
		$copy = $payload;
		unset( $copy['signature'] );
		if ( isset( $copy['sequence_steps'] ) && is_array( $copy['sequence_steps'] ) ) {
			ksort( $copy['sequence_steps'] );
		}
		ksort( $copy );

		return wp_hash( wp_json_encode( $copy ), 'wpagent_email_action' );
	}

	public function process_due() {
		$per_run = (int) apply_filters( 'wpagent_email_schedules_per_run', 30 );
		$due = $this->repository->get_due_email_subscriptions( $per_run );

		if ( empty( $due ) ) {
			return;
		}

		$agents_cache = array();
		$schedules_cache = array();

		foreach ( $due as $subscription ) {
			$schedule_id = absint( $subscription['schedule_id'] );
			$agent_slug  = sanitize_key( $subscription['agent_slug'] );

			if ( ! isset( $schedules_cache[ $schedule_id ] ) ) {
				$schedules_cache[ $schedule_id ] = $this->repository->get_email_schedule( $schedule_id );
			}
			$schedule = $schedules_cache[ $schedule_id ];
			if ( ! $schedule || 'active' !== $schedule['status'] ) {
				continue;
			}

			if ( ! isset( $agents_cache[ $agent_slug ] ) ) {
				$agents_cache[ $agent_slug ] = $this->agents->get_agent( $agent_slug );
			}
			$agent = $agents_cache[ $agent_slug ];

			$this->process_subscription( $subscription, $schedule, $agent );
		}
	}

	private function process_subscription( $subscription, $schedule, $agent ) {
		$subscription_id = absint( $subscription['id'] );

		if ( 'subscribed' !== $subscription['consent_status'] || 'active' !== $subscription['status'] ) {
			$this->repository->update_email_subscription( $subscription_id, array( 'next_send_at' => null ) );
			return;
		}

		if ( $this->subscription_is_finished( $subscription, $schedule ) ) {
			$this->repository->update_email_subscription( $subscription_id, array( 'next_send_at' => null, 'status' => 'completed' ) );
			return;
		}

		$generated = $this->generate_email_content( $schedule, $subscription, $agent );
		if ( is_wp_error( $generated ) ) {
			return;
		}

		$body = $this->append_unsubscribe_footer( $generated['body'], $subscription );
		$event_id = $this->repository->record_email_event(
			array(
				'user_id'         => absint( $subscription['user_id'] ),
				'agent_slug'      => sanitize_key( $subscription['agent_slug'] ),
				'conversation_id' => $subscription['conversation_id'],
				'session_id'      => $subscription['session_id'],
				'interaction_id'  => absint( $subscription['interaction_id'] ),
				'recipient_email' => $subscription['recipient_email'],
				'recipient_name'  => $subscription['recipient_name'],
				'subject'         => $generated['subject'],
				'body'            => $body,
				'purpose'         => 'schedule:' . absint( $schedule['id'] ),
				'status'          => 'queued',
				'metadata'        => array(
					'schedule_id'     => absint( $schedule['id'] ),
					'subscription_id' => $subscription_id,
					'step'            => absint( $subscription['current_step'] ),
				),
			)
		);

		if ( ! $event_id ) {
			return;
		}

		wp_schedule_single_event( time() + 5, 'wpagent_send_queued_email', array( $event_id ) );

		$this->advance_subscription( $subscription_id, $subscription, $schedule );
	}

	private function subscription_is_finished( $subscription, $schedule ) {
		$max_occ = absint( $schedule['max_occurrences'] );
		if ( $max_occ > 0 && absint( $subscription['occurrences_sent'] ) >= $max_occ ) {
			return true;
		}

		if ( 'sequence' === $schedule['schedule_type'] ) {
			$steps = is_array( $schedule['sequence_steps'] ) ? $schedule['sequence_steps'] : array();
			if ( absint( $subscription['current_step'] ) >= count( $steps ) ) {
				return true;
			}
		}

		return false;
	}

	private function advance_subscription( $subscription_id, $subscription, $schedule ) {
		$type = $schedule['schedule_type'];
		$occurrences = absint( $subscription['occurrences_sent'] ) + 1;
		$now = current_time( 'mysql', true );

		if ( 'sequence' === $type ) {
			$steps = is_array( $schedule['sequence_steps'] ) ? $schedule['sequence_steps'] : array();
			$next_step = absint( $subscription['current_step'] ) + 1;

			if ( $next_step >= count( $steps ) ) {
				$this->repository->update_email_subscription(
					$subscription_id,
					array(
						'occurrences_sent' => $occurrences,
						'last_sent_at'     => $now,
						'next_send_at'     => null,
						'status'           => 'completed',
					)
				);
				return;
			}

			$next_offset = absint( $steps[ $next_step ]['offset_hours'] ) * HOUR_IN_SECONDS;
			$next_send_at = gmdate( 'Y-m-d H:i:s', time() + $next_offset );

			$this->repository->update_email_subscription(
				$subscription_id,
				array(
					'occurrences_sent' => $occurrences,
					'current_step'     => $next_step,
					'last_sent_at'     => $now,
					'next_send_at'     => $next_send_at,
				)
			);
			return;
		}

		$next_send_at = gmdate( 'Y-m-d H:i:s', time() + $this->frequency_seconds( $schedule['frequency'] ) );
		$update = array(
			'occurrences_sent' => $occurrences,
			'last_sent_at'     => $now,
			'next_send_at'     => $next_send_at,
		);

		$max_occ = absint( $schedule['max_occurrences'] );
		if ( $max_occ > 0 && $occurrences >= $max_occ ) {
			$update['next_send_at'] = null;
			$update['status'] = 'completed';
		}

		$this->repository->update_email_subscription( $subscription_id, $update );
	}

	private function generate_email_content( $schedule, $subscription, $agent ) {
		$recipient_name = $subscription['recipient_name'];
		$recipient_email = $subscription['recipient_email'];
		$user_id = absint( $subscription['user_id'] );
		$agent_slug = sanitize_key( $subscription['agent_slug'] );

		$context_lines = array(
			'Destinatario: ' . ( $recipient_name ? $recipient_name . ' <' . $recipient_email . '>' : $recipient_email ),
		);

		if ( $user_id ) {
			$profile = $this->repository->get_user_profile_memory( $user_id, $agent_slug );
			if ( $profile && '' !== trim( wp_strip_all_tags( $profile ) ) ) {
				$context_lines[] = 'Perfil do destinatario: ' . trim( wp_strip_all_tags( $profile ) );
			}

			$memories = $this->repository->get_memories( $user_id, $agent_slug, 5 );
			if ( ! empty( $memories ) ) {
				$memory_lines = array();
				foreach ( $memories as $memory ) {
					$memory_lines[] = '- ' . trim( wp_strip_all_tags( $memory['content'] ) );
				}
				$context_lines[] = "Memorias do destinatario:\n" . implode( "\n", $memory_lines );
			}
		}

		$instruction = __( 'Voce gera o corpo de um e-mail recorrente para o destinatario. Escreva apenas o corpo pronto para envio, em texto plano, sem codigos de saida, sem markdown e sem mencionar que e automatico.', 'wpagent' );
		$prompt = trim( $schedule['content_prompt'] ) . "\n\n" . implode( "\n", $context_lines );

		$body = $this->generate_text( $prompt, $instruction, $agent );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$subject = $this->resolve_subject( $schedule['subject_template'], $recipient_name, $recipient_email );
		if ( '' === trim( $subject ) ) {
			$subject = $schedule['name'] ?: __( 'Atualizacao do WPAgent', 'wpagent' );
		}

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	private function resolve_subject( $template, $name, $email ) {
		$subject = str_replace( array( '{{nome}}', '{{Nome}}', '{{name}}', '{{email}}' ), array( $name, $name, $name, $email ), (string) $template );
		return substr( sanitize_text_field( $subject ), 0, 180 );
	}

	private function generate_text( $prompt, $system_instruction, $agent ) {
		if ( 'wordpress_ai' === ( $agent['provider_mode'] ?? '' ) ) {
			if ( function_exists( 'WordPress\\AI\\get_ai_service' ) ) {
				$service = call_user_func( 'WordPress\\AI\\get_ai_service' );
				$builder = $service->create_textgen_prompt(
					$prompt,
					array(
						'system_instruction' => $system_instruction,
						'temperature'        => 0.4,
					)
				);
			} elseif ( function_exists( 'wp_ai_client_prompt' ) ) {
				$builder = wp_ai_client_prompt( $prompt )
					->using_system_instruction( $system_instruction )
					->using_temperature( 0.4 );
			}

			if ( ! empty( $builder ) ) {
				$builder = $this->apply_model_preference( $builder, $agent );
				$result = $builder->generate_text();
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return (string) $result;
			}
		}

		return $this->generate_text_with_openrouter( $prompt, $system_instruction, $agent );
	}

	private function apply_model_preference( $builder, $agent ) {
		$provider = $agent['wordpress_ai_provider'] ?? $this->settings->get( 'wordpress_ai_provider', '' );
		$model    = $agent['wordpress_ai_model'] ?? $this->settings->get( 'wordpress_ai_model', '' );

		if ( empty( $provider ) || empty( $model ) || ! method_exists( $builder, 'using_model_preference' ) ) {
			return $builder;
		}

		return $builder->using_model_preference( array( $provider, $model ) );
	}

	private function generate_text_with_openrouter( $prompt, $system_instruction, $agent ) {
		$api_key = $this->settings->get( 'openrouter_api_key', '' );
		$model = $agent['openrouter_model'] ?? $this->settings->get( 'openrouter_model', 'openai/gpt-4.1-mini' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpagent_schedule_no_provider', __( 'Nenhum provedor de IA configurado para gerar o e-mail.', 'wpagent' ) );
		}

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => get_bloginfo( 'name' ),
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => array(
							array( 'role' => 'system', 'content' => $system_instruction ),
							array( 'role' => 'user', 'content' => $prompt ),
						),
						'temperature' => 0.4,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpagent_schedule_ai_error', $body['error']['message'] ?? __( 'Erro ao gerar o conteudo do e-mail.', 'wpagent' ) );
		}

		$text = $body['choices'][0]['message']['content'] ?? '';
		if ( '' === trim( (string) $text ) ) {
			return new WP_Error( 'wpagent_schedule_empty', __( 'O provedor de IA nao retornou conteudo.', 'wpagent' ) );
		}

		return trim( (string) $text );
	}

	private function append_unsubscribe_footer( $body, $subscription ) {
		$link = $this->unsubscribe_url( $subscription['unsubscribe_token'] );
		$footer = "\n\n--\n" . sprintf(
			/* translators: %s: unsubscribe url. */
			__( 'Voce recebeu este e-mail porque se inscreveu na conversa com o WPAgent. Para parar de receber, acesse: %s', 'wpagent' ),
			$link
		);

		return $body . $footer;
	}

	public function unsubscribe_url( $token ) {
		return add_query_arg(
			array(
				'wpagent_unsubscribe' => '1',
				't'                   => rawurlencode( $token ),
			),
			home_url()
		);
	}

	public function maybe_handle_unsubscribe() {
		if ( ! isset( $_GET['wpagent_unsubscribe'] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );
		if ( '' === $token ) {
			return;
		}

		$subscription = $this->repository->get_email_subscription_by_token( $token );
		if ( ! $subscription ) {
			return;
		}

		if ( 'subscribed' === $subscription['consent_status'] ) {
			$this->repository->update_email_subscription(
				absint( $subscription['id'] ),
				array(
					'consent_status' => 'unsubscribed',
					'unsubscribed_at'=> current_time( 'mysql', true ),
					'next_send_at'   => null,
					'status'         => 'unsubscribed',
				)
			);
		}

		$this->render_unsubscribe_page();
	}

	public function add_admin_menu() {
		add_submenu_page(
			'wpagent',
			__( 'Agendamentos', 'wpagent' ),
			__( 'Agendamentos', 'wpagent' ),
			'manage_options',
			'wpagent-email-schedules',
			array( $this, 'render_admin_page' )
		);
	}

	public function admin_url( $args = array() ) {
		$args = array_merge( array( 'page' => 'wpagent-email-schedules' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		$action       = sanitize_key( wp_unslash( $_GET['view'] ?? 'list' ) );
		$edit_id      = absint( wp_unslash( $_GET['edit'] ?? 0 ) );
		$subscribers  = absint( wp_unslash( $_GET['subscribers'] ?? 0 ) );
		$agents       = $this->agents->get_agent_options();

		echo '<div class="wrap"><h1>' . esc_html__( 'Agendamentos de e-mail', 'wpagent' ) . '</h1>';

		if ( 'new' === $action || $edit_id ) {
			$this->render_schedule_form( $edit_id, $agents );
			echo '</div>';
			return;
		}

		if ( $subscribers ) {
			$this->render_subscribers_page( $subscribers );
			echo '</div>';
			return;
		}

		echo '<p><a class="button button-primary" href="' . esc_url( $this->admin_url( array( 'view' => 'new' ) ) ) . '">' . esc_html__( 'Criar agendamento', 'wpagent' ) . '</a></p>';
		$this->render_schedules_table( $agents );
		echo '</div>';
	}

	private function render_schedules_table( $agents ) {
		$schedules = $this->repository->list_email_schedules();

		if ( empty( $schedules ) ) {
			echo '<p>' . esc_html__( 'Nenhum agendamento ainda. Crie um manualmente ou ative os e-mails recorrentes em um agente para que ele proponha agendamentos no chat.', 'wpagent' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Nome', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Agente', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Cadencia', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Inscritos', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Acoes', 'wpagent' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $schedules as $schedule ) {
			$id = absint( $schedule['id'] );
			$subscribed = $this->repository->count_email_subscriptions( $id, 'subscribed' );
			$status = sanitize_key( $schedule['status'] );
			$status_label = $this->status_label( $status );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $schedule['name'] ?: '—' ) . '</strong><br><span class="description">' . esc_html( $schedule['origin'] ) . '</span></td>';
			echo '<td>' . esc_html( $agents[ $schedule['agent_slug'] ] ?? $schedule['agent_slug'] ) . '</td>';
			echo '<td>' . esc_html( $this->type_label( $schedule['schedule_type'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( $this->cadence_summary( $schedule ) ) . '</td>';
			echo '<td><a href="' . esc_url( $this->admin_url( array( 'subscribers' => $id ) ) ) . '">' . absint( $subscribed ) . '</a></td>';
			echo '<td>' . esc_html( $status_label ) . '</td>';
			echo '<td>' . $this->action_links( $id, $status ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function action_links( $id, $status ) {
		$links = array();
		$links[] = '<a href="' . esc_url( $this->admin_url( array( 'edit' => $id ) ) ) . '">' . esc_html__( 'Editar', 'wpagent' ) . '</a>';

		if ( 'active' === $status ) {
			$links[] = $this->action_post_link( 'pause', $id, __( 'Pausar', 'wpagent' ) );
		} elseif ( 'paused' === $status ) {
			$links[] = $this->action_post_link( 'resume', $id, __( 'Retomar', 'wpagent' ) );
		}

		$links[] = $this->action_post_link( 'delete', $id, __( 'Excluir', 'wpagent' ), true );

		return implode( ' | ', $links );
	}

	private function action_post_link( $action, $id, $label, $confirm = false ) {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpagent_schedule_action&do=' . $action . '&id=' . absint( $id ) ),
			'wpagent_schedule_' . $action
		);
		$attr = $confirm ? ' onclick="return confirm(\'' . esc_js( __( 'Tem certeza?', 'wpagent' ) ) . '\')"' : '';
		return '<a href="' . esc_url( $url ) . '"' . $attr . '>' . esc_html( $label ) . '</a>';
	}

	private function render_schedule_form( $edit_id, $agents ) {
		$schedule = $edit_id ? $this->repository->get_email_schedule( $edit_id ) : null;

		$name          = $schedule['name'] ?? '';
		$agent_slug    = $schedule['agent_slug'] ?? array_key_first( $agents );
		$schedule_type = $schedule['schedule_type'] ?? 'recurring';
		$frequency     = $schedule['frequency'] ?? 'weekly';
		$steps         = $schedule['sequence_steps'] ?? array();
		$subject       = $schedule['subject_template'] ?? '';
		$content       = $schedule['content_prompt'] ?? '';
		$max_occ       = $schedule['max_occurrences'] ?? 0;

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpagent_save_schedule', 'wpagent_schedule_nonce' );
		echo '<input type="hidden" name="action" value="wpagent_save_schedule">';
		if ( $edit_id ) {
			echo '<input type="hidden" name="schedule_id" value="' . absint( $edit_id ) . '">';
		}

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="wpagent-sched-name">' . esc_html__( 'Nome', 'wpagent' ) . '</label></th>';
		echo '<td><input id="wpagent-sched-name" class="regular-text" name="schedule_name" value="' . esc_attr( $name ) . '"></td></tr>';

		echo '<tr><th scope="row"><label for="wpagent-sched-agent">' . esc_html__( 'Agente', 'wpagent' ) . '</label></th>';
		echo '<td><select id="wpagent-sched-agent" name="schedule_agent">';
		foreach ( $agents as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $agent_slug, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Tipo', 'wpagent' ) . '</th>';
		echo '<td><label><input type="radio" name="schedule_type" value="recurring" ' . checked( $schedule_type, 'recurring', false ) . '> ' . esc_html__( 'Recorrente', 'wpagent' ) . '</label><br>';
		echo '<label><input type="radio" name="schedule_type" value="sequence" ' . checked( $schedule_type, 'sequence', false ) . '> ' . esc_html__( 'Sequencia (drip)', 'wpagent' ) . '</label></td></tr>';

		echo '<tr><th scope="row"><label for="wpagent-sched-frequency">' . esc_html__( 'Frequencia', 'wpagent' ) . '</label></th>';
		echo '<td><select id="wpagent-sched-frequency" name="schedule_frequency">';
		foreach ( array( 'daily' => __( 'Diario', 'wpagent' ), 'weekly' => __( 'Semanal', 'wpagent' ), 'monthly' => __( 'Mensal', 'wpagent' ) ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $frequency, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Usado apenas para agendamentos recorrentes.', 'wpagent' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="wpagent-sched-steps">' . esc_html__( 'Passos da sequencia', 'wpagent' ) . '</label></th>';
		$steps_text = '';
		if ( is_array( $steps ) ) {
			$lines = array();
			foreach ( $steps as $step ) {
				$lines[] = ( $step['offset_hours'] ?? 0 ) . ' | ' . ( $step['label'] ?? '' );
			}
			$steps_text = implode( "\n", $lines );
		}
		echo '<td><textarea id="wpagent-sched-steps" class="large-text code" rows="4" name="schedule_steps" placeholder="0 | Boas-vindas&#10;72 | Follow-up 3 dias">' . esc_textarea( $steps_text ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Uma linha por passo no formato: horas a partir da inscricao | rotulo. Usado apenas para sequencia.', 'wpagent' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="wpagent-sched-subject">' . esc_html__( 'Assunto', 'wpagent' ) . '</label></th>';
		echo '<td><input id="wpagent-sched-subject" class="large-text" name="schedule_subject" value="' . esc_attr( $subject ) . '"><p class="description">' . esc_html__( 'Pode usar {{nome}} e {{email}}.', 'wpagent' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="wpagent-sched-content">' . esc_html__( 'Instrucao de conteudo', 'wpagent' ) . '</label></th>';
		echo '<td><textarea id="wpagent-sched-content" class="large-text" rows="6" name="schedule_content">' . esc_textarea( $content ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Como a IA deve gerar o corpo de cada e-mail na hora do envio.', 'wpagent' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="wpagent-sched-max">' . esc_html__( 'Maximo de envios por inscrito', 'wpagent' ) . '</label></th>';
		echo '<td><input id="wpagent-sched-max" class="small-text" type="number" min="0" name="schedule_max_occurrences" value="' . esc_attr( $max_occ ) . '"><p class="description">' . esc_html__( '0 = ilimitado (para recorrentes) ou ate o fim da sequencia.', 'wpagent' ) . '</p></td></tr>';
		echo '</table>';

		submit_button( $edit_id ? __( 'Salvar agendamento', 'wpagent' ) : __( 'Criar agendamento', 'wpagent' ) );
		echo '</form>';
		echo '<p><a href="' . esc_url( $this->admin_url() ) . '">' . esc_html__( 'Voltar', 'wpagent' ) . '</a></p>';
	}

	private function render_subscribers_page( $schedule_id ) {
		$schedule = $this->repository->get_email_schedule( $schedule_id );
		if ( ! $schedule ) {
			echo '<p>' . esc_html__( 'Agendamento nao encontrado.', 'wpagent' ) . '</p>';
			return;
		}

		$filter_status = sanitize_key( wp_unslash( $_GET['filter'] ?? '' ) );
		$args = $filter_status ? array( 'status' => $filter_status ) : array();
		$subs = $this->repository->list_email_subscriptions( $schedule_id, array_merge( $args, array( 'limit' => 500 ) ) );

		echo '<h2>' . esc_html( $schedule['name'] ) . ' — ' . esc_html__( 'Inscritos', 'wpagent' ) . '</h2>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( $this->admin_url( array( 'subscribers' => $schedule_id ) ) ) . '">' . esc_html__( 'Todos', 'wpagent' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $this->admin_url( array( 'subscribers' => $schedule_id, 'filter' => 'subscribed' ) ) ) . '">' . esc_html__( 'Ativos', 'wpagent' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $this->admin_url( array( 'subscribers' => $schedule_id, 'filter' => 'unsubscribed' ) ) ) . '">' . esc_html__( 'Descadastrados', 'wpagent' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_export_subscribers&id=' . $schedule_id ), 'wpagent_export_subscribers' ) ) . '">' . esc_html__( 'Exportar CSV', 'wpagent' ) . '</a>';
		echo '</p>';

		if ( empty( $subs ) ) {
			echo '<p>' . esc_html__( 'Nenhum inscrito ainda.', 'wpagent' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'E-mail', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Nome', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Enviados', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Ultimo envio', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Proximo envio', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Acao', 'wpagent' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $subs as $sub ) {
			$status = sanitize_key( $sub['consent_status'] );
			echo '<tr>';
			echo '<td>' . esc_html( $sub['recipient_email'] ) . '</td>';
			echo '<td>' . esc_html( $sub['recipient_name'] ) . '</td>';
			echo '<td>' . esc_html( $this->status_label( $status ) ) . '</td>';
			echo '<td>' . absint( $sub['occurrences_sent'] ) . '</td>';
			echo '<td>' . esc_html( $sub['last_sent_at'] ?? '—' ) . '</td>';
			echo '<td>' . esc_html( $sub['next_send_at'] ?? '—' ) . '</td>';
			echo '<td>';
			if ( 'subscribed' === $status ) {
				$url = wp_nonce_url(
					admin_url( 'admin-post.php?action=wpagent_schedule_action&do=unsubscribe&id=' . absint( $sub['id'] ) . '&from=' . absint( $schedule_id ) ),
					'wpagent_schedule_unsubscribe'
				);
				echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Descadastrar', 'wpagent' ) . '</a>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p><a href="' . esc_url( $this->admin_url() ) . '">' . esc_html__( 'Voltar', 'wpagent' ) . '</a></p>';
	}

	private function cadence_summary( $schedule ) {
		if ( 'sequence' === $schedule['schedule_type'] ) {
			$steps = is_array( $schedule['sequence_steps'] ) ? $schedule['sequence_steps'] : array();
			$labels = array_map( static function ( $step ) {
				return $step['offset_hours'] . 'h';
			}, $steps );
			return esc_html__( 'Sequencia', 'wpagent' ) . ': ' . esc_html( implode( ', ', $labels ) );
		}

		$labels = array(
			'daily'   => __( 'Diario', 'wpagent' ),
			'weekly'  => __( 'Semanal', 'wpagent' ),
			'monthly' => __( 'Mensal', 'wpagent' ),
		);

		return esc_html( $labels[ $schedule['frequency'] ] ?? $schedule['frequency'] );
	}

	private function type_label( $type ) {
		$labels = array(
			'recurring' => __( 'Recorrente', 'wpagent' ),
			'sequence'  => __( 'Sequencia', 'wpagent' ),
		);
		return $labels[ $type ] ?? $type;
	}

	private function status_label( $status ) {
		$labels = array(
			'active'       => __( 'Ativo', 'wpagent' ),
			'paused'       => __( 'Pausado', 'wpagent' ),
			'archived'     => __( 'Arquivado', 'wpagent' ),
			'completed'    => __( 'Concluido', 'wpagent' ),
			'subscribed'   => __( 'Inscrito', 'wpagent' ),
			'unsubscribed' => __( 'Descadastrado', 'wpagent' ),
		);
		return $labels[ $status ] ?? $status;
	}

	public function handle_save_schedule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		check_admin_referer( 'wpagent_save_schedule', 'wpagent_schedule_nonce' );

		$schedule_id = absint( $_POST['schedule_id'] ?? 0 );
		$name        = sanitize_text_field( wp_unslash( $_POST['schedule_name'] ?? '' ) );
		$agent_slug  = sanitize_key( wp_unslash( $_POST['schedule_agent'] ?? 'default' ) );
		$type        = in_array( sanitize_key( wp_unslash( $_POST['schedule_type'] ?? '' ) ), array( 'recurring', 'sequence' ), true ) ? sanitize_key( wp_unslash( $_POST['schedule_type'] ) ) : 'recurring';
		$frequency   = in_array( sanitize_key( wp_unslash( $_POST['schedule_frequency'] ?? '' ) ), self::RECURRING_FREQUENCIES, true ) ? sanitize_key( wp_unslash( $_POST['schedule_frequency'] ) ) : 'weekly';
		$subject     = sanitize_text_field( wp_unslash( $_POST['schedule_subject'] ?? '' ) );
		$content     = sanitize_textarea_field( wp_unslash( $_POST['schedule_content'] ?? '' ) );
		$max_occ     = absint( $_POST['schedule_max_occurrences'] ?? 0 );

		$steps = array();
		$steps_raw = sanitize_textarea_field( wp_unslash( $_POST['schedule_steps'] ?? '' ) );
		foreach ( preg_split( '/\r\n|\r|\n/', $steps_raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$steps[] = array(
				'offset_hours' => absint( $parts[0] ?? 0 ),
				'label'        => substr( sanitize_text_field( $parts[1] ?? '' ), 0, 120 ),
			);
		}
		usort( $steps, static function ( $a, $b ) {
			return $a['offset_hours'] <=> $b['offset_hours'];
		});

		$data = array(
			'agent_slug'       => $agent_slug,
			'name'             => $name,
			'schedule_type'    => $type,
			'frequency'        => $frequency,
			'sequence_steps'   => $steps,
			'subject_template' => $subject,
			'content_prompt'   => $content,
			'max_occurrences'  => $max_occ,
			'status'           => 'active',
			'origin'           => 'admin',
			'created_by'       => get_current_user_id(),
			'signature'        => wp_hash( $agent_slug . '|' . $name . '|' . $type . '|' . time(), 'wpagent_email_action' ),
		);

		if ( $schedule_id ) {
			$this->repository->update_email_schedule( $schedule_id, array(
				'name'             => $data['name'],
				'schedule_type'    => $data['schedule_type'],
				'frequency'        => $data['frequency'],
				'sequence_steps'   => $data['sequence_steps'],
				'subject_template' => $data['subject_template'],
				'content_prompt'   => $data['content_prompt'],
				'max_occurrences'  => $data['max_occurrences'],
			) );
		} else {
			$schedule_id = $this->repository->create_email_schedule( $data );
		}

		wp_safe_redirect( $this->admin_url() );
		exit;
	}

	public function handle_schedule_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		$do   = sanitize_key( wp_unslash( $_GET['do'] ?? '' ) );
		$id   = absint( wp_unslash( $_GET['id'] ?? 0 ) );

		check_admin_referer( 'wpagent_schedule_' . $do );

		switch ( $do ) {
			case 'pause':
				$this->repository->update_email_schedule( $id, array( 'status' => 'paused' ) );
				break;
			case 'resume':
				$this->repository->update_email_schedule( $id, array( 'status' => 'active' ) );
				break;
			case 'archive':
				$this->repository->update_email_schedule( $id, array( 'status' => 'archived' ) );
				break;
			case 'delete':
				$this->repository->delete_email_schedule( $id );
				break;
			case 'unsubscribe':
				$this->repository->update_email_subscription( $id, array(
					'consent_status' => 'unsubscribed',
					'unsubscribed_at'=> current_time( 'mysql', true ),
					'next_send_at'   => null,
					'status'         => 'unsubscribed',
				) );
				break;
		}

		$subscribers = absint( wp_unslash( $_GET['from'] ?? 0 ) );
		$redirect = $subscribers ? $this->admin_url( array( 'subscribers' => $subscribers ) ) : $this->admin_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_export_subscribers() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		check_admin_referer( 'wpagent_export_subscribers' );

		$schedule_id = absint( wp_unslash( $_GET['id'] ?? 0 ) );
		$schedule = $this->repository->get_email_schedule( $schedule_id );
		$subs = $this->repository->list_email_subscriptions( $schedule_id, array( 'limit' => 10000 ) );

		$filename = 'wpagent-subscribers-' . absint( $schedule_id ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'name', 'status', 'consented_at', 'occurrences_sent', 'last_sent_at', 'next_send_at' ) );
		foreach ( $subs as $sub ) {
			fputcsv( $out, array(
				$sub['recipient_email'],
				$sub['recipient_name'],
				$sub['consent_status'],
				$sub['consented_at'],
				$sub['occurrences_sent'],
				$sub['last_sent_at'] ?? '',
				$sub['next_send_at'] ?? '',
			) );
		}
		fclose( $out );
		exit;
	}

	private function render_unsubscribe_page() {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			status_header( 200 );
		}

		$title = __( 'Inscricao cancelada', 'wpagent' );
		$message = __( 'Voce foi descadastrado e nao recebera mais esses e-mails. Pode fechar esta janela.', 'wpagent' );

		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $title ) . '</title><style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e5e7eb;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}.card{background:#fff;color:#0f172a;border-radius:12px;padding:32px;max-width:440px;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.4)}h1{font-size:20px;margin:0 0 12px}p{margin:0;color:#475569;line-height:1.5}</style></head><body><div class="card"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></div></body></html>';
		exit;
	}
}

}
