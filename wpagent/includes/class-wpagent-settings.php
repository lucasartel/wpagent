<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Settings {
	const OPTION_NAME = 'wpagent_settings';

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'WPAgent Settings', 'wpagent' ),
			__( 'WPAgent', 'wpagent' ),
			'manage_options',
			'wpagent',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'wpagent',
			__( 'Settings', 'wpagent' ),
			__( 'Settings', 'wpagent' ),
			'manage_options',
			'wpagent',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wpagent_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize( $input ) {
		$current = $this->all();
		$submitted_key = isset( $input['openrouter_api_key'] ) ? trim( (string) $input['openrouter_api_key'] ) : '';

		$output = array(
			'agent_name'              => sanitize_text_field( $input['agent_name'] ?? $current['agent_name'] ?? 'WPAgent' ),
			'agent_slug'              => sanitize_key( $input['agent_slug'] ?? $current['agent_slug'] ?? 'default' ),
			'openrouter_api_key'      => '' === $submitted_key ? sanitize_text_field( $current['openrouter_api_key'] ?? '' ) : sanitize_text_field( $submitted_key ),
			'openrouter_model'        => sanitize_text_field( $input['openrouter_model'] ?? $current['openrouter_model'] ?? 'openai/gpt-4.1-mini' ),
			'wordpress_ai_provider'   => sanitize_key( $input['wordpress_ai_provider'] ?? $current['wordpress_ai_provider'] ?? '' ),
			'wordpress_ai_model'      => sanitize_text_field( $input['wordpress_ai_model'] ?? $current['wordpress_ai_model'] ?? '' ),
			'use_wp_ai_client'        => empty( $input['use_wp_ai_client'] ) ? '0' : '1',
			'allow_guest_chat'        => empty( $input['allow_guest_chat'] ) ? '0' : '1',
			'max_knowledge_items'     => max( 0, absint( $input['max_knowledge_items'] ?? 4 ) ),
			'max_memory_items'        => max( 0, absint( $input['max_memory_items'] ?? 8 ) ),
			'max_recent_interactions' => max( 0, absint( $input['max_recent_interactions'] ?? 4 ) ),
			'embedding_enabled'       => empty( $input['embedding_enabled'] ) ? '0' : '1',
			'embedding_provider'      => sanitize_key( $input['embedding_provider'] ?? $current['embedding_provider'] ?? 'openrouter' ),
			'embedding_model'         => sanitize_text_field( $input['embedding_model'] ?? $current['embedding_model'] ?? 'openai/text-embedding-3-small' ),
			'embedding_batch_size'    => max( 1, min( 50, absint( $input['embedding_batch_size'] ?? 10 ) ) ),
			'periodic_tasks_enabled'  => empty( $input['periodic_tasks_enabled'] ) ? '0' : '1',
			'periodic_task_mode'      => in_array( $input['periodic_task_mode'] ?? '', array( 'report_only', 'drafts' ), true ) ? sanitize_key( $input['periodic_task_mode'] ) : ( $current['periodic_task_mode'] ?? 'report_only' ),
			'periodic_tasks'          => $this->sanitize_periodic_tasks( $input['periodic_tasks'] ?? $current['periodic_tasks'] ?? array() ),
			'system_prompt'           => wp_kses_post( $input['system_prompt'] ?? $current['system_prompt'] ?? '' ),
		);

		return $output;
	}

	public function all() {
		$defaults = array(
			'agent_name'              => 'WPAgent',
			'agent_slug'              => 'default',
			'openrouter_api_key'      => '',
			'openrouter_model'        => 'openai/gpt-4.1-mini',
			'wordpress_ai_provider'   => '',
			'wordpress_ai_model'      => '',
			'use_wp_ai_client'        => '1',
			'allow_guest_chat'        => '0',
			'max_knowledge_items'     => 4,
			'max_memory_items'        => 8,
			'max_recent_interactions' => 4,
			'embedding_enabled'       => '0',
			'embedding_provider'      => 'openrouter',
			'embedding_model'         => 'openai/text-embedding-3-small',
			'embedding_batch_size'    => 10,
			'periodic_tasks_enabled'  => '0',
			'periodic_task_mode'      => 'report_only',
			'periodic_tasks'          => $this->default_periodic_tasks(),
			'system_prompt'           => 'Voce e um assistente pessoal cuidadoso, util e contextual.',
		);

		$options = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $options ) ? $options : array(), $defaults );
	}

	public function get( $key, $default = null ) {
		$options = $this->all();

		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	private function default_periodic_tasks() {
		$tasks = array();

		if ( class_exists( 'WPAgent_Periodic_Tasks' ) ) {
			foreach ( WPAgent_Periodic_Tasks::task_catalog() as $task_id => $definition ) {
				$tasks[ $task_id ] = array(
					'enabled'   => '0',
					'frequency' => 'daily',
					'prompt'    => $definition['default_prompt'] ?? '',
				);
			}
		}

		return $tasks;
	}

	private function sanitize_periodic_tasks( $input ) {
		$output = array();
		$catalog = class_exists( 'WPAgent_Periodic_Tasks' ) ? WPAgent_Periodic_Tasks::task_catalog() : array();
		$frequencies = class_exists( 'WPAgent_Periodic_Tasks' ) ? array_keys( WPAgent_Periodic_Tasks::frequency_options() ) : array( 'daily' );

		foreach ( $catalog as $task_id => $definition ) {
			$task = is_array( $input[ $task_id ] ?? null ) ? $input[ $task_id ] : array();
			$frequency = sanitize_key( $task['frequency'] ?? 'daily' );

			$output[ $task_id ] = array(
				'enabled'   => empty( $task['enabled'] ) ? '0' : '1',
				'frequency' => in_array( $frequency, $frequencies, true ) ? $frequency : 'daily',
				'prompt'    => sanitize_textarea_field( $task['prompt'] ?? $definition['default_prompt'] ?? '' ),
			);
		}

		return $output;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->all();
		$tab = sanitize_key( $_GET['tab'] ?? 'general' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPAgent', 'wpagent' ); ?></h1>
			<p><?php esc_html_e( 'Configure a conexao global com IA e crie agentes independentes, cada um com shortcode e treinamento proprio.', 'wpagent' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpagent_agent' ) ); ?>"><?php esc_html_e( 'Criar novo agente', 'wpagent' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpagent_agent' ) ); ?>"><?php esc_html_e( 'Ver agentes', 'wpagent' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-wp-admin' ) ); ?>"><?php esc_html_e( 'Plugin AI do WordPress', 'wpagent' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Connectors', 'wpagent' ); ?></a>
			</p>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent' ) ); ?>"><?php esc_html_e( 'Settings', 'wpagent' ); ?></a>
				<a class="nav-tab <?php echo 'periodic-tasks' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent&tab=periodic-tasks' ) ); ?>"><?php esc_html_e( 'Periodic Tasks', 'wpagent' ); ?></a>
			</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpagent_settings' ); ?>
				<?php if ( 'periodic-tasks' === $tab ) : ?>
					<?php $this->render_periodic_tasks_settings( $options ); ?>
				<?php else : ?>
					<?php $this->render_general_settings( $options ); ?>
				<?php endif; ?>
				<?php submit_button(); ?>
			</form>
			<?php if ( 'general' === $tab ) : ?>
				<h2><?php esc_html_e( 'Como usar', 'wpagent' ); ?></h2>
				<p><?php esc_html_e( 'Cada agente exibe seu shortcode na tela de edicao e recebe seus arquivos de treinamento na propria configuracao.', 'wpagent' ); ?></p>
				<p><code>[wpagent_chat agent="educadoria"]</code></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_general_settings( $options ) {
		$embedding_status = get_option( 'wpagent_embedding_status', array() );
		$embedding_status = is_array( $embedding_status ) ? $embedding_status : array();
		$next_embedding_run = wp_next_scheduled( WPAgent_Embeddings::CRON_HOOK );
		$wp_ai_available = function_exists( 'WordPress\\AI\\get_ai_service' ) || function_exists( 'wp_ai_client_prompt' );
		?>
				<h2><?php esc_html_e( 'WordPress AI / Connectors', 'wpagent' ); ?></h2>
				<p>
					<?php esc_html_e( 'O caminho recomendado e configurar OpenAI, Gemini, Claude, OpenRouter ou outros provedores no plugin AI oficial do WordPress em Settings > Connectors. O WPAgent usa essa configuracao para chats e tarefas de IA quando disponivel.', 'wpagent' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Status:', 'wpagent' ); ?></strong>
					<?php if ( $wp_ai_available ) : ?>
						<?php esc_html_e( 'WordPress AI Client detectado.', 'wpagent' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'WordPress AI Client ainda nao foi detectado. Instale/ative o plugin AI oficial ou use o OpenRouter direto abaixo.', 'wpagent' ); ?>
					<?php endif; ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Provedor padrao', 'wpagent' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[use_wp_ai_client]" value="1" <?php checked( $options['use_wp_ai_client'], '1' ); ?>> <?php esc_html_e( 'Usar WordPress AI / Connectors como provedor padrao para novos agentes.', 'wpagent' ); ?></label>
							<p class="description"><?php esc_html_e( 'Com esta opcao ativa, novos agentes herdam os fornecedores, chaves e preferencias configuradas no plugin AI oficial. Agentes existentes podem ser ajustados individualmente na tela do agente.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Modelo padrao do WordPress AI', 'wpagent' ); ?></th>
						<td>
							<p>
								<label for="wpagent-wordpress-ai-provider"><?php esc_html_e( 'Provider', 'wpagent' ); ?></label><br>
								<input id="wpagent-wordpress-ai-provider" class="regular-text" list="wpagent-provider-presets" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[wordpress_ai_provider]" value="<?php echo esc_attr( $options['wordpress_ai_provider'] ); ?>" placeholder="openai">
							</p>
							<p>
								<label for="wpagent-wordpress-ai-model"><?php esc_html_e( 'Modelo', 'wpagent' ); ?></label><br>
								<input id="wpagent-wordpress-ai-model" class="regular-text" list="wpagent-model-presets" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[wordpress_ai_model]" value="<?php echo esc_attr( $options['wordpress_ai_model'] ); ?>" placeholder="gpt-4.1-mini">
							</p>
							<?php $this->render_ai_model_datalists(); ?>
							<p class="description"><?php esc_html_e( 'Opcional. Quando preenchido, o WPAgent envia este provider/model como preferencia para o WordPress AI Client. Deixe em branco para usar o modelo padrao escolhido pelo conector/plugin AI.', 'wpagent' ); ?></p>
							<p class="description"><?php esc_html_e( 'A listagem automatica de modelos depende de cada conector expor essa informacao. Por enquanto, use o ID oficial do modelo informado pelo fornecedor.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-model"><?php esc_html_e( 'Modelo OpenRouter direto', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-model" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openrouter_model]" value="<?php echo esc_attr( $options['openrouter_model'] ); ?>">
							<p class="description"><?php esc_html_e( 'Valor usado apenas pelo modo OpenRouter direto do WPAgent ou como fallback quando o WordPress AI falhar e houver uma chave OpenRouter salva.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-api-key"><?php esc_html_e( 'OpenRouter API key opcional', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-api-key" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openrouter_api_key]" value="" placeholder="<?php echo empty( $options['openrouter_api_key'] ) ? '' : esc_attr__( 'Chave salva. Preencha apenas para trocar.', 'wpagent' ); ?>">
							<p class="description"><?php esc_html_e( 'Preencha somente se quiser usar OpenRouter direto pelo WPAgent ou manter um fallback proprio. Se houver um conector OpenRouter no WordPress AI, o WPAgent tambem tenta espelhar esta chave para ele.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Padrao: visitantes anonimos', 'wpagent' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[allow_guest_chat]" value="1" <?php checked( $options['allow_guest_chat'], '1' ); ?>> <?php esc_html_e( 'Permitir chat sem login para novos agentes.', 'wpagent' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-system-prompt"><?php esc_html_e( 'Instrucao base padrao', 'wpagent' ); ?></label></th>
						<td><textarea id="wpagent-system-prompt" class="large-text" rows="8" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[system_prompt]"><?php echo esc_textarea( $options['system_prompt'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Limites padrao', 'wpagent' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Treinamentos', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_knowledge_items]" value="<?php echo esc_attr( $options['max_knowledge_items'] ); ?>"></label>
							<label><?php esc_html_e( 'Memorias', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_memory_items]" value="<?php echo esc_attr( $options['max_memory_items'] ); ?>"></label>
							<label><?php esc_html_e( 'Interacoes recentes', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_recent_interactions]" value="<?php echo esc_attr( $options['max_recent_interactions'] ); ?>"></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Embeddings', 'wpagent' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_enabled]" value="1" <?php checked( $options['embedding_enabled'], '1' ); ?>> <?php esc_html_e( 'Ativar busca semantica por embeddings para documentos indexados.', 'wpagent' ); ?></label>
							<p>
								<label><?php esc_html_e( 'Provider', 'wpagent' ); ?> <input class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_provider]" value="<?php echo esc_attr( $options['embedding_provider'] ); ?>"></label>
							</p>
							<p>
								<label><?php esc_html_e( 'Modelo', 'wpagent' ); ?> <input class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_model]" value="<?php echo esc_attr( $options['embedding_model'] ); ?>"></label>
							</p>
							<p>
								<label><?php esc_html_e( 'Lote por cron', 'wpagent' ); ?> <input class="small-text" type="number" min="1" max="50" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_batch_size]" value="<?php echo esc_attr( $options['embedding_batch_size'] ); ?>"></label>
							</p>
							<p class="description"><?php esc_html_e( 'Nesta primeira versao, o provider suportado e openrouter via /api/v1/embeddings. Os embeddings sao gerados em segundo plano pelo WP-Cron.', 'wpagent' ); ?></p>
							<?php if ( ! empty( $embedding_status ) ) : ?>
								<p>
									<strong><?php esc_html_e( 'Ultimo processamento:', 'wpagent' ); ?></strong>
									<?php echo esc_html( $embedding_status['message'] ?? '' ); ?>
									<?php if ( ! empty( $embedding_status['updated_at'] ) ) : ?>
										<span class="description"><?php echo esc_html( $embedding_status['updated_at'] ); ?></span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<?php if ( $next_embedding_run ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: next cron date. */
										esc_html__( 'Proximo lote automatico: %s.', 'wpagent' ),
										esc_html( wp_date( 'Y-m-d H:i:s', $next_embedding_run ) )
									);
									?>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Nao ha lote automatico agendado no momento. Salve as configuracoes com embeddings ativos para agendar.', 'wpagent' ); ?></p>
							<?php endif; ?>
							<p>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_process_embeddings_now' ), 'wpagent_process_embeddings_now' ) ); ?>"><?php esc_html_e( 'Processar embeddings agora', 'wpagent' ); ?></a>
							</p>
						</td>
					</tr>
				</table>
		<?php
	}

	private function render_ai_model_datalists() {
		?>
		<datalist id="wpagent-provider-presets">
			<option value="openai">
			<option value="anthropic">
			<option value="google">
			<option value="openrouter">
		</datalist>
		<datalist id="wpagent-model-presets">
			<option value="gpt-4.1-mini">
			<option value="gpt-4.1">
			<option value="gpt-4o-mini">
			<option value="claude-3-5-sonnet-latest">
			<option value="claude-3-5-haiku-latest">
			<option value="gemini-2.0-flash">
			<option value="gemini-1.5-pro">
		</datalist>
		<?php
	}

	private function render_periodic_tasks_settings( $options ) {
		$catalog = WPAgent_Periodic_Tasks::task_catalog();
		$frequencies = WPAgent_Periodic_Tasks::frequency_options();
		$state = get_option( WPAgent_Periodic_Tasks::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		$tasks = is_array( $options['periodic_tasks'] ?? null ) ? $options['periodic_tasks'] : array();
		$next_run = wp_next_scheduled( WPAgent_Periodic_Tasks::CRON_HOOK );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Tarefas periodicas', 'wpagent' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[periodic_tasks_enabled]" value="1" <?php checked( $options['periodic_tasks_enabled'] ?? '0', '1' ); ?>> <?php esc_html_e( 'Ativar cuidador periodico do site.', 'wpagent' ); ?></label>
					<p class="description"><?php esc_html_e( 'As tarefas usam WP-Cron. Em hospedagens comuns, elas rodam quando o site recebe visitas ou quando ha cron real configurado no servidor.', 'wpagent' ); ?></p>
					<?php if ( $next_run ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: next cron date. */
								esc_html__( 'Proxima verificacao automatica: %s.', 'wpagent' ),
								esc_html( wp_date( 'Y-m-d H:i:s', $next_run ) )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modo de execucao', 'wpagent' ); ?></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[periodic_task_mode]" value="report_only" <?php checked( $options['periodic_task_mode'] ?? 'report_only', 'report_only' ); ?>> <?php esc_html_e( 'Apenas relatorios e recomendacoes', 'wpagent' ); ?></label><br>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[periodic_task_mode]" value="drafts" <?php checked( $options['periodic_task_mode'] ?? 'report_only', 'drafts' ); ?>> <?php esc_html_e( 'Permitir criacao de rascunhos revisaveis', 'wpagent' ); ?></label>
					<p class="description"><?php esc_html_e( 'Atualizacao de plugins, publicacao de posts e moderacao de comentarios continuam exigindo acao humana nesta versao.', 'wpagent' ); ?></p>
				</td>
			</tr>
		</table>
		<h2><?php esc_html_e( 'Funcoes habilitadas', 'wpagent' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ativar', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Funcao', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Periodicidade', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Instrucao', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Ultimo resultado', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Executar', 'wpagent' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $catalog as $task_id => $definition ) : ?>
					<?php
					$task = wp_parse_args(
						$tasks[ $task_id ] ?? array(),
						array(
							'enabled'   => '0',
							'frequency' => 'daily',
							'prompt'    => $definition['default_prompt'] ?? '',
						)
					);
					$task_state = $state[ $task_id ] ?? array();
					?>
					<tr>
						<td><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[periodic_tasks][<?php echo esc_attr( $task_id ); ?>][enabled]" value="1" <?php checked( $task['enabled'], '1' ); ?>></td>
						<td>
							<strong><?php echo esc_html( $definition['label'] ); ?></strong>
							<p class="description"><?php echo esc_html( $definition['description'] ); ?></p>
						</td>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[periodic_tasks][<?php echo esc_attr( $task_id ); ?>][frequency]">
								<?php foreach ( $frequencies as $frequency => $label ) : ?>
									<option value="<?php echo esc_attr( $frequency ); ?>" <?php selected( $task['frequency'], $frequency ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<textarea class="large-text" rows="4" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[periodic_tasks][<?php echo esc_attr( $task_id ); ?>][prompt]"><?php echo esc_textarea( $task['prompt'] ); ?></textarea>
						</td>
						<td>
							<?php if ( ! empty( $task_state ) ) : ?>
								<strong><?php echo esc_html( $task_state['message'] ?? '' ); ?></strong>
								<?php if ( ! empty( $task_state['last_run'] ) ) : ?>
									<p class="description"><?php echo esc_html( $task_state['last_run'] ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $task_state['post_id'] ) ) : ?>
									<p><a href="<?php echo esc_url( get_edit_post_link( absint( $task_state['post_id'] ) ) ); ?>"><?php esc_html_e( 'Abrir rascunho', 'wpagent' ); ?></a></p>
								<?php endif; ?>
								<?php if ( ! empty( $task_state['result'] ) ) : ?>
									<details>
										<summary><?php esc_html_e( 'Ver detalhes', 'wpagent' ); ?></summary>
										<p><?php echo nl2br( esc_html( wp_trim_words( $task_state['result'], 120 ) ) ); ?></p>
									</details>
								<?php endif; ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Ainda nao executada.', 'wpagent' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_run_periodic_task_now&task=' . $task_id ), 'wpagent_run_periodic_task_now' ) ); ?>"><?php esc_html_e( 'Executar agora', 'wpagent' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
