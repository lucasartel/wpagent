(function () {
	var i18n = window.wpagentChatI18n || {};
	var chatRequestTimeoutMs = 70000;

	function t(key, fallback) {
		return i18n[key] || fallback;
	}

	function uuid() {
		if (window.crypto && window.crypto.randomUUID) {
			return window.crypto.randomUUID();
		}
		return 'wpagent-' + Date.now() + '-' + Math.random().toString(16).slice(2);
	}

	function responseLooksHtml(response, text) {
		var contentType = response.headers && response.headers.get ? response.headers.get('content-type') || '' : '';
		if (contentType.indexOf('text/html') !== -1) {
			return true;
		}

		return /^\s*(<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>])/i.test(text || '');
	}

	function compactErrorText(text) {
		var cleaned = String(text || '')
			.replace(/<script[\s\S]*?<\/script>/gi, ' ')
			.replace(/<style[\s\S]*?<\/style>/gi, ' ')
			.replace(/<[^>]+>/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();

		if (!cleaned) {
			return '';
		}

		return cleaned.length > 220 ? cleaned.slice(0, 217) + '...' : cleaned;
	}

	function responseErrorMessage(response, body, text, isHtml) {
		var status = response.status || 0;

		if (status === 504) {
			return t('serverTimeoutError', 'O servidor demorou demais para responder. Tente novamente em instantes ou use uma pergunta mais curta.');
		}

		if (status === 502 || status === 503) {
			return t('serverUnavailableError', 'O servidor ou provedor de IA ficou indisponível por alguns instantes. Tente novamente.');
		}

		if (isHtml) {
			return t('invalidServerResponse', 'O servidor retornou uma página de erro em vez de uma resposta do WPAgent. Tente novamente em instantes.');
		}

		return body.message || compactErrorText(text) || (t('genericErrorPrefix', 'Erro no WPAgent. Status ') + status);
	}

	function request(config, url, options) {
		options = options || {};
		options.headers = options.headers || {};
		options.headers['X-WP-Nonce'] = config.nonce;
		options.credentials = 'same-origin';
		var controller = window.AbortController ? new AbortController() : null;
		var timer = null;

		if (controller) {
			options.signal = controller.signal;
			timer = window.setTimeout(function () {
				controller.abort();
			}, chatRequestTimeoutMs);
		}

		return fetch(url, options).then(function (response) {
			if (timer) {
				window.clearTimeout(timer);
			}
			return response.text().then(function (text) {
				var body = {};
				var isHtml = responseLooksHtml(response, text);
				if (text && !isHtml) {
					try {
						body = JSON.parse(text);
					} catch (error) {
						body = { message: compactErrorText(text) };
					}
				}
				if (!response.ok) {
					throw new Error(responseErrorMessage(response, body, text, isHtml));
				}
				if (isHtml) {
					throw new Error(t('invalidServerResponse', 'O servidor retornou uma página de erro em vez de uma resposta do WPAgent. Tente novamente em instantes.'));
				}
				return body;
			});
		}).catch(function (error) {
			if (timer) {
				window.clearTimeout(timer);
			}
			if (error.name === 'AbortError') {
				throw new Error(t('serverTimeoutError', 'O servidor demorou demais para responder. Tente novamente em instantes ou use uma pergunta mais curta.'));
			}
			throw error;
		});
	}

	function formatDate(value) {
		if (!value) {
			return '';
		}

		var date = new Date(String(value).replace(' ', 'T'));
		if (Number.isNaN(date.getTime())) {
			return '';
		}

		return date.toLocaleDateString(undefined, {
			day: '2-digit',
			month: 'short'
		});
	}

	function setup(chat) {
		try {
			i18n = JSON.parse(chat.getAttribute('data-i18n') || '{}') || i18n;
		} catch (error) {}

		var form = chat.querySelector('.wpagent-chat__form');
		var textarea = form.querySelector('textarea');
		var messages = chat.querySelector('.wpagent-chat__messages');
		var list = chat.querySelector('.wpagent-chat__conversations');
		var newButtons = chat.querySelectorAll('.wpagent-chat__new-conversation');
		var renameButton = chat.querySelector('.wpagent-chat__rename-conversation');
		var title = chat.querySelector('.wpagent-chat__conversation-title');
		var status = chat.querySelector('.wpagent-chat__status');
		var submitButton = form.querySelector('button[type="submit"]');
		var toggleButtons = chat.querySelectorAll('.wpagent-chat__toggle-conversations');
		var themeButton = chat.querySelector('.wpagent-chat__theme-toggle');
		var profilePanel = chat.querySelector('[data-wpagent-profile]');
		var profileTextarea = profilePanel ? profilePanel.querySelector('[data-profile-free]') : null;
		var profileFields = profilePanel ? profilePanel.querySelectorAll('[data-profile-key]') : [];
		var profileSaveButton = profilePanel ? profilePanel.querySelector('.wpagent-chat__profile-save') : null;
		var profileStatus = profilePanel ? profilePanel.querySelector('.wpagent-chat__profile-status') : null;
		var agent = chat.getAttribute('data-agent') || 'default';
		var themeStorageKey = 'wpagent-theme-' + agent;
		var guestStorageKey = 'wpagent-guest-chat-' + agent;
		var config = {
			restUrl: chat.getAttribute('data-rest-url') || '',
			conversationsUrl: chat.getAttribute('data-conversations-url') || '',
			profileUrl: chat.getAttribute('data-profile-url') || '',
			abilitiesUrl: chat.getAttribute('data-abilities-url') || '',
			emailActionsUrl: chat.getAttribute('data-email-actions-url') || '',
			nonce: chat.getAttribute('data-nonce') || '',
			isLoggedIn: chat.getAttribute('data-logged-in') === '1',
			userProfileEnabled: chat.getAttribute('data-user-profile-enabled') === '1'
		};
		var storedGuestState = loadGuestState();
		var sessionId = storedGuestState.sessionId || uuid();
		var conversationId = '';
		var conversations = [];

		function applyTheme(theme) {
			var isDark = theme === 'dark';
			chat.classList.toggle('is-dark-mode', isDark);
			if (themeButton) {
				themeButton.textContent = isDark ? t('lightMode', 'Modo claro') : t('darkMode', 'Modo escuro');
				themeButton.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			}
		}

		function loadTheme() {
			var stored = '';
			try {
				stored = window.localStorage.getItem(themeStorageKey) || '';
			} catch (error) {}

			applyTheme(stored === 'dark' ? 'dark' : 'light');
		}

		function toggleTheme() {
			var next = chat.classList.contains('is-dark-mode') ? 'light' : 'dark';
			applyTheme(next);
			try {
				window.localStorage.setItem(themeStorageKey, next);
			} catch (error) {}
		}

		function setStatus(text) {
			if (status) {
				status.textContent = text;
			}
		}

		function setProfileStatus(text) {
			if (profileStatus) {
				profileStatus.textContent = text;
			}
		}

		function setEmpty() {
			messages.innerHTML = '';
			var empty = document.createElement('div');
			empty.className = 'wpagent-chat__empty';

			var heading = document.createElement('strong');
			heading.textContent = t('emptyTitle', 'Como posso ajudar?');
			empty.appendChild(heading);

			var text = document.createElement('span');
			text.textContent = t('emptyText', 'Escreva uma pergunta ou escolha uma conversa anterior.');
			empty.appendChild(text);

			messages.appendChild(empty);
		}

		function loadGuestState() {
			if (chat.getAttribute('data-logged-in') === '1') {
				return {};
			}

			try {
				var raw = window.localStorage.getItem(guestStorageKey);
				var parsed = raw ? JSON.parse(raw) : {};
				return parsed && typeof parsed === 'object' ? parsed : {};
			} catch (error) {
				return {};
			}
		}

		function saveGuestState() {
			if (config.isLoggedIn) {
				return;
			}

			var storedMessages = [];
			messages.querySelectorAll('.wpagent-chat__message').forEach(function (item) {
				if (item.classList.contains('wpagent-chat__message--pending')) {
					return;
				}

				var role = item.classList.contains('wpagent-chat__message--user') ? 'user' : 'agent';
				var text = messageText(item).trim();
				if (text) {
					storedMessages.push({
						role: role,
						text: text
					});
				}
			});

			try {
				window.localStorage.setItem(guestStorageKey, JSON.stringify({
					sessionId: sessionId,
					messages: storedMessages.slice(-80),
					updatedAt: Date.now()
				}));
			} catch (error) {}
		}

		function clearGuestState() {
			if (config.isLoggedIn) {
				return;
			}

			try {
				window.localStorage.removeItem(guestStorageKey);
			} catch (error) {}
		}

		function restoreGuestConversation() {
			if (config.isLoggedIn || !storedGuestState.messages || !storedGuestState.messages.length) {
				return false;
			}

			messages.innerHTML = '';
			storedGuestState.messages.forEach(function (item) {
				if (!item || !item.text) {
					return;
				}

				addMessage(item.role === 'user' ? 'user' : 'agent', item.text);
			});
			setStatus(t('ready', 'Pronto para conversar'));
			return true;
		}

		function isMobileLayout() {
			return window.matchMedia && window.matchMedia('(max-width: 782px)').matches;
		}

		function updateConversationToggleState() {
			var expanded = isMobileLayout() ? chat.classList.contains('is-conversation-list-open') : !chat.classList.contains('is-sidebar-collapsed');
			toggleButtons.forEach(function (button) {
				button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			});
		}

		function ensureConversationPanel(open) {
			if (isMobileLayout()) {
				chat.classList.toggle('is-conversation-list-open', open);
			} else {
				chat.classList.toggle('is-sidebar-collapsed', !open);
			}
			updateConversationToggleState();
		}

		function toggleConversationPanel() {
			if (isMobileLayout()) {
				ensureConversationPanel(!chat.classList.contains('is-conversation-list-open'));
				return;
			}

			ensureConversationPanel(chat.classList.contains('is-sidebar-collapsed'));
		}

		function closeConversationPanelOnMobile() {
			if (isMobileLayout()) {
				ensureConversationPanel(false);
			}
		}

		function addMessage(role, text, extraClass) {
			var empty = messages.querySelector('.wpagent-chat__empty');
			if (empty) {
				empty.remove();
			}

			var item = document.createElement('div');
			item.className = 'wpagent-chat__message wpagent-chat__message--' + role;
			if (extraClass) {
				item.className += ' ' + extraClass;
			}

			var content = document.createElement('span');
			content.className = 'wpagent-chat__message-content';
			content.textContent = displayMessageText(text);
			item.appendChild(content);

			if (role === 'agent') {
				item.appendChild(createCopyButton(item));
				updateExportButton(item, text);
			}

			messages.appendChild(item);
			item.scrollIntoView({ block: 'end', behavior: 'smooth' });
			saveGuestState();
			return item;
		}

		function createExportButton(message) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'wpagent-chat__export-message';
			button.setAttribute('aria-label', t('exportRtf', 'Baixar RTF'));
			button.setAttribute('title', t('exportRtf', 'Baixar RTF'));
			button.textContent = 'RTF';
			button.addEventListener('click', function () {
				exportMessageAsRtf(message, button);
			});
			return button;
		}

		function createCopyButton(message) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'wpagent-chat__copy-message';
			button.setAttribute('aria-label', t('copyMessage', 'Copiar resposta'));
			button.setAttribute('title', t('copyMessage', 'Copiar resposta'));
			button.appendChild(document.createElement('span'));
			button.addEventListener('click', function () {
				copyMessageText(message, button);
			});
			return button;
		}

		function messageText(item) {
			var content = item.querySelector('.wpagent-chat__message-content');
			return content ? content.textContent : item.textContent;
		}

		function setMessageText(item, text) {
			var content = item.querySelector('.wpagent-chat__message-content');
			if (content) {
				content.textContent = displayMessageText(text);
				updateExportButton(item, text);
				saveGuestState();
				return;
			}
			item.textContent = displayMessageText(text);
			updateExportButton(item, text);
			saveGuestState();
		}

		function copyMessageText(message, button) {
			var text = messageText(message);
			var copied = function () {
				var previous = button.getAttribute('aria-label');
				button.classList.add('is-copied');
				button.setAttribute('aria-label', t('copiedMessage', 'Copiado'));
				button.setAttribute('title', t('copiedMessage', 'Copiado'));
				window.setTimeout(function () {
					button.classList.remove('is-copied');
					button.setAttribute('aria-label', previous || t('copyMessage', 'Copiar resposta'));
					button.setAttribute('title', previous || t('copyMessage', 'Copiar resposta'));
				}, 1400);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(copied).catch(function () {
					fallbackCopy(text, copied, button);
				});
				return;
			}

			fallbackCopy(text, copied, button);
		}

		function fallbackCopy(text, done, button) {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', 'readonly');
			area.style.position = 'fixed';
			area.style.left = '-9999px';
			document.body.appendChild(area);
			area.select();
			try {
				document.execCommand('copy');
				done();
			} catch (error) {
				button.setAttribute('title', t('copyError', 'Nao foi possivel copiar'));
			}
			document.body.removeChild(area);
		}

		function exportMessageAsRtf(message, button) {
			var text = exportMessageText(messageText(message)).trim();
			if (!text) {
				button.setAttribute('title', t('exportRtfError', 'Nao foi possivel gerar o RTF'));
				return;
			}

			var filename = exportFilename(text);
			var blob = new Blob([textToRtf(text)], { type: 'application/rtf' });
			var url = window.URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = filename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			window.setTimeout(function () {
				window.URL.revokeObjectURL(url);
			}, 1000);
			button.setAttribute('title', t('exportedRtf', 'RTF gerado'));
		}

		function updateExportButton(message, rawText) {
			var existing = message.querySelector('.wpagent-chat__export-message');
			if (!shouldOfferExport(rawText || messageText(message))) {
				if (existing) {
					existing.remove();
				}
				return;
			}

			if (!existing) {
				message.appendChild(createExportButton(message));
			}
		}

		function shouldOfferExport(text) {
			var value = String(text || '').toLowerCase();
			return /\[wpagent(?::|_)export(?:_|-)?rtf\]|\[wpagent:rtf\]|<!--\s*wpagent(?::|-)rtf\s*-->/i.test(value)
				|| /bot[aã]o\s+rtf|rtf\s+button|baixar.+rtf|download.+rtf|formato edit[aá]vel.+rtf|editable format.+rtf/i.test(value);
		}

		function displayMessageText(text) {
			return stripExportMarkers(text);
		}

		function exportMessageText(text) {
			return stripExportInstruction(stripExportMarkers(text));
		}

		function stripExportMarkers(text) {
			return String(text || '')
				.replace(/\[wpagent(?::|_)export(?:_|-)?rtf\]/gi, '')
				.replace(/\[wpagent:rtf\]/gi, '')
				.replace(/<!--\s*wpagent(?::|-)rtf\s*-->/gi, '')
				.trim();
		}

		function stripExportInstruction(text) {
			return String(text || '').split(/\r?\n/).filter(function (line) {
				return !/bot[aã]o\s+rtf|rtf\s+button|baixar.+rtf|download.+rtf|formato edit[aá]vel.+rtf|editable format.+rtf/i.test(line);
			}).join('\n').trim();
		}

		function exportFilename(text) {
			var firstLine = (text.split(/\r?\n/).find(function (line) {
				return line.trim();
			}) || t('conversation', 'Conversa')).replace(/^#+\s*/, '').trim();

			return slugify(firstLine).slice(0, 80) + '.rtf';
		}

		function slugify(text) {
			var value = text.toLowerCase();
			try {
				value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
			} catch (error) {}
			value = value.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
			return value || 'wpagent-export';
		}

		function textToRtf(text) {
			var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
			var body = lines.map(rtfLine).join('');
			return '{\\rtf1\\ansi\\ansicpg1252\\deff0{\\fonttbl{\\f0 Arial;}}\\viewkind4\\uc1\\pard\\fs24 ' + body + '}';
		}

		function rtfLine(line) {
			var clean = line.trim();
			if (!clean) {
				return '\\par\n';
			}

			var heading = clean.match(/^(#{1,3})\s+(.+)$/);
			if (heading) {
				return '\\b\\fs32 ' + escapeRtf(heading[2]) + '\\b0\\fs24\\par\n';
			}

			var bullet = clean.match(/^[-*]\s+(.+)$/);
			if (bullet) {
				return '\\bullet\\tab ' + escapeRtf(bullet[1]) + '\\par\n';
			}

			var numbered = clean.match(/^(\d+[.)])\s+(.+)$/);
			if (numbered) {
				return escapeRtf(numbered[1] + ' ' + numbered[2]) + '\\par\n';
			}

			if (clean.length < 90 && /:$/.test(clean)) {
				return '\\b ' + escapeRtf(clean) + '\\b0\\par\n';
			}

			return escapeRtf(clean) + '\\par\n';
		}

		function escapeRtf(text) {
			var out = '';
			for (var index = 0; index < text.length; index++) {
				var char = text.charAt(index);
				if (char === '\\' || char === '{' || char === '}') {
					out += '\\\\' + char;
					continue;
				}

				var cp1252 = cp1252Code(char);
				if (cp1252 !== null) {
					out += cp1252 < 128 ? char : "\\'" + hexByte(cp1252);
					continue;
				}

				var code = text.codePointAt(index);
				if (code > 65535) {
					index++;
				}

				out += '?';
			}
			return out;
		}

		function cp1252Code(char) {
			var code = char.charCodeAt(0);
			var map = {
				'\u20ac': 0x80,
				'\u201a': 0x82,
				'\u0192': 0x83,
				'\u201e': 0x84,
				'\u2026': 0x85,
				'\u2020': 0x86,
				'\u2021': 0x87,
				'\u02c6': 0x88,
				'\u2030': 0x89,
				'\u0160': 0x8a,
				'\u2039': 0x8b,
				'\u0152': 0x8c,
				'\u017d': 0x8e,
				'\u2018': 0x91,
				'\u2019': 0x92,
				'\u201c': 0x93,
				'\u201d': 0x94,
				'\u2022': 0x95,
				'\u2013': 0x96,
				'\u2014': 0x97,
				'\u02dc': 0x98,
				'\u2122': 0x99,
				'\u0161': 0x9a,
				'\u203a': 0x9b,
				'\u0153': 0x9c,
				'\u017e': 0x9e,
				'\u0178': 0x9f
			};

			if (Object.prototype.hasOwnProperty.call(map, char)) {
				return map[char];
			}

			if ((code >= 0 && code <= 0x7f) || (code >= 0xa0 && code <= 0xff)) {
				return code;
			}

			return null;
		}

		function hexByte(value) {
			var hex = value.toString(16);
			return hex.length === 1 ? '0' + hex : hex;
		}

		function markError(item, text) {
			item.className = 'wpagent-chat__message wpagent-chat__message--agent wpagent-chat__message--error';
			setMessageText(item, text);
		}

		function addAbilityProposal(message, proposal) {
			if (!proposal || !proposal.ability || !config.abilitiesUrl) {
				return;
			}

			var card = document.createElement('div');
			var heading = document.createElement('strong');
			var details = document.createElement('span');
			var input = document.createElement('pre');
			var button = document.createElement('button');

			card.className = 'wpagent-chat__ability-proposal';
			heading.textContent = t('abilityProposal', 'Acao proposta') + ': ' + (proposal.label || proposal.ability);
			details.textContent = proposal.reason || proposal.description || proposal.ability;
			input.textContent = JSON.stringify(proposal.input || {}, null, 2);
			button.type = 'button';
			button.textContent = t('runAbility', 'Executar acao');

			button.addEventListener('click', function () {
				if (!window.confirm(t('confirmAbility', 'Executar esta acao no WordPress?'))) {
					return;
				}

				button.disabled = true;
				setStatus(t('runningAbility', 'Executando acao...'));
				request(config, config.abilitiesUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						agent_slug: agent,
						ability: proposal.ability,
						input: proposal.input || {}
					})
				}).then(function (body) {
					card.classList.add('is-executed');
					button.textContent = t('abilityExecuted', 'Acao executada');
					setStatus(t('abilityExecuted', 'Acao executada'));
					addMessage('agent', formatAbilityResult(body));
				}).catch(function (error) {
					button.disabled = false;
					setStatus(t('abilityError', 'Erro ao executar acao'));
					addMessage('agent', error.message, 'wpagent-chat__message--error');
				});
			});

			card.appendChild(heading);
			card.appendChild(details);
			card.appendChild(input);
			card.appendChild(button);
			message.appendChild(card);
		}

		function addEmailProposal(message, proposal, body) {
			if (!proposal || !proposal.to_email || !config.emailActionsUrl) {
				return;
			}

			var card = document.createElement('div');
			var heading = document.createElement('strong');
			var details = document.createElement('span');
			var preview = document.createElement('pre');
			var button = document.createElement('button');

			card.className = 'wpagent-chat__email-proposal';
			heading.textContent = t('emailProposal', 'Email preparado') + ': ' + (proposal.subject || proposal.to_email);
			details.textContent = (proposal.to_name ? proposal.to_name + ' <' + proposal.to_email + '>' : proposal.to_email);
			preview.textContent = (proposal.body || '').slice(0, 900);
			button.type = 'button';
			button.textContent = t('sendEmail', 'Enviar email');

			button.addEventListener('click', function () {
				if (!window.confirm(t('confirmEmail', 'Enviar este email agora?'))) {
					return;
				}

				button.disabled = true;
				setStatus(t('sendingEmail', 'Enviando email...'));
				request(config, config.emailActionsUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						agent_slug: agent,
						proposal: proposal,
						session_id: sessionId,
						conversation_id: conversationId,
						interaction_id: body && body.interaction_id ? body.interaction_id : 0
					})
				}).then(function (result) {
					card.classList.add('is-executed');
					button.textContent = t('emailQueued', 'Email agendado');
					setStatus(t('emailQueued', 'Email agendado'));
					addMessage('agent', formatEmailResult(result));
				}).catch(function (error) {
					button.disabled = false;
					setStatus(t('emailError', 'Erro ao enviar email'));
					addMessage('agent', error.message, 'wpagent-chat__message--error');
				});
			});

			card.appendChild(heading);
			card.appendChild(details);
			card.appendChild(preview);
			card.appendChild(button);
			message.appendChild(card);
		}

		function formatEmailResult(body) {
			if (!body || (!body.sent && !body.queued)) {
				return t('emailError', 'Erro ao enviar email');
			}

			if (body.queued) {
				return t('emailQueuedFor', 'Email agendado para ') + (body.to_email || '') + '.';
			}

			return t('emailSentTo', 'Email enviado para ') + (body.to_email || '') + '.';
		}

		function formatAbilityResult(body) {
			var ability = body && body.ability ? body.ability : {};
			var result = body && Object.prototype.hasOwnProperty.call(body, 'result') ? body.result : {};
			var text = t('abilityExecuted', 'Acao executada') + ': ' + (ability.label || ability.ability || '');

			if (typeof result === 'string') {
				return text + '\n\n' + result;
			}

			try {
				return text + '\n\n' + JSON.stringify(result, null, 2);
			} catch (error) {
				return text;
			}
		}

		function renderConversations() {
			list.innerHTML = '';

			if (!config.isLoggedIn) {
				var note = document.createElement('div');
				note.className = 'wpagent-chat__conversation-note';
				note.textContent = t('loginToSave', 'Entre na conta para salvar e retomar conversas.');
				list.appendChild(note);
				return;
			}

			if (!conversations.length) {
				var empty = document.createElement('div');
				empty.className = 'wpagent-chat__conversation-note';
				empty.textContent = t('noConversations', 'Nenhuma conversa salva ainda.');
				list.appendChild(empty);
				return;
			}

			conversations.forEach(function (conversation) {
				var row = document.createElement('div');
				var button = document.createElement('button');
				var deleteButton = document.createElement('button');
				var label = conversation.title || t('conversation', 'Conversa');
				row.className = 'wpagent-chat__conversation-row';
				button.type = 'button';
				button.className = 'wpagent-chat__conversation';
				button.setAttribute('aria-label', t('openConversation', 'Abrir conversa ') + label);
				if (conversation.conversation_id === conversationId) {
					button.className += ' is-active';
				}

				var name = document.createElement('span');
				name.className = 'wpagent-chat__conversation-title-small';
				name.textContent = label;
				button.appendChild(name);

				var meta = document.createElement('span');
				meta.className = 'wpagent-chat__conversation-meta';
				meta.textContent = formatDate(conversation.updated_at || conversation.created_at) || t('savedConversation', 'Conversa salva');
				button.appendChild(meta);

				button.addEventListener('click', function () {
					selectConversation(conversation.conversation_id, label);
					closeConversationPanelOnMobile();
				});

				deleteButton.type = 'button';
				deleteButton.className = 'wpagent-chat__delete-conversation';
				deleteButton.setAttribute('aria-label', t('deleteConversation', 'Apagar conversa ') + label);
				deleteButton.textContent = 'x';
				deleteButton.addEventListener('click', function () {
					deleteConversation(conversation.conversation_id, label);
				});

				row.appendChild(button);
				row.appendChild(deleteButton);
				list.appendChild(row);
			});
		}

		function loadConversations() {
			if (!config.isLoggedIn || !config.conversationsUrl) {
				renderConversations();
				return Promise.resolve();
			}

			setStatus(t('loadingConversations', 'Carregando conversas...'));
			return request(config, config.conversationsUrl + '?agent_slug=' + encodeURIComponent(agent))
				.then(function (body) {
					conversations = body.conversations || [];
					renderConversations();
					setStatus(t('ready', 'Pronto para conversar'));
					if (!conversationId && conversations.length) {
						return selectConversation(conversations[0].conversation_id, conversations[0].title);
					}
				})
				.catch(function (error) {
					setStatus(t('cannotLoadConversations', 'Nao foi possivel carregar conversas'));
					addMessage('agent', error.message);
				});
		}

		function selectConversation(id, label) {
			conversationId = id;
			title.textContent = label || t('conversation', 'Conversa');
			setStatus(t('loadingMessages', 'Carregando mensagens...'));
			messages.innerHTML = '';
			renderConversations();

			return request(config, config.conversationsUrl + '/' + encodeURIComponent(id) + '?agent_slug=' + encodeURIComponent(agent))
				.then(function (body) {
					messages.innerHTML = '';
					(body.messages || []).forEach(function (item) {
						addMessage('user', item.message || '');
						addMessage('agent', item.reply || '');
					});
					if (!body.messages || !body.messages.length) {
						setEmpty();
					}
					setStatus(t('ready', 'Pronto para conversar'));
				})
				.catch(function (error) {
					setStatus(t('loadConversationError', 'Erro ao carregar conversa'));
					addMessage('agent', error.message);
				});
		}

		function createConversation() {
			messages.innerHTML = '';
			setEmpty();
			title.textContent = t('newConversation', 'Nova conversa');
			setStatus(t('newConversationStarted', 'Nova conversa iniciada'));

			if (!config.isLoggedIn || !config.conversationsUrl) {
				conversationId = '';
				sessionId = uuid();
				clearGuestState();
				renderConversations();
				closeConversationPanelOnMobile();
				return Promise.resolve();
			}

			return request(config, config.conversationsUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					agent_slug: agent,
					title: t('newConversation', 'Nova conversa')
				})
			}).then(function (body) {
				var conversation = body.conversation;
				conversationId = conversation.conversation_id;
				title.textContent = conversation.title;
				closeConversationPanelOnMobile();
				return loadConversations();
			}).catch(function (error) {
				setStatus(t('createConversationError', 'Erro ao criar conversa'));
				addMessage('agent', error.message);
			});
		}

		function renameConversation() {
			if (!config.isLoggedIn || !conversationId || !config.conversationsUrl) {
				setStatus(t('loginToRename', 'Entre na conta para renomear conversas'));
				return;
			}

			var current = title.textContent || t('conversation', 'Conversa');
			var next = window.prompt(t('renamePrompt', 'Nome da conversa'), current);
			if (!next || !next.trim()) {
				return;
			}

			setStatus(t('renaming', 'Renomeando conversa...'));
			request(config, config.conversationsUrl + '/' + encodeURIComponent(conversationId), {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					agent_slug: agent,
					title: next.trim()
				})
			}).then(function (body) {
				title.textContent = body.conversation.title;
				setStatus(t('renamed', 'Conversa renomeada'));
				return loadConversations();
			}).catch(function (error) {
				setStatus(t('renameError', 'Erro ao renomear conversa'));
				addMessage('agent', error.message);
			});
		}

		function deleteConversation(id, label) {
			if (!config.isLoggedIn || !id || !config.conversationsUrl) {
				return;
			}

			if (!window.confirm(t('deleteConfirmBefore', 'Apagar a conversa "') + label + t('deleteConfirmAfter', '"?'))) {
				return;
			}

			setStatus(t('deleting', 'Apagando conversa...'));
			request(config, config.conversationsUrl + '/' + encodeURIComponent(id) + '/delete', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					agent_slug: agent
				})
			}).then(function () {
				if (conversationId === id) {
					conversationId = '';
					title.textContent = t('newConversation', 'Nova conversa');
					setEmpty();
				}
				conversations = conversations.filter(function (conversation) {
					return conversation.conversation_id !== id;
				});
				renderConversations();
				setStatus(t('deleted', 'Conversa apagada'));
				if (!conversationId && conversations.length) {
					return selectConversation(conversations[0].conversation_id, conversations[0].title);
				}
			}).catch(function (error) {
				setStatus(t('deleteError', 'Erro ao apagar conversa'));
				addMessage('agent', error.message);
			});
		}

		function loadUserProfile() {
			if (!config.isLoggedIn || !config.userProfileEnabled || !config.profileUrl || !profileTextarea) {
				return Promise.resolve();
			}

			setProfileStatus(t('loadingProfile', 'Carregando perfil...'));
			return request(config, config.profileUrl + '?agent_slug=' + encodeURIComponent(agent))
				.then(function (body) {
					profileTextarea.value = body.content || '';
					setStructuredProfileValues(body.structured || {});
					setProfileStatus(profileHasValue() ? t('profileLoaded', 'Perfil carregado') : t('profileReady', 'Perfil opcional'));
				})
				.catch(function (error) {
					setProfileStatus(error.message || t('profileLoadError', 'Erro ao carregar perfil'));
				});
		}

		function saveUserProfile() {
			if (!config.isLoggedIn || !config.userProfileEnabled || !config.profileUrl || !profileTextarea || !profileSaveButton) {
				return;
			}

			profileSaveButton.disabled = true;
			setProfileStatus(t('savingProfile', 'Salvando perfil...'));
			request(config, config.profileUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					agent_slug: agent,
					content: profileTextarea.value || '',
					structured: structuredProfileValues()
				})
			}).then(function (body) {
				profileTextarea.value = body.content || '';
				setStructuredProfileValues(body.structured || {});
				setProfileStatus(t('profileSaved', 'Perfil salvo'));
			}).catch(function (error) {
				setProfileStatus(error.message || t('profileSaveError', 'Erro ao salvar perfil'));
			}).finally(function () {
				profileSaveButton.disabled = false;
			});
		}

		function structuredProfileValues() {
			var values = {};
			profileFields.forEach(function (field) {
				var key = field.getAttribute('data-profile-key') || '';
				if (key) {
					values[key] = field.value || '';
				}
			});
			return values;
		}

		function setStructuredProfileValues(values) {
			profileFields.forEach(function (field) {
				var key = field.getAttribute('data-profile-key') || '';
				field.value = key && Object.prototype.hasOwnProperty.call(values, key) ? values[key] : '';
			});
		}

		function profileHasValue() {
			if (profileTextarea.value.trim()) {
				return true;
			}

			return Array.prototype.some.call(profileFields, function (field) {
				return field.value.trim();
			});
		}

		function resizeTextarea() {
			textarea.style.height = 'auto';
			textarea.style.height = Math.min(textarea.scrollHeight, 160) + 'px';
		}

		function sendMessage() {
			var message = textarea.value.trim();
			if (!message || submitButton.disabled) {
				return;
			}

			textarea.value = '';
			resizeTextarea();
			addMessage('user', message);
			var pending = addMessage('agent', t('processing', 'Processando'), 'wpagent-chat__message--pending');
			submitButton.disabled = true;
			setStatus(t('processingReply', 'Processando resposta...'));

			if (!config.restUrl || !config.nonce) {
				markError(pending, t('configMissing', 'Configuracao do WPAgent nao encontrada nesta pagina. Atualize a pagina e tente novamente.'));
				submitButton.disabled = false;
				setStatus(t('configError', 'Erro de configuracao'));
				return;
			}

			request(config, config.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					message: message,
					agent_slug: agent,
					session_id: sessionId,
					conversation_id: conversationId
				})
			})
				.then(function (body) {
					pending.classList.remove('wpagent-chat__message--pending');
					setMessageText(pending, body.reply || '');
					addAbilityProposal(pending, body.proposed_ability);
					addEmailProposal(pending, body.proposed_email, body);
					sessionId = body.session_id || sessionId;
					conversationId = body.conversation_id || conversationId;
					saveGuestState();
					setStatus(t('replyReceived', 'Resposta recebida'));
					if (conversationId) {
						return loadConversations();
					}
				})
				.catch(function (error) {
					markError(pending, error.message);
					setStatus(t('replyError', 'Erro ao responder'));
				})
				.finally(function () {
					submitButton.disabled = false;
					textarea.focus();
				});
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			sendMessage();
		});

		textarea.addEventListener('input', resizeTextarea);
		textarea.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' && !event.shiftKey) {
				event.preventDefault();
				sendMessage();
			}
		});

		toggleButtons.forEach(function (button) {
			button.addEventListener('click', toggleConversationPanel);
		});

		if (themeButton) {
			themeButton.addEventListener('click', toggleTheme);
		}

		if (profileSaveButton) {
			profileSaveButton.addEventListener('click', saveUserProfile);
		}

		window.addEventListener('resize', updateConversationToggleState);

		newButtons.forEach(function (button) {
			button.addEventListener('click', createConversation);
		});
		renameButton.addEventListener('click', renameConversation);
		loadTheme();
		setEmpty();
		updateConversationToggleState();
		restoreGuestConversation();
		loadConversations();
		loadUserProfile();
	}

	function setupFloatingAssistant(assistant) {
		var launcher = assistant.querySelector('.wpagent-floating-assistant__launcher');
		var closeButton = assistant.querySelector('.wpagent-floating-assistant__close');
		var panel = assistant.querySelector('.wpagent-floating-assistant__panel');

		if (!launcher || !panel) {
			return;
		}

		function setOpen(open) {
			assistant.classList.toggle('is-open', open);
			launcher.setAttribute('aria-expanded', open ? 'true' : 'false');

			if (open) {
				var textarea = assistant.querySelector('.wpagent-chat__form textarea');
				if (textarea) {
					window.setTimeout(function () {
						textarea.focus();
					}, 80);
				}
			}
		}

		launcher.addEventListener('click', function () {
			setOpen(!assistant.classList.contains('is-open'));
		});

		if (closeButton) {
			closeButton.addEventListener('click', function () {
				setOpen(false);
			});
		}

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && assistant.classList.contains('is-open')) {
				setOpen(false);
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.wpagent-chat').forEach(setup);
		document.querySelectorAll('[data-wpagent-floating]').forEach(setupFloatingAssistant);
	});
})();
