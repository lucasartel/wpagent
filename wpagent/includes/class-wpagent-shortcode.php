<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Shortcode' ) ) {
class WPAgent_Shortcode {
	private $settings;
	private $agents;
	private $inline_script_added = false;

	public function __construct( WPAgent_Settings $settings, WPAgent_Agents $agents ) {
		$this->settings = $settings;
		$this->agents   = $agents;
	}

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_public_site_assistant' ), 10 );
		add_action( 'admin_footer', array( $this, 'render_admin_assistant' ), 10 );
		add_shortcode( 'wpagent_chat', array( $this, 'render' ) );
	}

	public function register_assets() {
		wp_register_script(
			'wpagent-chat',
			WPAGENT_URL . 'assets/js/chat.js',
			array(),
			WPAGENT_VERSION,
			true
		);

		wp_register_style(
			'wpagent-chat',
			WPAGENT_URL . 'assets/css/chat.css',
			array(),
			WPAGENT_VERSION
		);

		if ( $this->should_enqueue_floating_assets() ) {
			$this->enqueue_chat_assets();
		}
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'agent' => $this->settings->get( 'agent_slug', 'default' ),
				'title' => '',
			),
			$atts,
			'wpagent_chat'
		);

		$agent = $this->agents->get_agent( $atts['agent'] );
		$title = $atts['title'] ?: $agent['name'];

		return $this->render_chat( $agent, $title );
	}

	public function render_public_site_assistant() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		$agent = $this->agents->get_automatic_agent( 'public_site' );
		if ( ! $agent || '1' !== $agent['allow_guest_chat'] ) {
			return;
		}

		echo $this->render_floating_assistant( $agent, 'public' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_admin_assistant() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$agent = $this->agents->get_automatic_agent( 'admin' );
		if ( ! $agent ) {
			return;
		}

		echo $this->render_floating_assistant( $agent, 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function render_chat( $agent, $title, $extra_class = '' ) {
		$this->enqueue_chat_assets();
		$provider_timeout = max( 15, min( 180, (int) apply_filters( 'wpagent_ai_request_timeout', 90, 'chat' ) ) );
		$browser_timeout  = max( 30000, min( 240000, (int) apply_filters( 'wpagent_chat_request_timeout', ( $provider_timeout + 15 ) * 1000, $agent ) ) );

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( trim( 'wpagent-chat ' . $extra_class ) ); ?>"
			data-agent="<?php echo esc_attr( $agent['slug'] ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'wpagent/v1/chat' ) ); ?>"
			data-conversations-url="<?php echo esc_url( rest_url( 'wpagent/v1/conversations' ) ); ?>"
			data-profile-url="<?php echo esc_url( rest_url( 'wpagent/v1/profile' ) ); ?>"
			data-abilities-url="<?php echo esc_url( rest_url( 'wpagent/v1/abilities/run' ) ); ?>"
			data-email-actions-url="<?php echo esc_url( rest_url( 'wpagent/v1/email/send' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-request-timeout="<?php echo esc_attr( $browser_timeout ); ?>"
			data-debug-errors="<?php echo current_user_can( 'manage_options' ) ? '1' : '0'; ?>"
			data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>"
			data-user-profile-enabled="<?php echo '1' === ( $agent['user_profile_enabled'] ?? '0' ) ? '1' : '0'; ?>"
			data-show-token-usage="<?php echo '1' === ( $agent['show_token_usage'] ?? '0' ) ? '1' : '0'; ?>"
			data-default-theme="<?php echo 'dark' === ( $agent['default_theme'] ?? 'light' ) ? 'dark' : 'light'; ?>"
			data-i18n="<?php echo esc_attr( wp_json_encode( WPAgent_I18n::chat_strings() ) ); ?>"
		>
			<div class="wpagent-chat__mobile-bar">
				<button type="button" class="wpagent-chat__toggle-conversations" aria-expanded="false"><?php esc_html_e( 'Conversas', 'wpagent' ); ?></button>
				<span class="wpagent-chat__mobile-title"><?php echo esc_html( $title ); ?></span>
				<button type="button" class="wpagent-chat__new-conversation wpagent-chat__new-conversation--mobile"><?php esc_html_e( 'Nova', 'wpagent' ); ?></button>
			</div>
			<div class="wpagent-chat__sidebar" aria-label="<?php esc_attr_e( 'Conversas', 'wpagent' ); ?>">
				<div class="wpagent-chat__sidebar-header">
					<div>
						<strong><?php echo esc_html( $title ); ?></strong>
						<span><?php esc_html_e( 'Suas conversas', 'wpagent' ); ?></span>
					</div>
					<button type="button" class="wpagent-chat__new-conversation"><?php esc_html_e( 'Nova', 'wpagent' ); ?></button>
				</div>
				<div class="wpagent-chat__conversations"></div>
				<?php if ( is_user_logged_in() && '1' === ( $agent['user_profile_enabled'] ?? '0' ) ) : ?>
					<div class="wpagent-chat__profile" data-wpagent-profile>
						<label for="wpagent-profile-<?php echo esc_attr( $agent['slug'] ); ?>"><?php echo esc_html( $agent['user_profile_label'] ?: __( 'Sobre voce', 'wpagent' ) ); ?></label>
						<p><?php echo esc_html( $agent['user_profile_description'] ?: __( 'Compartilhe informacoes que ajudam este agente a personalizar as respostas.', 'wpagent' ) ); ?></p>
						<?php $this->render_profile_fields( $agent ); ?>
						<?php if ( ! empty( $agent['user_profile_fields'] ) ) : ?>
							<label class="wpagent-chat__profile-free-label" for="wpagent-profile-<?php echo esc_attr( $agent['slug'] ); ?>"><?php esc_html_e( 'Observacoes adicionais', 'wpagent' ); ?></label>
						<?php endif; ?>
						<textarea id="wpagent-profile-<?php echo esc_attr( $agent['slug'] ); ?>" rows="5" maxlength="4000" data-profile-free placeholder="<?php esc_attr_e( 'Ex.: ano em que leciono, estilo de aula, objetivos e preferencias...', 'wpagent' ); ?>"></textarea>
						<div class="wpagent-chat__profile-actions">
							<span class="wpagent-chat__profile-status" aria-live="polite"></span>
							<button type="button" class="wpagent-chat__profile-save"><?php esc_html_e( 'Salvar perfil', 'wpagent' ); ?></button>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<div class="wpagent-chat__main">
				<div class="wpagent-chat__header">
					<button type="button" class="wpagent-chat__toggle-conversations wpagent-chat__toggle-conversations--desktop" aria-expanded="true"><?php esc_html_e( 'Conversas', 'wpagent' ); ?></button>
					<div class="wpagent-chat__conversation-heading">
						<strong class="wpagent-chat__conversation-title"><?php esc_html_e( 'Nova conversa', 'wpagent' ); ?></strong>
						<span class="wpagent-chat__status"><?php esc_html_e( 'Pronto para conversar', 'wpagent' ); ?></span>
					</div>
					<button type="button" class="wpagent-chat__theme-toggle" aria-pressed="false"><?php esc_html_e( 'Modo escuro', 'wpagent' ); ?></button>
					<button type="button" class="wpagent-chat__rename-conversation"><?php esc_html_e( 'Renomear', 'wpagent' ); ?></button>
				</div>
				<div class="wpagent-chat__messages" aria-live="polite">
					<div class="wpagent-chat__empty">
						<strong><?php esc_html_e( 'Como posso ajudar?', 'wpagent' ); ?></strong>
						<span><?php esc_html_e( 'Escreva uma pergunta ou escolha uma conversa anterior.', 'wpagent' ); ?></span>
					</div>
				</div>
				<form class="wpagent-chat__form">
					<label class="screen-reader-text" for="wpagent-message-<?php echo esc_attr( $agent['slug'] ); ?>"><?php esc_html_e( 'Mensagem', 'wpagent' ); ?></label>
					<div class="wpagent-chat__composer">
						<textarea id="wpagent-message-<?php echo esc_attr( $agent['slug'] ); ?>" rows="1" placeholder="<?php esc_attr_e( 'Escreva sua mensagem...', 'wpagent' ); ?>"></textarea>
						<button type="submit"><?php esc_html_e( 'Enviar', 'wpagent' ); ?></button>
					</div>
					<div class="wpagent-chat__hint"><?php esc_html_e( 'Enter envia. Shift + Enter quebra linha.', 'wpagent' ); ?></div>
					<?php if ( '1' === ( $agent['show_token_usage'] ?? '0' ) ) : ?>
						<div class="wpagent-chat__token-usage" data-wpagent-token-usage>
							<div class="wpagent-chat__token-summary">
								<span class="wpagent-chat__token-count"></span>
								<span class="wpagent-chat__token-limit"></span>
							</div>
							<div class="wpagent-chat__token-progress" aria-hidden="true"><span></span></div>
						</div>
					<?php endif; ?>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_profile_fields( $agent ) {
		$fields = is_array( $agent['user_profile_fields'] ?? null ) ? $agent['user_profile_fields'] : array();

		if ( empty( $fields ) ) {
			return;
		}

		echo '<div class="wpagent-chat__profile-fields">';
		foreach ( $fields as $field ) {
			$key = sanitize_key( $field['key'] ?? '' );
			$label = sanitize_text_field( $field['label'] ?? '' );
			$type = sanitize_key( $field['type'] ?? 'text' );
			$placeholder = sanitize_text_field( $field['placeholder'] ?? '' );
			$id = 'wpagent-profile-' . $agent['slug'] . '-' . $key;

			if ( '' === $key || '' === $label ) {
				continue;
			}
			?>
			<div class="wpagent-chat__profile-field">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
				<?php if ( 'textarea' === $type ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" rows="3" maxlength="1000" data-profile-key="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"></textarea>
				<?php else : ?>
					<input id="<?php echo esc_attr( $id ); ?>" type="text" maxlength="1000" data-profile-key="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
				<?php endif; ?>
			</div>
			<?php
		}
		echo '</div>';
	}

	private function enqueue_chat_assets() {
		wp_enqueue_script( 'wpagent-chat' );
		wp_enqueue_style( 'wpagent-chat' );

		if ( $this->inline_script_added ) {
			return;
		}

		wp_add_inline_script(
			'wpagent-chat',
			'window.wpagentChatI18n = ' . wp_json_encode( WPAgent_I18n::chat_strings() ) . ';',
			'before'
		);

		$this->inline_script_added = true;
	}

	private function should_enqueue_floating_assets() {
		if ( is_admin() ) {
			return current_user_can( 'manage_options' ) && (bool) $this->agents->get_automatic_agent( 'admin' );
		}

		if ( is_user_logged_in() ) {
			return false;
		}

		$agent = $this->agents->get_automatic_agent( 'public_site' );

		return $agent && '1' === $agent['allow_guest_chat'];
	}

	private function render_floating_assistant( $agent, $context ) {
		$title = $agent['name'];

		ob_start();
		?>
		<div class="wpagent-floating-assistant wpagent-floating-assistant--<?php echo esc_attr( $context ); ?>" data-wpagent-floating>
			<button type="button" class="wpagent-floating-assistant__launcher" aria-expanded="false">
				<span class="wpagent-floating-assistant__launcher-icon" aria-hidden="true"></span>
				<span><?php echo esc_html( $title ); ?></span>
			</button>
			<div class="wpagent-floating-assistant__panel" role="dialog" aria-label="<?php echo esc_attr( $title ); ?>">
				<div class="wpagent-floating-assistant__panel-header">
					<strong><?php echo esc_html( $title ); ?></strong>
					<button type="button" class="wpagent-floating-assistant__close" aria-label="<?php esc_attr_e( 'Fechar assistente', 'wpagent' ); ?>"><?php esc_html_e( 'Fechar', 'wpagent' ); ?></button>
				</div>
				<?php echo $this->render_chat( $agent, $title, 'wpagent-chat--floating' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
}
