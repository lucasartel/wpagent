<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Reports' ) ) {
class WPAgent_Reports {
	private $repository;
	private $agents;

	public function __construct( WPAgent_Repository $repository, WPAgent_Agents $agents ) {
		$this->repository = $repository;
		$this->agents     = $agents;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
	}

	public function add_menu() {
		add_submenu_page(
			'wpagent',
			__( 'Reports', 'wpagent' ),
			__( 'Reports', 'wpagent' ),
			'manage_options',
			'wpagent-reports',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$agent_rows = $this->repository->get_token_usage_report_by_agent();
		$user_rows  = $this->repository->get_token_usage_report_by_user( 100 );
		$lead_summary_rows = $this->repository->get_email_lead_summary_by_agent();
		$lead_rows = $this->repository->get_email_leads_report( 200 );
		$starts     = $this->repository->get_token_period_starts();
		$agents     = $this->agents->get_agent_options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPAgent Reports', 'wpagent' ); ?></h1>
			<p><?php esc_html_e( 'Acompanhe o consumo de tokens registrado por agente e por usuario.', 'wpagent' ); ?></p>
			<p class="description">
				<?php
				printf(
					/* translators: 1: day start, 2: week start, 3: month start. */
					esc_html__( 'Periodos atuais: dia desde %1$s, semana desde %2$s, mes desde %3$s.', 'wpagent' ),
					esc_html( $starts['day'] ),
					esc_html( $starts['week'] ),
					esc_html( $starts['month'] )
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Consumo por agente', 'wpagent' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Agente', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Interacoes', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Usuarios', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Hoje', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Semana', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Mes', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Entrada', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Saida', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Limites', 'wpagent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $agent_rows ) ) : ?>
						<tr><td colspan="10"><?php esc_html_e( 'Nenhum uso registrado ainda.', 'wpagent' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $agent_rows as $row ) : ?>
							<?php $agent = $this->agents->get_agent( $row['agent_slug'] ); ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $agents[ $row['agent_slug'] ] ?? $row['agent_slug'] ); ?></strong><br>
									<code><?php echo esc_html( $row['agent_slug'] ); ?></code>
								</td>
								<td><?php echo esc_html( number_format_i18n( $row['interactions'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['users'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['day_tokens'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['week_tokens'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['month_tokens'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['input_tokens'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['output_tokens'] ) ); ?></td>
								<td><strong><?php echo esc_html( number_format_i18n( $row['total_tokens'] ) ); ?></strong></td>
								<td><?php echo esc_html( $this->format_limits( $agent ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Leads por email', 'wpagent' ); ?></h2>
			<p><?php esc_html_e( 'Acompanhe os emails coletados com consentimento durante conversas em que o agente preparou e enviou mensagens autorizadas pelo usuario.', 'wpagent' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Agente', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Leads', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Emails enviados', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Falhas', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Total de eventos', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Ultimo email', 'wpagent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $lead_summary_rows ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Nenhum lead por email registrado ainda.', 'wpagent' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $lead_summary_rows as $row ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $agents[ $row['agent_slug'] ] ?? $row['agent_slug'] ); ?></strong><br>
									<code><?php echo esc_html( $row['agent_slug'] ); ?></code>
								</td>
								<td><strong><?php echo esc_html( number_format_i18n( $row['leads'] ) ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( $row['sent_count'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['failed_count'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['email_events'] ) ); ?></td>
								<td><?php echo esc_html( $row['last_email_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Leads recentes', 'wpagent' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Nome', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Agente', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Ultimo assunto', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Finalidade', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Status recente', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Envios', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Primeiro registro', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Ultimo email', 'wpagent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $lead_rows ) ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'Nenhum lead por email registrado ainda.', 'wpagent' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $lead_rows as $row ) : ?>
							<tr>
								<td><a href="mailto:<?php echo esc_attr( $row['recipient_email'] ); ?>"><?php echo esc_html( $row['recipient_email'] ); ?></a></td>
								<td><?php echo esc_html( $row['recipient_name'] ?: '-' ); ?></td>
								<td><code><?php echo esc_html( $row['agent_slug'] ); ?></code></td>
								<td><?php echo esc_html( $this->compact_report_text( $row['last_subject'] ?? '', 80 ) ); ?></td>
								<td><?php echo esc_html( $this->compact_report_text( $row['last_purpose'] ?? '', 80 ) ); ?></td>
								<td><?php echo esc_html( $this->email_status_label( $row['last_status'] ?? '' ) ); ?></td>
								<td>
									<?php
									printf(
										/* translators: 1: sent count, 2: failed count, 3: total count. */
										esc_html__( '%1$s enviados, %2$s falhas, %3$s total', 'wpagent' ),
										esc_html( number_format_i18n( $row['sent_count'] ) ),
										esc_html( number_format_i18n( $row['failed_count'] ) ),
										esc_html( number_format_i18n( $row['email_events'] ) )
									);
									?>
								</td>
								<td><?php echo esc_html( $row['first_seen_at'] ); ?></td>
								<td><?php echo esc_html( $row['last_email_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Maiores consumos por usuario', 'wpagent' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Agente', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Usuario', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Interacoes', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Entrada', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Saida', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Ultimo uso', 'wpagent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $user_rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Nenhum uso registrado ainda.', 'wpagent' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $user_rows as $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( $row['agent_slug'] ); ?></code></td>
								<td><?php echo esc_html( $this->user_label( $row['user_id'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['interactions'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['input_tokens'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['output_tokens'] ) ); ?></td>
								<td><strong><?php echo esc_html( number_format_i18n( $row['total_tokens'] ) ); ?></strong></td>
								<td><?php echo esc_html( $row['last_used_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function format_limits( $agent ) {
		return sprintf(
			/* translators: 1: daily, 2: weekly, 3: monthly. */
			__( 'Dia: %1$s / Semana: %2$s / Mes: %3$s', 'wpagent' ),
			$this->limit_label( $agent['token_limit_day'] ?? 0 ),
			$this->limit_label( $agent['token_limit_week'] ?? 0 ),
			$this->limit_label( $agent['token_limit_month'] ?? 0 )
		);
	}

	private function limit_label( $value ) {
		$value = absint( $value );

		return $value ? number_format_i18n( $value ) : __( 'ilimitado', 'wpagent' );
	}

	private function user_label( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return __( 'Visitante', 'wpagent' );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return sprintf(
				/* translators: %d: user id. */
				__( 'Usuario #%d', 'wpagent' ),
				$user_id
			);
		}

		return $user->display_name . ' (#' . $user_id . ')';
	}

	private function email_status_label( $status ) {
		$labels = array(
			'sent'    => __( 'enviado', 'wpagent' ),
			'failed'  => __( 'falhou', 'wpagent' ),
			'queued'  => __( 'agendado', 'wpagent' ),
			'pending' => __( 'pendente', 'wpagent' ),
		);

		return $labels[ $status ] ?? sanitize_text_field( $status );
	}

	private function compact_report_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );

		if ( strlen( $text ) <= $limit ) {
			return $text ?: '-';
		}

		return substr( $text, 0, $limit - 3 ) . '...';
	}
}
}
