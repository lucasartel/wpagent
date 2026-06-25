<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_I18n {
	public static function register() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_filter( 'gettext', array( __CLASS__, 'translate' ), 10, 3 );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'wpagent', false, dirname( plugin_basename( WPAGENT_FILE ) ) . '/languages' );
	}

	public static function locale_family( $locale = '' ) {
		if ( '' === $locale && function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
		}

		$locale = $locale ?: get_locale();
		$locale = strtolower( (string) $locale );

		if ( 0 === strpos( $locale, 'pt' ) ) {
			return 'pt';
		}

		if ( 0 === strpos( $locale, 'es' ) ) {
			return 'es';
		}

		return 'en';
	}

	public static function translate( $translation, $text, $domain ) {
		if ( 'wpagent' !== $domain ) {
			return $translation;
		}

		if ( '' !== $translation && $translation !== $text ) {
			return $translation;
		}

		$catalog = self::catalog();
		$family  = self::locale_family();

		if ( isset( $catalog[ $text ][ $family ] ) ) {
			return $catalog[ $text ][ $family ];
		}

		return $translation;
	}

	public static function chat_strings() {
		return array(
			'genericErrorPrefix'       => __( 'Erro no WPAgent. Status ', 'wpagent' ),
			'timeoutError'             => __( 'A resposta demorou demais. Verifique a chave, o modelo e o fornecedor de IA.', 'wpagent' ),
			'emptyTitle'               => __( 'Como posso ajudar?', 'wpagent' ),
			'emptyText'                => __( 'Escreva uma pergunta ou escolha uma conversa anterior.', 'wpagent' ),
			'loginToSave'              => __( 'Entre na conta para salvar e retomar conversas.', 'wpagent' ),
			'noConversations'          => __( 'Nenhuma conversa salva ainda.', 'wpagent' ),
			'conversation'             => __( 'Conversa', 'wpagent' ),
			'savedConversation'        => __( 'Conversa salva', 'wpagent' ),
			'openConversation'         => __( 'Abrir conversa ', 'wpagent' ),
			'deleteConversation'       => __( 'Apagar conversa ', 'wpagent' ),
			'loadingConversations'     => __( 'Carregando conversas...', 'wpagent' ),
			'ready'                    => __( 'Pronto para conversar', 'wpagent' ),
			'cannotLoadConversations'  => __( 'Nao foi possivel carregar conversas', 'wpagent' ),
			'loadingMessages'          => __( 'Carregando mensagens...', 'wpagent' ),
			'loadConversationError'    => __( 'Erro ao carregar conversa', 'wpagent' ),
			'newConversation'          => __( 'Nova conversa', 'wpagent' ),
			'newConversationStarted'   => __( 'Nova conversa iniciada', 'wpagent' ),
			'createConversationError'  => __( 'Erro ao criar conversa', 'wpagent' ),
			'loginToRename'            => __( 'Entre na conta para renomear conversas', 'wpagent' ),
			'renamePrompt'             => __( 'Nome da conversa', 'wpagent' ),
			'renaming'                 => __( 'Renomeando conversa...', 'wpagent' ),
			'renamed'                  => __( 'Conversa renomeada', 'wpagent' ),
			'renameError'              => __( 'Erro ao renomear conversa', 'wpagent' ),
			'deleteConfirmBefore'      => __( 'Apagar a conversa "', 'wpagent' ),
			'deleteConfirmAfter'       => __( '"?', 'wpagent' ),
			'deleting'                 => __( 'Apagando conversa...', 'wpagent' ),
			'deleted'                  => __( 'Conversa apagada', 'wpagent' ),
			'deleteError'              => __( 'Erro ao apagar conversa', 'wpagent' ),
			'processing'               => __( 'Processando', 'wpagent' ),
			'processingReply'          => __( 'Processando resposta...', 'wpagent' ),
			'configMissing'            => __( 'Configuracao do WPAgent nao encontrada nesta pagina. Atualize a pagina e tente novamente.', 'wpagent' ),
			'configError'              => __( 'Erro de configuracao', 'wpagent' ),
			'replyReceived'            => __( 'Resposta recebida', 'wpagent' ),
			'replyError'               => __( 'Erro ao responder', 'wpagent' ),
			'copyMessage'              => __( 'Copiar resposta', 'wpagent' ),
			'copiedMessage'            => __( 'Copiado', 'wpagent' ),
			'copyError'                => __( 'Nao foi possivel copiar', 'wpagent' ),
			'exportRtf'                => __( 'Baixar RTF', 'wpagent' ),
			'exportedRtf'              => __( 'RTF gerado', 'wpagent' ),
			'exportRtfError'           => __( 'Nao foi possivel gerar o RTF', 'wpagent' ),
			'darkMode'                 => __( 'Modo escuro', 'wpagent' ),
			'lightMode'                => __( 'Modo claro', 'wpagent' ),
			'abilityProposal'          => __( 'Acao proposta', 'wpagent' ),
			'runAbility'               => __( 'Executar acao', 'wpagent' ),
			'confirmAbility'           => __( 'Executar esta acao no WordPress?', 'wpagent' ),
			'runningAbility'           => __( 'Executando acao...', 'wpagent' ),
			'abilityExecuted'          => __( 'Acao executada', 'wpagent' ),
			'abilityError'             => __( 'Erro ao executar acao', 'wpagent' ),
			'emailProposal'            => __( 'Email preparado', 'wpagent' ),
			'sendEmail'                => __( 'Enviar email', 'wpagent' ),
			'confirmEmail'             => __( 'Enviar este email agora?', 'wpagent' ),
			'sendingEmail'             => __( 'Enviando email...', 'wpagent' ),
			'emailSent'                => __( 'Email enviado', 'wpagent' ),
			'emailQueued'              => __( 'Email agendado', 'wpagent' ),
			'emailError'               => __( 'Erro ao enviar email', 'wpagent' ),
			'emailSentTo'              => __( 'Email enviado para ', 'wpagent' ),
			'emailQueuedFor'           => __( 'Email agendado para ', 'wpagent' ),
			'loadingProfile'           => __( 'Carregando perfil...', 'wpagent' ),
			'profileLoaded'            => __( 'Perfil carregado', 'wpagent' ),
			'profileReady'             => __( 'Perfil opcional', 'wpagent' ),
			'profileLoadError'         => __( 'Erro ao carregar perfil', 'wpagent' ),
			'savingProfile'            => __( 'Salvando perfil...', 'wpagent' ),
			'profileSaved'             => __( 'Perfil salvo', 'wpagent' ),
			'profileSaveError'         => __( 'Erro ao salvar perfil', 'wpagent' ),
		);
	}

	private static function catalog() {
		static $catalog = null;

		if ( null !== $catalog ) {
			return $catalog;
		}

		$catalog = array(
			'WPAgent Settings' => array(
				'en' => 'WPAgent Settings',
				'pt' => 'Configurações do WPAgent',
				'es' => 'Ajustes de WPAgent',
			),
			'WPAgent Reports' => array(
				'en' => 'WPAgent Reports',
				'pt' => 'Relatórios do WPAgent',
				'es' => 'Informes de WPAgent',
			),
			'Settings' => array(
				'en' => 'Settings',
				'pt' => 'Configurações',
				'es' => 'Ajustes',
			),
			'Periodic Tasks' => array(
				'en' => 'Periodic Tasks',
				'pt' => 'Tarefas periódicas',
				'es' => 'Tareas periódicas',
			),
			'Reports' => array(
				'en' => 'Reports',
				'pt' => 'Relatórios',
				'es' => 'Informes',
			),
			'Agents' => array(
				'en' => 'Agents',
				'pt' => 'Agentes',
				'es' => 'Agentes',
			),
			'Agent' => array(
				'en' => 'Agent',
				'pt' => 'Agente',
				'es' => 'Agente',
			),
			'Adicionar agente' => array(
				'en' => 'Add agent',
				'pt' => 'Adicionar agente',
				'es' => 'Añadir agente',
			),
			'Editar agente' => array(
				'en' => 'Edit agent',
				'pt' => 'Editar agente',
				'es' => 'Editar agente',
			),
			'Configuracao do agente' => array(
				'en' => 'Agent configuration',
				'pt' => 'Configuração do agente',
				'es' => 'Configuración del agente',
			),
			'Arquivos de treinamento' => array(
				'en' => 'Training files',
				'pt' => 'Arquivos de treinamento',
				'es' => 'Archivos de entrenamiento',
			),
			'Configure a conexao global com IA e crie agentes independentes, cada um com shortcode e treinamento proprio.' => array(
				'en' => 'Configure the global AI connection and create independent agents, each with its own shortcode and training material.',
				'pt' => 'Configure a conexão global com IA e crie agentes independentes, cada um com shortcode e treinamento próprio.',
				'es' => 'Configura la conexión global con IA y crea agentes independientes, cada uno con su propio shortcode y material de entrenamiento.',
			),
			'Criar novo agente' => array(
				'en' => 'Create new agent',
				'pt' => 'Criar novo agente',
				'es' => 'Crear nuevo agente',
			),
			'Ver agentes' => array(
				'en' => 'View agents',
				'pt' => 'Ver agentes',
				'es' => 'Ver agentes',
			),
			'Plugin AI do WordPress' => array(
				'en' => 'WordPress AI plugin',
				'pt' => 'Plugin AI do WordPress',
				'es' => 'Plugin AI de WordPress',
			),
			'Como usar' => array(
				'en' => 'How to use',
				'pt' => 'Como usar',
				'es' => 'Cómo usar',
			),
			'Cada agente exibe seu shortcode na tela de edicao e recebe seus arquivos de treinamento na propria configuracao.' => array(
				'en' => 'Each agent shows its shortcode on the edit screen and receives training files in its own configuration.',
				'pt' => 'Cada agente exibe seu shortcode na tela de edição e recebe seus arquivos de treinamento na própria configuração.',
				'es' => 'Cada agente muestra su shortcode en la pantalla de edición y recibe archivos de entrenamiento en su propia configuración.',
			),
			'WordPress AI / Connectors' => array(
				'en' => 'WordPress AI / Connectors',
				'pt' => 'WordPress AI / Connectors',
				'es' => 'WordPress AI / Connectors',
			),
			'O caminho recomendado e configurar OpenAI, Gemini, Claude, OpenRouter ou outros provedores no plugin AI oficial do WordPress em Settings > Connectors. O WPAgent usa essa configuracao para chats e tarefas de IA quando disponivel.' => array(
				'en' => 'Recommended setup: configure OpenAI, Gemini, Claude, OpenRouter, or another provider in the official WordPress AI plugin under Settings > Connectors. WPAgent uses that configuration for chats and AI tasks when available.',
				'pt' => 'Caminho recomendado: configure OpenAI, Gemini, Claude, OpenRouter ou outro provedor no plugin AI oficial do WordPress em Configurações > Connectors. O WPAgent usa essa configuração em chats e tarefas de IA quando disponível.',
				'es' => 'Configuración recomendada: configura OpenAI, Gemini, Claude, OpenRouter u otro proveedor en el plugin oficial WordPress AI, en Ajustes > Connectors. WPAgent usa esa configuración para chats y tareas de IA cuando está disponible.',
			),
			'Status:' => array(
				'en' => 'Status:',
				'pt' => 'Status:',
				'es' => 'Estado:',
			),
			'WordPress AI Client detectado.' => array(
				'en' => 'WordPress AI Client detected.',
				'pt' => 'WordPress AI Client detectado.',
				'es' => 'WordPress AI Client detectado.',
			),
			'WordPress AI Client ainda nao foi detectado. Instale/ative o plugin AI oficial ou use o OpenRouter direto abaixo.' => array(
				'en' => 'WordPress AI Client has not been detected yet. Install/activate the official AI plugin or use direct OpenRouter below.',
				'pt' => 'WordPress AI Client ainda não foi detectado. Instale/ative o plugin AI oficial ou use o OpenRouter direto abaixo.',
				'es' => 'WordPress AI Client aún no fue detectado. Instala/activa el plugin AI oficial o usa OpenRouter directo abajo.',
			),
			'Provedor padrao' => array(
				'en' => 'Default provider',
				'pt' => 'Provedor padrão',
				'es' => 'Proveedor predeterminado',
			),
			'Usar WordPress AI / Connectors como provedor padrao para novos agentes.' => array(
				'en' => 'Use WordPress AI / Connectors as the default provider for new agents.',
				'pt' => 'Usar WordPress AI / Connectors como provedor padrão para novos agentes.',
				'es' => 'Usar WordPress AI / Connectors como proveedor predeterminado para nuevos agentes.',
			),
			'Com esta opcao ativa, novos agentes herdam os fornecedores, chaves e preferencias configuradas no plugin AI oficial. Agentes existentes podem ser ajustados individualmente na tela do agente.' => array(
				'en' => 'When enabled, new agents inherit the providers, keys, and preferences configured in the official AI plugin. Existing agents can be adjusted individually on the agent screen.',
				'pt' => 'Com esta opção ativa, novos agentes herdam os fornecedores, chaves e preferências configuradas no plugin AI oficial. Agentes existentes podem ser ajustados individualmente na tela do agente.',
				'es' => 'Con esta opción activa, los nuevos agentes heredan los proveedores, claves y preferencias configuradas en el plugin AI oficial. Los agentes existentes pueden ajustarse individualmente en la pantalla del agente.',
			),
			'OpenRouter API key opcional' => array(
				'en' => 'Optional OpenRouter API key',
				'pt' => 'Chave de API OpenRouter opcional',
				'es' => 'Clave API de OpenRouter opcional',
			),
			'Chave salva. Preencha apenas para trocar.' => array(
				'en' => 'Key saved. Fill in only to replace it.',
				'pt' => 'Chave salva. Preencha apenas para trocar.',
				'es' => 'Clave guardada. Completa este campo solo para cambiarla.',
			),
			'Preencha somente se quiser usar OpenRouter direto pelo WPAgent ou manter um fallback proprio. Se houver um conector OpenRouter no WordPress AI, o WPAgent tambem tenta espelhar esta chave para ele.' => array(
				'en' => 'Fill this in only if you want to use direct OpenRouter through WPAgent or keep your own fallback. If an OpenRouter connector exists in WordPress AI, WPAgent also tries to mirror this key to it.',
				'pt' => 'Preencha somente se quiser usar OpenRouter direto pelo WPAgent ou manter um fallback próprio. Se houver um conector OpenRouter no WordPress AI, o WPAgent também tenta espelhar esta chave para ele.',
				'es' => 'Completa este campo solo si quieres usar OpenRouter directo mediante WPAgent o mantener un fallback propio. Si existe un conector OpenRouter en WordPress AI, WPAgent también intenta copiar esta clave allí.',
			),
			'Modelo OpenRouter direto' => array(
				'en' => 'Direct OpenRouter model',
				'pt' => 'Modelo OpenRouter direto',
				'es' => 'Modelo OpenRouter directo',
			),
			'Valor usado apenas pelo modo OpenRouter direto do WPAgent ou como fallback quando o WordPress AI falhar e houver uma chave OpenRouter salva.' => array(
				'en' => 'Used only by WPAgent direct OpenRouter mode, or as a fallback when WordPress AI fails and an OpenRouter key is saved.',
				'pt' => 'Usado apenas pelo modo OpenRouter direto do WPAgent ou como fallback quando o WordPress AI falhar e houver uma chave OpenRouter salva.',
				'es' => 'Se usa solo en el modo OpenRouter directo de WPAgent, o como fallback cuando WordPress AI falla y hay una clave OpenRouter guardada.',
			),
			'Padrao: visitantes anonimos' => array(
				'en' => 'Default: anonymous visitors',
				'pt' => 'Padrão: visitantes anônimos',
				'es' => 'Predeterminado: visitantes anónimos',
			),
			'Permitir chat sem login para novos agentes.' => array(
				'en' => 'Allow chat without login for new agents.',
				'pt' => 'Permitir chat sem login para novos agentes.',
				'es' => 'Permitir chat sin iniciar sesión para nuevos agentes.',
			),
			'Instrucao base padrao' => array(
				'en' => 'Default base instruction',
				'pt' => 'Instrução base padrão',
				'es' => 'Instrucción base predeterminada',
			),
			'Limites padrao' => array(
				'en' => 'Default limits',
				'pt' => 'Limites padrão',
				'es' => 'Límites predeterminados',
			),
			'Treinamentos' => array(
				'en' => 'Training',
				'pt' => 'Treinamentos',
				'es' => 'Entrenamientos',
			),
			'Memorias' => array(
				'en' => 'Memories',
				'pt' => 'Memórias',
				'es' => 'Memorias',
			),
			'Interacoes recentes' => array(
				'en' => 'Recent interactions',
				'pt' => 'Interações recentes',
				'es' => 'Interacciones recientes',
			),
			'Ativar busca semantica por embeddings para documentos indexados.' => array(
				'en' => 'Enable semantic embedding search for indexed documents.',
				'pt' => 'Ativar busca semântica por embeddings para documentos indexados.',
				'es' => 'Activar búsqueda semántica por embeddings para documentos indexados.',
			),
			'Nesta primeira versao, o provider suportado e openrouter via /api/v1/embeddings. Os embeddings sao gerados em segundo plano pelo WP-Cron.' => array(
				'en' => 'In this first version, the supported embedding provider is OpenRouter via /api/v1/embeddings. Embeddings are generated in the background by WP-Cron.',
				'pt' => 'Nesta primeira versão, o provider suportado é OpenRouter via /api/v1/embeddings. Os embeddings são gerados em segundo plano pelo WP-Cron.',
				'es' => 'En esta primera versión, el proveedor de embeddings compatible es OpenRouter vía /api/v1/embeddings. Los embeddings se generan en segundo plano mediante WP-Cron.',
			),
			'Ultimo processamento:' => array(
				'en' => 'Last processing:',
				'pt' => 'Último processamento:',
				'es' => 'Último procesamiento:',
			),
			'Proximo lote automatico: %s.' => array(
				'en' => 'Next automatic batch: %s.',
				'pt' => 'Próximo lote automático: %s.',
				'es' => 'Próximo lote automático: %s.',
			),
			'Nao ha lote automatico agendado no momento. Salve as configuracoes com embeddings ativos para agendar.' => array(
				'en' => 'There is no automatic batch scheduled right now. Save settings with embeddings enabled to schedule one.',
				'pt' => 'Não há lote automático agendado no momento. Salve as configurações com embeddings ativos para agendar.',
				'es' => 'No hay ningún lote automático programado por ahora. Guarda los ajustes con embeddings activos para programarlo.',
			),
			'Processar embeddings agora' => array(
				'en' => 'Process embeddings now',
				'pt' => 'Processar embeddings agora',
				'es' => 'Procesar embeddings ahora',
			),
			'Tarefas periodicas' => array(
				'en' => 'Periodic tasks',
				'pt' => 'Tarefas periódicas',
				'es' => 'Tareas periódicas',
			),
			'Ativar cuidador periodico do site.' => array(
				'en' => 'Enable the periodic site caretaker.',
				'pt' => 'Ativar cuidador periódico do site.',
				'es' => 'Activar el cuidador periódico del sitio.',
			),
			'As tarefas usam WP-Cron. Em hospedagens comuns, elas rodam quando o site recebe visitas ou quando ha cron real configurado no servidor.' => array(
				'en' => 'Tasks use WP-Cron. On common shared hosting, they run when the site receives visits or when a real server cron is configured.',
				'pt' => 'As tarefas usam WP-Cron. Em hospedagens comuns, elas rodam quando o site recebe visitas ou quando há cron real configurado no servidor.',
				'es' => 'Las tareas usan WP-Cron. En hospedajes compartidos comunes, se ejecutan cuando el sitio recibe visitas o cuando hay un cron real configurado en el servidor.',
			),
			'Proxima verificacao automatica: %s.' => array(
				'en' => 'Next automatic check: %s.',
				'pt' => 'Próxima verificação automática: %s.',
				'es' => 'Próxima verificación automática: %s.',
			),
			'Modo de execucao' => array(
				'en' => 'Execution mode',
				'pt' => 'Modo de execução',
				'es' => 'Modo de ejecución',
			),
			'Apenas relatorios e recomendacoes' => array(
				'en' => 'Reports and recommendations only',
				'pt' => 'Apenas relatórios e recomendações',
				'es' => 'Solo informes y recomendaciones',
			),
			'Permitir criacao de rascunhos revisaveis' => array(
				'en' => 'Allow creation of reviewable drafts',
				'pt' => 'Permitir criação de rascunhos revisáveis',
				'es' => 'Permitir creación de borradores revisables',
			),
			'Atualizacao de plugins, publicacao de posts e moderacao de comentarios continuam exigindo acao humana nesta versao.' => array(
				'en' => 'Plugin updates, post publishing, and comment moderation still require human action in this version.',
				'pt' => 'Atualização de plugins, publicação de posts e moderação de comentários continuam exigindo ação humana nesta versão.',
				'es' => 'La actualización de plugins, publicación de entradas y moderación de comentarios siguen requiriendo acción humana en esta versión.',
			),
			'Funcoes habilitadas' => array(
				'en' => 'Enabled functions',
				'pt' => 'Funções habilitadas',
				'es' => 'Funciones habilitadas',
			),
			'Ativar' => array(
				'en' => 'Enable',
				'pt' => 'Ativar',
				'es' => 'Activar',
			),
			'Funcao' => array(
				'en' => 'Function',
				'pt' => 'Função',
				'es' => 'Función',
			),
			'Periodicidade' => array(
				'en' => 'Frequency',
				'pt' => 'Periodicidade',
				'es' => 'Periodicidad',
			),
			'Instrucao' => array(
				'en' => 'Instruction',
				'pt' => 'Instrução',
				'es' => 'Instrucción',
			),
			'Ultimo resultado' => array(
				'en' => 'Last result',
				'pt' => 'Último resultado',
				'es' => 'Último resultado',
			),
			'Executar' => array(
				'en' => 'Run',
				'pt' => 'Executar',
				'es' => 'Ejecutar',
			),
			'Ainda nao executada.' => array(
				'en' => 'Not run yet.',
				'pt' => 'Ainda não executada.',
				'es' => 'Aún no ejecutada.',
			),
			'Executar agora' => array(
				'en' => 'Run now',
				'pt' => 'Executar agora',
				'es' => 'Ejecutar ahora',
			),
			'Ver detalhes' => array(
				'en' => 'View details',
				'pt' => 'Ver detalhes',
				'es' => 'Ver detalles',
			),
			'A cada hora' => array(
				'en' => 'Hourly',
				'pt' => 'A cada hora',
				'es' => 'Cada hora',
			),
			'Duas vezes por dia' => array(
				'en' => 'Twice daily',
				'pt' => 'Duas vezes por dia',
				'es' => 'Dos veces al día',
			),
			'Diariamente' => array(
				'en' => 'Daily',
				'pt' => 'Diariamente',
				'es' => 'Diariamente',
			),
			'Semanalmente' => array(
				'en' => 'Weekly',
				'pt' => 'Semanalmente',
				'es' => 'Semanalmente',
			),
			'Every five minutes' => array(
				'en' => 'Every five minutes',
				'pt' => 'A cada cinco minutos',
				'es' => 'Cada cinco minutos',
			),
			'Weekly' => array(
				'en' => 'Weekly',
				'pt' => 'Semanalmente',
				'es' => 'Semanalmente',
			),
			'Consumo por agente' => array(
				'en' => 'Usage by agent',
				'pt' => 'Consumo por agente',
				'es' => 'Consumo por agente',
			),
			'Acompanhe o consumo de tokens registrado por agente e por usuario.' => array(
				'en' => 'Track recorded token usage by agent and user.',
				'pt' => 'Acompanhe o consumo de tokens registrado por agente e por usuário.',
				'es' => 'Acompaña el consumo de tokens registrado por agente y usuario.',
			),
			'Periodos atuais: dia desde %1$s, semana desde %2$s, mes desde %3$s.' => array(
				'en' => 'Current periods: day since %1$s, week since %2$s, month since %3$s.',
				'pt' => 'Períodos atuais: dia desde %1$s, semana desde %2$s, mês desde %3$s.',
				'es' => 'Periodos actuales: día desde %1$s, semana desde %2$s, mes desde %3$s.',
			),
			'Hoje' => array(
				'en' => 'Today',
				'pt' => 'Hoje',
				'es' => 'Hoy',
			),
			'Semana' => array(
				'en' => 'Week',
				'pt' => 'Semana',
				'es' => 'Semana',
			),
			'Mes' => array(
				'en' => 'Month',
				'pt' => 'Mês',
				'es' => 'Mes',
			),
			'Entrada' => array(
				'en' => 'Input',
				'pt' => 'Entrada',
				'es' => 'Entrada',
			),
			'Saida' => array(
				'en' => 'Output',
				'pt' => 'Saída',
				'es' => 'Salida',
			),
			'Total' => array(
				'en' => 'Total',
				'pt' => 'Total',
				'es' => 'Total',
			),
			'Limites' => array(
				'en' => 'Limits',
				'pt' => 'Limites',
				'es' => 'Límites',
			),
			'Nenhum uso registrado ainda.' => array(
				'en' => 'No usage recorded yet.',
				'pt' => 'Nenhum uso registrado ainda.',
				'es' => 'Aún no hay consumo registrado.',
			),
			'Leads por email' => array(
				'en' => 'Email leads',
				'pt' => 'Leads por email',
				'es' => 'Leads por email',
			),
			'Acompanhe os emails coletados com consentimento durante conversas em que o agente preparou e enviou mensagens autorizadas pelo usuario.' => array(
				'en' => 'Track emails collected with consent during conversations where the agent prepared and sent user-authorized messages.',
				'pt' => 'Acompanhe os emails coletados com consentimento durante conversas em que o agente preparou e enviou mensagens autorizadas pelo usuario.',
				'es' => 'Acompana los emails recopilados con consentimiento durante conversaciones en que el agente preparo y envio mensajes autorizados por el usuario.',
			),
			'Leads' => array(
				'en' => 'Leads',
				'pt' => 'Leads',
				'es' => 'Leads',
			),
			'Emails enviados' => array(
				'en' => 'Sent emails',
				'pt' => 'Emails enviados',
				'es' => 'Emails enviados',
			),
			'Falhas' => array(
				'en' => 'Failures',
				'pt' => 'Falhas',
				'es' => 'Fallos',
			),
			'Total de eventos' => array(
				'en' => 'Total events',
				'pt' => 'Total de eventos',
				'es' => 'Total de eventos',
			),
			'Ultimo email' => array(
				'en' => 'Last email',
				'pt' => 'Ultimo email',
				'es' => 'Ultimo email',
			),
			'Nenhum lead por email registrado ainda.' => array(
				'en' => 'No email leads recorded yet.',
				'pt' => 'Nenhum lead por email registrado ainda.',
				'es' => 'Aun no hay leads por email registrados.',
			),
			'Leads recentes' => array(
				'en' => 'Recent leads',
				'pt' => 'Leads recentes',
				'es' => 'Leads recientes',
			),
			'Email' => array(
				'en' => 'Email',
				'pt' => 'Email',
				'es' => 'Email',
			),
			'Nome' => array(
				'en' => 'Name',
				'pt' => 'Nome',
				'es' => 'Nombre',
			),
			'Ultimo assunto' => array(
				'en' => 'Last subject',
				'pt' => 'Ultimo assunto',
				'es' => 'Ultimo asunto',
			),
			'Finalidade' => array(
				'en' => 'Purpose',
				'pt' => 'Finalidade',
				'es' => 'Finalidad',
			),
			'Status recente' => array(
				'en' => 'Recent status',
				'pt' => 'Status recente',
				'es' => 'Estado reciente',
			),
			'Envios' => array(
				'en' => 'Sends',
				'pt' => 'Envios',
				'es' => 'Envios',
			),
			'Primeiro registro' => array(
				'en' => 'First record',
				'pt' => 'Primeiro registro',
				'es' => 'Primer registro',
			),
			'%1$s enviados, %2$s falhas, %3$s total' => array(
				'en' => '%1$s sent, %2$s failed, %3$s total',
				'pt' => '%1$s enviados, %2$s falhas, %3$s total',
				'es' => '%1$s enviados, %2$s fallos, %3$s total',
			),
			'enviado' => array(
				'en' => 'sent',
				'pt' => 'enviado',
				'es' => 'enviado',
			),
			'falhou' => array(
				'en' => 'failed',
				'pt' => 'falhou',
				'es' => 'fallo',
			),
			'agendado' => array(
				'en' => 'queued',
				'pt' => 'agendado',
				'es' => 'programado',
			),
			'pendente' => array(
				'en' => 'pending',
				'pt' => 'pendente',
				'es' => 'pendiente',
			),
			'Maiores consumos por usuario' => array(
				'en' => 'Top usage by user',
				'pt' => 'Maiores consumos por usuário',
				'es' => 'Mayor consumo por usuario',
			),
			'Usuario' => array(
				'en' => 'User',
				'pt' => 'Usuário',
				'es' => 'Usuario',
			),
			'Interacoes' => array(
				'en' => 'Interactions',
				'pt' => 'Interações',
				'es' => 'Interacciones',
			),
			'Ultimo uso' => array(
				'en' => 'Last usage',
				'pt' => 'Último uso',
				'es' => 'Último uso',
			),
			'Visitante' => array(
				'en' => 'Visitor',
				'pt' => 'Visitante',
				'es' => 'Visitante',
			),
			'Usuario #%d' => array(
				'en' => 'User #%d',
				'pt' => 'Usuário #%d',
				'es' => 'Usuario #%d',
			),
			'ilimitado' => array(
				'en' => 'unlimited',
				'pt' => 'ilimitado',
				'es' => 'ilimitado',
			),
			'Dia: %1$s / Semana: %2$s / Mes: %3$s' => array(
				'en' => 'Day: %1$s / Week: %2$s / Month: %3$s',
				'pt' => 'Dia: %1$s / Semana: %2$s / Mês: %3$s',
				'es' => 'Día: %1$s / Semana: %2$s / Mes: %3$s',
			),
			'Identificador' => array(
				'en' => 'Identifier',
				'pt' => 'Identificador',
				'es' => 'Identificador',
			),
			'Use apenas letras, numeros e hifens. Ele define o shortcode e separa memoria/treinamento.' => array(
				'en' => 'Use only letters, numbers, and hyphens. It defines the shortcode and separates memory/training data.',
				'pt' => 'Use apenas letras, números e hifens. Ele define o shortcode e separa memória/treinamento.',
				'es' => 'Usa solo letras, números y guiones. Define el shortcode y separa memoria/datos de entrenamiento.',
			),
			'Fornecedor de IA' => array(
				'en' => 'AI provider',
				'pt' => 'Fornecedor de IA',
				'es' => 'Proveedor de IA',
			),
			'WordPress AI / Connectors (recomendado)' => array(
				'en' => 'WordPress AI / Connectors (recommended)',
				'pt' => 'WordPress AI / Connectors (recomendado)',
				'es' => 'WordPress AI / Connectors (recomendado)',
			),
			'OpenRouter direto pelo WPAgent' => array(
				'en' => 'Direct OpenRouter through WPAgent',
				'pt' => 'OpenRouter direto pelo WPAgent',
				'es' => 'OpenRouter directo mediante WPAgent',
			),
			'Use WordPress AI para aproveitar OpenAI, Gemini, Claude, OpenRouter ou outros conectores configurados no plugin oficial de IA do WordPress. O OpenRouter direto permanece disponivel como fallback ou opcao avancada.' => array(
				'en' => 'Use WordPress AI to leverage OpenAI, Gemini, Claude, OpenRouter, or other connectors configured in the official WordPress AI plugin. Direct OpenRouter remains available as a fallback or advanced option.',
				'pt' => 'Use WordPress AI para aproveitar OpenAI, Gemini, Claude, OpenRouter ou outros conectores configurados no plugin oficial de IA do WordPress. O OpenRouter direto permanece disponível como fallback ou opção avançada.',
				'es' => 'Usa WordPress AI para aprovechar OpenAI, Gemini, Claude, OpenRouter u otros conectores configurados en el plugin oficial de IA de WordPress. OpenRouter directo sigue disponible como fallback u opción avanzada.',
			),
			'Usado somente quando este agente estiver em OpenRouter direto ou quando o WordPress AI falhar e houver uma chave OpenRouter no WPAgent.' => array(
				'en' => 'Used only when this agent is set to direct OpenRouter, or when WordPress AI fails and WPAgent has an OpenRouter key.',
				'pt' => 'Usado somente quando este agente estiver em OpenRouter direto ou quando o WordPress AI falhar e houver uma chave OpenRouter no WPAgent.',
				'es' => 'Se usa solo cuando este agente está en OpenRouter directo, o cuando WordPress AI falla y WPAgent tiene una clave OpenRouter.',
			),
			'Provider/model do WordPress AI' => array(
				'en' => 'WordPress AI provider/model',
				'pt' => 'Provider/model do WordPress AI',
				'es' => 'Proveedor/modelo de WordPress AI',
			),
			'Provider' => array(
				'en' => 'Provider',
				'pt' => 'Provider',
				'es' => 'Proveedor',
			),
			'Model' => array(
				'en' => 'Model',
				'pt' => 'Modelo',
				'es' => 'Modelo',
			),
			'Modelo' => array(
				'en' => 'Model',
				'pt' => 'Modelo',
				'es' => 'Modelo',
			),
			'Deixe em branco para usar as preferencias globais do plugin AI/Connectors. Preencha apenas quando quiser prender este agente a um fornecedor e modelo especificos configurados no WordPress AI.' => array(
				'en' => 'Leave blank to use the global AI/Connectors plugin preferences. Fill in only when you want to pin this agent to a specific provider and model configured in WordPress AI.',
				'pt' => 'Deixe em branco para usar as preferências globais do plugin AI/Connectors. Preencha apenas quando quiser prender este agente a um fornecedor e modelo específicos configurados no WordPress AI.',
				'es' => 'Déjalo en blanco para usar las preferencias globales del plugin AI/Connectors. Complétalo solo cuando quieras fijar este agente a un proveedor y modelo específicos configurados en WordPress AI.',
			),
			'Visitantes anonimos' => array(
				'en' => 'Anonymous visitors',
				'pt' => 'Visitantes anônimos',
				'es' => 'Visitantes anónimos',
			),
			'Permitir chat sem login para este agente.' => array(
				'en' => 'Allow chat without login for this agent.',
				'pt' => 'Permitir chat sem login para este agente.',
				'es' => 'Permitir chat sin iniciar sesión para este agente.',
			),
			'Assistente publico do site' => array(
				'en' => 'Public site assistant',
				'pt' => 'Assistente público do site',
				'es' => 'Asistente público del sitio',
			),
			'Exibir este agente automaticamente como pop-up no front-end para visitantes.' => array(
				'en' => 'Automatically show this agent as a front-end pop-up for visitors.',
				'pt' => 'Exibir este agente automaticamente como pop-up no front-end para visitantes.',
				'es' => 'Mostrar este agente automáticamente como pop-up en el front-end para visitantes.',
			),
			'Use para atendimento publico do site. Ao ativar, o chat sem login tambem fica habilitado para este agente.' => array(
				'en' => 'Use this for public site support. When enabled, guest chat is also enabled for this agent.',
				'pt' => 'Use para atendimento público do site. Ao ativar, o chat sem login também fica habilitado para este agente.',
				'es' => 'Úsalo para soporte público del sitio. Al activarlo, también se habilita el chat sin inicio de sesión para este agente.',
			),
			'Assistente interno do admin' => array(
				'en' => 'Internal admin assistant',
				'pt' => 'Assistente interno do admin',
				'es' => 'Asistente interno del admin',
			),
			'Exibir este agente automaticamente como pop-up no painel para administradores.' => array(
				'en' => 'Automatically show this agent as a dashboard pop-up for administrators.',
				'pt' => 'Exibir este agente automaticamente como pop-up no painel para administradores.',
				'es' => 'Mostrar este agente automáticamente como pop-up en el panel para administradores.',
			),
			'Use para apoio operacional no WordPress. Nesta versao ele conversa no painel; a execucao de acoes administrativas deve ser habilitada por ferramentas com permissao propria.' => array(
				'en' => 'Use this for operational WordPress support. In this version it chats in the dashboard; administrative actions must be enabled through tools with their own permission layer.',
				'pt' => 'Use para apoio operacional no WordPress. Nesta versão ele conversa no painel; a execução de ações administrativas deve ser habilitada por ferramentas com permissão própria.',
				'es' => 'Úsalo para soporte operativo en WordPress. En esta versión conversa en el panel; las acciones administrativas deben habilitarse mediante herramientas con su propia capa de permisos.',
			),
			'Instrucao base' => array(
				'en' => 'Base instruction',
				'pt' => 'Instrução base',
				'es' => 'Instrucción base',
			),
			'Define o papel, tom, limites e regras fixas do agente. Links externos aqui sao tratados como texto; para usar o conteudo, importe ou cole o material em Treinamentos.' => array(
				'en' => 'Defines the agent role, tone, limits, and fixed rules. External links here are treated as text; to use their content, import or paste the material under Training.',
				'pt' => 'Define o papel, tom, limites e regras fixas do agente. Links externos aqui são tratados como texto; para usar o conteúdo, importe ou cole o material em Treinamentos.',
				'es' => 'Define el rol, tono, límites y reglas fijas del agente. Los enlaces externos aquí se tratan como texto; para usar su contenido, importa o pega el material en Entrenamientos.',
			),
			'Limites de contexto' => array(
				'en' => 'Context limits',
				'pt' => 'Limites de contexto',
				'es' => 'Límites de contexto',
			),
			'Interacoes' => array(
				'en' => 'Interactions',
				'pt' => 'Interações',
				'es' => 'Interacciones',
			),
			'Treinamentos: quantidade maxima de trechos da base do agente enviados ao modelo. Memorias: registros persistentes sobre o usuario. Interacoes: pares recentes de pergunta/resposta da conversa atual.' => array(
				'en' => 'Training: maximum number of agent knowledge snippets sent to the model. Memories: persistent records about the user. Interactions: recent question/answer pairs from the current conversation.',
				'pt' => 'Treinamentos: quantidade máxima de trechos da base do agente enviados ao modelo. Memórias: registros persistentes sobre o usuário. Interações: pares recentes de pergunta/resposta da conversa atual.',
				'es' => 'Entrenamientos: cantidad máxima de fragmentos de conocimiento del agente enviados al modelo. Memorias: registros persistentes sobre el usuario. Interacciones: pares recientes de pregunta/respuesta de la conversación actual.',
			),
			'Valores maiores dao mais contexto, mas aumentam custo, latencia e risco de ultrapassar o limite do modelo.' => array(
				'en' => 'Higher values add more context, but increase cost, latency, and the risk of exceeding the model limit.',
				'pt' => 'Valores maiores dão mais contexto, mas aumentam custo, latência e risco de ultrapassar o limite do modelo.',
				'es' => 'Valores mayores añaden más contexto, pero aumentan costo, latencia y riesgo de superar el límite del modelo.',
			),
			'Limites de tokens' => array(
				'en' => 'Token limits',
				'pt' => 'Limites de tokens',
				'es' => 'Límites de tokens',
			),
			'Por dia' => array(
				'en' => 'Per day',
				'pt' => 'Por dia',
				'es' => 'Por día',
			),
			'Por semana' => array(
				'en' => 'Per week',
				'pt' => 'Por semana',
				'es' => 'Por semana',
			),
			'Por mes' => array(
				'en' => 'Per month',
				'pt' => 'Por mês',
				'es' => 'Por mes',
			),
			'Controla o total de tokens consumidos por este agente em todos os usuarios. Use 0 para ilimitado. O bloqueio acontece quando o consumo registrado do periodo atinge o limite.' => array(
				'en' => 'Controls the total tokens consumed by this agent across all users. Use 0 for unlimited. Blocking happens when recorded usage for the period reaches the limit.',
				'pt' => 'Controla o total de tokens consumidos por este agente em todos os usuários. Use 0 para ilimitado. O bloqueio acontece quando o consumo registrado do período atinge o limite.',
				'es' => 'Controla el total de tokens consumidos por este agente entre todos los usuarios. Usa 0 para ilimitado. El bloqueo ocurre cuando el consumo registrado del período alcanza el límite.',
			),
			'A contagem depende do fornecedor retornar uso de tokens. OpenRouter normalmente retorna; alguns conectores do WordPress AI podem retornar 0.' => array(
				'en' => 'Counting depends on the provider returning token usage. OpenRouter usually does; some WordPress AI connectors may return 0.',
				'pt' => 'A contagem depende do fornecedor retornar uso de tokens. OpenRouter normalmente retorna; alguns conectores do WordPress AI podem retornar 0.',
				'es' => 'El conteo depende de que el proveedor devuelva el uso de tokens. OpenRouter normalmente lo hace; algunos conectores de WordPress AI pueden devolver 0.',
			),
			'Cole este shortcode em qualquer pagina ou post.' => array(
				'en' => 'Paste this shortcode into any page or post.',
				'pt' => 'Cole este shortcode em qualquer página ou post.',
				'es' => 'Pega este shortcode en cualquier página o entrada.',
			),
			'Envie documentos para compor a base pesquisavel deste agente. O WPAgent quebra textos longos em trechos e usa somente os trechos relevantes em cada resposta.' => array(
				'en' => 'Upload documents to build this agent searchable knowledge base. WPAgent splits long texts into chunks and uses only the relevant snippets in each response.',
				'pt' => 'Envie documentos para compor a base pesquisável deste agente. O WPAgent quebra textos longos em trechos e usa somente os trechos relevantes em cada resposta.',
				'es' => 'Sube documentos para crear la base consultable de este agente. WPAgent divide textos largos en fragmentos y usa solo los fragmentos relevantes en cada respuesta.',
			),
			'TXT, Markdown, CSV, JSON, HTML e DOCX sao extraidos automaticamente. PDF e tentado apenas quando houver texto legivel; se a extracao sair corrompida, ele nao sera usado no chat.' => array(
				'en' => 'TXT, Markdown, CSV, JSON, HTML, and DOCX are extracted automatically. PDF is attempted only when readable text exists; if extraction looks corrupted, it will not be used in chat.',
				'pt' => 'TXT, Markdown, CSV, JSON, HTML e DOCX são extraídos automaticamente. PDF é tentado apenas quando houver texto legível; se a extração sair corrompida, ele não será usado no chat.',
				'es' => 'TXT, Markdown, CSV, JSON, HTML y DOCX se extraen automáticamente. PDF se intenta solo cuando hay texto legible; si la extracción parece dañada, no se usará en el chat.',
			),
			'Texto manual de treinamento' => array(
				'en' => 'Manual training text',
				'pt' => 'Texto manual de treinamento',
				'es' => 'Texto manual de entrenamiento',
			),
			'Use este campo para colar uma versao limpa em TXT/Markdown de PDFs grandes quando a hospedagem nao tiver extrator confiavel. Ao salvar, o texto sera dividido em trechos pesquisaveis.' => array(
				'en' => 'Use this field to paste a clean TXT/Markdown version of large PDFs when the host does not have a reliable extractor. When saved, the text is split into searchable chunks.',
				'pt' => 'Use este campo para colar uma versão limpa em TXT/Markdown de PDFs grandes quando a hospedagem não tiver extrator confiável. Ao salvar, o texto será dividido em trechos pesquisáveis.',
				'es' => 'Usa este campo para pegar una versión limpia en TXT/Markdown de PDFs grandes cuando el hosting no tenga un extractor confiable. Al guardar, el texto se dividirá en fragmentos consultables.',
			),
			'Titulo da fonte, ex.: BNCC em Markdown' => array(
				'en' => 'Source title, e.g. BNCC in Markdown',
				'pt' => 'Título da fonte, ex.: BNCC em Markdown',
				'es' => 'Título de la fuente, ej.: BNCC en Markdown',
			),
			'Cole aqui o texto limpo ou Markdown...' => array(
				'en' => 'Paste clean text or Markdown here...',
				'pt' => 'Cole aqui o texto limpo ou Markdown...',
				'es' => 'Pega aquí texto limpio o Markdown...',
			),
			'Documento' => array(
				'en' => 'Document',
				'pt' => 'Documento',
				'es' => 'Documento',
			),
			'Indice' => array(
				'en' => 'Index',
				'pt' => 'Índice',
				'es' => 'Índice',
			),
			'Reindexar' => array(
				'en' => 'Reindex',
				'pt' => 'Reindexar',
				'es' => 'Reindexar',
			),
			'Remover' => array(
				'en' => 'Remove',
				'pt' => 'Remover',
				'es' => 'Eliminar',
			),
			'%1$d trechos, %2$d caracteres' => array(
				'en' => '%1$d chunks, %2$d characters',
				'pt' => '%1$d trechos, %2$d caracteres',
				'es' => '%1$d fragmentos, %2$d caracteres',
			),
			'%1$d/%2$d prontos' => array(
				'en' => '%1$d/%2$d ready',
				'pt' => '%1$d/%2$d prontos',
				'es' => '%1$d/%2$d listos',
			),
			'Nenhum documento indexado ainda.' => array(
				'en' => 'No indexed documents yet.',
				'pt' => 'Nenhum documento indexado ainda.',
				'es' => 'Aún no hay documentos indexados.',
			),
			'Conversas' => array(
				'en' => 'Conversations',
				'pt' => 'Conversas',
				'es' => 'Conversaciones',
			),
			'Nova' => array(
				'en' => 'New',
				'pt' => 'Nova',
				'es' => 'Nueva',
			),
			'Suas conversas' => array(
				'en' => 'Your conversations',
				'pt' => 'Suas conversas',
				'es' => 'Tus conversaciones',
			),
			'Nova conversa' => array(
				'en' => 'New conversation',
				'pt' => 'Nova conversa',
				'es' => 'Nueva conversación',
			),
			'Pronto para conversar' => array(
				'en' => 'Ready to chat',
				'pt' => 'Pronto para conversar',
				'es' => 'Listo para conversar',
			),
			'Renomear' => array(
				'en' => 'Rename',
				'pt' => 'Renomear',
				'es' => 'Renombrar',
			),
			'Como posso ajudar?' => array(
				'en' => 'How can I help?',
				'pt' => 'Como posso ajudar?',
				'es' => '¿Cómo puedo ayudar?',
			),
			'Escreva uma pergunta ou escolha uma conversa anterior.' => array(
				'en' => 'Ask a question or choose a previous conversation.',
				'pt' => 'Escreva uma pergunta ou escolha uma conversa anterior.',
				'es' => 'Escribe una pregunta o elige una conversación anterior.',
			),
			'Mensagem' => array(
				'en' => 'Message',
				'pt' => 'Mensagem',
				'es' => 'Mensaje',
			),
			'Escreva sua mensagem...' => array(
				'en' => 'Write your message...',
				'pt' => 'Escreva sua mensagem...',
				'es' => 'Escribe tu mensaje...',
			),
			'Enviar' => array(
				'en' => 'Send',
				'pt' => 'Enviar',
				'es' => 'Enviar',
			),
			'Enter envia. Shift + Enter quebra linha.' => array(
				'en' => 'Enter sends. Shift + Enter adds a new line.',
				'pt' => 'Enter envia. Shift + Enter quebra linha.',
				'es' => 'Enter envía. Shift + Enter agrega una línea.',
			),
			'Fechar' => array(
				'en' => 'Close',
				'pt' => 'Fechar',
				'es' => 'Cerrar',
			),
			'Fechar assistente' => array(
				'en' => 'Close assistant',
				'pt' => 'Fechar assistente',
				'es' => 'Cerrar asistente',
			),
			'Erro no WPAgent. Status ' => array(
				'en' => 'WPAgent error. Status ',
				'pt' => 'Erro no WPAgent. Status ',
				'es' => 'Error de WPAgent. Estado ',
			),
			'A resposta demorou demais. Verifique a chave, o modelo e o fornecedor de IA.' => array(
				'en' => 'The response took too long. Check the key, model, and AI provider.',
				'pt' => 'A resposta demorou demais. Verifique a chave, o modelo e o fornecedor de IA.',
				'es' => 'La respuesta tardó demasiado. Verifica la clave, el modelo y el proveedor de IA.',
			),
			'Entre na conta para salvar e retomar conversas.' => array(
				'en' => 'Sign in to save and resume conversations.',
				'pt' => 'Entre na conta para salvar e retomar conversas.',
				'es' => 'Inicia sesión para guardar y retomar conversaciones.',
			),
			'Nenhuma conversa salva ainda.' => array(
				'en' => 'No saved conversations yet.',
				'pt' => 'Nenhuma conversa salva ainda.',
				'es' => 'Aún no hay conversaciones guardadas.',
			),
			'Conversa' => array(
				'en' => 'Conversation',
				'pt' => 'Conversa',
				'es' => 'Conversación',
			),
			'Conversa salva' => array(
				'en' => 'Saved conversation',
				'pt' => 'Conversa salva',
				'es' => 'Conversación guardada',
			),
			'Abrir conversa ' => array(
				'en' => 'Open conversation ',
				'pt' => 'Abrir conversa ',
				'es' => 'Abrir conversación ',
			),
			'Apagar conversa ' => array(
				'en' => 'Delete conversation ',
				'pt' => 'Apagar conversa ',
				'es' => 'Eliminar conversación ',
			),
			'Carregando conversas...' => array(
				'en' => 'Loading conversations...',
				'pt' => 'Carregando conversas...',
				'es' => 'Cargando conversaciones...',
			),
			'Nao foi possivel carregar conversas' => array(
				'en' => 'Could not load conversations',
				'pt' => 'Não foi possível carregar conversas',
				'es' => 'No fue posible cargar las conversaciones',
			),
			'Carregando mensagens...' => array(
				'en' => 'Loading messages...',
				'pt' => 'Carregando mensagens...',
				'es' => 'Cargando mensajes...',
			),
			'Erro ao carregar conversa' => array(
				'en' => 'Error loading conversation',
				'pt' => 'Erro ao carregar conversa',
				'es' => 'Error al cargar la conversación',
			),
			'Nova conversa iniciada' => array(
				'en' => 'New conversation started',
				'pt' => 'Nova conversa iniciada',
				'es' => 'Nueva conversación iniciada',
			),
			'Erro ao criar conversa' => array(
				'en' => 'Error creating conversation',
				'pt' => 'Erro ao criar conversa',
				'es' => 'Error al crear la conversación',
			),
			'Entre na conta para renomear conversas' => array(
				'en' => 'Sign in to rename conversations',
				'pt' => 'Entre na conta para renomear conversas',
				'es' => 'Inicia sesión para renombrar conversaciones',
			),
			'Nome da conversa' => array(
				'en' => 'Conversation name',
				'pt' => 'Nome da conversa',
				'es' => 'Nombre de la conversación',
			),
			'Renomeando conversa...' => array(
				'en' => 'Renaming conversation...',
				'pt' => 'Renomeando conversa...',
				'es' => 'Renombrando conversación...',
			),
			'Conversa renomeada' => array(
				'en' => 'Conversation renamed',
				'pt' => 'Conversa renomeada',
				'es' => 'Conversación renombrada',
			),
			'Erro ao renomear conversa' => array(
				'en' => 'Error renaming conversation',
				'pt' => 'Erro ao renomear conversa',
				'es' => 'Error al renombrar la conversación',
			),
			'Apagar a conversa "' => array(
				'en' => 'Delete conversation "',
				'pt' => 'Apagar a conversa "',
				'es' => 'Eliminar la conversación "',
			),
			'"?' => array(
				'en' => '"?',
				'pt' => '"?',
				'es' => '"?',
			),
			'Apagando conversa...' => array(
				'en' => 'Deleting conversation...',
				'pt' => 'Apagando conversa...',
				'es' => 'Eliminando conversación...',
			),
			'Conversa apagada' => array(
				'en' => 'Conversation deleted',
				'pt' => 'Conversa apagada',
				'es' => 'Conversación eliminada',
			),
			'Erro ao apagar conversa' => array(
				'en' => 'Error deleting conversation',
				'pt' => 'Erro ao apagar conversa',
				'es' => 'Error al eliminar la conversación',
			),
			'Processando' => array(
				'en' => 'Processing',
				'pt' => 'Processando',
				'es' => 'Procesando',
			),
			'Processando resposta...' => array(
				'en' => 'Processing response...',
				'pt' => 'Processando resposta...',
				'es' => 'Procesando respuesta...',
			),
			'Configuracao do WPAgent nao encontrada nesta pagina. Atualize a pagina e tente novamente.' => array(
				'en' => 'WPAgent configuration was not found on this page. Refresh the page and try again.',
				'pt' => 'Configuração do WPAgent não encontrada nesta página. Atualize a página e tente novamente.',
				'es' => 'No se encontró la configuración de WPAgent en esta página. Actualiza la página e inténtalo de nuevo.',
			),
			'Erro de configuracao' => array(
				'en' => 'Configuration error',
				'pt' => 'Erro de configuração',
				'es' => 'Error de configuración',
			),
			'Resposta recebida' => array(
				'en' => 'Response received',
				'pt' => 'Resposta recebida',
				'es' => 'Respuesta recibida',
			),
			'Erro ao responder' => array(
				'en' => 'Error replying',
				'pt' => 'Erro ao responder',
				'es' => 'Error al responder',
			),
			'Copiar resposta' => array(
				'en' => 'Copy response',
				'pt' => 'Copiar resposta',
				'es' => 'Copiar respuesta',
			),
			'Copiado' => array(
				'en' => 'Copied',
				'pt' => 'Copiado',
				'es' => 'Copiado',
			),
			'Nao foi possivel copiar' => array(
				'en' => 'Could not copy',
				'pt' => 'Não foi possível copiar',
				'es' => 'No fue posible copiar',
			),
			'Baixar RTF' => array(
				'en' => 'Download RTF',
				'pt' => 'Baixar RTF',
				'es' => 'Descargar RTF',
			),
			'RTF gerado' => array(
				'en' => 'RTF generated',
				'pt' => 'RTF gerado',
				'es' => 'RTF generado',
			),
			'Nao foi possivel gerar o RTF' => array(
				'en' => 'Could not generate the RTF',
				'pt' => 'NÃ£o foi possÃ­vel gerar o RTF',
				'es' => 'No fue posible generar el RTF',
			),
			'Modo escuro' => array(
				'en' => 'Dark mode',
				'pt' => 'Modo escuro',
				'es' => 'Modo oscuro',
			),
			'Modo claro' => array(
				'en' => 'Light mode',
				'pt' => 'Modo claro',
				'es' => 'Modo claro',
			),
			'Acao proposta' => array(
				'en' => 'Proposed action',
				'pt' => 'Ação proposta',
				'es' => 'Acción propuesta',
			),
			'Executar acao' => array(
				'en' => 'Run action',
				'pt' => 'Executar ação',
				'es' => 'Ejecutar acción',
			),
			'Executar esta acao no WordPress?' => array(
				'en' => 'Run this action in WordPress?',
				'pt' => 'Executar esta ação no WordPress?',
				'es' => '¿Ejecutar esta acción en WordPress?',
			),
			'Executando acao...' => array(
				'en' => 'Running action...',
				'pt' => 'Executando ação...',
				'es' => 'Ejecutando acción...',
			),
			'Acao executada' => array(
				'en' => 'Action executed',
				'pt' => 'Ação executada',
				'es' => 'Acción ejecutada',
			),
			'Erro ao executar acao' => array(
				'en' => 'Error running action',
				'pt' => 'Erro ao executar ação',
				'es' => 'Error al ejecutar la acción',
			),
			'Email preparado' => array(
				'en' => 'Email prepared',
				'pt' => 'Email preparado',
				'es' => 'Email preparado',
			),
			'Enviar email' => array(
				'en' => 'Send email',
				'pt' => 'Enviar email',
				'es' => 'Enviar email',
			),
			'Enviar este email agora?' => array(
				'en' => 'Send this email now?',
				'pt' => 'Enviar este email agora?',
				'es' => 'Enviar este email agora?',
			),
			'Enviando email...' => array(
				'en' => 'Sending email...',
				'pt' => 'Enviando email...',
				'es' => 'Enviando email...',
			),
			'Email enviado' => array(
				'en' => 'Email sent',
				'pt' => 'Email enviado',
				'es' => 'Email enviado',
			),
			'Email agendado' => array(
				'en' => 'Email queued',
				'pt' => 'Email agendado',
				'es' => 'Email programado',
			),
			'Erro ao enviar email' => array(
				'en' => 'Error sending email',
				'pt' => 'Erro ao enviar email',
				'es' => 'Error al enviar email',
			),
			'Email enviado para ' => array(
				'en' => 'Email sent to ',
				'pt' => 'Email enviado para ',
				'es' => 'Email enviado a ',
			),
			'Email agendado para ' => array(
				'en' => 'Email queued for ',
				'pt' => 'Email agendado para ',
				'es' => 'Email programado para ',
			),
			'Preparei um email para sua revisao.' => array(
				'en' => 'I prepared an email for your review.',
				'pt' => 'Preparei um email para sua revisao.',
				'es' => 'Prepare un email para tu revision.',
			),
			'Informe um email valido para o envio.' => array(
				'en' => 'Enter a valid email address for sending.',
				'pt' => 'Informe um email valido para o envio.',
				'es' => 'Ingresa un email valido para el envio.',
			),
			'O email precisa de assunto e conteudo antes do envio.' => array(
				'en' => 'The email needs a subject and content before sending.',
				'pt' => 'O email precisa de assunto e conteudo antes do envio.',
				'es' => 'El email necesita asunto y contenido antes del envio.',
			),
			'Este agente nao esta habilitado para enviar emails.' => array(
				'en' => 'This agent is not enabled to send emails.',
				'pt' => 'Este agente nao esta habilitado para enviar emails.',
				'es' => 'Este agente no esta habilitado para enviar emails.',
			),
			'A proposta de email expirou ou foi alterada. Peca ao agente para preparar o envio novamente.' => array(
				'en' => 'The email proposal expired or was changed. Ask the agent to prepare the send action again.',
				'pt' => 'A proposta de email expirou ou foi alterada. Peca ao agente para preparar o envio novamente.',
				'es' => 'La propuesta de email expiro o fue alterada. Pide al agente que prepare el envio nuevamente.',
			),
			'O WordPress nao conseguiu enviar o email. Verifique a configuracao SMTP/hospedagem.' => array(
				'en' => 'WordPress could not send the email. Check the SMTP/hosting configuration.',
				'pt' => 'O WordPress nao conseguiu enviar o email. Verifique a configuracao SMTP/hospedagem.',
				'es' => 'WordPress no pudo enviar el email. Revisa la configuracion SMTP/hosting.',
			),
			'Email enviado com sucesso.' => array(
				'en' => 'Email sent successfully.',
				'pt' => 'Email enviado com sucesso.',
				'es' => 'Email enviado correctamente.',
			),
			'Email agendado para envio.' => array(
				'en' => 'Email queued for sending.',
				'pt' => 'Email agendado para envio.',
				'es' => 'Email programado para envio.',
			),
			'Nao foi possivel registrar o email para envio.' => array(
				'en' => 'Could not register the email for sending.',
				'pt' => 'Nao foi possivel registrar o email para envio.',
				'es' => 'No fue posible registrar el email para envio.',
			),
			'Nao foi possivel agendar o envio do email.' => array(
				'en' => 'Could not queue the email for sending.',
				'pt' => 'Nao foi possivel agendar o envio do email.',
				'es' => 'No fue posible programar el envio del email.',
			),
			'O email agendado perdeu destinatario, assunto ou conteudo valido.' => array(
				'en' => 'The queued email lost a valid recipient, subject, or content.',
				'pt' => 'O email agendado perdeu destinatario, assunto ou conteudo valido.',
				'es' => 'El email programado perdio destinatario, asunto o contenido valido.',
			),
			'O envio de email do WPAgent nao esta disponivel.' => array(
				'en' => 'WPAgent email sending is not available.',
				'pt' => 'O envio de email do WPAgent nao esta disponivel.',
				'es' => 'El envio de email de WPAgent no esta disponible.',
			),
			'Muitos emails solicitados em pouco tempo. Aguarde e tente novamente.' => array(
				'en' => 'Too many emails requested in a short time. Please wait and try again.',
				'pt' => 'Muitos emails solicitados em pouco tempo. Aguarde e tente novamente.',
				'es' => 'Demasiados emails solicitados en poco tiempo. Espera e intenta nuevamente.',
			),
			'Gerenciamento de conteudo' => array(
				'en' => 'Content management',
				'pt' => 'Gerenciamento de conteúdo',
				'es' => 'Gestión de contenido',
			),
			'Capacidades para criar, consultar e atualizar conteudos do WordPress.' => array(
				'en' => 'Capabilities to create, search, and update WordPress content.',
				'pt' => 'Capacidades para criar, consultar e atualizar conteúdos do WordPress.',
				'es' => 'Capacidades para crear, buscar y actualizar contenido de WordPress.',
			),
			'Criar post como rascunho' => array(
				'en' => 'Create post as draft',
				'pt' => 'Criar post como rascunho',
				'es' => 'Crear entrada como borrador',
			),
			'Cria um novo post ou pagina como rascunho para revisao humana no WordPress.' => array(
				'en' => 'Creates a new post or page as a draft for human review in WordPress.',
				'pt' => 'Cria um novo post ou página como rascunho para revisão humana no WordPress.',
				'es' => 'Crea una nueva entrada o página como borrador para revisión humana en WordPress.',
			),
			'Titulo do conteudo.' => array(
				'en' => 'Content title.',
				'pt' => 'Título do conteúdo.',
				'es' => 'Título del contenido.',
			),
			'Conteudo em HTML ou texto simples.' => array(
				'en' => 'HTML or plain text content.',
				'pt' => 'Conteúdo em HTML ou texto simples.',
				'es' => 'Contenido en HTML o texto simple.',
			),
			'Resumo opcional.' => array(
				'en' => 'Optional excerpt.',
				'pt' => 'Resumo opcional.',
				'es' => 'Resumen opcional.',
			),
			'Tipo de post. Use post ou page.' => array(
				'en' => 'Post type. Use post or page.',
				'pt' => 'Tipo de post. Use post ou page.',
				'es' => 'Tipo de contenido. Usa post o page.',
			),
			'Use para criar apenas rascunhos revisaveis. Nunca afirme que publicou.' => array(
				'en' => 'Use only to create reviewable drafts. Never claim that content was published.',
				'pt' => 'Use para criar apenas rascunhos revisáveis. Nunca afirme que publicou.',
				'es' => 'Úsalo solo para crear borradores revisables. Nunca afirmes que el contenido fue publicado.',
			),
			'Atualizar post existente' => array(
				'en' => 'Update existing post',
				'pt' => 'Atualizar post existente',
				'es' => 'Actualizar entrada existente',
			),
			'Altera somente o titulo de um post ou pagina existente.' => array(
				'en' => 'Changes only the title of an existing post or page.',
				'pt' => 'Altera somente o título de um post ou página existente.',
				'es' => 'Cambia solo el título de una entrada o página existente.',
			),
			'Alterar titulo de post ou pagina' => array(
				'en' => 'Change post or page title',
				'pt' => 'Alterar título de post ou página',
				'es' => 'Cambiar título de entrada o página',
			),
			'Buscar posts' => array(
				'en' => 'Search posts',
				'pt' => 'Buscar posts',
				'es' => 'Buscar entradas',
			),
			'Busca posts e paginas por texto para encontrar o ID correto antes de uma edicao.' => array(
				'en' => 'Searches posts and pages by text to find the correct ID before editing.',
				'pt' => 'Busca posts e páginas por texto para encontrar o ID correto antes de uma edição.',
				'es' => 'Busca entradas y páginas por texto para encontrar el ID correcto antes de editar.',
			),
			'Ver comentarios' => array(
				'en' => 'View comments',
				'pt' => 'Ver comentários',
				'es' => 'Ver comentarios',
			),
			'Lista comentarios para revisao, incluindo pendentes, aprovados, spam ou lixeira.' => array(
				'en' => 'Lists comments for review, including pending, approved, spam, or trashed comments.',
				'pt' => 'Lista comentários para revisão, incluindo pendentes, aprovados, spam ou lixeira.',
				'es' => 'Lista comentarios para revisión, incluidos pendientes, aprobados, spam o papelera.',
			),
			'Aprovar ou rejeitar comentario' => array(
				'en' => 'Approve or reject comment',
				'pt' => 'Aprovar ou rejeitar comentário',
				'es' => 'Aprobar o rechazar comentario',
			),
			'Aprova, rejeita, marca como spam ou coloca um comentario na lixeira.' => array(
				'en' => 'Approves, rejects, marks as spam, or moves a comment to trash.',
				'pt' => 'Aprova, rejeita, marca como spam ou coloca um comentário na lixeira.',
				'es' => 'Aprueba, rechaza, marca como spam o mueve un comentario a la papelera.',
			),
			'Preparei uma acao administrativa para sua revisao.' => array(
				'en' => 'I prepared an administrative action for your review.',
				'pt' => 'Preparei uma ação administrativa para sua revisão.',
				'es' => 'Preparé una acción administrativa para tu revisión.',
			),
			'Rascunho criado.' => array(
				'en' => 'Draft created.',
				'pt' => 'Rascunho criado.',
				'es' => 'Borrador creado.',
			),
			'Post atualizado.' => array(
				'en' => 'Post updated.',
				'pt' => 'Post atualizado.',
				'es' => 'Entrada actualizada.',
			),
			'Titulo atualizado.' => array(
				'en' => 'Title updated.',
				'pt' => 'Título atualizado.',
				'es' => 'Título actualizado.',
			),
			'Comentario moderado.' => array(
				'en' => 'Comment moderated.',
				'pt' => 'Comentário moderado.',
				'es' => 'Comentario moderado.',
			),
			'A Abilities API do WordPress nao esta disponivel.' => array(
				'en' => 'The WordPress Abilities API is not available.',
				'pt' => 'A Abilities API do WordPress não está disponível.',
				'es' => 'La API de Abilities de WordPress no está disponible.',
			),
			'O WordPress AI Client nao esta disponivel.' => array(
				'en' => 'The WordPress AI Client is not available.',
				'pt' => 'O WordPress AI Client não está disponível.',
				'es' => 'WordPress AI Client no está disponible.',
			),
			'Nao foi possivel concluir a resposta do provedor de IA. Tente novamente em instantes.' => array(
				'en' => 'The AI provider could not complete the response. Try again in a moment.',
				'pt' => 'NÃ£o foi possÃ­vel concluir a resposta do provedor de IA. Tente novamente em instantes.',
				'es' => 'No fue posible completar la respuesta del proveedor de IA. IntÃ©ntalo de nuevo en unos instantes.',
			),
			'A resposta demorou demais. Tente novamente em instantes ou use uma pergunta mais curta.' => array(
				'en' => 'The response took too long. Try again in a moment or use a shorter question.',
				'pt' => 'A resposta demorou demais. Tente novamente em instantes ou use uma pergunta mais curta.',
				'es' => 'La respuesta tardÃ³ demasiado. IntÃ©ntalo de nuevo en unos instantes o usa una pregunta mÃ¡s corta.',
			),
			'Configure a chave da OpenRouter nas opcoes do WPAgent.' => array(
				'en' => 'Configure the OpenRouter key in WPAgent settings.',
				'pt' => 'Configure a chave da OpenRouter nas opções do WPAgent.',
				'es' => 'Configura la clave de OpenRouter en los ajustes de WPAgent.',
			),
			'Envie uma mensagem para o agente.' => array(
				'en' => 'Send a message to the agent.',
				'pt' => 'Envie uma mensagem para o agente.',
				'es' => 'Envía un mensaje al agente.',
			),
			'Este agente nao esta habilitado como assistente interno do admin.' => array(
				'en' => 'This agent is not enabled as an internal admin assistant.',
				'pt' => 'Este agente não está habilitado como assistente interno do admin.',
				'es' => 'Este agente no está habilitado como asistente interno del admin.',
			),
			'Informe um nome para a conversa.' => array(
				'en' => 'Enter a conversation name.',
				'pt' => 'Informe um nome para a conversa.',
				'es' => 'Informa un nombre para la conversación.',
			),
			'Nao foi possivel apagar esta conversa.' => array(
				'en' => 'Could not delete this conversation.',
				'pt' => 'Não foi possível apagar esta conversa.',
				'es' => 'No fue posible eliminar esta conversación.',
			),
			'Muitas mensagens em pouco tempo. Aguarde um minuto e tente novamente.' => array(
				'en' => 'Too many messages in a short time. Wait a minute and try again.',
				'pt' => 'Muitas mensagens em pouco tempo. Aguarde um minuto e tente novamente.',
				'es' => 'Demasiados mensajes en poco tiempo. Espera un minuto e inténtalo de nuevo.',
			),
			'Este agente atingiu o limite %1$s de %2$s tokens. Tente novamente depois ou fale com o administrador.' => array(
				'en' => 'This agent reached the %1$s limit of %2$s tokens. Try again later or contact the administrator.',
				'pt' => 'Este agente atingiu o limite %1$s de %2$s tokens. Tente novamente depois ou fale com o administrador.',
				'es' => 'Este agente alcanzó el límite %1$s de %2$s tokens. Inténtalo más tarde o habla con el administrador.',
			),
			'diario' => array(
				'en' => 'daily',
				'pt' => 'diário',
				'es' => 'diario',
			),
			'semanal' => array(
				'en' => 'weekly',
				'pt' => 'semanal',
				'es' => 'semanal',
			),
			'mensal' => array(
				'en' => 'monthly',
				'pt' => 'mensal',
				'es' => 'mensual',
			),
		);

		$catalog = array_merge( $catalog, self::secondary_catalog() );

		return $catalog;
	}

	private static function secondary_catalog() {
		return array(
			'WPAgent' => array(
				'en' => 'WPAgent',
				'pt' => 'WPAgent',
				'es' => 'WPAgent',
			),
			'Agente' => array(
				'en' => 'Agent',
				'pt' => 'Agente',
				'es' => 'Agente',
			),
			'Sobre voce' => array(
				'en' => 'About you',
				'pt' => 'Sobre vocÃª',
				'es' => 'Sobre ti',
			),
			'Compartilhe informacoes que ajudam este agente a personalizar as respostas.' => array(
				'en' => 'Share information that helps this agent personalize its responses.',
				'pt' => 'Compartilhe informaÃ§Ãµes que ajudam este agente a personalizar as respostas.',
				'es' => 'Comparte informaciÃ³n que ayude a este agente a personalizar sus respuestas.',
			),
			'Perfil declarado pelo usuario' => array(
				'en' => 'User-declared profile',
				'pt' => 'Perfil declarado pelo usuÃ¡rio',
				'es' => 'Perfil declarado por el usuario',
			),
			'Permitir que usuarios logados informem dados pessoais para este agente considerar nas respostas.' => array(
				'en' => 'Allow logged-in users to provide personal context for this agent to consider in replies.',
				'pt' => 'Permitir que usuÃ¡rios logados informem dados pessoais para este agente considerar nas respostas.',
				'es' => 'Permitir que usuarios conectados proporcionen contexto personal para que este agente lo considere en sus respuestas.',
			),
			'Titulo do campo' => array(
				'en' => 'Field title',
				'pt' => 'TÃ­tulo do campo',
				'es' => 'TÃ­tulo del campo',
			),
			'Descricao para o usuario' => array(
				'en' => 'Description for the user',
				'pt' => 'DescriÃ§Ã£o para o usuÃ¡rio',
				'es' => 'DescripciÃ³n para el usuario',
			),
			'Explique quais informacoes ajudam este agente a personalizar as respostas.' => array(
				'en' => 'Explain which information helps this agent personalize its responses.',
				'pt' => 'Explique quais informaÃ§Ãµes ajudam este agente a personalizar as respostas.',
				'es' => 'Explica quÃ© informaciÃ³n ayuda a este agente a personalizar sus respuestas.',
			),
			'Use para orientar o usuario a informar contexto estavel, como turma, area de atuacao, estilo pessoal, objetivos e preferencias. Essas informacoes entram no prompt como contexto declarado pelo usuario.' => array(
				'en' => 'Use this to guide users toward stable context such as class year, field of work, personal style, goals, and preferences. This information enters the prompt as user-declared context.',
				'pt' => 'Use para orientar o usuÃ¡rio a informar contexto estÃ¡vel, como turma, Ã¡rea de atuaÃ§Ã£o, estilo pessoal, objetivos e preferÃªncias. Essas informaÃ§Ãµes entram no prompt como contexto declarado pelo usuÃ¡rio.',
				'es' => 'Ãšsalo para orientar al usuario a informar contexto estable, como curso, Ã¡rea de trabajo, estilo personal, objetivos y preferencias. Esta informaciÃ³n entra en el prompt como contexto declarado por el usuario.',
			),
			'Ex.: ano em que leciono, estilo de aula, objetivos e preferencias...' => array(
				'en' => 'Example: grade I teach, class style, goals, and preferences...',
				'pt' => 'Ex.: ano em que leciono, estilo de aula, objetivos e preferÃªncias...',
				'es' => 'Ej.: curso en el que enseÃ±o, estilo de clase, objetivos y preferencias...',
			),
			'Campos estruturados' => array(
				'en' => 'Structured fields',
				'pt' => 'Campos estruturados',
				'es' => 'Campos estructurados',
			),
			'Ano em que leciono | teaching_year | text | Ex.: 7 ano' => array(
				'en' => 'Grade I teach | teaching_year | text | Example: 7th grade',
				'pt' => 'Ano em que leciono | teaching_year | text | Ex.: 7 ano',
				'es' => 'Curso en el que enseÃƒÂ±o | teaching_year | text | Ej.: 7Âº aÃƒÂ±o',
			),
			'Adicione um campo por linha no formato: Rotulo | chave | tipo | placeholder. Tipos aceitos: text ou textarea. A chave deve usar letras, numeros e sublinhado.' => array(
				'en' => 'Add one field per line in this format: Label | key | type | placeholder. Accepted types: text or textarea. The key should use letters, numbers, and underscores.',
				'pt' => 'Adicione um campo por linha no formato: RÃƒÂ³tulo | chave | tipo | placeholder. Tipos aceitos: text ou textarea. A chave deve usar letras, nÃƒÂºmeros e sublinhado.',
				'es' => 'Agrega un campo por lÃƒÂ­nea con este formato: Etiqueta | clave | tipo | placeholder. Tipos aceptados: text o textarea. La clave debe usar letras, nÃƒÂºmeros y guion bajo.',
			),
			'Envio de email autorizado' => array(
				'en' => 'Authorized email sending',
				'pt' => 'Envio de email autorizado',
				'es' => 'Envio de email autorizado',
			),
			'Permitir que este agente prepare emails para envio apos confirmacao do usuario.' => array(
				'en' => 'Allow this agent to prepare emails for sending after user confirmation.',
				'pt' => 'Permitir que este agente prepare emails para envio apos confirmacao do usuario.',
				'es' => 'Permitir que este agente prepare emails para envio tras la confirmacion del usuario.',
			),
			'O agente pode coletar email, nome e dados relevantes durante a conversa, mas o envio so acontece quando o usuario confirma no botao exibido pelo WPAgent.' => array(
				'en' => 'The agent can collect email, name, and relevant data during the conversation, but sending only happens when the user confirms using the button shown by WPAgent.',
				'pt' => 'O agente pode coletar email, nome e dados relevantes durante a conversa, mas o envio so acontece quando o usuario confirma no botao exibido pelo WPAgent.',
				'es' => 'El agente puede recopilar email, nombre y datos relevantes durante la conversacion, pero el envio solo ocurre cuando el usuario confirma con el boton mostrado por WPAgent.',
			),
			'Instrucoes adicionais para emails' => array(
				'en' => 'Additional email instructions',
				'pt' => 'Instrucoes adicionais para emails',
				'es' => 'Instrucciones adicionales para emails',
			),
			'Ex.: envie apenas materiais finalizados; inclua uma saudacao curta; nao envie anexos.' => array(
				'en' => 'Example: send only finalized materials; include a short greeting; do not send attachments.',
				'pt' => 'Ex.: envie apenas materiais finalizados; inclua uma saudacao curta; nao envie anexos.',
				'es' => 'Ej.: envia solo materiales finalizados; incluye un saludo breve; no envies adjuntos.',
			),
			'Use para casos como enviar plano de aula, mensagem pastoral, resumo, proposta, dados de produto ou material de apoio. O envio usa wp_mail(), entao a entrega depende da configuracao de email/SMTP do WordPress.' => array(
				'en' => 'Use this for cases such as sending a lesson plan, pastoral message, summary, proposal, product details, or support material. Sending uses wp_mail(), so delivery depends on the WordPress email/SMTP configuration.',
				'pt' => 'Use para casos como enviar plano de aula, mensagem pastoral, resumo, proposta, dados de produto ou material de apoio. O envio usa wp_mail(), entao a entrega depende da configuracao de email/SMTP do WordPress.',
				'es' => 'Usalo para casos como enviar un plan de clase, mensaje pastoral, resumen, propuesta, datos de producto o material de apoyo. El envio usa wp_mail(), por lo que la entrega depende de la configuracion de email/SMTP de WordPress.',
			),
			'Observacoes adicionais' => array(
				'en' => 'Additional notes',
				'pt' => 'ObservaÃƒÂ§ÃƒÂµes adicionais',
				'es' => 'Observaciones adicionales',
			),
			'Salvar perfil' => array(
				'en' => 'Save profile',
				'pt' => 'Salvar perfil',
				'es' => 'Guardar perfil',
			),
			'Carregando perfil...' => array(
				'en' => 'Loading profile...',
				'pt' => 'Carregando perfil...',
				'es' => 'Cargando perfil...',
			),
			'Perfil carregado' => array(
				'en' => 'Profile loaded',
				'pt' => 'Perfil carregado',
				'es' => 'Perfil cargado',
			),
			'Perfil opcional' => array(
				'en' => 'Optional profile',
				'pt' => 'Perfil opcional',
				'es' => 'Perfil opcional',
			),
			'Erro ao carregar perfil' => array(
				'en' => 'Error loading profile',
				'pt' => 'Erro ao carregar perfil',
				'es' => 'Error al cargar el perfil',
			),
			'Salvando perfil...' => array(
				'en' => 'Saving profile...',
				'pt' => 'Salvando perfil...',
				'es' => 'Guardando perfil...',
			),
			'Perfil salvo' => array(
				'en' => 'Profile saved',
				'pt' => 'Perfil salvo',
				'es' => 'Perfil guardado',
			),
			'Erro ao salvar perfil' => array(
				'en' => 'Error saving profile',
				'pt' => 'Erro ao salvar perfil',
				'es' => 'Error al guardar el perfil',
			),
			'Nao foi possivel salvar o perfil do usuario.' => array(
				'en' => 'Could not save the user profile.',
				'pt' => 'NÃ£o foi possÃ­vel salvar o perfil do usuÃ¡rio.',
				'es' => 'No fue posible guardar el perfil del usuario.',
			),
			'Usuarios' => array(
				'en' => 'Users',
				'pt' => 'Usuários',
				'es' => 'Usuarios',
			),
			'Connectors' => array(
				'en' => 'Connectors',
				'pt' => 'Connectors',
				'es' => 'Connectors',
			),
			'Modelo padrao do WordPress AI' => array(
				'en' => 'Default WordPress AI model',
				'pt' => 'Modelo padrão do WordPress AI',
				'es' => 'Modelo predeterminado de WordPress AI',
			),
			'Opcional. Quando preenchido, o WPAgent envia este provider/model como preferencia para o WordPress AI Client. Deixe em branco para usar o modelo padrao escolhido pelo conector/plugin AI.' => array(
				'en' => 'Optional. When filled in, WPAgent sends this provider/model as a preference to the WordPress AI Client. Leave blank to use the default model chosen by the connector/AI plugin.',
				'pt' => 'Opcional. Quando preenchido, o WPAgent envia este provider/model como preferência para o WordPress AI Client. Deixe em branco para usar o modelo padrão escolhido pelo conector/plugin AI.',
				'es' => 'Opcional. Cuando se completa, WPAgent envía este provider/model como preferencia al WordPress AI Client. Déjalo en blanco para usar el modelo predeterminado elegido por el conector/plugin AI.',
			),
			'A listagem automatica de modelos depende de cada conector expor essa informacao. Por enquanto, use o ID oficial do modelo informado pelo fornecedor.' => array(
				'en' => 'Automatic model listing depends on each connector exposing that information. For now, use the official model ID provided by the vendor.',
				'pt' => 'A listagem automática de modelos depende de cada conector expor essa informação. Por enquanto, use o ID oficial do modelo informado pelo fornecedor.',
				'es' => 'La lista automática de modelos depende de que cada conector exponga esa información. Por ahora, usa el ID oficial del modelo informado por el proveedor.',
			),
			'Modelo do agente no WordPress AI' => array(
				'en' => 'Agent model in WordPress AI',
				'pt' => 'Modelo do agente no WordPress AI',
				'es' => 'Modelo del agente en WordPress AI',
			),
			'Deixe em branco para herdar o modelo padrao definido em WPAgent > Settings. Preencha apenas quando este agente precisar usar um provider/model especifico.' => array(
				'en' => 'Leave blank to inherit the default model defined in WPAgent > Settings. Fill in only when this agent needs a specific provider/model.',
				'pt' => 'Deixe em branco para herdar o modelo padrão definido em WPAgent > Settings. Preencha apenas quando este agente precisar usar um provider/model específico.',
				'es' => 'Déjalo en blanco para heredar el modelo predeterminado definido en WPAgent > Settings. Complétalo solo cuando este agente necesite un provider/model específico.',
			),
			'Modelo efetivo atual: %1$s / %2$s' => array(
				'en' => 'Current effective model: %1$s / %2$s',
				'pt' => 'Modelo efetivo atual: %1$s / %2$s',
				'es' => 'Modelo efectivo actual: %1$s / %2$s',
			),
			'automatico' => array(
				'en' => 'automatic',
				'pt' => 'automático',
				'es' => 'automático',
			),
			'Shortcode' => array(
				'en' => 'Shortcode',
				'pt' => 'Shortcode',
				'es' => 'Shortcode',
			),
			'Status' => array(
				'en' => 'Status',
				'pt' => 'Status',
				'es' => 'Estado',
			),
			'Embeddings' => array(
				'en' => 'Embeddings',
				'pt' => 'Embeddings',
				'es' => 'Embeddings',
			),
			'Lote por cron' => array(
				'en' => 'Cron batch',
				'pt' => 'Lote por cron',
				'es' => 'Lote por cron',
			),
			'Abrir rascunho' => array(
				'en' => 'Open draft',
				'pt' => 'Abrir rascunho',
				'es' => 'Abrir borrador',
			),
			'Desativado' => array(
				'en' => 'Disabled',
				'pt' => 'Desativado',
				'es' => 'Desactivado',
			),
			'Indexado' => array(
				'en' => 'Indexed',
				'pt' => 'Indexado',
				'es' => 'Indexado',
			),
			'Indexando texto manual.' => array(
				'en' => 'Indexing manual text.',
				'pt' => 'Indexando texto manual.',
				'es' => 'Indexando texto manual.',
			),
			'Extraindo texto e criando indice.' => array(
				'en' => 'Extracting text and creating the index.',
				'pt' => 'Extraindo texto e criando índice.',
				'es' => 'Extrayendo texto y creando el índice.',
			),
			'Aguardando indice' => array(
				'en' => 'Waiting for index',
				'pt' => 'Aguardando índice',
				'es' => 'Esperando índice',
			),
			'Aguardando extrator' => array(
				'en' => 'Waiting for extractor',
				'pt' => 'Aguardando extrator',
				'es' => 'Esperando extractor',
			),
			'Extracao insuficiente' => array(
				'en' => 'Insufficient extraction',
				'pt' => 'Extração insuficiente',
				'es' => 'Extracción insuficiente',
			),
			'Sem texto' => array(
				'en' => 'No text',
				'pt' => 'Sem texto',
				'es' => 'Sin texto',
			),
			'Sem permissao.' => array(
				'en' => 'Permission denied.',
				'pt' => 'Sem permissão.',
				'es' => 'Sin permiso.',
			),
			'Arquivo de treinamento' => array(
				'en' => 'Training file',
				'pt' => 'Arquivo de treinamento',
				'es' => 'Archivo de entrenamiento',
			),
			'Documento de treinamento' => array(
				'en' => 'Training document',
				'pt' => 'Documento de treinamento',
				'es' => 'Documento de entrenamiento',
			),
			' (pag. %d)' => array(
				'en' => ' (page %d)',
				'pt' => ' (pág. %d)',
				'es' => ' (pág. %d)',
			),
			'%1$s foi recebido, mas nao foi indexado: %2$s' => array(
				'en' => '%1$s was received but not indexed: %2$s',
				'pt' => '%1$s foi recebido, mas não foi indexado: %2$s',
				'es' => '%1$s fue recibido, pero no fue indexado: %2$s',
			),
			'%d trechos indexados.' => array(
				'en' => '%d chunks indexed.',
				'pt' => '%d trechos indexados.',
				'es' => '%d fragmentos indexados.',
			),
			'%d trechos indexados a partir do texto manual.' => array(
				'en' => '%d chunks indexed from manual text.',
				'pt' => '%d trechos indexados a partir do texto manual.',
				'es' => '%d fragmentos indexados a partir del texto manual.',
			),
			'%d embeddings processados manualmente.' => array(
				'en' => '%d embeddings processed manually.',
				'pt' => '%d embeddings processados manualmente.',
				'es' => '%d embeddings procesados manualmente.',
			),
			'%d embeddings processados no ultimo lote.' => array(
				'en' => '%d embeddings processed in the last batch.',
				'pt' => '%d embeddings processados no último lote.',
				'es' => '%d embeddings procesados en el último lote.',
			),
			'Nenhum chunk pendente para embeddings.' => array(
				'en' => 'No chunks pending embeddings.',
				'pt' => 'Nenhum chunk pendente para embeddings.',
				'es' => 'No hay fragmentos pendientes de embeddings.',
			),
			'Embeddings desativados.' => array(
				'en' => 'Embeddings disabled.',
				'pt' => 'Embeddings desativados.',
				'es' => 'Embeddings desactivados.',
			),
			'Configure a chave da OpenRouter para gerar embeddings.' => array(
				'en' => 'Configure the OpenRouter key to generate embeddings.',
				'pt' => 'Configure a chave da OpenRouter para gerar embeddings.',
				'es' => 'Configura la clave de OpenRouter para generar embeddings.',
			),
			'Provider de embeddings ainda nao suportado.' => array(
				'en' => 'Embedding provider is not supported yet.',
				'pt' => 'Provider de embeddings ainda não suportado.',
				'es' => 'Proveedor de embeddings aún no compatible.',
			),
			'O provedor nao retornou embedding.' => array(
				'en' => 'The provider did not return an embedding.',
				'pt' => 'O provedor não retornou embedding.',
				'es' => 'El proveedor no devolvió un embedding.',
			),
			'Erro ao gerar embeddings.' => array(
				'en' => 'Error generating embeddings.',
				'pt' => 'Erro ao gerar embeddings.',
				'es' => 'Error al generar embeddings.',
			),
			'Arquivo nao encontrado para indexacao.' => array(
				'en' => 'File not found for indexing.',
				'pt' => 'Arquivo não encontrado para indexação.',
				'es' => 'Archivo no encontrado para indexación.',
			),
			'Nao foi possivel ler o arquivo enviado.' => array(
				'en' => 'Could not read the uploaded file.',
				'pt' => 'Não foi possível ler o arquivo enviado.',
				'es' => 'No fue posible leer el archivo subido.',
			),
			'Nao foi possivel enviar %1$s: %2$s' => array(
				'en' => 'Could not upload %1$s: %2$s',
				'pt' => 'Não foi possível enviar %1$s: %2$s',
				'es' => 'No fue posible subir %1$s: %2$s',
			),
			'Nao foi possivel registrar a fonte de treinamento.' => array(
				'en' => 'Could not register the training source.',
				'pt' => 'Não foi possível registrar a fonte de treinamento.',
				'es' => 'No fue posible registrar la fuente de entrenamiento.',
			),
			'Nenhum texto util foi encontrado neste documento.' => array(
				'en' => 'No useful text was found in this document.',
				'pt' => 'Nenhum texto útil foi encontrado neste documento.',
				'es' => 'No se encontró texto útil en este documento.',
			),
			'Tipo de arquivo ainda nao suportado para extracao automatica.' => array(
				'en' => 'This file type is not yet supported for automatic extraction.',
				'pt' => 'Tipo de arquivo ainda não suportado para extração automática.',
				'es' => 'Este tipo de archivo aún no es compatible con extracción automática.',
			),
			'PDF recebido, mas nao foi possivel extrair texto dele. Instale pdftotext no servidor, use um PDF com texto selecionavel ou conecte um extrator/OCR pelo filtro wpagent_extract_pdf_text.' => array(
				'en' => 'PDF received, but text could not be extracted. Install pdftotext on the server, use a PDF with selectable text, or connect an extractor/OCR through the wpagent_extract_pdf_text filter.',
				'pt' => 'PDF recebido, mas não foi possível extrair texto dele. Instale pdftotext no servidor, use um PDF com texto selecionável ou conecte um extrator/OCR pelo filtro wpagent_extract_pdf_text.',
				'es' => 'PDF recibido, pero no fue posible extraer texto. Instala pdftotext en el servidor, usa un PDF con texto seleccionable o conecta un extractor/OCR mediante el filtro wpagent_extract_pdf_text.',
			),
			'A extracao nao encontrou texto legivel suficiente.' => array(
				'en' => 'Extraction did not find enough readable text.',
				'pt' => 'A extração não encontrou texto legível suficiente.',
				'es' => 'La extracción no encontró suficiente texto legible.',
			),
			'A extracao encontrou muitos simbolos e poucas palavras legiveis. Converta o documento para TXT/Markdown ou use um extrator/OCR externo.' => array(
				'en' => 'Extraction found too many symbols and too few readable words. Convert the document to TXT/Markdown or use an external extractor/OCR.',
				'pt' => 'A extração encontrou muitos símbolos e poucas palavras legíveis. Converta o documento para TXT/Markdown ou use um extrator/OCR externo.',
				'es' => 'La extracción encontró demasiados símbolos y pocas palabras legibles. Convierte el documento a TXT/Markdown o usa un extractor/OCR externo.',
			),
			'ZipArchive nao esta disponivel para extrair arquivos DOCX.' => array(
				'en' => 'ZipArchive is not available to extract DOCX files.',
				'pt' => 'ZipArchive não está disponível para extrair arquivos DOCX.',
				'es' => 'ZipArchive no está disponible para extraer archivos DOCX.',
			),
			'Nao foi possivel abrir o arquivo DOCX.' => array(
				'en' => 'Could not open the DOCX file.',
				'pt' => 'Não foi possível abrir o arquivo DOCX.',
				'es' => 'No fue posible abrir el archivo DOCX.',
			),
			'Nao foi possivel localizar o texto principal do DOCX.' => array(
				'en' => 'Could not locate the main DOCX text.',
				'pt' => 'Não foi possível localizar o texto principal do DOCX.',
				'es' => 'No fue posible localizar el texto principal del DOCX.',
			),
			'Tarefa invalida.' => array(
				'en' => 'Invalid task.',
				'pt' => 'Tarefa inválida.',
				'es' => 'Tarea inválida.',
			),
			'Executando tarefa.' => array(
				'en' => 'Running task.',
				'pt' => 'Executando tarefa.',
				'es' => 'Ejecutando tarea.',
			),
			'Tarefa concluida.' => array(
				'en' => 'Task completed.',
				'pt' => 'Tarefa concluída.',
				'es' => 'Tarea completada.',
			),
			'Configure um provedor de IA antes de executar tarefas periodicas.' => array(
				'en' => 'Configure an AI provider before running periodic tasks.',
				'pt' => 'Configure um provedor de IA antes de executar tarefas periódicas.',
				'es' => 'Configura un proveedor de IA antes de ejecutar tareas periódicas.',
			),
			'Erro ao chamar a OpenRouter.' => array(
				'en' => 'Error calling OpenRouter.',
				'pt' => 'Erro ao chamar a OpenRouter.',
				'es' => 'Error al llamar a OpenRouter.',
			),
			'Erro ao chamar o provedor de IA.' => array(
				'en' => 'Error calling the AI provider.',
				'pt' => 'Erro ao chamar o provedor de IA.',
				'es' => 'Error al llamar al proveedor de IA.',
			),
			'O fornecedor de IA respondeu sem conteudo.' => array(
				'en' => 'The AI provider returned an empty response.',
				'pt' => 'O fornecedor de IA respondeu sem conteúdo.',
				'es' => 'El proveedor de IA respondió sin contenido.',
			),
			'O provedor de IA respondeu sem conteudo.' => array(
				'en' => 'The AI provider returned an empty response.',
				'pt' => 'O provedor de IA respondeu sem conteúdo.',
				'es' => 'El proveedor de IA respondió sin contenido.',
			),
			'Avaliar comentarios pendentes' => array(
				'en' => 'Review pending comments',
				'pt' => 'Avaliar comentários pendentes',
				'es' => 'Revisar comentarios pendientes',
			),
			'Avalie os comentarios pendentes e destaque riscos, prioridade de resposta e sugestoes de moderacao.' => array(
				'en' => 'Review pending comments and highlight risks, response priority, and moderation suggestions.',
				'pt' => 'Avalie os comentários pendentes e destaque riscos, prioridade de resposta e sugestões de moderação.',
				'es' => 'Revisa los comentarios pendientes y destaca riesgos, prioridad de respuesta y sugerencias de moderación.',
			),
			'Analisa comentarios pendentes e gera recomendacoes. Nao aprova, rejeita ou marca spam sozinho.' => array(
				'en' => 'Analyzes pending comments and generates recommendations. It does not approve, reject, or mark spam by itself.',
				'pt' => 'Analisa comentários pendentes e gera recomendações. Não aprova, rejeita ou marca spam sozinho.',
				'es' => 'Analiza comentarios pendientes y genera recomendaciones. No aprueba, rechaza ni marca spam por sí solo.',
			),
			'Nenhum comentario pendente para avaliar.' => array(
				'en' => 'No pending comments to review.',
				'pt' => 'Nenhum comentário pendente para avaliar.',
				'es' => 'No hay comentarios pendientes para revisar.',
			),
			'Relatorio de comentarios gerado.' => array(
				'en' => 'Comment report generated.',
				'pt' => 'Relatório de comentários gerado.',
				'es' => 'Informe de comentarios generado.',
			),
			'Revisar atualizacoes de plugins' => array(
				'en' => 'Review plugin updates',
				'pt' => 'Revisar atualizações de plugins',
				'es' => 'Revisar actualizaciones de plugins',
			),
			'Analise as atualizacoes de plugins disponiveis e recomende uma ordem segura de revisao.' => array(
				'en' => 'Analyze available plugin updates and recommend a safe review order.',
				'pt' => 'Analise as atualizações de plugins disponíveis e recomende uma ordem segura de revisão.',
				'es' => 'Analiza las actualizaciones de plugins disponibles y recomienda un orden seguro de revisión.',
			),
			'Lista atualizacoes disponiveis e gera uma recomendacao. Nao atualiza plugins automaticamente.' => array(
				'en' => 'Lists available updates and generates a recommendation. It does not update plugins automatically.',
				'pt' => 'Lista atualizações disponíveis e gera uma recomendação. Não atualiza plugins automaticamente.',
				'es' => 'Lista actualizaciones disponibles y genera una recomendación. No actualiza plugins automáticamente.',
			),
			'Nenhuma atualizacao de plugin disponivel.' => array(
				'en' => 'No plugin updates available.',
				'pt' => 'Nenhuma atualização de plugin disponível.',
				'es' => 'No hay actualizaciones de plugins disponibles.',
			),
			'Relatorio de atualizacoes gerado.' => array(
				'en' => 'Update report generated.',
				'pt' => 'Relatório de atualizações gerado.',
				'es' => 'Informe de actualizaciones generado.',
			),
			'Escrever rascunho de post' => array(
				'en' => 'Write post draft',
				'pt' => 'Escrever rascunho de post',
				'es' => 'Escribir borrador de entrada',
			),
			'Escreva um post curto para o site, com tom claro e util para o publico principal.' => array(
				'en' => 'Write a short post for the site, with a clear and useful tone for the main audience.',
				'pt' => 'Escreva um post curto para o site, com tom claro e útil para o público principal.',
				'es' => 'Escribe una entrada corta para el sitio, con tono claro y útil para el público principal.',
			),
			'Gera um rascunho de post para revisao editorial. Nunca publica automaticamente.' => array(
				'en' => 'Generates a post draft for editorial review. Never publishes automatically.',
				'pt' => 'Gera um rascunho de post para revisão editorial. Nunca publica automaticamente.',
				'es' => 'Genera un borrador de entrada para revisión editorial. Nunca publica automáticamente.',
			),
			'Rascunho gerado pelo WPAgent' => array(
				'en' => 'Draft generated by WPAgent',
				'pt' => 'Rascunho gerado pelo WPAgent',
				'es' => 'Borrador generado por WPAgent',
			),
			'Rascunho criado com ID %d.' => array(
				'en' => 'Draft created with ID %d.',
				'pt' => 'Rascunho criado com ID %d.',
				'es' => 'Borrador creado con ID %d.',
			),
			'Rascunho de post criado para revisao.' => array(
				'en' => 'Post draft created for review.',
				'pt' => 'Rascunho de post criado para revisão.',
				'es' => 'Borrador de entrada creado para revisión.',
			),
			'Sugestao de post gerada em modo relatorio.' => array(
				'en' => 'Post suggestion generated in report mode.',
				'pt' => 'Sugestão de post gerada em modo relatório.',
				'es' => 'Sugerencia de entrada generada en modo informe.',
			),
			'Atualiza titulo, conteudo, resumo ou status de um post existente, respeitando as permissoes do usuario atual.' => array(
				'en' => 'Updates title, content, excerpt, or status of an existing post while respecting the current user permissions.',
				'pt' => 'Atualiza título, conteúdo, resumo ou status de um post existente, respeitando as permissões do usuário atual.',
				'es' => 'Actualiza título, contenido, resumen o estado de una entrada existente respetando los permisos del usuario actual.',
			),
			'Novo titulo opcional.' => array(
				'en' => 'Optional new title.',
				'pt' => 'Novo título opcional.',
				'es' => 'Nuevo título opcional.',
			),
			'Novo titulo.' => array(
				'en' => 'New title.',
				'pt' => 'Novo título.',
				'es' => 'Nuevo título.',
			),
			'Novo conteudo opcional em HTML ou texto simples.' => array(
				'en' => 'Optional new content in HTML or plain text.',
				'pt' => 'Novo conteúdo opcional em HTML ou texto simples.',
				'es' => 'Nuevo contenido opcional en HTML o texto simple.',
			),
			'Novo resumo opcional.' => array(
				'en' => 'Optional new excerpt.',
				'pt' => 'Novo resumo opcional.',
				'es' => 'Nuevo resumen opcional.',
			),
			'Novo status opcional.' => array(
				'en' => 'Optional new status.',
				'pt' => 'Novo status opcional.',
				'es' => 'Nuevo estado opcional.',
			),
			'ID do post a atualizar.' => array(
				'en' => 'ID of the post to update.',
				'pt' => 'ID do post a atualizar.',
				'es' => 'ID de la entrada a actualizar.',
			),
			'ID do post ou pagina.' => array(
				'en' => 'Post or page ID.',
				'pt' => 'ID do post ou página.',
				'es' => 'ID de entrada o página.',
			),
			'ID opcional do post.' => array(
				'en' => 'Optional post ID.',
				'pt' => 'ID opcional do post.',
				'es' => 'ID opcional de la entrada.',
			),
			'ID do comentario.' => array(
				'en' => 'Comment ID.',
				'pt' => 'ID do comentário.',
				'es' => 'ID del comentario.',
			),
			'Status dos comentarios.' => array(
				'en' => 'Comment status.',
				'pt' => 'Status dos comentários.',
				'es' => 'Estado de los comentarios.',
			),
			'Texto opcional para buscar.' => array(
				'en' => 'Optional search text.',
				'pt' => 'Texto opcional para buscar.',
				'es' => 'Texto opcional para buscar.',
			),
			'Texto para buscar no titulo ou conteudo.' => array(
				'en' => 'Text to search in title or content.',
				'pt' => 'Texto para buscar no título ou conteúdo.',
				'es' => 'Texto para buscar en título o contenido.',
			),
			'Quantidade maxima de resultados.' => array(
				'en' => 'Maximum number of results.',
				'pt' => 'Quantidade máxima de resultados.',
				'es' => 'Cantidad máxima de resultados.',
			),
			'Tipo de post. Use post, page ou any.' => array(
				'en' => 'Post type. Use post, page, or any.',
				'pt' => 'Tipo de post. Use post, page ou any.',
				'es' => 'Tipo de contenido. Usa post, page o any.',
			),
			'Acao de moderacao. reject move o comentario para a lixeira.' => array(
				'en' => 'Moderation action. reject moves the comment to trash.',
				'pt' => 'Ação de moderação. reject move o comentário para a lixeira.',
				'es' => 'Acción de moderación. reject mueve el comentario a la papelera.',
			),
			'Use antes de editar quando o usuario nao informar o ID do post.' => array(
				'en' => 'Use before editing when the user does not provide the post ID.',
				'pt' => 'Use antes de editar quando o usuário não informar o ID do post.',
				'es' => 'Úsalo antes de editar cuando el usuario no informa el ID de la entrada.',
			),
			'Use para encontrar o ID do comentario antes de aprovar ou rejeitar.' => array(
				'en' => 'Use to find the comment ID before approving or rejecting.',
				'pt' => 'Use para encontrar o ID do comentário antes de aprovar ou rejeitar.',
				'es' => 'Úsalo para encontrar el ID del comentario antes de aprobar o rechazar.',
			),
			'Use quando o usuario pedir apenas para alterar titulo de post ou pagina. Se o ID nao estiver claro, use wpagent/search-posts antes.' => array(
				'en' => 'Use when the user only asks to change a post or page title. If the ID is unclear, use wpagent/search-posts first.',
				'pt' => 'Use quando o usuário pedir apenas para alterar título de post ou página. Se o ID não estiver claro, use wpagent/search-posts antes.',
				'es' => 'Úsalo cuando el usuario solo pida cambiar el título de una entrada o página. Si el ID no está claro, usa wpagent/search-posts primero.',
			),
			'Use somente apos identificar claramente qual post sera alterado. Peca confirmacao quando faltar ID ou titulo.' => array(
				'en' => 'Use only after clearly identifying which post will be changed. Ask for confirmation when ID or title is missing.',
				'pt' => 'Use somente após identificar claramente qual post será alterado. Peça confirmação quando faltar ID ou título.',
				'es' => 'Úsalo solo después de identificar claramente qué entrada será modificada. Pide confirmación cuando falte ID o título.',
			),
			'Use somente quando o ID do comentario e a acao estiverem claros. Para rejeitar, use action=reject.' => array(
				'en' => 'Use only when the comment ID and action are clear. To reject, use action=reject.',
				'pt' => 'Use somente quando o ID do comentário e a ação estiverem claros. Para rejeitar, use action=reject.',
				'es' => 'Úsalo solo cuando el ID del comentario y la acción estén claros. Para rechazar, usa action=reject.',
			),
			'Ability nao encontrada.' => array(
				'en' => 'Ability not found.',
				'pt' => 'Ability não encontrada.',
				'es' => 'Ability no encontrada.',
			),
			'O usuario atual nao tem permissao para executar esta ability.' => array(
				'en' => 'The current user does not have permission to run this ability.',
				'pt' => 'O usuário atual não tem permissão para executar esta ability.',
				'es' => 'El usuario actual no tiene permiso para ejecutar esta ability.',
			),
			'Voce nao tem permissao para criar este tipo de conteudo.' => array(
				'en' => 'You do not have permission to create this type of content.',
				'pt' => 'Você não tem permissão para criar este tipo de conteúdo.',
				'es' => 'No tienes permiso para crear este tipo de contenido.',
			),
			'Voce nao tem permissao para editar este post.' => array(
				'en' => 'You do not have permission to edit this post.',
				'pt' => 'Você não tem permissão para editar este post.',
				'es' => 'No tienes permiso para editar esta entrada.',
			),
			'Voce nao tem permissao para publicar este post.' => array(
				'en' => 'You do not have permission to publish this post.',
				'pt' => 'Você não tem permissão para publicar este post.',
				'es' => 'No tienes permiso para publicar esta entrada.',
			),
			'Voce nao tem permissao para alterar o titulo deste conteudo.' => array(
				'en' => 'You do not have permission to change this content title.',
				'pt' => 'Você não tem permissão para alterar o título deste conteúdo.',
				'es' => 'No tienes permiso para cambiar el título de este contenido.',
			),
			'Voce nao tem permissao para moderar este comentario.' => array(
				'en' => 'You do not have permission to moderate this comment.',
				'pt' => 'Você não tem permissão para moderar este comentário.',
				'es' => 'No tienes permiso para moderar este comentario.',
			),
			'Nao foi possivel atualizar o comentario.' => array(
				'en' => 'Could not update the comment.',
				'pt' => 'Não foi possível atualizar o comentário.',
				'es' => 'No fue posible actualizar el comentario.',
			),
			'Acao de comentario invalida.' => array(
				'en' => 'Invalid comment action.',
				'pt' => 'Ação de comentário inválida.',
				'es' => 'Acción de comentario inválida.',
			),
			'Voce e um assistente editorial de WordPress. Gere apenas conteudo revisavel, sem publicar nada.' => array(
				'en' => 'You are a WordPress editorial assistant. Generate only reviewable content, without publishing anything.',
				'pt' => 'Você é um assistente editorial de WordPress. Gere apenas conteúdo revisável, sem publicar nada.',
				'es' => 'Eres un asistente editorial de WordPress. Genera solo contenido revisable, sin publicar nada.',
			),
			'Voce e um assistente de moderacao. Recomende acoes, mas nao diga que executou moderacao.' => array(
				'en' => 'You are a moderation assistant. Recommend actions, but do not say that moderation was executed.',
				'pt' => 'Você é um assistente de moderação. Recomende ações, mas não diga que executou moderação.',
				'es' => 'Eres un asistente de moderación. Recomienda acciones, pero no digas que ejecutaste moderación.',
			),
			'Voce e um assistente de manutencao WordPress. Recomende uma ordem segura de revisao, mas nao afirme que atualizou plugins.' => array(
				'en' => 'You are a WordPress maintenance assistant. Recommend a safe review order, but do not claim that plugins were updated.',
				'pt' => 'Você é um assistente de manutenção WordPress. Recomende uma ordem segura de revisão, mas não afirme que atualizou plugins.',
				'es' => 'Eres un asistente de mantenimiento de WordPress. Recomienda un orden seguro de revisión, pero no afirmes que actualizaste plugins.',
			),
		);
	}
}
