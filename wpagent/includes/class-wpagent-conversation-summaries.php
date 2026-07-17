<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Conversation_Summaries' ) ) {
class WPAgent_Conversation_Summaries {
	const CRON_HOOK = 'wpagent_process_conversation_summaries';

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

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_post_wpagent_generate_summary', array( $this, 'handle_generate_now' ) );
		}
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 180, 'hourly', self::CRON_HOOK );
		}
	}

	public function admin_url( $args = array() ) {
		$args = array_merge( array( 'page' => 'wpagent-conversation-summaries' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'wpagent',
			__( 'Atendimentos', 'wpagent' ),
			__( 'Atendimentos', 'wpagent' ),
			'manage_options',
			'wpagent-conversation-summaries',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		$agent_filter = sanitize_key( wp_unslash( $_GET['agent'] ?? '' ) );
		$agent_options = $this->agents->get_agent_options();

		$args = array( 'limit' => 200 );
		if ( $agent_filter ) {
			$args['agent_slug'] = $agent_filter;
		}
		$summaries = $this->repository->list_conversation_summaries( $args );

		echo '<div class="wrap"><h1>' . esc_html__( 'Atendimentos', 'wpagent' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Resumos automaticos das conversas de cada agente, gerados 4 horas apos a ultima interacao, para consulta e follow-up humano.', 'wpagent' ) . '</p>';

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin-bottom:16px">';
		echo '<input type="hidden" name="page" value="wpagent-conversation-summaries">';
		echo '<select name="agent">';
		echo '<option value="">' . esc_html__( 'Todos os agentes', 'wpagent' ) . '</option>';
		foreach ( $agent_options as $slug => $name ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $agent_filter, $slug, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filtrar', 'wpagent' ), 'secondary', 'filter', false );
		echo '</form>';

		if ( empty( $summaries ) ) {
			echo '<p>' . esc_html__( 'Nenhum resumo gerado ainda. Os resumos sao produzidos automaticamente quando o agente tem a opcao ativada e a conversa fica inativa por 4 horas.', 'wpagent' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Conversa', 'wpagent' ) . '</th>';
		echo '<th>' . esc_html__( 'Agente', 'wpagent' ) . '</th>';
		echo '<th style="width:80px">' . esc_html__( 'Interacoes', 'wpagent' ) . '</th>';
		echo '<th style="width:140px">' . esc_html__( 'Ultima interacao', 'wpagent' ) . '</th>';
		echo '<th style="width:140px">' . esc_html__( 'Gerado em', 'wpagent' ) . '</th>';
		echo '<th style="width:100px">' . esc_html__( 'Acao', 'wpagent' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $summaries as $summary ) {
			$data   = is_array( $summary['data_points'] ) ? $summary['data_points'] : array();
			$topic  = sanitize_text_field( $data['topic'] ?? $data['topico'] ?? $data['tópico'] ?? '' );
			$status_label = sanitize_text_field( $data['status'] ?? '' );

			echo '<tr>';
			echo '<td>';
			echo '<strong>' . esc_html( $summary['conversation_title'] ?: '—' ) . '</strong>';
			if ( $topic ) {
				echo '<br><span class="description">' . esc_html( $topic ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $agent_options[ $summary['agent_slug'] ] ?? $summary['agent_slug'] ) . '</td>';
			echo '<td>' . absint( $summary['interaction_count'] ) . '</td>';
			echo '<td>' . esc_html( $summary['last_interaction_at'] ?? '—' ) . '</td>';
			echo '<td>' . esc_html( $summary['generated_at'] ?? '—' ) . '</td>';
			echo '<td>';
			echo '<a href="#summary-detail-' . absint( $summary['id'] ) . '" class="button button-small" onclick="var d=document.getElementById(\'summary-detail-' . absint( $summary['id'] ) . '\');d.style.display=d.style.display===\'none\'?\'block\':\'none\';return false">' . esc_html__( 'Ver', 'wpagent' ) . '</a>';
			echo '</td>';
			echo '</tr>';

			echo '<tr id="summary-detail-' . absint( $summary['id'] ) . '" style="display:none">';
			echo '<td colspan="6" style="padding:16px;background:#f8f9fa">';
			echo '<h4>' . esc_html__( 'Resumo', 'wpagent' ) . '</h4>';
			echo '<div style="white-space:pre-wrap;max-width:800px;line-height:1.6">' . esc_html( $summary['summary'] ) . '</div>';
			if ( ! empty( $data ) ) {
				echo '<h4 style="margin-top:14px">' . esc_html__( 'Dados extraidos', 'wpagent' ) . '</h4>';
				echo '<table class="widefat" style="max-width:600px">';
				foreach ( $data as $key => $value ) {
					$key_label = $this->data_point_label( $key );
					echo '<tr><td style="font-weight:650;width:180px">' . esc_html( $key_label ) . '</td><td>' . esc_html( is_string( $value ) ? $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) ) . '</td></tr>';
				}
				echo '</table>';
			}
			if ( $status_label ) {
				echo '<p style="margin-top:10px"><strong>' . esc_html__( 'Status:', 'wpagent' ) . '</strong> ' . esc_html( $status_label ) . '</p>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private function data_point_label( $key ) {
		$labels = array(
			'topic'     => __( 'Topico', 'wpagent' ),
			'objetivo'  => __( 'Objetivo', 'wpagent' ),
			'dados'     => __( 'Dados coletados', 'wpagent' ),
			'status'    => __( 'Status', 'wpagent' ),
			'nome'      => __( 'Nome', 'wpagent' ),
			'email'     => __( 'E-mail', 'wpagent' ),
		);

		return $labels[ strtolower( $key ) ] ?? ucfirst( $key );
	}

	public function process_due() {
		$per_run = (int) apply_filters( 'wpagent_conversation_summaries_per_run', 10 );
		$agents  = $this->agents->get_agent_options();
		$processed = 0;

		foreach ( $agents as $slug => $name ) {
			if ( $processed >= $per_run ) {
				break;
			}

			$agent = $this->agents->get_agent( $slug );
			if ( '1' !== ( $agent['conversation_summary_enabled'] ?? '0' ) ) {
				continue;
			}

			$delay_hours  = max( 1, absint( $agent['conversation_summary_delay'] ?? 4 ) );
			$conversations = $this->repository->get_conversations_needing_summary( $slug, $delay_hours, $per_run - $processed );

			if ( empty( $conversations ) ) {
				continue;
			}

			foreach ( $conversations as $conv ) {
				$this->generate_summary_for_conversation( $conv, $agent );
				$processed++;
				if ( $processed >= $per_run ) {
					break;
				}
			}
		}
	}

public function generate_summary_for_conversation( $conversation, $agent ) {
		$user_id          = absint( $conversation['user_id'] );
		$conversation_id  = sanitize_text_field( $conversation['conversation_id'] );
		$agent_slug       = sanitize_key( $conversation['agent_slug'] );

		$recent = $this->repository->get_conversation_interactions( $conversation_id, $agent_slug, 50 );
		if ( empty( $recent ) || count( $recent ) < 2 ) {
			return;
		}

		$transcript = '';
		foreach ( $recent as $item ) {
			$transcript .= "Usuario: " . trim( wp_strip_all_tags( $item['message'] ) ) . "\n";
			$transcript .= "Agente: " . trim( wp_strip_all_tags( $item['reply'] ) ) . "\n\n";
		}

		$prompt = "Analise a conversa abaixo entre um usuario e um agente IA. Extraia apenas informacoes uteis para atendimento humano posterior. Nao repita a conversa, nao invente dados — extraia apenas o que esta presente.\n\n"
			. "Concentre-se em:\n"
			. "- Topico principal da conversa\n"
			. "- Objetivo declarado pelo usuario\n"
			. "- Dados fornecidos pelo usuario (nome, e-mail, preferencias, contexto)\n"
			. "- Se o atendimento foi resolvido na conversa ou se precisa de follow-up humano\n\n"
			. "Responda em portugues, exatamente neste formato (JSON valido, sem texto fora do JSON):\n"
			. '{"topic":"assunto principal em uma frase","objetivo":"o que o usuario buscava","dados":"informacoes fornecidas pelo usuario","status":"resolvido | pendente | precisa de follow-up","resumo":"paragrafo executivo de 2 a 5 linhas"}'
			. "\n\nConversa:\n\n" . $transcript;

		$system = 'Voce e um assistente de atendimento. Extraia dados relevantes da conversa para uso por um operador humano. Responda APENAS com o JSON solicitado, sem texto adicional.';
		$result = $this->generate_text( $prompt, $system, $agent );

		if ( is_wp_error( $result ) ) {
			return;
		}

		$data_points = $this->parse_summary_json( $result );
		$plain_summary = $this->format_plain_summary( $data_points );

		$this->repository->upsert_conversation_summary( array(
			'agent_slug'          => $agent_slug,
			'user_id'             => $user_id,
			'conversation_id'     => $conversation_id,
			'conversation_title'  => $conversation['title'] ?? '',
			'summary'             => $plain_summary,
			'data_points'         => $data_points,
			'interaction_count'   => absint( $conversation['interaction_count'] ),
			'last_interaction_id' => absint( $conversation['last_interaction_id'] ),
			'last_interaction_at' => $conversation['last_interaction_at'] ?? '',
		) );
	}

	private function parse_summary_json( $text ) {
		$data = null;
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/s', (string) $text, $m ) ) {
			$data = json_decode( trim( $m[1] ), true );
		}
		if ( ! is_array( $data ) ) {
			$data = json_decode( trim( (string) $text ), true );
		}
		if ( ! is_array( $data ) ) {
			$data = array(
				'topic'    => __( '(formato nao reconhecido)', 'wpagent' ),
				'objetivo' => '',
				'dados'    => '',
				'status'   => '',
				'resumo'   => trim( wp_strip_all_tags( (string) $text ) ),
			);
		}
		return $data;
	}

	private function format_plain_summary( $data ) {
		$lines = array();
		if ( ! empty( $data['topic'] ) ) {
			$lines[] = __( 'Topico', 'wpagent' ) . ': ' . $data['topic'];
		}
		if ( ! empty( $data['objetivo'] ) ) {
			$lines[] = __( 'Objetivo', 'wpagent' ) . ': ' . $data['objetivo'];
		}
		if ( ! empty( $data['dados'] ) ) {
			$lines[] = __( 'Dados coletados', 'wpagent' ) . ': ' . $data['dados'];
		}
		if ( ! empty( $data['status'] ) ) {
			$lines[] = __( 'Status', 'wpagent' ) . ': ' . $data['status'];
		}
		if ( ! empty( $data['resumo'] ) ) {
			$lines[] = "\n" . __( 'Resumo', 'wpagent' ) . ":\n" . $data['resumo'];
		}
		return implode( "\n", $lines );
	}

	public function handle_generate_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}
		check_admin_referer( 'wpagent_generate_summary' );

		$conversation_id = sanitize_text_field( wp_unslash( $_GET['conversation_id'] ?? '' ) );
		$agent_slug      = sanitize_key( wp_unslash( $_GET['agent_slug'] ?? 'default' ) );
		$agent = $this->agents->get_agent( $agent_slug );
		$conv  = $this->repository->get_conversation( $conversation_id, 0, $agent_slug );

		if ( ! $conv ) {
			$conv = $this->repository->get_conversation( $conversation_id, get_current_user_id(), $agent_slug );
		}

		$data = $conv ? array(
			'conversation_id'    => $conv['conversation_id'] ?? $conversation_id,
			'title'              => $conv['title'] ?? '',
			'user_id'            => absint( $conv['user_id'] ?? 0 ),
			'agent_slug'         => $agent_slug,
			'interaction_count'  => 0,
			'last_interaction_id' => 0,
			'last_interaction_at' => '',
		) : array(
			'conversation_id'    => $conversation_id,
			'title'              => '',
			'user_id'            => 0,
			'agent_slug'         => $agent_slug,
			'interaction_count'  => 0,
			'last_interaction_id' => 0,
			'last_interaction_at' => '',
		);

		$this->generate_summary_for_conversation( $data, $agent );

		wp_safe_redirect( $this->admin_url( array( 'agent' => $agent_slug ) ) );
		exit;
	}

	private function generate_text( $prompt, $system_instruction, $agent ) {
		if ( 'wordpress_ai' === ( $agent['provider_mode'] ?? '' ) ) {
			if ( function_exists( 'WordPress\\AI\\get_ai_service' ) ) {
				$service = call_user_func( 'WordPress\\AI\\get_ai_service' );
				$builder = $service->create_textgen_prompt( $prompt, array(
					'system_instruction' => $system_instruction,
					'temperature'        => 0.2,
				) );
			} elseif ( function_exists( 'wp_ai_client_prompt' ) ) {
				$builder = wp_ai_client_prompt( $prompt )
					->using_system_instruction( $system_instruction )
					->using_temperature( 0.2 );
			}

			if ( ! empty( $builder ) ) {
				$builder = $this->apply_model_preference( $builder, $agent );
				$result  = $builder->generate_text();
				return is_wp_error( $result ) ? $result : (string) $result;
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
		$model   = $agent['openrouter_model'] ?? $this->settings->get( 'openrouter_model', 'openai/gpt-4.1-mini' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpagent_summary_no_provider', __( 'Nenhum provedor de IA configurado.', 'wpagent' ) );
		}

		$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'HTTP-Referer'  => home_url(),
				'X-Title'       => get_bloginfo( 'name' ),
			),
			'body' => wp_json_encode( array(
				'model'       => $model,
				'messages'    => array(
					array( 'role' => 'system', 'content' => $system_instruction ),
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'temperature' => 0.2,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpagent_summary_ai_error', $body['error']['message'] ?? __( 'Erro ao gerar o resumo.', 'wpagent' ) );
		}

		$text = $body['choices'][0]['message']['content'] ?? '';
		return '' === trim( (string) $text )
			? new WP_Error( 'wpagent_summary_empty', __( 'O provedor devolveu resposta vazia.', 'wpagent' ) )
			: trim( (string) $text );
	}
}
}
