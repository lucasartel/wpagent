<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Admin_Abilities' ) ) {
class WPAgent_Admin_Abilities {
	private $repository;
	private $settings;

	public function __construct( ?WPAgent_Repository $repository = null, ?WPAgent_Settings $settings = null ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_token_usage_menu' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_token_usage_widget' ) );
		add_action( 'admin_post_wpagent_reset_user_tokens', array( $this, 'handle_reset_user_tokens' ) );

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

		wp_register_ability(
			'wpagent/list-pages',
			array(
				'label'               => __( 'Listar paginas', 'wpagent' ),
				'description'         => __( 'Lista paginas do WordPress por titulo, status e data.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status' => array(
							'type'        => 'string',
							'description' => __( 'Status das paginas.', 'wpagent' ),
							'enum'        => array( 'publish', 'draft', 'pending', 'private', 'any' ),
							'default'     => 'publish',
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => __( 'Quantidade maxima.', 'wpagent' ),
							'minimum'     => 1,
							'maximum'     => 50,
							'default'     => 20,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'pages' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'list_pages' ),
				'permission_callback' => array( $this, 'can_list_pages' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/create-category',
			array(
				'label'               => __( 'Criar categoria', 'wpagent' ),
				'description'         => __( 'Cria uma nova categoria de posts no WordPress.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'        => array(
							'type'        => 'string',
							'description' => __( 'Nome da categoria.', 'wpagent' ),
							'minLength'   => 1,
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Descricao opcional.', 'wpagent' ),
						),
						'parent'      => array(
							'type'        => 'integer',
							'description' => __( 'ID da categoria pai (0 para nenhuma).', 'wpagent' ),
							'default'     => 0,
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ), 'name' => array( 'type' => 'string' ), 'message' => array( 'type' => 'string' ) ) ),
				'execute_callback'    => array( $this, 'create_category' ),
				'permission_callback' => array( $this, 'can_manage_categories' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
			)
		);

		wp_register_ability(
			'wpagent/list-categories',
			array(
				'label'               => __( 'Listar categorias', 'wpagent' ),
				'description'         => __( 'Lista as categorias de posts existentes.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => __( 'Quantidade maxima.', 'wpagent' ),
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 50,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'categories' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'list_categories' ),
				'permission_callback' => array( $this, 'can_manage_categories' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/list-users',
			array(
				'label'               => __( 'Listar usuarios', 'wpagent' ),
				'description'         => __( 'Lista usuarios do WordPress com funcoes e status.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'role'  => array(
							'type'        => 'string',
							'description' => __( 'Filtrar por funcao (ex: administrator, editor, author).', 'wpagent' ),
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => __( 'Quantidade maxima.', 'wpagent' ),
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'users' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'list_users' ),
				'permission_callback' => array( $this, 'can_list_users' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/search-users',
			array(
				'label'               => __( 'Buscar usuarios', 'wpagent' ),
				'description'         => __( 'Busca usuarios por nome ou e-mail.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Nome ou e-mail para buscar.', 'wpagent' ),
							'minLength'   => 1,
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => __( 'Quantidade maxima.', 'wpagent' ),
							'minimum'     => 1,
							'maximum'     => 50,
							'default'     => 10,
						),
					),
					'required'             => array( 'search' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'users' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'search_users' ),
				'permission_callback' => array( $this, 'can_list_users' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/list-plugins',
			array(
				'label'               => __( 'Listar plugins', 'wpagent' ),
				'description'         => __( 'Lista plugins instalados com status (ativo/inativo).', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'active' => array(
							'type'        => 'boolean',
							'description' => __( 'Se true, mostra apenas ativos. Se false, apenas inativos. Se omitido, mostra todos.', 'wpagent' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'plugins' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'list_plugins' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/list-themes',
			array(
				'label'               => __( 'Listar temas', 'wpagent' ),
				'description'         => __( 'Lista temas instalados indicando qual esta ativo.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'themes' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'list_themes' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/list-media',
			array(
				'label'               => __( 'Listar midia', 'wpagent' ),
				'description'         => __( 'Lista itens da biblioteca de midia.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'  => array(
							'type'        => 'integer',
							'description' => __( 'Quantidade maxima.', 'wpagent' ),
							'minimum'     => 1,
							'maximum'     => 50,
							'default'     => 20,
						),
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Texto para buscar no titulo.', 'wpagent' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'media' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'list_media' ),
				'permission_callback' => array( $this, 'can_list_media' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/get-site-info',
			array(
				'label'               => __( 'Informacoes do site', 'wpagent' ),
				'description'         => __( 'Retorna informacoes gerais do site: titulo, descricao, versao do WP, tema ativo, plugins ativos e total de conteudo.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site_name'      => array( 'type' => 'string' ),
						'description'    => array( 'type' => 'string' ),
						'wp_version'     => array( 'type' => 'string' ),
						'active_theme'   => array( 'type' => 'string' ),
						'active_plugins' => array( 'type' => 'array' ),
						'total_posts'    => array( 'type' => 'integer' ),
						'total_pages'    => array( 'type' => 'integer' ),
						'total_users'    => array( 'type' => 'integer' ),
						'total_comments' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'get_site_info' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'wpagent/list-menus',
			array(
				'label'               => __( 'Listar menus de navegacao', 'wpagent' ),
				'description'         => __( 'Lista os menus de navegacao registrados e seus itens.', 'wpagent' ),
				'category'            => 'content-management',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menu' => array(
							'type'        => 'string',
							'description' => __( 'Slug ou ID do menu especifico. Se omitido, lista todos.', 'wpagent' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'menus' => array( 'type' => 'array' ) ) ),
				'execute_callback'    => array( $this, 'list_menus' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
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

	public function can_manage_options() {
		return current_user_can( 'manage_options' );
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

	public function can_list_pages() {
		return current_user_can( 'edit_pages' );
	}

	public function list_pages( $input = array() ) {
		$status = sanitize_key( $input['status'] ?? 'publish' );
		if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'any' ), true ) ) {
			$status = 'publish';
		}

		$pages = get_posts( array(
			'post_type'   => 'page',
			'post_status' => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private' ) : $status,
			'numberposts' => min( 50, max( 1, absint( $input['limit'] ?? 20 ) ) ),
			'orderby'     => 'title',
			'order'       => 'ASC',
		) );

		$result = array();
		foreach ( $pages as $page ) {
			$result[] = array(
				'id'        => $page->ID,
				'title'     => get_the_title( $page ),
				'status'    => $page->post_status,
				'edit_link' => get_edit_post_link( $page->ID, 'raw' ),
			);
		}

		return array( 'pages' => $result );
	}

	public function can_manage_categories() {
		return current_user_can( 'manage_categories' );
	}

	public function create_category( $input ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return new WP_Error( 'wpagent_create_category_forbidden', __( 'Voce nao tem permissao para criar categorias.', 'wpagent' ) );
		}

		$name = sanitize_text_field( $input['name'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'wpagent_empty_category_name', __( 'Nome da categoria e obrigatorio.', 'wpagent' ) );
		}

		$exists = get_term_by( 'name', $name, 'category' );
		if ( $exists ) {
			return new WP_Error( 'wpagent_category_exists', __( 'Categoria com este nome ja existe.', 'wpagent' ) );
		}

		$result = wp_insert_term( $name, 'category', array(
			'description' => sanitize_textarea_field( $input['description'] ?? '' ),
			'parent'      => absint( $input['parent'] ?? 0 ),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'id'      => absint( $result['term_id'] ),
			'name'    => $name,
			'message' => __( 'Categoria criada.', 'wpagent' ),
		);
	}

	public function list_categories( $input = array() ) {
		$categories = get_terms( array(
			'taxonomy'   => 'category',
			'number'     => min( 100, max( 1, absint( $input['limit'] ?? 50 ) ) ),
			'orderby'    => 'name',
			'order'      => 'ASC',
			'hide_empty' => false,
		) );

		if ( is_wp_error( $categories ) ) {
			return array( 'categories' => array() );
		}

		$result = array();
		foreach ( $categories as $cat ) {
			$result[] = array(
				'id'          => $cat->term_id,
				'name'        => $cat->name,
				'slug'        => $cat->slug,
				'count'       => $cat->count,
				'parent'      => $cat->parent,
				'description' => $cat->description,
			);
		}

		return array( 'categories' => $result );
	}

	public function can_list_users() {
		return current_user_can( 'list_users' );
	}

	public function list_users( $input = array() ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'wpagent_list_users_forbidden', __( 'Voce nao tem permissao para listar usuarios.', 'wpagent' ) );
		}

		$args = array(
			'number' => min( 100, max( 1, absint( $input['limit'] ?? 20 ) ) ),
			'orderby'=> 'display_name',
			'order'  => 'ASC',
		);

		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_text_field( $input['role'] );
		}

		$wp_users = get_users( $args );
		$result   = array();

		foreach ( $wp_users as $user ) {
			$result[] = array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'roles'        => $user->roles,
				'registered'   => $user->user_registered,
			);
		}

		return array( 'users' => $result );
	}

	public function search_users( $input = array() ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'wpagent_search_users_forbidden', __( 'Voce nao tem permissao para buscar usuarios.', 'wpagent' ) );
		}

		$search = sanitize_text_field( $input['search'] ?? '' );
		$limit  = min( 50, max( 1, absint( $input['limit'] ?? 10 ) ) );

		$wp_users = get_users( array(
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'display_name', 'user_login', 'user_email' ),
			'number'         => $limit,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
		) );

		$result = array();
		foreach ( $wp_users as $user ) {
			$result[] = array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'roles'        => $user->roles,
			);
		}

		return array( 'users' => $result );
	}

	public function list_plugins( $input = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wpagent_list_plugins_forbidden', __( 'Voce nao tem permissao para listar plugins.', 'wpagent' ) );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = get_option( 'active_plugins', array() );
		$filter      = array_key_exists( 'active', $input ) ? (bool) $input['active'] : null;
		$result      = array();

		foreach ( $all_plugins as $file => $data ) {
			$is_active = in_array( $file, $active, true );

			if ( null !== $filter && $filter !== $is_active ) {
				continue;
			}

			$result[] = array(
				'file'    => $file,
				'name'    => $data['Name'] ?? '',
				'version' => $data['Version'] ?? '',
				'active'  => $is_active,
			);
		}

		return array( 'plugins' => $result );
	}

	public function list_themes( $input = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wpagent_list_themes_forbidden', __( 'Voce nao tem permissao para listar temas.', 'wpagent' ) );
		}

		$wp_themes = wp_get_themes();
		$active    = get_option( 'template' );
		$result    = array();

		foreach ( $wp_themes as $slug => $theme ) {
			$result[] = array(
				'slug'        => $slug,
				'name'        => $theme->get( 'Name' ),
				'version'     => $theme->get( 'Version' ),
				'active'      => $slug === $active,
				'description' => $theme->get( 'Description' ),
			);
		}

		return array( 'themes' => $result );
	}

	public function can_list_media() {
		return current_user_can( 'upload_files' );
	}

	public function list_media( $input = array() ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'wpagent_list_media_forbidden', __( 'Voce nao tem permissao para listar midia.', 'wpagent' ) );
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => min( 50, max( 1, absint( $input['limit'] ?? 20 ) ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$query   = new WP_Query( $args );
		$result  = array();

		foreach ( $query->posts as $item ) {
			$url = wp_get_attachment_url( $item->ID );
			$result[] = array(
				'id'        => $item->ID,
				'title'     => get_the_title( $item ),
				'filename'  => get_the_title( $item ),
				'mime_type' => $item->post_mime_type,
				'url'       => $url ? $url : '',
				'date'      => $item->post_date,
			);
		}

		return array( 'media' => $result );
	}

	public function get_site_info( $input = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wpagent_site_info_forbidden', __( 'Voce nao tem permissao para ver informacoes do site.', 'wpagent' ) );
		}

		$posts_count = wp_count_posts( 'post' );
		$pages_count = wp_count_posts( 'page' );
		$users_count = count_users();

		$active_plugins = array();
		if ( function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins  = get_plugins();
		$active_files = get_option( 'active_plugins', array() );
		foreach ( $active_files as $file ) {
			if ( isset( $all_plugins[ $file ] ) ) {
				$active_plugins[] = $all_plugins[ $file ]['Name'] ?? $file;
			}
		}

		return array(
			'site_name'      => get_bloginfo( 'name' ),
			'description'    => get_bloginfo( 'description' ),
			'wp_version'     => get_bloginfo( 'version' ),
			'active_theme'   => wp_get_theme()->get( 'Name' ),
			'active_plugins' => $active_plugins,
			'total_posts'    => absint( $posts_count->publish ),
			'total_pages'    => absint( $pages_count->publish ),
			'total_users'    => absint( $users_count['total_users'] ),
			'total_comments' => absint( wp_count_comments()->approved ),
		);
	}

	public function list_menus( $input = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wpagent_list_menus_forbidden', __( 'Voce nao tem permissao para listar menus.', 'wpagent' ) );
		}

		$menus = get_terms( array(
			'taxonomy'   => 'nav_menu',
			'hide_empty' => false,
		) );

		if ( is_wp_error( $menus ) ) {
			return array( 'menus' => array() );
		}

		$result = array();
		foreach ( $menus as $menu ) {
			$items     = wp_get_nav_menu_items( $menu->term_id );
			$menu_items = array();

			if ( $items ) {
				foreach ( $items as $item ) {
					$menu_items[] = array(
						'id'    => $item->ID,
						'title' => $item->title,
						'url'   => $item->url,
						'order' => $item->menu_order,
					);
				}
			}

			$result[] = array(
				'id'    => $menu->term_id,
				'name'  => $menu->name,
				'slug'  => $menu->slug,
				'count' => $menu->count,
				'items' => $menu_items,
			);
		}

		return array( 'menus' => $result );
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

	public function handle_reset_user_tokens() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['action'] ) || 'wpagent_reset_user_tokens' !== $_POST['action'] ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wpagent_reset_tokens' ) ) {
			return;
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$reset_widget = isset( $_POST['reset_user_token_usage'] );

		if ( $reset_widget && $this->repository ) {
			global $wpdb;
			$table = $wpdb->prefix . 'wpagent_user_tokens_usage';
			$wpdb->query( "TRUNCATE TABLE {$table}" );
			wp_safe_redirect( admin_url( 'admin.php?page=wpagent-token-usage' ) );
			exit;
		}

		if ( $user_id > 0 && $this->repository ) {
			$this->repository->reset_monthly_usage( $user_id );
			wp_safe_redirect( admin_url( 'admin.php?page=wpagent-token-usage' ) );
			exit;
		}
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

	public function add_token_usage_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_submenu_page(
			'wpagent',
			__( 'Uso de Tokens', 'wpagent' ),
			__( 'Uso de Tokens', 'wpagent' ),
			'manage_options',
			'wpagent-token-usage',
			array( $this, 'render_token_usage_page' )
		);
	}

	public function render_token_usage_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->settings ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Configuracao nao disponivel.', 'wpagent' ) . '</p></div></div>';
			return;
		}

		$enable_global_limit = '1' === $this->settings->get( 'enable_global_token_limit', '0' );
		$global_limit        = (int) $this->settings->get( 'global_token_limit', 100000 );

		$users_token_usage = $this->repository ? $this->repository->get_all_users_token_usage( 100 ) : array();
		$total_tokens = 0;
		$active_users = 0;

		foreach ( $users_token_usage as $usage ) {
			$total_tokens += (int) ( $usage['total_tokens'] ?? 0 );
			if ( (int) ( $usage['total_tokens'] ?? 0 ) > 0 ) {
				$active_users++;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPAgent - Uso de Tokens', 'wpagent' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configure o limite na pagina de Configuracoes Gerais.', 'wpagent' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent&tab=general' ) ); ?>"><?php esc_html_e( 'Ir para Configuracoes', 'wpagent' ); ?></a>
			</p>
			<p>
				<strong><?php esc_html_e( 'Controle ativo:', 'wpagent' ); ?></strong>
				<?php echo $enable_global_limit ? esc_html__( 'Sim', 'wpagent' ) : esc_html__( 'Nao', 'wpagent' ); ?>
				&mdash;
				<strong><?php esc_html_e( 'Limite:', 'wpagent' ); ?></strong>
				<?php echo 0 === $global_limit ? esc_html__( 'Ilimitado', 'wpagent' ) : esc_html( sprintf( __( '%s tokens/mes', 'wpagent' ), number_format_i18n( $global_limit ) ) ); ?>
			</p>

			<h2><?php esc_html_e( 'Resumo de Uso Mensal', 'wpagent' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Usuario', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Total de Tokens', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Criado em', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Acoes', 'wpagent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td colspan="4" class="center">
							<strong><?php echo esc_html( sprintf( __( 'Total: %s tokens de %s usuários ativos', 'wpagent' ), number_format_i18n( $total_tokens ), number_format_i18n( $active_users ) ) ); ?></strong>
						</td>
					</tr>
					<?php if ( empty( $users_token_usage ) ) : ?>
						<tr>
							<td colspan="4" class="center">
								<p><?php esc_html_e( 'Nenhum dado de uso de tokens encontrado.', 'wpagent' ); ?></p>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $users_token_usage as $usage ) : ?>
							<tr>
								<td>
									<?php
									$user = get_userdata( absint( $usage['user_id'] ) );
									echo $user ? esc_html( $user->display_name ) : sprintf( __( 'ID: %d', 'wpagent' ), absint( $usage['user_id'] ) );
									?>
								</td>
								<td>
							<strong><?php echo esc_html( number_format_i18n( (int) ( $usage['total_tokens'] ?? 0 ) ) ); ?></strong>
								</td>
								<td><?php echo esc_html( $usage['created_at'] ?? '-' ); ?></td>
								<td>
									<?php if ( $user && current_user_can( 'manage_options' ) ) : ?>
										<button type="button" class="button button-small" onclick="wpagent_reset_tokens(<?php echo esc_attr( absint( $usage['user_id'] ) ); ?>)">
											<?php esc_html_e( 'Resetar', 'wpagent' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $users_token_usage ) ) : ?>
				<h2><?php esc_html_e( 'Resetar Uso de Usuario', 'wpagent' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja resetar o uso de tokens deste usuario?', 'wpagent' ) ); ?>');">
					<input type="hidden" name="action" value="wpagent_reset_user_tokens">
					<input type="hidden" name="user_id" id="reset_user_id" value="">
					<?php wp_nonce_field( 'wpagent_reset_tokens' ); ?>
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Resetar Tokens', 'wpagent' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>

		<script>
		function wpagent_reset_tokens(userId) {
			document.getElementById('reset_user_id').value = userId;
			document.querySelector('form[action*="admin-post.php"]').submit();
		}
		</script>
		<?php
	}

	public function add_token_usage_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wpagent_token_usage_widget',
			__( 'WPAgent - Uso de Tokens', 'wpagent' ),
			array( $this, 'render_token_usage_widget_content' )
		);
	}

	public function render_token_usage_widget_content() {
		if ( ! $this->repository ) {
			echo '<p>' . esc_html__( 'Repositorio nao disponivel.', 'wpagent' ) . '</p>';
			return;
		}
		$users_token_usage = $this->repository->get_all_users_token_usage( 10 );
		$total_tokens = 0;

		foreach ( $users_token_usage as $usage ) {
			$total_tokens += (int) ( $usage['total_tokens'] ?? 0 );
		}
		?>
		<p><strong><?php esc_html_e( 'Total de tokens este mes:', 'wpagent' ); ?></strong> <?php echo esc_html( number_format_i18n( $total_tokens ) ); ?></p>
		<p><strong><?php esc_html_e( 'Usuarios ativos:', 'wpagent' ); ?></strong> <?php echo esc_html( number_format_i18n( count( $users_token_usage ) ) ); ?></p>
		<?php if ( ! empty( $users_token_usage ) ) : ?>
			<p><strong><?php esc_html_e( 'Top usuarios por uso:', 'wpagent' ); ?></strong></p>
			<ul>
				<?php foreach ( $users_token_usage as $usage ) : ?>
					<?php
					$user = get_userdata( absint( $usage['user_id'] ) );
					if ( $user ) :
					?>
						<li>
							<?php echo esc_html( $user->display_name ); ?>
							&mdash; <?php echo esc_html( sprintf( __( '%s tokens', 'wpagent' ), number_format_i18n( (int) ( $usage['total_tokens'] ?? 0 ) ) ) ); ?>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Nenhum dado de uso de tokens encontrado.', 'wpagent' ); ?></p>
		<?php endif; ?>
		<?php
	}
}
}
