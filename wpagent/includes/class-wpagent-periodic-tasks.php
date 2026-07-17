<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Periodic_Tasks' ) ) {
class WPAgent_Periodic_Tasks {
	const CRON_HOOK = 'wpagent_process_periodic_tasks';
	const STATE_OPTION = 'wpagent_periodic_task_state';

	private $settings;

	public function __construct( WPAgent_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'process_due_tasks' ) );
		add_action( 'admin_post_wpagent_run_periodic_task_now', array( $this, 'handle_run_now' ) );
	}

	public static function task_catalog() {
		return array(
			'content_draft' => array(
				'label'       => __( 'Escrever rascunho de post', 'wpagent' ),
				'description' => __( 'Gera um rascunho de post para revisao editorial. Nunca publica automaticamente.', 'wpagent' ),
				'default_prompt' => __( 'Escreva um post curto para o site, com tom claro e util para o publico principal.', 'wpagent' ),
			),
			'comment_review' => array(
				'label'       => __( 'Avaliar comentarios pendentes', 'wpagent' ),
				'description' => __( 'Analisa comentarios pendentes e gera recomendacoes. Nao aprova, rejeita ou marca spam sozinho.', 'wpagent' ),
				'default_prompt' => __( 'Avalie os comentarios pendentes e destaque riscos, prioridade de resposta e sugestoes de moderacao.', 'wpagent' ),
			),
			'plugin_update_review' => array(
				'label'       => __( 'Revisar atualizacoes de plugins', 'wpagent' ),
				'description' => __( 'Lista atualizacoes disponiveis e gera uma recomendacao. Nao atualiza plugins automaticamente.', 'wpagent' ),
				'default_prompt' => __( 'Analise as atualizacoes de plugins disponiveis e recomende uma ordem segura de revisao.', 'wpagent' ),
			),
		);
	}

	public static function frequency_options() {
		return array(
			'hourly' => __( 'A cada hora', 'wpagent' ),
			'twicedaily' => __( 'Duas vezes por dia', 'wpagent' ),
			'daily' => __( 'Diariamente', 'wpagent' ),
			'weekly' => __( 'Semanalmente', 'wpagent' ),
		);
	}

	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['wpagent_weekly'] ) ) {
			$schedules['wpagent_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Weekly', 'wpagent' ),
			);
		}

		return $schedules;
	}

	public function maybe_schedule() {
		if ( '1' !== $this->settings->get( 'periodic_tasks_enabled', '0' ) ) {
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
		}
	}

	public function process_due_tasks() {
		if ( '1' !== $this->settings->get( 'periodic_tasks_enabled', '0' ) ) {
			return;
		}

		$options = $this->settings->all();
		$tasks   = is_array( $options['periodic_tasks'] ?? null ) ? $options['periodic_tasks'] : array();

		foreach ( self::task_catalog() as $task_id => $definition ) {
			$task = $tasks[ $task_id ] ?? array();
			if ( '1' !== ( $task['enabled'] ?? '0' ) ) {
				continue;
			}

			if ( $this->task_is_due( $task_id, $task['frequency'] ?? 'daily' ) ) {
				$this->run_task( $task_id );
			}
		}
	}

	public function handle_run_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		check_admin_referer( 'wpagent_run_periodic_task_now' );

		$task_id = sanitize_key( wp_unslash( $_GET['task'] ?? '' ) );
		if ( ! isset( self::task_catalog()[ $task_id ] ) ) {
			wp_die( esc_html__( 'Tarefa invalida.', 'wpagent' ) );
		}

		$this->run_task( $task_id );
		wp_safe_redirect( admin_url( 'admin.php?page=wpagent&tab=periodic-tasks' ) );
		exit;
	}

	private function task_is_due( $task_id, $frequency ) {
		$state = $this->state();
		$last_run = strtotime( $state[ $task_id ]['last_run'] ?? '' );

		if ( ! $last_run ) {
			return true;
		}

		return ( time() - $last_run ) >= $this->frequency_seconds( $frequency );
	}

	private function frequency_seconds( $frequency ) {
		$map = array(
			'hourly' => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily' => DAY_IN_SECONDS,
			'weekly' => WEEK_IN_SECONDS,
		);

		return $map[ $frequency ] ?? DAY_IN_SECONDS;
	}

	private function run_task( $task_id ) {
		$this->update_task_state( $task_id, array( 'status' => 'running', 'message' => __( 'Executando tarefa.', 'wpagent' ) ) );

		$result = null;

		if ( 'content_draft' === $task_id ) {
			$result = $this->run_content_draft();
		} elseif ( 'comment_review' === $task_id ) {
			$result = $this->run_comment_review();
		} elseif ( 'plugin_update_review' === $task_id ) {
			$result = $this->run_plugin_update_review();
		}

		if ( is_wp_error( $result ) ) {
			$this->update_task_state(
				$task_id,
				array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
					'result'  => '',
				)
			);
			return;
		}

		$this->update_task_state(
			$task_id,
			array(
				'status'  => 'success',
				'message' => $result['message'] ?? __( 'Tarefa concluida.', 'wpagent' ),
				'result'  => $result['result'] ?? '',
				'post_id' => absint( $result['post_id'] ?? 0 ),
			)
		);
	}

	private function run_content_draft() {
		$prompt = $this->task_prompt( 'content_draft' );
		$text = $this->generate_text(
			$prompt,
			__( 'Voce e um assistente editorial de WordPress. Gere apenas conteudo revisavel, sem publicar nada.', 'wpagent' )
		);

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		if ( 'drafts' !== $this->settings->get( 'periodic_task_mode', 'report_only' ) ) {
			return array(
				'message' => __( 'Sugestao de post gerada em modo relatorio.', 'wpagent' ),
				'result'  => $text,
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => wp_trim_words( wp_strip_all_tags( $text ), 10, '' ) ?: __( 'Rascunho gerado pelo WPAgent', 'wpagent' ),
				'post_content' => wp_kses_post( $text ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'message' => __( 'Rascunho de post criado para revisao.', 'wpagent' ),
			'result'  => sprintf(
				/* translators: %d: post id. */
				__( 'Rascunho criado com ID %d.', 'wpagent' ),
				absint( $post_id )
			),
			'post_id' => absint( $post_id ),
		);
	}

	private function run_comment_review() {
		$comments = get_comments(
			array(
				'status' => 'hold',
				'number' => 20,
				'orderby' => 'comment_date_gmt',
				'order' => 'ASC',
			)
		);

		if ( empty( $comments ) ) {
			return array(
				'message' => __( 'Nenhum comentario pendente para avaliar.', 'wpagent' ),
				'result'  => '',
			);
		}

		$lines = array();
		foreach ( $comments as $comment ) {
			$lines[] = sprintf(
				"ID %d | Autor: %s | Comentario: %s",
				absint( $comment->comment_ID ),
				wp_strip_all_tags( $comment->comment_author ),
				wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 70 )
			);
		}

		$text = $this->generate_text(
			$this->task_prompt( 'comment_review' ) . "\n\nComentarios pendentes:\n" . implode( "\n", $lines ),
			__( 'Voce e um assistente de moderacao. Recomende acoes, mas nao diga que executou moderacao.', 'wpagent' )
		);

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return array(
			'message' => __( 'Relatorio de comentarios gerado.', 'wpagent' ),
			'result'  => $text,
		);
	}

	private function run_plugin_update_review() {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		wp_update_plugins();
		$updates = get_plugin_updates();

		if ( empty( $updates ) ) {
			return array(
				'message' => __( 'Nenhuma atualizacao de plugin disponivel.', 'wpagent' ),
				'result'  => '',
			);
		}

		$lines = array();
		foreach ( $updates as $file => $update ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
			$lines[] = sprintf(
				"%s | Atual: %s | Nova: %s",
				$plugin_data['Name'] ?? $file,
				$plugin_data['Version'] ?? '',
				$update->update->new_version ?? ''
			);
		}

		$text = $this->generate_text(
			$this->task_prompt( 'plugin_update_review' ) . "\n\nAtualizacoes disponiveis:\n" . implode( "\n", $lines ),
			__( 'Voce e um assistente de manutencao WordPress. Recomende uma ordem segura de revisao, mas nao afirme que atualizou plugins.', 'wpagent' )
		);

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return array(
			'message' => __( 'Relatorio de atualizacoes gerado.', 'wpagent' ),
			'result'  => $text,
		);
	}

	private function task_prompt( $task_id ) {
		$options = $this->settings->all();
		$tasks = is_array( $options['periodic_tasks'] ?? null ) ? $options['periodic_tasks'] : array();
		$catalog = self::task_catalog();

		return trim( wp_strip_all_tags( $tasks[ $task_id ]['prompt'] ?? $catalog[ $task_id ]['default_prompt'] ?? '' ) );
	}

	private function generate_text( $prompt, $system_instruction ) {
		$builder = null;

		if ( function_exists( 'WordPress\\AI\\get_ai_service' ) ) {
			$service = call_user_func( 'WordPress\\AI\\get_ai_service' );
			$builder = $service->create_textgen_prompt(
				$prompt,
				array(
					'system_instruction' => $system_instruction,
					'temperature'        => 0.3,
				)
			);
		} elseif ( function_exists( 'wp_ai_client_prompt' ) ) {
			$builder = wp_ai_client_prompt( $prompt )
				->using_system_instruction( $system_instruction )
				->using_temperature( 0.3 );
		}

		if ( $builder ) {
			$builder = $this->maybe_apply_wordpress_ai_model_preference( $builder );
			$result = $builder->generate_text();

			return is_wp_error( $result ) ? $result : (string) $result;
		}

		return $this->generate_text_with_openrouter( $prompt, $system_instruction );
	}

	private function maybe_apply_wordpress_ai_model_preference( $builder ) {
		$provider = $this->settings->get( 'wordpress_ai_provider', '' );
		$model    = $this->settings->get( 'wordpress_ai_model', '' );

		if ( empty( $provider ) || empty( $model ) || ! method_exists( $builder, 'using_model_preference' ) ) {
			return $builder;
		}

		return $builder->using_model_preference(
			array(
				$provider,
				$model,
			)
		);
	}

	private function generate_text_with_openrouter( $prompt, $system_instruction ) {
		$api_key = $this->settings->get( 'openrouter_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpagent_periodic_missing_ai', __( 'Configure um provedor de IA antes de executar tarefas periodicas.', 'wpagent' ) );
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
						'model'       => $this->settings->get( 'openrouter_model', 'openai/gpt-4.1-mini' ),
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => $system_instruction,
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature' => 0.3,
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
			return new WP_Error( 'wpagent_periodic_ai_error', $body['error']['message'] ?? __( 'Erro ao chamar o provedor de IA.', 'wpagent' ) );
		}

		$text = $body['choices'][0]['message']['content'] ?? '';
		if ( '' === trim( (string) $text ) ) {
			return new WP_Error( 'wpagent_periodic_empty_ai_reply', __( 'O provedor de IA respondeu sem conteudo.', 'wpagent' ) );
		}

		return $text;
	}

	private function state() {
		$state = get_option( self::STATE_OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	private function update_task_state( $task_id, $data ) {
		$state = $this->state();
		$state[ $task_id ] = wp_parse_args(
			$data,
			array(
				'last_run' => current_time( 'mysql' ),
				'status'   => '',
				'message'  => '',
				'result'   => '',
				'post_id'  => 0,
			)
		);
		$state[ $task_id ]['last_run'] = current_time( 'mysql' );

		update_option( self::STATE_OPTION, $state, false );
	}
}
}
