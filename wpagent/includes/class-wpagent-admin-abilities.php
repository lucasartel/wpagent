<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Admin_Abilities {
	public function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	public function available() {
		return function_exists( 'wp_get_abilities' ) && function_exists( 'wp_get_ability' );
	}

	public function register_categories() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'content-management',
			array(
				'label'       => __( 'Gerenciamento de conteudo', 'wpagent' ),
				'description' => __( 'Capacidades para criar, consultar e atualizar conteudos do WordPress.', 'wpagent' ),
			)
		);
	}

	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'wpagent/create-post',
			array(
				'label'               => __( 'Criar post como rascunho', 'wpagent' ),
				'description'         => __( 'Cria um novo post ou pagina como rascunho para revisao humana no WordPress.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Titulo do conteudo.', 'wpagent' ),
							'minLength'   => 1,
						),
						'content'   => array(
							'type'        => 'string',
							'description' => __( 'Conteudo em HTML ou texto simples.', 'wpagent' ),
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => __( 'Resumo opcional.', 'wpagent' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de post. Use post ou page.', 'wpagent' ),
							'enum'        => array( 'post', 'page' ),
							'default'     => 'post',
						),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->post_output_schema(),
				'execute_callback'    => array( $this, 'create_post' ),
				'permission_callback' => array( $this, 'can_create_post' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
						'instructions'=> __( 'Use para criar apenas rascunhos revisaveis. Nunca afirme que publicou.', 'wpagent' ),
					),
				),
			)
		);

		wp_register_ability(
			'wpagent/update-post',
			array(
				'label'               => __( 'Atualizar post existente', 'wpagent' ),
				'description'         => __( 'Atualiza titulo, conteudo, resumo ou status de um post existente, respeitando as permissoes do usuario atual.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID do post a atualizar.', 'wpagent' ),
							'minimum'     => 1,
						),
						'title'   => array(
							'type'        => 'string',
							'description' => __( 'Novo titulo opcional.', 'wpagent' ),
						),
						'content' => array(
							'type'        => 'string',
							'description' => __( 'Novo conteudo opcional em HTML ou texto simples.', 'wpagent' ),
						),
						'excerpt' => array(
							'type'        => 'string',
							'description' => __( 'Novo resumo opcional.', 'wpagent' ),
						),
						'status'  => array(
							'type'        => 'string',
							'description' => __( 'Novo status opcional.', 'wpagent' ),
							'enum'        => array( 'draft', 'pending', 'publish', 'private' ),
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->post_output_schema(),
				'execute_callback'    => array( $this, 'update_post' ),
				'permission_callback' => array( $this, 'can_update_post' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
						'instructions'=> __( 'Use somente apos identificar claramente qual post sera alterado. Peca confirmacao quando faltar ID ou titulo.', 'wpagent' ),
					),
				),
			)
		);

		wp_register_ability(
			'wpagent/rename-content-title',
			array(
				'label'               => __( 'Alterar titulo de post ou pagina', 'wpagent' ),
				'description'         => __( 'Altera somente o titulo de um post ou pagina existente.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'   => array(
							'type'        => 'integer',
							'description' => __( 'ID do post ou pagina.', 'wpagent' ),
							'minimum'     => 1,
						),
						'new_title' => array(
							'type'        => 'string',
							'description' => __( 'Novo titulo.', 'wpagent' ),
							'minLength'   => 1,
						),
					),
					'required'             => array( 'post_id', 'new_title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->post_output_schema(),
				'execute_callback'    => array( $this, 'rename_content_title' ),
				'permission_callback' => array( $this, 'can_rename_content_title' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
						'instructions'=> __( 'Use quando o usuario pedir apenas para alterar titulo de post ou pagina. Se o ID nao estiver claro, use wpagent/search-posts antes.', 'wpagent' ),
					),
				),
			)
		);

		wp_register_ability(
			'wpagent/search-posts',
			array(
				'label'               => __( 'Buscar posts', 'wpagent' ),
				'description'         => __( 'Busca posts e paginas por texto para encontrar o ID correto antes de uma edicao.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'    => array(
							'type'        => 'string',
							'description' => __( 'Texto para buscar no titulo ou conteudo.', 'wpagent' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de post. Use post, page ou any.', 'wpagent' ),
							'enum'        => array( 'post', 'page', 'any' ),
							'default'     => 'any',
						),
						'limit'     => array(
							'type'        => 'integer',
							'description' => __( 'Quantidade maxima de resultados.', 'wpagent' ),
							'minimum'     => 1,
							'maximum'     => 20,
							'default'     => 10,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'posts' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'search_posts' ),
				'permission_callback' => array( $this, 'can_search_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
						'instructions'=> __( 'Use antes de editar quando o usuario nao informar o ID do post.', 'wpagent' ),
					),
				),
			)
		);

		wp_register_ability(
			'wpagent/list-comments',
			array(
				'label'               => __( 'Ver comentarios', 'wpagent' ),
				'description'         => __( 'Lista comentarios para revisao, incluindo pendentes, aprovados, spam ou lixeira.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status'  => array(
							'type'        => 'string',
							'description' => __( 'Status dos comentarios.', 'wpagent' ),
							'enum'        => array( 'hold', 'approve', 'spam', 'trash', 'all' ),
							'default'     => 'hold',
						),
						'search'  => array(
							'type'        => 'string',
							'description' => __( 'Texto opcional para buscar.', 'wpagent' ),
						),
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID opcional do post.', 'wpagent' ),
							'minimum'     => 1,
						),
						'limit'   => array(
							'type'        => 'integer',
							'description' => __( 'Quantidade maxima de resultados.', 'wpagent' ),
							'minimum'     => 1,
							'maximum'     => 30,
							'default'     => 10,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'comments' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'list_comments' ),
				'permission_callback' => array( $this, 'can_moderate_comments' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
						'instructions'=> __( 'Use para encontrar o ID do comentario antes de aprovar ou rejeitar.', 'wpagent' ),
					),
				),
			)
		);

		wp_register_ability(
			'wpagent/moderate-comment',
			array(
				'label'               => __( 'Aprovar ou rejeitar comentario', 'wpagent' ),
				'description'         => __( 'Aprova, rejeita, marca como spam ou coloca um comentario na lixeira.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'comment_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID do comentario.', 'wpagent' ),
							'minimum'     => 1,
						),
						'action'     => array(
							'type'        => 'string',
							'description' => __( 'Acao de moderacao. reject move o comentario para a lixeira.', 'wpagent' ),
							'enum'        => array( 'approve', 'reject', 'spam', 'trash', 'hold' ),
						),
					),
					'required'             => array( 'comment_id', 'action' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->comment_output_schema(),
				'execute_callback'    => array( $this, 'moderate_comment' ),
				'permission_callback' => array( $this, 'can_moderate_comment' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
						'instructions'=> __( 'Use somente quando o ID do comentario e a acao estiverem claros. Para rejeitar, use action=reject.', 'wpagent' ),
					),
				),
			)
		);
	}

	public function prompt_context( $limit = 16 ) {
		if ( ! $this->available() || ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$abilities = $this->list_allowed( $limit );
		if ( empty( $abilities ) ) {
			return '';
		}

		$lines = array(
			'Capacidades administrativas do WordPress disponiveis para o usuario atual:',
		);

		foreach ( $abilities as $ability ) {
			$line = '- ' . $ability['name'] . ': ' . $ability['label'] . '. ' . $this->compact_text( $ability['description'], 260 );
			$line .= ' Readonly: ' . ( $ability['readonly'] ? 'sim' : 'nao' ) . '. Destrutiva: ' . ( $ability['destructive'] ? 'sim' : 'nao' ) . '.';

			if ( ! empty( $ability['input_schema'] ) ) {
				$line .= ' Entrada JSON: ' . $this->compact_text( wp_json_encode( $ability['input_schema'] ), 420 );
			}

			$lines[] = $line;
		}

		$lines[] = 'Quando o usuario pedir uma acao administrativa que corresponda a uma dessas capacidades, nao diga que executou. Explique brevemente o que voce pretende fazer e termine a resposta com um bloco exatamente neste formato:';
		$lines[] = '```wpagent-action';
		$lines[] = '{"ability":"namespace/nome-da-ability","input":{},"reason":"por que esta acao atende ao pedido"}';
		$lines[] = '```';
		$lines[] = 'Use somente nomes de abilities listados acima. Se faltar informacao para preencher o input, faca uma pergunta antes de propor a acao. A execucao real sempre dependera de confirmacao do administrador.';

		return implode( "\n", $lines );
	}

	public function list_allowed( $limit = 50 ) {
		if ( ! $this->available() ) {
			return array();
		}

		$registered = wp_get_abilities();
		$abilities  = array();

		foreach ( $registered as $name => $ability ) {
			$item = $this->ability_to_array( $name, $ability );
			if ( ! $item ) {
				continue;
			}

			$permission = $this->check_permissions( $ability, null );
			if ( false === $permission ) {
				continue;
			}

			$abilities[] = $item;

			if ( count( $abilities ) >= $limit ) {
				break;
			}
		}

		return $abilities;
	}

	public function extract_proposal( $reply ) {
		if ( ! $this->available() || ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		if ( ! preg_match( '/```wpagent-action\s*(\{.*?\})\s*```/s', (string) $reply, $matches ) ) {
			return null;
		}

		$data = json_decode( $matches[1], true );
		if ( ! is_array( $data ) || empty( $data['ability'] ) ) {
			return null;
		}

		$ability_name = sanitize_text_field( $data['ability'] );
		$input        = isset( $data['input'] ) ? $data['input'] : array();
		$reason       = sanitize_textarea_field( $data['reason'] ?? '' );
		$proposal     = $this->prepare_proposal( $ability_name, $input, $reason );

		if ( is_wp_error( $proposal ) ) {
			return null;
		}

		$clean_reply = trim( preg_replace( '/```wpagent-action\s*\{.*?\}\s*```/s', '', (string) $reply ) );
		if ( '' === $clean_reply ) {
			$clean_reply = __( 'Preparei uma acao administrativa para sua revisao.', 'wpagent' );
		}

		return array(
			'reply'    => $clean_reply,
			'proposal' => $proposal,
		);
	}

	public function prepare_proposal( $ability_name, $input = array(), $reason = '' ) {
		if ( ! $this->available() ) {
			return new WP_Error( 'wpagent_abilities_unavailable', __( 'A Abilities API do WordPress nao esta disponivel.', 'wpagent' ), array( 'status' => 501 ) );
		}

		$ability_name = sanitize_text_field( $ability_name );
		$ability      = wp_get_ability( $ability_name );

		if ( ! $ability ) {
			return new WP_Error( 'wpagent_ability_not_found', __( 'Ability nao encontrada.', 'wpagent' ), array( 'status' => 404 ) );
		}

		$input      = $this->sanitize_input( $input );
		$permission = $this->check_permissions( $ability, $input );

		if ( true !== $permission ) {
			return new WP_Error( 'wpagent_ability_forbidden', __( 'O usuario atual nao tem permissao para executar esta ability.', 'wpagent' ), array( 'status' => 403 ) );
		}

		$item = $this->ability_to_array( $ability_name, $ability );

		return array(
			'ability'     => $item['name'],
			'label'       => $item['label'],
			'description' => $item['description'],
			'input'       => $input,
			'reason'      => $reason,
			'readonly'    => $item['readonly'],
			'destructive' => $item['destructive'],
		);
	}

	public function execute( $ability_name, $input = array() ) {
		$proposal = $this->prepare_proposal( $ability_name, $input );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		$ability = wp_get_ability( $proposal['ability'] );
		if ( ! $ability ) {
			return new WP_Error( 'wpagent_ability_not_found', __( 'Ability nao encontrada.', 'wpagent' ), array( 'status' => 404 ) );
		}

		try {
			$result = $ability->execute( $proposal['input'] );
		} catch ( Throwable $throwable ) {
			return new WP_Error( 'wpagent_ability_exception', $throwable->getMessage(), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'ability' => $proposal,
			'result'  => $result,
		);
	}

	public function can_create_post( $input = array() ) {
		$post_type = $this->safe_post_type( $input['post_type'] ?? 'post' );
		$type      = get_post_type_object( $post_type );

		return $type && current_user_can( $type->cap->edit_posts );
	}

	public function create_post( $input ) {
		$post_type = $this->safe_post_type( $input['post_type'] ?? 'post' );
		$type      = get_post_type_object( $post_type );

		if ( ! $type || ! current_user_can( $type->cap->edit_posts ) ) {
			return new WP_Error( 'wpagent_create_post_forbidden', __( 'Voce nao tem permissao para criar este tipo de conteudo.', 'wpagent' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => 'draft',
				'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
				'post_content' => wp_kses_post( $input['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return $this->post_result( $post_id, __( 'Rascunho criado.', 'wpagent' ) );
	}

	public function can_update_post( $input = array() ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		if ( 'publish' === ( $input['status'] ?? '' ) && ! $this->can_publish_post_type( $post->post_type ) ) {
			return false;
		}

		return true;
	}

	public function update_post( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wpagent_update_post_forbidden', __( 'Voce nao tem permissao para editar este post.', 'wpagent' ) );
		}

		$post_data = array( 'ID' => $post_id );

		if ( array_key_exists( 'title', $input ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}

		if ( array_key_exists( 'content', $input ) ) {
			$post_data['post_content'] = wp_kses_post( $input['content'] );
		}

		if ( array_key_exists( 'excerpt', $input ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}

		if ( ! empty( $input['status'] ) ) {
			if ( 'publish' === $input['status'] && ! $this->can_publish_post_type( $post->post_type ) ) {
				return new WP_Error( 'wpagent_publish_post_forbidden', __( 'Voce nao tem permissao para publicar este post.', 'wpagent' ) );
			}
			$post_data['post_status'] = sanitize_key( $input['status'] );
		}

		$updated = wp_update_post( $post_data, true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $this->post_result( $post_id, __( 'Post atualizado.', 'wpagent' ) );
	}

	public function can_rename_content_title( $input = array() ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	public function rename_content_title( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wpagent_rename_title_forbidden', __( 'Voce nao tem permissao para alterar o titulo deste conteudo.', 'wpagent' ) );
		}

		$updated = wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => sanitize_text_field( $input['new_title'] ?? '' ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $this->post_result( $post_id, __( 'Titulo atualizado.', 'wpagent' ) );
	}

	public function can_search_posts() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	public function search_posts( $input = array() ) {
		$post_type = $input['post_type'] ?? 'any';
		if ( ! in_array( $post_type, array( 'post', 'page', 'any' ), true ) ) {
			$post_type = 'any';
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				's'              => sanitize_text_field( $input['search'] ?? '' ),
				'posts_per_page' => min( 20, max( 1, absint( $input['limit'] ?? 10 ) ) ),
				'no_found_rows'  => true,
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$posts[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'type'      => $post->post_type,
				'status'    => $post->post_status,
				'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		return array( 'posts' => $posts );
	}

	public function can_moderate_comments() {
		return current_user_can( 'moderate_comments' );
	}

	public function can_moderate_comment( $input = array() ) {
		$comment_id = absint( $input['comment_id'] ?? 0 );

		return $comment_id && get_comment( $comment_id ) && current_user_can( 'moderate_comments' );
	}

	public function list_comments( $input = array() ) {
		$status = sanitize_key( $input['status'] ?? 'hold' );
		if ( ! in_array( $status, array( 'hold', 'approve', 'spam', 'trash', 'all' ), true ) ) {
			$status = 'hold';
		}

		$args = array(
			'status' => $status,
			'number' => min( 30, max( 1, absint( $input['limit'] ?? 10 ) ) ),
			'orderby'=> 'comment_date_gmt',
			'order'  => 'DESC',
		);

		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['post_id'] ) ) {
			$args['post_id'] = absint( $input['post_id'] );
		}

		$comments = get_comments( $args );
		$items    = array();

		foreach ( $comments as $comment ) {
			$items[] = $this->comment_result( $comment->comment_ID, '' );
		}

		return array( 'comments' => $items );
	}

	public function moderate_comment( $input ) {
		$comment_id = absint( $input['comment_id'] ?? 0 );
		$comment    = get_comment( $comment_id );

		if ( ! $comment || ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'wpagent_moderate_comment_forbidden', __( 'Voce nao tem permissao para moderar este comentario.', 'wpagent' ) );
		}

		$action = sanitize_key( $input['action'] ?? '' );
		$status_map = array(
			'approve' => 'approve',
			'reject'  => 'trash',
			'spam'    => 'spam',
			'trash'   => 'trash',
			'hold'    => 'hold',
		);

		if ( empty( $status_map[ $action ] ) ) {
			return new WP_Error( 'wpagent_invalid_comment_action', __( 'Acao de comentario invalida.', 'wpagent' ) );
		}

		$updated = wp_set_comment_status( $comment_id, $status_map[ $action ] );
		if ( ! $updated ) {
			return new WP_Error( 'wpagent_comment_not_updated', __( 'Nao foi possivel atualizar o comentario.', 'wpagent' ) );
		}

		return $this->comment_result( $comment_id, __( 'Comentario moderado.', 'wpagent' ) );
	}

	private function ability_to_array( $fallback_name, $ability ) {
		if ( ! is_object( $ability ) ) {
			return null;
		}

		$name = method_exists( $ability, 'get_name' ) ? $ability->get_name() : $fallback_name;
		if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name ) ) {
			return null;
		}

		$meta        = method_exists( $ability, 'get_meta' ) ? (array) $ability->get_meta() : array();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : $meta;

		return array(
			'name'          => $name,
			'label'         => method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : $name,
			'description'   => method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '',
			'input_schema'  => method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : null,
			'output_schema' => method_exists( $ability, 'get_output_schema' ) ? $ability->get_output_schema() : null,
			'readonly'      => ! empty( $annotations['readonly'] ),
			'destructive'   => ! empty( $annotations['destructive'] ),
		);
	}

	private function check_permissions( $ability, $input ) {
		try {
			if ( null === $input ) {
				return $ability->check_permissions();
			}

			return $ability->check_permissions( $input );
		} catch ( Throwable $throwable ) {
			return new WP_Error( 'wpagent_ability_permission_exception', $throwable->getMessage(), array( 'status' => 500 ) );
		}
	}

	private function sanitize_input( $input ) {
		if ( is_array( $input ) ) {
			return map_deep( $input, 'sanitize_text_field' );
		}

		if ( is_object( $input ) ) {
			return map_deep( (array) $input, 'sanitize_text_field' );
		}

		if ( null === $input ) {
			return array();
		}

		return sanitize_text_field( $input );
	}

	private function safe_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type ?: 'post' );

		return in_array( $post_type, array( 'post', 'page' ), true ) ? $post_type : 'post';
	}

	private function post_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'type'      => array( 'type' => 'string' ),
				'edit_link' => array( 'type' => 'string' ),
				'message'   => array( 'type' => 'string' ),
			),
		);
	}

	private function comment_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'post_id'     => array( 'type' => 'integer' ),
				'post_title'  => array( 'type' => 'string' ),
				'author'      => array( 'type' => 'string' ),
				'author_email'=> array( 'type' => 'string' ),
				'content'     => array( 'type' => 'string' ),
				'status'      => array( 'type' => 'string' ),
				'edit_link'   => array( 'type' => 'string' ),
				'message'     => array( 'type' => 'string' ),
			),
		);
	}

	private function can_publish_post_type( $post_type ) {
		$type = get_post_type_object( $post_type );

		return $type && ! empty( $type->cap->publish_posts ) && current_user_can( $type->cap->publish_posts );
	}

	private function post_result( $post_id, $message ) {
		$post = get_post( $post_id );

		return array(
			'id'        => absint( $post_id ),
			'title'     => $post ? get_the_title( $post ) : '',
			'status'    => $post ? $post->post_status : '',
			'type'      => $post ? $post->post_type : '',
			'edit_link' => get_edit_post_link( $post_id, 'raw' ),
			'message'   => $message,
		);
	}

	private function comment_result( $comment_id, $message ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return array(
				'id'      => absint( $comment_id ),
				'message' => $message,
			);
		}

		return array(
			'id'          => absint( $comment_id ),
			'post_id'     => absint( $comment->comment_post_ID ),
			'post_title'  => get_the_title( $comment->comment_post_ID ),
			'author'      => sanitize_text_field( $comment->comment_author ),
			'author_email'=> sanitize_email( $comment->comment_author_email ),
			'content'     => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 55 ),
			'status'      => wp_get_comment_status( $comment ),
			'edit_link'   => get_edit_comment_link( $comment_id ),
			'message'     => $message,
		);
	}

	private function compact_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );

		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		return substr( $text, 0, $limit - 3 ) . '...';
	}
}
