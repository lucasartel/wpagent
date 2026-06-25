<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Agents {
	const POST_TYPE = 'wpagent_agent';

	private $settings;
	private $repository;
	private $indexer;

	public function __construct( WPAgent_Settings $settings, WPAgent_Repository $repository ) {
		$this->settings   = $settings;
		$this->repository = $repository;
		$this->indexer    = new WPAgent_Document_Indexer( $repository );
	}

	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'ensure_default_agent' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'post_edit_form_tag', array( $this, 'post_edit_form_tag' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_agent' ), 10, 2 );
		add_filter( 'upload_mimes', array( $this, 'training_upload_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'allow_markdown_filetype' ), 10, 5 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Agents', 'wpagent' ),
					'singular_name' => __( 'Agent', 'wpagent' ),
					'add_new_item'  => __( 'Adicionar agente', 'wpagent' ),
					'edit_item'     => __( 'Editar agente', 'wpagent' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'wpagent',
				'supports'        => array( 'title', 'revisions' ),
				'capabilities'    => array(
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'delete_posts'       => 'manage_options',
					'delete_others_posts'=> 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options',
					'create_posts'       => 'manage_options',
				),
				'map_meta_cap'    => false,
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box(
			'wpagent-agent-config',
			__( 'Configuracao do agente', 'wpagent' ),
			array( $this, 'render_config_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wpagent-agent-shortcode',
			__( 'Shortcode', 'wpagent' ),
			array( $this, 'render_shortcode_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'wpagent-agent-training',
			__( 'Arquivos de treinamento', 'wpagent' ),
			array( $this, 'render_training_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	public function post_edit_form_tag() {
		global $post;

		if ( $post && self::POST_TYPE === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	public function training_upload_mimes( $mimes ) {
		$mimes['txt']  = 'text/plain';
		$mimes['md']   = 'text/markdown';
		$mimes['markdown'] = 'text/markdown';
		$mimes['csv']  = 'text/csv';
		$mimes['json'] = 'application/json';
		$mimes['html'] = 'text/html';
		$mimes['pdf']  = 'application/pdf';
		$mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

		return $mimes;
	}

	public function allow_markdown_filetype( $data, $file, $filename, $mimes, $real_mime ) {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'md', 'markdown' ), true ) ) {
			return $data;
		}

		$data['ext']             = $extension;
		$data['type']            = 'text/markdown';
		$data['proper_filename'] = false;

		return $data;
	}

	public function admin_notices() {
		$notice = get_transient( 'wpagent_admin_notice_' . get_current_user_id() );
		if ( ! $notice ) {
			return;
		}

		delete_transient( 'wpagent_admin_notice_' . get_current_user_id() );
		echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
	}

	public function render_config_box( $post ) {
		wp_nonce_field( 'wpagent_save_agent', 'wpagent_agent_nonce' );

		$agent = $this->get_agent_by_post( $post->ID );
		$agent_wordpress_ai_provider = get_post_meta( $post->ID, '_wpagent_wordpress_ai_provider', true );
		$agent_wordpress_ai_model    = get_post_meta( $post->ID, '_wpagent_wordpress_ai_model', true );
		$default_wordpress_ai_provider = $this->settings->get( 'wordpress_ai_provider', '' );
		$default_wordpress_ai_model    = $this->settings->get( 'wordpress_ai_model', '' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wpagent-agent-slug"><?php esc_html_e( 'Identificador', 'wpagent' ); ?></label></th>
				<td>
					<input id="wpagent-agent-slug" class="regular-text" name="wpagent_agent[slug]" value="<?php echo esc_attr( $agent['slug'] ); ?>">
					<p class="description"><?php esc_html_e( 'Use apenas letras, numeros e hifens. Ele define o shortcode e separa memoria/treinamento.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpagent-agent-provider-mode"><?php esc_html_e( 'Fornecedor de IA', 'wpagent' ); ?></label></th>
				<td>
					<select id="wpagent-agent-provider-mode" name="wpagent_agent[provider_mode]">
						<option value="wordpress_ai" <?php selected( $agent['provider_mode'], 'wordpress_ai' ); ?>><?php esc_html_e( 'WordPress AI / Connectors (recomendado)', 'wpagent' ); ?></option>
						<option value="wpagent_openrouter" <?php selected( $agent['provider_mode'], 'wpagent_openrouter' ); ?>><?php esc_html_e( 'OpenRouter direto pelo WPAgent', 'wpagent' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Use WordPress AI para aproveitar OpenAI, Gemini, Claude, OpenRouter ou outros conectores configurados no plugin oficial de IA do WordPress. O OpenRouter direto permanece disponivel como fallback ou opcao avancada.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpagent-agent-model"><?php esc_html_e( 'Modelo OpenRouter direto', 'wpagent' ); ?></label></th>
				<td>
					<input id="wpagent-agent-model" class="regular-text" name="wpagent_agent[openrouter_model]" value="<?php echo esc_attr( $agent['openrouter_model'] ); ?>">
					<p class="description"><?php esc_html_e( 'Usado somente quando este agente estiver em OpenRouter direto ou quando o WordPress AI falhar e houver uma chave OpenRouter no WPAgent.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modelo do agente no WordPress AI', 'wpagent' ); ?></th>
				<td>
					<p>
						<label><?php esc_html_e( 'Provider', 'wpagent' ); ?><br>
							<input class="regular-text" list="wpagent-provider-presets" name="wpagent_agent[wordpress_ai_provider]" value="<?php echo esc_attr( $agent_wordpress_ai_provider ); ?>" placeholder="<?php echo esc_attr( $default_wordpress_ai_provider ?: 'openai' ); ?>">
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'Model', 'wpagent' ); ?><br>
							<input class="regular-text" list="wpagent-model-presets" name="wpagent_agent[wordpress_ai_model]" value="<?php echo esc_attr( $agent_wordpress_ai_model ); ?>" placeholder="<?php echo esc_attr( $default_wordpress_ai_model ?: 'gpt-4.1-mini' ); ?>">
						</label>
					</p>
					<?php $this->render_ai_model_datalists(); ?>
					<p class="description"><?php esc_html_e( 'Deixe em branco para herdar o modelo padrao definido em WPAgent > Settings. Preencha apenas quando este agente precisar usar um provider/model especifico.', 'wpagent' ); ?></p>
					<p class="description">
						<?php
						printf(
							/* translators: 1: provider id, 2: model id. */
							esc_html__( 'Modelo efetivo atual: %1$s / %2$s', 'wpagent' ),
							esc_html( $agent['wordpress_ai_provider'] ?: __( 'automatico', 'wpagent' ) ),
							esc_html( $agent['wordpress_ai_model'] ?: __( 'automatico', 'wpagent' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Visitantes anonimos', 'wpagent' ); ?></th>
				<td><label><input type="checkbox" name="wpagent_agent[allow_guest_chat]" value="1" <?php checked( $agent['allow_guest_chat'], '1' ); ?>> <?php esc_html_e( 'Permitir chat sem login para este agente.', 'wpagent' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Assistente publico do site', 'wpagent' ); ?></th>
				<td>
					<label><input type="checkbox" name="wpagent_agent[public_site_assistant]" value="1" <?php checked( $agent['public_site_assistant'], '1' ); ?>> <?php esc_html_e( 'Exibir este agente automaticamente como pop-up no front-end para visitantes.', 'wpagent' ); ?></label>
					<p class="description"><?php esc_html_e( 'Use para atendimento publico do site. Ao ativar, o chat sem login tambem fica habilitado para este agente.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Assistente interno do admin', 'wpagent' ); ?></th>
				<td>
					<label><input type="checkbox" name="wpagent_agent[admin_assistant]" value="1" <?php checked( $agent['admin_assistant'], '1' ); ?>> <?php esc_html_e( 'Exibir este agente automaticamente como pop-up no painel para administradores.', 'wpagent' ); ?></label>
					<p class="description"><?php esc_html_e( 'Use para apoio operacional no WordPress. Nesta versao ele conversa no painel; a execucao de acoes administrativas deve ser habilitada por ferramentas com permissao propria.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Perfil declarado pelo usuario', 'wpagent' ); ?></th>
				<td>
					<label><input type="checkbox" name="wpagent_agent[user_profile_enabled]" value="1" <?php checked( $agent['user_profile_enabled'], '1' ); ?>> <?php esc_html_e( 'Permitir que usuarios logados informem dados pessoais para este agente considerar nas respostas.', 'wpagent' ); ?></label>
					<p>
						<label for="wpagent-user-profile-label"><?php esc_html_e( 'Titulo do campo', 'wpagent' ); ?></label><br>
						<input id="wpagent-user-profile-label" class="regular-text" name="wpagent_agent[user_profile_label]" value="<?php echo esc_attr( $agent['user_profile_label'] ); ?>" placeholder="<?php esc_attr_e( 'Sobre voce', 'wpagent' ); ?>">
					</p>
					<p>
						<label for="wpagent-user-profile-description"><?php esc_html_e( 'Descricao para o usuario', 'wpagent' ); ?></label><br>
						<textarea id="wpagent-user-profile-description" class="large-text" rows="3" name="wpagent_agent[user_profile_description]" placeholder="<?php esc_attr_e( 'Explique quais informacoes ajudam este agente a personalizar as respostas.', 'wpagent' ); ?>"><?php echo esc_textarea( $agent['user_profile_description'] ); ?></textarea>
					</p>
					<p>
						<label for="wpagent-user-profile-fields"><?php esc_html_e( 'Campos estruturados', 'wpagent' ); ?></label><br>
						<textarea id="wpagent-user-profile-fields" class="large-text code" rows="6" name="wpagent_agent[user_profile_fields]" placeholder="<?php esc_attr_e( 'Ano em que leciono | teaching_year | text | Ex.: 7 ano', 'wpagent' ); ?>"><?php echo esc_textarea( $this->profile_fields_to_text( $agent['user_profile_fields'] ?? array() ) ); ?></textarea>
					</p>
					<p class="description"><?php esc_html_e( 'Adicione um campo por linha no formato: Rotulo | chave | tipo | placeholder. Tipos aceitos: text ou textarea. A chave deve usar letras, numeros e sublinhado.', 'wpagent' ); ?></p>
					<p class="description"><?php esc_html_e( 'Use para orientar o usuario a informar contexto estavel, como turma, area de atuacao, estilo pessoal, objetivos e preferencias. Essas informacoes entram no prompt como contexto declarado pelo usuario.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Envio de email autorizado', 'wpagent' ); ?></th>
				<td>
					<label><input type="checkbox" name="wpagent_agent[email_actions_enabled]" value="1" <?php checked( $agent['email_actions_enabled'], '1' ); ?>> <?php esc_html_e( 'Permitir que este agente prepare emails para envio apos confirmacao do usuario.', 'wpagent' ); ?></label>
					<p class="description"><?php esc_html_e( 'O agente pode coletar email, nome e dados relevantes durante a conversa, mas o envio so acontece quando o usuario confirma no botao exibido pelo WPAgent.', 'wpagent' ); ?></p>
					<p>
						<label for="wpagent-email-actions-instructions"><?php esc_html_e( 'Instrucoes adicionais para emails', 'wpagent' ); ?></label><br>
						<textarea id="wpagent-email-actions-instructions" class="large-text" rows="4" name="wpagent_agent[email_actions_instructions]" placeholder="<?php esc_attr_e( 'Ex.: envie apenas materiais finalizados; inclua uma saudacao curta; nao envie anexos.', 'wpagent' ); ?>"><?php echo esc_textarea( $agent['email_actions_instructions'] ); ?></textarea>
					</p>
					<p class="description"><?php esc_html_e( 'Use para casos como enviar plano de aula, mensagem pastoral, resumo, proposta, dados de produto ou material de apoio. O envio usa wp_mail(), entao a entrega depende da configuracao de email/SMTP do WordPress.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpagent-agent-system-prompt"><?php esc_html_e( 'Instrucao base', 'wpagent' ); ?></label></th>
				<td>
					<textarea id="wpagent-agent-system-prompt" class="large-text" rows="10" name="wpagent_agent[system_prompt]"><?php echo esc_textarea( $agent['system_prompt'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Define o papel, tom, limites e regras fixas do agente. Links externos aqui sao tratados como texto; para usar o conteudo, importe ou cole o material em Treinamentos.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Limites de contexto', 'wpagent' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Treinamentos', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="wpagent_agent[max_knowledge_items]" value="<?php echo esc_attr( $agent['max_knowledge_items'] ); ?>"></label>
					<label><?php esc_html_e( 'Memorias', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="wpagent_agent[max_memory_items]" value="<?php echo esc_attr( $agent['max_memory_items'] ); ?>"></label>
					<label><?php esc_html_e( 'Interacoes', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="wpagent_agent[max_recent_interactions]" value="<?php echo esc_attr( $agent['max_recent_interactions'] ); ?>"></label>
					<p class="description"><?php esc_html_e( 'Treinamentos: quantidade maxima de trechos da base do agente enviados ao modelo. Memorias: registros persistentes sobre o usuario. Interacoes: pares recentes de pergunta/resposta da conversa atual.', 'wpagent' ); ?></p>
					<p class="description"><?php esc_html_e( 'Valores maiores dao mais contexto, mas aumentam custo, latencia e risco de ultrapassar o limite do modelo.', 'wpagent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Limites de tokens', 'wpagent' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Por dia', 'wpagent' ); ?> <input class="regular-text" type="number" min="0" name="wpagent_agent[token_limit_day]" value="<?php echo esc_attr( $agent['token_limit_day'] ); ?>"></label><br>
					<label><?php esc_html_e( 'Por semana', 'wpagent' ); ?> <input class="regular-text" type="number" min="0" name="wpagent_agent[token_limit_week]" value="<?php echo esc_attr( $agent['token_limit_week'] ); ?>"></label><br>
					<label><?php esc_html_e( 'Por mes', 'wpagent' ); ?> <input class="regular-text" type="number" min="0" name="wpagent_agent[token_limit_month]" value="<?php echo esc_attr( $agent['token_limit_month'] ); ?>"></label>
					<p class="description"><?php esc_html_e( 'Controla o total de tokens consumidos por este agente em todos os usuarios. Use 0 para ilimitado. O bloqueio acontece quando o consumo registrado do periodo atinge o limite.', 'wpagent' ); ?></p>
					<p class="description"><?php esc_html_e( 'A contagem depende do fornecedor retornar uso de tokens. OpenRouter normalmente retorna; alguns conectores do WordPress AI podem retornar 0.', 'wpagent' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function render_shortcode_box( $post ) {
		$agent = $this->get_agent_by_post( $post->ID );
		?>
		<p><code>[wpagent_chat agent="<?php echo esc_attr( $agent['slug'] ); ?>"]</code></p>
		<p class="description"><?php esc_html_e( 'Cole este shortcode em qualquer pagina ou post.', 'wpagent' ); ?></p>
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

	public function render_training_box( $post ) {
		$agent   = $this->get_agent_by_post( $post->ID );
		$sources = $this->repository->list_training_sources( $agent['slug'] );
		?>
		<p><?php esc_html_e( 'Envie documentos para compor a base pesquisavel deste agente. O WPAgent quebra textos longos em trechos e usa somente os trechos relevantes em cada resposta.', 'wpagent' ); ?></p>
		<p class="description"><?php esc_html_e( 'TXT, Markdown, CSV, JSON, HTML e DOCX sao extraidos automaticamente. PDF e tentado apenas quando houver texto legivel; se a extracao sair corrompida, ele nao sera usado no chat.', 'wpagent' ); ?></p>
		<p><input type="file" name="wpagent_training_files[]" multiple></p>
		<hr>
		<p><strong><?php esc_html_e( 'Texto manual de treinamento', 'wpagent' ); ?></strong></p>
		<p class="description"><?php esc_html_e( 'Use este campo para colar uma versao limpa em TXT/Markdown de PDFs grandes quando a hospedagem nao tiver extrator confiavel. Ao salvar, o texto sera dividido em trechos pesquisaveis.', 'wpagent' ); ?></p>
		<p><input class="regular-text" name="wpagent_manual_training_title" value="" placeholder="<?php esc_attr_e( 'Titulo da fonte, ex.: BNCC em Markdown', 'wpagent' ); ?>"></p>
		<p><textarea class="large-text" rows="10" name="wpagent_manual_training_text" placeholder="<?php esc_attr_e( 'Cole aqui o texto limpo ou Markdown...', 'wpagent' ); ?>"></textarea></p>
		<?php if ( ! empty( $sources ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Documento', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Indice', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Embeddings', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Reindexar', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Remover', 'wpagent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sources as $source ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $source['title'] ?: $source['filename'] ?: __( 'Documento', 'wpagent' ) ); ?></strong><br>
								<code><?php echo esc_html( $source['filename'] ); ?></code>
							</td>
							<td>
								<strong><?php echo esc_html( $this->status_label( $source['status'] ) ); ?></strong><br>
								<span class="description"><?php echo esc_html( $source['status_message'] ); ?></span>
							</td>
							<td>
								<?php
								printf(
									/* translators: 1: chunks, 2: characters. */
									esc_html__( '%1$d trechos, %2$d caracteres', 'wpagent' ),
									absint( $source['chunk_count'] ),
									absint( $source['char_count'] )
								);
								?>
							</td>
							<td><?php echo esc_html( $this->embedding_progress_label( $source ) ); ?></td>
							<td><label><input type="checkbox" name="wpagent_reindex_training_sources[]" value="<?php echo esc_attr( $source['id'] ); ?>"> <?php esc_html_e( 'Reindexar', 'wpagent' ); ?></label></td>
							<td><label><input type="checkbox" name="wpagent_remove_training_sources[]" value="<?php echo esc_attr( $source['id'] ); ?>"> <?php esc_html_e( 'Remover', 'wpagent' ); ?></label></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Nenhum documento indexado ainda.', 'wpagent' ); ?></em></p>
		<?php endif; ?>
		<?php
	}

	public function save_agent( $post_id, $post ) {
		if ( ! isset( $_POST['wpagent_agent_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpagent_agent_nonce'] ) ), 'wpagent_save_agent' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$input = isset( $_POST['wpagent_agent'] ) && is_array( $_POST['wpagent_agent'] ) ? wp_unslash( $_POST['wpagent_agent'] ) : array();
		$slug  = sanitize_key( $input['slug'] ?? '' );

		if ( empty( $slug ) ) {
			$slug = sanitize_key( $post->post_title );
		}

		if ( empty( $slug ) ) {
			$slug = 'agent-' . absint( $post_id );
		}

		if ( $this->slug_exists( $slug, $post_id ) ) {
			$slug = $slug . '-' . absint( $post_id );
		}

		$public_site_assistant = empty( $input['public_site_assistant'] ) ? '0' : '1';
		$allow_guest_chat      = '1' === $public_site_assistant || ! empty( $input['allow_guest_chat'] ) ? '1' : '0';

		$values = array(
			'_wpagent_agent_slug'              => $slug,
			'_wpagent_provider_mode'           => in_array( $input['provider_mode'] ?? '', array( 'wpagent_openrouter', 'wordpress_ai' ), true ) ? $input['provider_mode'] : 'wordpress_ai',
			'_wpagent_openrouter_model'        => sanitize_text_field( $input['openrouter_model'] ?? $this->settings->get( 'openrouter_model', 'openai/gpt-4.1-mini' ) ),
			'_wpagent_wordpress_ai_provider'   => sanitize_key( $input['wordpress_ai_provider'] ?? '' ),
			'_wpagent_wordpress_ai_model'      => sanitize_text_field( $input['wordpress_ai_model'] ?? '' ),
			'_wpagent_use_wp_ai_client'        => ( ( $input['provider_mode'] ?? '' ) === 'wordpress_ai' ) ? '1' : '0',
			'_wpagent_allow_guest_chat'        => $allow_guest_chat,
			'_wpagent_public_site_assistant'   => $public_site_assistant,
			'_wpagent_admin_assistant'         => empty( $input['admin_assistant'] ) ? '0' : '1',
			'_wpagent_user_profile_enabled'    => empty( $input['user_profile_enabled'] ) ? '0' : '1',
			'_wpagent_user_profile_label'      => sanitize_text_field( $input['user_profile_label'] ?? __( 'Sobre voce', 'wpagent' ) ),
			'_wpagent_user_profile_description' => sanitize_textarea_field( $input['user_profile_description'] ?? __( 'Compartilhe informacoes que ajudam este agente a personalizar as respostas.', 'wpagent' ) ),
			'_wpagent_user_profile_fields'     => $this->sanitize_user_profile_fields_text( $input['user_profile_fields'] ?? '' ),
			'_wpagent_email_actions_enabled'   => empty( $input['email_actions_enabled'] ) ? '0' : '1',
			'_wpagent_email_actions_instructions' => sanitize_textarea_field( $input['email_actions_instructions'] ?? '' ),
			'_wpagent_system_prompt'           => wp_kses_post( $input['system_prompt'] ?? $this->settings->get( 'system_prompt', '' ) ),
			'_wpagent_max_knowledge_items'     => max( 0, absint( $input['max_knowledge_items'] ?? 4 ) ),
			'_wpagent_max_memory_items'        => max( 0, absint( $input['max_memory_items'] ?? 8 ) ),
			'_wpagent_max_recent_interactions' => max( 0, absint( $input['max_recent_interactions'] ?? 4 ) ),
			'_wpagent_token_limit_day'         => max( 0, absint( $input['token_limit_day'] ?? 0 ) ),
			'_wpagent_token_limit_week'        => max( 0, absint( $input['token_limit_week'] ?? 0 ) ),
			'_wpagent_token_limit_month'       => max( 0, absint( $input['token_limit_month'] ?? 0 ) ),
		);

		foreach ( $values as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		$this->save_training_files( $post_id );
	}

	public function columns( $columns ) {
		$columns['wpagent_shortcode'] = __( 'Shortcode', 'wpagent' );
		$columns['wpagent_slug']      = __( 'Identificador', 'wpagent' );

		return $columns;
	}

	public function column_content( $column, $post_id ) {
		$agent = $this->get_agent_by_post( $post_id );

		if ( 'wpagent_shortcode' === $column ) {
			echo '<code>[wpagent_chat agent="' . esc_attr( $agent['slug'] ) . '"]</code>';
		}

		if ( 'wpagent_slug' === $column ) {
			echo esc_html( $agent['slug'] );
		}
	}

	public function get_agent( $slug = '' ) {
		$slug = sanitize_key( $slug ?: $this->settings->get( 'agent_slug', 'default' ) );

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => '_wpagent_agent_slug',
				'meta_value'     => $slug,
			)
		);

		if ( ! empty( $posts ) ) {
			return $this->get_agent_by_post( $posts[0]->ID );
		}

		return $this->fallback_agent( $slug );
	}

	public function get_agent_by_post( $post_id ) {
		$post     = get_post( $post_id );
		$defaults = $this->fallback_agent( sanitize_key( $post ? $post->post_name : '' ) );
		$slug     = get_post_meta( $post_id, '_wpagent_agent_slug', true );

		if ( empty( $slug ) && $post ) {
			$slug = sanitize_key( $post->post_title );
		}

		if ( empty( $slug ) ) {
			$slug = 'agent-' . absint( $post_id );
		}

		return array(
			'post_id'                  => absint( $post_id ),
			'name'                     => $post ? get_the_title( $post ) : $defaults['name'],
			'slug'                     => sanitize_key( $slug ),
			'provider_mode'            => $this->meta_or_default( $post_id, '_wpagent_provider_mode', $defaults['provider_mode'] ),
			'openrouter_model'         => $this->meta_or_default( $post_id, '_wpagent_openrouter_model', $defaults['openrouter_model'] ),
			'wordpress_ai_provider'    => $this->meta_or_default( $post_id, '_wpagent_wordpress_ai_provider', $defaults['wordpress_ai_provider'] ),
			'wordpress_ai_model'       => $this->meta_or_default( $post_id, '_wpagent_wordpress_ai_model', $defaults['wordpress_ai_model'] ),
			'use_wp_ai_client'         => $this->meta_or_default( $post_id, '_wpagent_use_wp_ai_client', $defaults['use_wp_ai_client'] ),
			'allow_guest_chat'         => $this->meta_or_default( $post_id, '_wpagent_allow_guest_chat', $defaults['allow_guest_chat'] ),
			'public_site_assistant'    => $this->meta_or_default( $post_id, '_wpagent_public_site_assistant', $defaults['public_site_assistant'] ),
			'admin_assistant'          => $this->meta_or_default( $post_id, '_wpagent_admin_assistant', $defaults['admin_assistant'] ),
			'user_profile_enabled'     => $this->meta_or_default( $post_id, '_wpagent_user_profile_enabled', $defaults['user_profile_enabled'] ),
			'user_profile_label'       => $this->meta_or_default( $post_id, '_wpagent_user_profile_label', $defaults['user_profile_label'] ),
			'user_profile_description' => $this->meta_or_default( $post_id, '_wpagent_user_profile_description', $defaults['user_profile_description'] ),
			'user_profile_fields'      => $this->profile_fields_or_default( $post_id, $defaults['user_profile_fields'] ),
			'email_actions_enabled'    => $this->meta_or_default( $post_id, '_wpagent_email_actions_enabled', $defaults['email_actions_enabled'] ),
			'email_actions_instructions' => $this->meta_or_default( $post_id, '_wpagent_email_actions_instructions', $defaults['email_actions_instructions'] ),
			'system_prompt'            => $this->meta_or_default( $post_id, '_wpagent_system_prompt', $defaults['system_prompt'] ),
			'max_knowledge_items'      => absint( $this->meta_or_default( $post_id, '_wpagent_max_knowledge_items', $defaults['max_knowledge_items'] ) ),
			'max_memory_items'         => absint( $this->meta_or_default( $post_id, '_wpagent_max_memory_items', $defaults['max_memory_items'] ) ),
			'max_recent_interactions'  => absint( $this->meta_or_default( $post_id, '_wpagent_max_recent_interactions', $defaults['max_recent_interactions'] ) ),
			'token_limit_day'          => absint( $this->meta_or_default( $post_id, '_wpagent_token_limit_day', $defaults['token_limit_day'] ) ),
			'token_limit_week'         => absint( $this->meta_or_default( $post_id, '_wpagent_token_limit_week', $defaults['token_limit_week'] ) ),
			'token_limit_month'        => absint( $this->meta_or_default( $post_id, '_wpagent_token_limit_month', $defaults['token_limit_month'] ) ),
			'training_files'           => $this->get_training_files( $post_id ),
		);
	}

	public function get_agent_options() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$options = array();
		foreach ( $posts as $post ) {
			$agent = $this->get_agent_by_post( $post->ID );
			$options[ $agent['slug'] ] = $agent['name'];
		}

		if ( empty( $options ) ) {
			$fallback = $this->fallback_agent( 'default' );
			$options[ $fallback['slug'] ] = $fallback['name'];
		}

		return $options;
	}

	public function get_automatic_agent( $role ) {
		$meta_key = '';

		if ( 'public_site' === $role ) {
			$meta_key = '_wpagent_public_site_assistant';
		} elseif ( 'admin' === $role ) {
			$meta_key = '_wpagent_admin_assistant';
		}

		if ( empty( $meta_key ) ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish' ),
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_key'       => $meta_key,
				'meta_value'     => '1',
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		return $this->get_agent_by_post( $posts[0]->ID );
	}

	public function ensure_default_agent() {
		$tracked_post_id = absint( get_option( 'wpagent_default_agent_post_id', 0 ) );

		if ( $tracked_post_id && get_post( $tracked_post_id ) ) {
			return;
		}

		$existing = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $existing ) ) {
			update_option( 'wpagent_default_agent_post_id', $existing[0]->ID );
			return;
		}

		$default = $this->fallback_agent( 'default' );
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $default['name'],
			)
		);

		if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_wpagent_agent_slug', $default['slug'] );
		update_post_meta( $post_id, '_wpagent_provider_mode', $default['provider_mode'] );
		update_post_meta( $post_id, '_wpagent_openrouter_model', $default['openrouter_model'] );
		update_post_meta( $post_id, '_wpagent_wordpress_ai_provider', $default['wordpress_ai_provider'] );
		update_post_meta( $post_id, '_wpagent_wordpress_ai_model', $default['wordpress_ai_model'] );
		update_post_meta( $post_id, '_wpagent_use_wp_ai_client', $default['use_wp_ai_client'] );
		update_post_meta( $post_id, '_wpagent_allow_guest_chat', $default['allow_guest_chat'] );
		update_post_meta( $post_id, '_wpagent_public_site_assistant', $default['public_site_assistant'] );
		update_post_meta( $post_id, '_wpagent_admin_assistant', $default['admin_assistant'] );
		update_post_meta( $post_id, '_wpagent_user_profile_enabled', $default['user_profile_enabled'] );
		update_post_meta( $post_id, '_wpagent_user_profile_label', $default['user_profile_label'] );
		update_post_meta( $post_id, '_wpagent_user_profile_description', $default['user_profile_description'] );
		update_post_meta( $post_id, '_wpagent_user_profile_fields', $default['user_profile_fields'] );
		update_post_meta( $post_id, '_wpagent_email_actions_enabled', $default['email_actions_enabled'] );
		update_post_meta( $post_id, '_wpagent_email_actions_instructions', $default['email_actions_instructions'] );
		update_post_meta( $post_id, '_wpagent_system_prompt', $default['system_prompt'] );
		update_post_meta( $post_id, '_wpagent_max_knowledge_items', $default['max_knowledge_items'] );
		update_post_meta( $post_id, '_wpagent_max_memory_items', $default['max_memory_items'] );
		update_post_meta( $post_id, '_wpagent_max_recent_interactions', $default['max_recent_interactions'] );
		update_post_meta( $post_id, '_wpagent_token_limit_day', $default['token_limit_day'] );
		update_post_meta( $post_id, '_wpagent_token_limit_week', $default['token_limit_week'] );
		update_post_meta( $post_id, '_wpagent_token_limit_month', $default['token_limit_month'] );
		update_option( 'wpagent_default_agent_post_id', $post_id );
	}

	private function fallback_agent( $slug ) {
		return array(
			'post_id'                  => 0,
			'name'                     => $this->settings->get( 'agent_name', 'WPAgent' ),
			'slug'                     => sanitize_key( $slug ?: $this->settings->get( 'agent_slug', 'default' ) ),
			'provider_mode'            => '1' === $this->settings->get( 'use_wp_ai_client', '1' ) ? 'wordpress_ai' : 'wpagent_openrouter',
			'openrouter_model'         => $this->settings->get( 'openrouter_model', 'openai/gpt-4.1-mini' ),
			'wordpress_ai_provider'    => $this->settings->get( 'wordpress_ai_provider', '' ),
			'wordpress_ai_model'       => $this->settings->get( 'wordpress_ai_model', '' ),
			'use_wp_ai_client'         => $this->settings->get( 'use_wp_ai_client', '1' ),
			'allow_guest_chat'         => $this->settings->get( 'allow_guest_chat', '0' ),
			'public_site_assistant'    => '0',
			'admin_assistant'          => '0',
			'user_profile_enabled'     => '0',
			'user_profile_label'       => __( 'Sobre voce', 'wpagent' ),
			'user_profile_description' => __( 'Compartilhe informacoes que ajudam este agente a personalizar as respostas.', 'wpagent' ),
			'user_profile_fields'      => array(),
			'email_actions_enabled'    => '0',
			'email_actions_instructions' => '',
			'system_prompt'            => $this->settings->get( 'system_prompt', 'Voce e um assistente pessoal cuidadoso, util e contextual.' ),
			'max_knowledge_items'      => absint( $this->settings->get( 'max_knowledge_items', 4 ) ),
			'max_memory_items'         => absint( $this->settings->get( 'max_memory_items', 8 ) ),
			'max_recent_interactions'  => absint( $this->settings->get( 'max_recent_interactions', 4 ) ),
			'token_limit_day'          => 0,
			'token_limit_week'         => 0,
			'token_limit_month'        => 0,
			'training_files'           => array(),
		);
	}

	private function slug_exists( $slug, $exclude_post_id ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => '_wpagent_agent_slug',
				'meta_value'     => $slug,
				'post__not_in'   => array( absint( $exclude_post_id ) ),
			)
		);

		return ! empty( $posts );
	}

	private function meta_or_default( $post_id, $key, $default ) {
		$value = get_post_meta( $post_id, $key, true );

		return '' === $value ? $default : $value;
	}

	private function profile_fields_or_default( $post_id, $default ) {
		$fields = get_post_meta( $post_id, '_wpagent_user_profile_fields', true );

		if ( is_array( $fields ) ) {
			return $this->sanitize_user_profile_fields_array( $fields );
		}

		return is_array( $default ) ? $default : array();
	}

	private function sanitize_user_profile_fields_text( $text ) {
		$fields = array();
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line ) );
			$label = sanitize_text_field( $parts[0] ?? '' );
			$key = sanitize_key( str_replace( ' ', '_', $parts[1] ?? $label ) );
			$type = sanitize_key( $parts[2] ?? 'text' );
			$placeholder = sanitize_text_field( $parts[3] ?? '' );

			if ( '' === $label || '' === $key ) {
				continue;
			}

			$fields[] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => in_array( $type, array( 'text', 'textarea' ), true ) ? $type : 'text',
				'placeholder' => $placeholder,
			);
		}

		return $this->sanitize_user_profile_fields_array( $fields );
	}

	private function sanitize_user_profile_fields_array( $fields ) {
		$output = array();
		$seen = array();

		foreach ( (array) $fields as $field ) {
			$key = sanitize_key( $field['key'] ?? '' );
			$label = sanitize_text_field( $field['label'] ?? '' );
			$type = sanitize_key( $field['type'] ?? 'text' );
			$placeholder = sanitize_text_field( $field['placeholder'] ?? '' );

			if ( '' === $key || '' === $label || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$output[] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => in_array( $type, array( 'text', 'textarea' ), true ) ? $type : 'text',
				'placeholder' => $placeholder,
			);

			if ( count( $output ) >= 12 ) {
				break;
			}
		}

		return $output;
	}

	private function profile_fields_to_text( $fields ) {
		$lines = array();

		foreach ( (array) $fields as $field ) {
			$lines[] = implode(
				' | ',
				array(
					$field['label'] ?? '',
					$field['key'] ?? '',
					$field['type'] ?? 'text',
					$field['placeholder'] ?? '',
				)
			);
		}

		return implode( "\n", $lines );
	}

	public function get_training_files( $post_id ) {
		$files = get_post_meta( $post_id, '_wpagent_training_files', true );

		return is_array( $files ) ? $files : array();
	}

	private function save_training_files( $post_id ) {
		$agent = $this->get_agent_by_post( $post_id );
		$remove = isset( $_POST['wpagent_remove_training_sources'] ) && is_array( $_POST['wpagent_remove_training_sources'] )
			? array_map( 'absint', wp_unslash( $_POST['wpagent_remove_training_sources'] ) )
			: array();
		$reindex = isset( $_POST['wpagent_reindex_training_sources'] ) && is_array( $_POST['wpagent_reindex_training_sources'] )
			? array_map( 'absint', wp_unslash( $_POST['wpagent_reindex_training_sources'] ) )
			: array();

		if ( ! empty( $remove ) ) {
			foreach ( $remove as $source_id ) {
				$this->repository->delete_training_source( $source_id, $agent['slug'] );
			}
		}

		if ( ! empty( $reindex ) ) {
			foreach ( $reindex as $source_id ) {
				if ( in_array( $source_id, $remove, true ) ) {
					continue;
				}

				$source = $this->repository->get_training_source( $source_id, $agent['slug'] );
				if ( $source && ! empty( $source['attachment_id'] ) ) {
					$this->indexer->index_attachment( $post_id, $agent['slug'], absint( $source['attachment_id'] ) );
				}
			}
		}

		$manual_text = isset( $_POST['wpagent_manual_training_text'] ) ? wp_unslash( $_POST['wpagent_manual_training_text'] ) : '';
		if ( '' !== trim( wp_strip_all_tags( $manual_text ) ) ) {
			$manual_title = isset( $_POST['wpagent_manual_training_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wpagent_manual_training_title'] ) ) : '';
			$this->indexer->index_manual_text( $post_id, $agent['slug'], $manual_title ?: __( 'Texto manual de treinamento', 'wpagent' ), $manual_text );
		}

		if ( ! empty( $_FILES['wpagent_training_files']['name'][0] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$file_count = count( $_FILES['wpagent_training_files']['name'] );

			for ( $index = 0; $index < $file_count; $index++ ) {
				if ( empty( $_FILES['wpagent_training_files']['name'][ $index ] ) ) {
					continue;
				}

				$file = array(
					'name'     => sanitize_file_name( $_FILES['wpagent_training_files']['name'][ $index ] ),
					'type'     => sanitize_mime_type( $_FILES['wpagent_training_files']['type'][ $index ] ),
					'tmp_name' => $_FILES['wpagent_training_files']['tmp_name'][ $index ],
					'error'    => absint( $_FILES['wpagent_training_files']['error'][ $index ] ),
					'size'     => absint( $_FILES['wpagent_training_files']['size'][ $index ] ),
				);

				$_FILES['wpagent_training_file'] = $file;
				$attachment_id = media_handle_upload( 'wpagent_training_file', $post_id );
				unset( $_FILES['wpagent_training_file'] );

				if ( is_wp_error( $attachment_id ) ) {
					$this->set_admin_notice(
						'error',
						sprintf(
							/* translators: 1: filename, 2: error message. */
							__( 'Nao foi possivel enviar %1$s: %2$s', 'wpagent' ),
							$file['name'],
							$attachment_id->get_error_message()
						)
					);
					continue;
				}

				$indexed = $this->indexer->index_attachment( $post_id, $agent['slug'], $attachment_id );
				if ( is_wp_error( $indexed ) ) {
					$this->set_admin_notice(
						'warning',
						sprintf(
							/* translators: 1: filename, 2: error message. */
							__( '%1$s foi recebido, mas nao foi indexado: %2$s', 'wpagent' ),
							$file['name'],
							$indexed->get_error_message()
						)
					);
				}
			}
		}
	}

	private function set_admin_notice( $type, $message ) {
		set_transient(
			'wpagent_admin_notice_' . get_current_user_id(),
			array(
				'type'    => sanitize_key( $type ),
				'message' => wp_strip_all_tags( $message ),
			),
			60
		);
	}

	private function preview_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );

		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		return substr( $text, 0, $limit - 3 ) . '...';
	}

	private function status_label( $status ) {
		$labels = array(
			'processing'       => __( 'Processando', 'wpagent' ),
			'indexed'          => __( 'Indexado', 'wpagent' ),
			'needs_extraction' => __( 'Aguardando extrator', 'wpagent' ),
			'extraction_insufficient' => __( 'Extracao insuficiente', 'wpagent' ),
			'empty'            => __( 'Sem texto', 'wpagent' ),
		);

		return $labels[ $status ] ?? $status;
	}

	private function embedding_progress_label( $source ) {
		$settings = $this->settings->all();
		if ( '1' !== ( $settings['embedding_enabled'] ?? '0' ) ) {
			return __( 'Desativado', 'wpagent' );
		}

		if ( 'indexed' !== ( $source['status'] ?? '' ) ) {
			return __( 'Aguardando indice', 'wpagent' );
		}

		$count = $this->repository->get_embedding_count_for_source(
			absint( $source['id'] ),
			$settings['embedding_provider'] ?? 'openrouter',
			$settings['embedding_model'] ?? 'openai/text-embedding-3-small'
		);

		return sprintf(
			/* translators: 1: ready embeddings, 2: total chunks. */
			__( '%1$d/%2$d prontos', 'wpagent' ),
			$count,
			absint( $source['chunk_count'] )
		);
	}
}
