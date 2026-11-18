/* Suilaft Compliance AI — cliente */
(function () {
  'use strict';

  const config = readConfig();
  const $ = (selector, root) => (root || document).querySelector(selector);
  const $$ = (selector, root) => Array.from((root || document).querySelectorAll(selector));

  const state = {
    conversationId: config.conversationId || 0,
    projectId: config.projectId || 0,
    skill: config.skill || '',
    busy: false,
    lastAnswer: null,
    voice: loadPref('suilaft_voice_enabled') === '1',
  };

  const skillsBySlug = {};
  (config.skills || []).forEach((skill) => { skillsBySlug[skill.slug] = skill; });

  const STAGES = {
    queued: 'En cola…',
    processing: 'Preparando la respuesta…',
    triage: 'Revisando el alcance de tu consulta…',
    retrieval: 'Buscando en tus documentos y en la biblioteca normativa…',
    drafting: 'Analizando y redactando la respuesta…',
    compacting: 'Compactando la conversación en memoria…',
  };

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    setupSidebar();
    setupMenus();
    setupSheets();
    setupBanners();
    setupFilters();
    setupShortcuts();
    setupChatMenu();
    setupProjectRowMenu();
    setupAppearancePickers();
    setupKnowledgeUploads();
    setupDocumentDeletes();
    setupComposer();
    setupThread();
    setupExport();
    setupGuided();
    setupVoice();
    resumePendingJob();
  }

  /* ------------------------------------------------------------------------
   * Utilidades
   * ---------------------------------------------------------------------- */

  function readConfig() {
    const node = document.getElementById('appConfig');
    if (!node) return {};
    try { return JSON.parse(node.textContent || '{}'); } catch (e) { return {}; }
  }

  function loadPref(key) {
    try { return window.localStorage.getItem(key); } catch (e) { return null; }
  }

  function savePref(key, value) {
    try { window.localStorage.setItem(key, value); } catch (e) { /* sin almacenamiento */ }
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function escapeAttr(text) {
    return String(text || '').replace(/&(?!amp;|lt;|gt;|quot;|#039;)/g, '&amp;').replace(/"/g, '&quot;');
  }

  function icon(name) {
    const paths = {
      sparkle: '<path d="M9.9 15.5a2 2 0 0 0-1.4-1.4l-6.1-1.6a.5.5 0 0 1 0-1l6.1-1.6a2 2 0 0 0 1.4-1.4l1.6-6.1a.5.5 0 0 1 1 0l1.6 6.1a2 2 0 0 0 1.4 1.4l6.1 1.6a.5.5 0 0 1 0 1l-6.1 1.6a2 2 0 0 0-1.4 1.4l-1.6 6.1a.5.5 0 0 1-1 0z"/>',
      shield: '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
      library: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
      copy: '<rect x="8" y="8" width="14" height="14" rx="2"/><path d="M4 16a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2"/>',
      check: '<path d="M20 6 9 17l-5-5"/>',
      download: '<path d="M12 15V3M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5"/>',
      speaker: '<path d="M11 4.7a.7.7 0 0 0-1.2-.5L6.4 7.6a1.4 1.4 0 0 1-1 .4H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.4a1.4 1.4 0 0 1 1 .4l3.4 3.4a.7.7 0 0 0 1.2-.5z"/><path d="M16 9a5 5 0 0 1 0 6"/>',
      alert: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
      retry: '<path d="M3 12a9 9 0 1 0 9-9 9.8 9.8 0 0 0-6.7 2.7L3 8"/><path d="M3 3v5h5"/>',
      chevron: '<path d="m9 18 6-6-6-6"/>',
      doc: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6M16 13H8M16 17H8M10 9H8"/>',
      book: '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a2.5 2.5 0 0 1 0-5H20"/>',
      grid: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',
      route: '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
      calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
      search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
      chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
      info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
      x: '<path d="M18 6 6 18M6 6l12 12"/>',
    };
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (paths[name] || paths.sparkle) + '</svg>';
  }

  function brandMark() {
    return '<span class="brand-mark brand-mark-sm" aria-hidden="true"><img src="' + escapeHtml(config.brandImage || 'assets/img/escudo-160.png') + '" alt=""></span>';
  }

  async function api(action, options) {
    const opts = options || {};
    const headers = { Accept: 'application/json', 'X-CSRF-Token': config.csrf || '' };
    let body = opts.body;
    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }
    const response = await fetch('index.php?action=' + action + (opts.query || ''), {
      method: opts.method || (body ? 'POST' : 'GET'),
      headers: headers,
      body: body,
      credentials: 'same-origin',
    });
    const text = await response.text();
    let data;
    try { data = JSON.parse(text); } catch (e) { data = { error: 'El servidor no devolvió una respuesta válida.', details: cleanServerText(text) }; }
    return { ok: response.ok, status: response.status, data: data || {} };
  }

  function cleanServerText(text) {
    const value = String(text || '').replace(/<style[\s\S]*?<\/style>/gi, ' ').replace(/<script[\s\S]*?<\/script>/gi, ' ')
      .replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (!value) return 'Respuesta vacía del servidor.';
    return value.length > 600 ? value.slice(0, 600) + '…' : value;
  }

  function errorMessage(data, fallback) {
    if (!data) return fallback || 'Algo salió mal.';
    return data.error || fallback || 'Algo salió mal.';
  }

  function toast(message, tone) {
    const region = $('#toastRegion');
    if (!region) return;
    const node = document.createElement('div');
    node.className = 'toast' + (tone ? ' toast-' + tone : '');
    node.setAttribute('role', tone === 'error' ? 'alert' : 'status');
    node.innerHTML = icon(tone === 'error' ? 'alert' : tone === 'success' ? 'check' : 'info') + '<span></span>';
    node.querySelector('span').textContent = message;
    region.appendChild(node);
    window.setTimeout(() => { node.remove(); }, tone === 'error' ? 6500 : 3800);
  }

  function setStatus(text) {
    const pill = $('#statusPill');
    if (!pill) return;
    if (!text) { pill.hidden = true; return; }
    $('#statusText').textContent = text;
    pill.hidden = false;
  }

  function sleep(ms) { return new Promise((resolve) => window.setTimeout(resolve, ms)); }

  /* ------------------------------------------------------------------------
   * Sidebar
   * ---------------------------------------------------------------------- */

  function setupSidebar() {
    const shell = $('#shell');
    const toggle = $('#sidebarToggle');
    const scrim = $('#sidebarScrim');
    if (!shell || !toggle) return;

    const mobile = () => window.matchMedia('(max-width: 860px)').matches;
    const sync = () => {
      const open = mobile() ? shell.classList.contains('sidebar-open') : !shell.classList.contains('sidebar-collapsed');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (scrim) scrim.hidden = !(mobile() && open);
    };

    if (loadPref('suilaft_sidebar') === 'collapsed') shell.classList.add('sidebar-collapsed');
    sync();

    toggle.addEventListener('click', toggleSidebar);
    const collapse = $('#sidebarCollapse');
    if (collapse) collapse.addEventListener('click', toggleSidebar);
    setupSidebarSearch();
    if (scrim) scrim.addEventListener('click', () => { shell.classList.remove('sidebar-open'); sync(); });
    window.addEventListener('resize', sync);

    function toggleSidebar() {
      if (mobile()) {
        shell.classList.toggle('sidebar-open');
      } else {
        shell.classList.toggle('sidebar-collapsed');
        savePref('suilaft_sidebar', shell.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open');
      }
      sync();
    }

    setupSidebar.toggle = toggleSidebar;
    setupSidebar.closeMobile = () => { shell.classList.remove('sidebar-open'); sync(); };
  }

  function setupSidebarSearch() {
    const toggle = $('#sidebarSearchToggle');
    const wrap = $('#sidebarSearchWrap');
    const input = $('#sidebarSearch');
    const scroll = $('#sidebarScroll');
    if (!toggle || !wrap || !input || !scroll) return;

    const filter = () => {
      const query = fold(input.value.trim());
      let visible = 0;
      $$('[data-filter-text]', scroll).forEach((row) => {
        const match = !query || row.getAttribute('data-filter-text').indexOf(query) !== -1;
        row.hidden = !match;
        if (match) visible++;
      });
      $$('.sidebar-group', scroll).forEach((group) => {
        const rows = $$('[data-filter-text]', group);
        group.hidden = !!query && rows.length > 0 && rows.every((row) => row.hidden);
        if (query && rows.length === 0) group.hidden = true;
      });
      const empty = $('#sidebarSearchEmpty');
      if (empty) empty.hidden = !query || visible > 0;
    };
    const open = () => { wrap.hidden = false; input.focus(); };
    const close = () => { wrap.hidden = true; input.value = ''; filter(); };

    toggle.addEventListener('click', () => (wrap.hidden ? open() : close()));
    input.addEventListener('input', filter);
    input.addEventListener('keydown', (event) => { if (event.key === 'Escape') { event.stopPropagation(); close(); } });
    setupSidebarSearch.open = open;
  }

  function setupShortcuts() {
    document.addEventListener('keydown', (event) => {
      const mod = event.ctrlKey || event.metaKey;
      if (mod && (event.key === 'k' || event.key === 'K')) {
        event.preventDefault();
        if (setupSidebarSearch.open) setupSidebarSearch.open();
      }
      if (mod && event.key === '\\') {
        event.preventDefault();
        if (setupSidebar.toggle) setupSidebar.toggle();
      }
      if (mod && event.shiftKey && (event.key === 'o' || event.key === 'O')) {
        event.preventDefault();
        window.location.href = $('#newChatLink') ? $('#newChatLink').getAttribute('href') : 'index.php';
      }
      if (event.key === 'Escape') {
        closeMenus();
        if (setupSidebar.closeMobile) setupSidebar.closeMobile();
      }
    });
  }

  /* ------------------------------------------------------------------------
   * Menús y hojas
   * ---------------------------------------------------------------------- */

  const menuPairs = [
    ['#plusButton', '#plusMenu'],
    ['#skillButton', '#skillMenu'],
    ['#accountButton', '#accountMenu'],
    ['#projectMenuButton', '#projectMenu'],
  ];

  function setupMenus() {
    menuPairs.forEach(([buttonSel, menuSel]) => {
      const button = $(buttonSel);
      const menu = $(menuSel);
      if (!button || !menu) return;
      button.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = menu.hidden;
        closeMenus();
        if (willOpen) {
          if (menuSel === '#skillMenu') renderSkillMenu();
          menu.hidden = false;
          button.setAttribute('aria-expanded', 'true');
          const first = menu.querySelector('.menu-item');
          if (first && event.detail === 0) first.focus();
        }
      });
      menu.addEventListener('click', (event) => event.stopPropagation());
      menu.addEventListener('keydown', (event) => menuKeys(event, menu));
    });

    document.addEventListener('click', closeMenus);
  }

  function menuKeys(event, menu) {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
    const items = $$('.menu-item', menu);
    const index = items.indexOf(document.activeElement);
    const next = event.key === 'ArrowDown' ? Math.min(items.length - 1, index + 1) : Math.max(0, index - 1);
    if (items[next]) { items[next].focus(); event.preventDefault(); }
  }

  function closeMenus() {
    menuPairs.forEach(([buttonSel, menuSel]) => {
      const button = $(buttonSel);
      const menu = $(menuSel);
      if (menu) menu.hidden = true;
      if (button) button.setAttribute('aria-expanded', 'false');
    });
    const projectRowMenu = $('#projectRowMenu');
    if (projectRowMenu) projectRowMenu.hidden = true;
    const chatMenu = $('#chatMenu');
    if (chatMenu && !chatMenu.hidden) {
      chatMenu.hidden = true;
      $$('[data-chat-menu][aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    }
  }

  function setupSheets() {
    document.addEventListener('click', (event) => {
      const opener = event.target.closest('[data-open-sheet]');
      if (opener) {
        closeMenus();
        openSheet(opener.getAttribute('data-open-sheet'));
        return;
      }
      const closer = event.target.closest('[data-close-sheet]');
      if (closer) {
        const dialog = closer.closest('dialog');
        if (dialog) dialog.close();
      }
    });

    $$('dialog.sheet').forEach((dialog) => {
      // Clic fuera de la tarjeta cierra la hoja.
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
      });
    });
  }

  function openSheet(id) {
    const dialog = document.getElementById(id);
    if (!dialog || typeof dialog.showModal !== 'function') return null;
    dialog.showModal();
    const focusTarget = dialog.querySelector('[autofocus]') || dialog.querySelector('input:not([type=hidden]), textarea, select');
    if (focusTarget) window.setTimeout(() => focusTarget.focus(), 30);
    return dialog;
  }

  function confirmAction(title, text, acceptLabel) {
    return new Promise((resolve) => {
      const dialog = $('#confirmSheet');
      if (!dialog) { resolve(window.confirm(title)); return; }
      $('#confirmTitle').textContent = title;
      $('#confirmText').textContent = text || '';
      const accept = $('#confirmAccept');
      accept.textContent = acceptLabel || 'Eliminar';
      let accepted = false;
      const onAccept = () => { accepted = true; dialog.close(); };
      accept.addEventListener('click', onAccept, { once: true });
      dialog.addEventListener('close', () => {
        accept.removeEventListener('click', onAccept);
        resolve(accepted);
      }, { once: true });
      openSheet('confirmSheet');
    });
  }

  function setupBanners() {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-dismiss-banner]');
      if (button) {
        const wrap = button.closest('.banner-wrap');
        if (wrap) wrap.remove();
      }
    });
  }

  function setupFilters() {
    const searchToggle = $('#projectSearchToggle');
    if (searchToggle) {
      searchToggle.addEventListener('click', () => {
        const wrap = $('#projectSearchWrap');
        const input = $('#projectSearch');
        const open = wrap.classList.toggle('is-open');
        if (open) { input.focus(); } else { input.value = ''; input.dispatchEvent(new Event('input')); }
      });
    }

    $$('[data-filter-target]').forEach((input) => {
      const list = $(input.getAttribute('data-filter-target'));
      const empty = list ? document.getElementById(list.id + 'Empty') : null;
      if (!list) return;
      input.addEventListener('input', () => {
        const query = fold(input.value.trim());
        let visible = 0;
        $$('[data-filter-text]', list).forEach((item) => {
          const match = !query || item.getAttribute('data-filter-text').indexOf(query) !== -1;
          item.hidden = !match;
          if (match) visible++;
        });
        if (empty) empty.hidden = visible > 0;
      });
    });
  }

  function fold(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  /* ------------------------------------------------------------------------
   * Menú contextual de chats (renombrar, mover, eliminar)
   * ---------------------------------------------------------------------- */

  function setupChatMenu() {
    const menu = $('#chatMenu');
    if (!menu) return;
    let target = null;

    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-chat-menu]');
      if (!button) return;
      event.preventDefault();
      event.stopPropagation();
      const wasOpen = !menu.hidden && target && target.button === button;
      closeMenus();
      if (wasOpen) return;
      target = {
        button: button,
        id: Number(button.getAttribute('data-chat-menu')),
        title: button.getAttribute('data-chat-title') || '',
        projectId: Number(button.getAttribute('data-chat-project') || 0),
        pinned: button.getAttribute('data-chat-pinned') === '1',
      };
      const pinLabel = $('[data-pin-label]', menu);
      if (pinLabel) pinLabel.textContent = target.pinned ? 'Desfijar' : 'Fijar';
      const rect = button.getBoundingClientRect();
      menu.hidden = false;
      const width = menu.offsetWidth;
      const height = menu.offsetHeight;
      let left = Math.min(window.innerWidth - width - 12, rect.right - width);
      if (left < 12) left = 12;
      let top = rect.bottom + 6;
      if (top + height > window.innerHeight - 12) top = rect.top - height - 6;
      menu.style.left = left + 'px';
      menu.style.top = top + 'px';
      button.setAttribute('aria-expanded', 'true');
      if (event.detail === 0) { const first = menu.querySelector('.menu-item'); if (first) first.focus(); }
    });

    menu.addEventListener('click', (event) => {
      event.stopPropagation();
      const item = event.target.closest('[data-chat-op]');
      if (!item || !target) return;
      const op = item.getAttribute('data-chat-op');
      const current = target;
      closeMenus();
      if (op === 'pin') togglePin('chat', current.id);
      if (op === 'rename') renameChat(current);
      if (op === 'move') moveChat(current);
      if (op === 'delete') deleteChat(current);
      if (op === 'memory') openMemory(current.id);
    });
    menu.addEventListener('keydown', (event) => menuKeys(event, menu));
  }

  async function openMemory(conversationId) {
    if (!conversationId) { toast('La memoria se crea cuando la conversación empieza.', 'error'); return; }
    const result = await api('chat_update', { body: { op: 'memory_get', conversation_id: conversationId } });
    if (!result.ok) { toast(errorMessage(result.data), 'error'); return; }
    const dialog = openSheet('memorySheet');
    if (!dialog) return;
    const text = $('#memoryText');
    text.value = result.data.memory || '';
    $('#memoryMeta').textContent = result.data.updated
      ? 'Actualizada ' + result.data.updated + '. La IA la lee en cada respuesta en lugar del historial antiguo.'
      : 'Aún no hay memoria. Se genera al compactar la conversación.';
    $('#memoryDownload').setAttribute('href', 'index.php?action=memory_download&c=' + conversationId);
    const compact = $('#memoryCompact');
    compact.hidden = conversationId !== state.conversationId || config.view !== 'chat';
    const onCompact = () => { dialog.close(); sendMessage('/compact'); };
    const form = $('#memoryForm');
    const onSubmit = async (event) => {
      event.preventDefault();
      const saved = await api('chat_update', { body: { op: 'memory_save', conversation_id: conversationId, memory: text.value } });
      if (!saved.ok) { toast(errorMessage(saved.data), 'error'); return; }
      dialog.close();
      toast('Memoria guardada.', 'success');
    };
    compact.addEventListener('click', onCompact);
    form.addEventListener('submit', onSubmit);
    dialog.addEventListener('close', () => {
      compact.removeEventListener('click', onCompact);
      form.removeEventListener('submit', onSubmit);
    }, { once: true });
  }

  async function togglePin(type, id) {
    const result = await api('pin', { body: { type: type, id: id } });
    if (!result.ok) { toast(errorMessage(result.data), 'error'); return; }
    window.location.reload();
  }

  function setupProjectRowMenu() {
    const menu = $('#projectRowMenu');
    if (!menu) return;
    let target = null;

    document.addEventListener('click', (event) => {
      const pinButton = event.target.closest('[data-pin-project]');
      if (pinButton) {
        closeMenus();
        togglePin('project', Number(pinButton.getAttribute('data-pin-project')));
        return;
      }
      const button = event.target.closest('[data-project-menu]');
      if (!button) return;
      event.preventDefault();
      event.stopPropagation();
      const wasOpen = !menu.hidden && target && target.button === button;
      closeMenus();
      if (wasOpen) return;
      target = { button: button, id: Number(button.getAttribute('data-project-menu')), pinned: button.getAttribute('data-project-pinned') === '1' };
      $('[data-pin-label]', menu).textContent = target.pinned ? 'Desfijar' : 'Fijar';
      const rect = button.getBoundingClientRect();
      menu.hidden = false;
      let left = Math.min(window.innerWidth - menu.offsetWidth - 12, rect.right - menu.offsetWidth);
      if (left < 12) left = 12;
      let top = rect.bottom + 6;
      if (top + menu.offsetHeight > window.innerHeight - 12) top = rect.top - menu.offsetHeight - 6;
      menu.style.left = left + 'px';
      menu.style.top = top + 'px';
      button.setAttribute('aria-expanded', 'true');
    });

    menu.addEventListener('click', (event) => {
      event.stopPropagation();
      const item = event.target.closest('[data-project-op]');
      if (!item || !target) return;
      const op = item.getAttribute('data-project-op');
      const current = target;
      closeMenus();
      if (op === 'pin') togglePin('project', current.id);
      if (op === 'edit') window.location.href = 'index.php?p=' + current.id + '#editar';
      if (op === 'open') window.location.href = 'index.php?p=' + current.id;
    });
    menu.addEventListener('keydown', (event) => menuKeys(event, menu));

    if (config.view === 'project' && window.location.hash === '#editar') {
      openSheet('projectEditSheet');
      try { window.history.replaceState(null, '', 'index.php?p=' + config.projectId); } catch (e) { /* ignorar */ }
    }
  }

  function setupAppearancePickers() {
    $$('[data-appearance]').forEach((picker) => {
      const preview = $('[data-preview]', picker);
      picker.addEventListener('change', () => {
        const color = $('input[name="color"]:checked', picker);
        const iconInput = $('input[name="icon"]:checked', picker);
        if (color) preview.className = 'pc pc-' + color.value;
        if (iconInput) {
          const svg = iconInput.parentElement.querySelector('svg');
          if (svg) preview.innerHTML = svg.outerHTML;
        }
      });
    });
  }

  function renameChat(target) {
    const dialog = openSheet('renameSheet');
    if (!dialog) return;
    const input = $('#renameInput');
    input.value = target.title;
    input.select();
    const form = $('#renameForm');
    const onSubmit = async (event) => {
      event.preventDefault();
      const title = input.value.trim();
      if (!title) return;
      const result = await api('chat_update', { body: { op: 'rename', conversation_id: target.id, title: title } });
      if (!result.ok) { toast(errorMessage(result.data), 'error'); return; }
      dialog.close();
      $$('[data-chat-row="' + target.id + '"] [data-chat-title-text]').forEach((n) => { n.textContent = result.data.title; });
      $$('[data-chat-menu="' + target.id + '"]').forEach((b) => b.setAttribute('data-chat-title', result.data.title));
      if (state.conversationId === target.id) {
        const heading = $('#pageTitle');
        if (heading) heading.textContent = result.data.title;
        document.title = result.data.title + ' · Suilaft';
      }
      toast('Chat renombrado.', 'success');
    };
    form.addEventListener('submit', onSubmit);
    dialog.addEventListener('close', () => form.removeEventListener('submit', onSubmit), { once: true });
  }

  function moveChat(target) {
    const dialog = openSheet('moveSheet');
    if (!dialog) return;
    const select = $('#moveSelect');
    select.value = String(target.projectId || 0);
    const form = $('#moveForm');
    const onSubmit = async (event) => {
      event.preventDefault();
      const projectId = Number(select.value);
      const result = await api('chat_update', { body: { op: 'move', conversation_id: target.id, project_id: projectId } });
      if (!result.ok) { toast(errorMessage(result.data), 'error'); return; }
      dialog.close();
      window.location.reload();
    };
    form.addEventListener('submit', onSubmit);
    dialog.addEventListener('close', () => form.removeEventListener('submit', onSubmit), { once: true });
  }

  async function deleteChat(target) {
    const ok = await confirmAction('¿Eliminar “' + target.title + '”?', 'El chat y sus respuestas dejarán de estar disponibles.', 'Eliminar');
    if (!ok) return;
    const result = await api('chat_update', { body: { op: 'delete', conversation_id: target.id } });
    if (!result.ok) { toast(errorMessage(result.data), 'error'); return; }
    if (state.conversationId === target.id) {
      window.location.href = result.data.redirect || 'index.php';
      return;
    }
    $$('[data-chat-row="' + target.id + '"]').forEach((row) => row.remove());
    toast('Chat eliminado.', 'success');
  }

  /* ------------------------------------------------------------------------
   * Archivos: conocimiento del proyecto y biblioteca normativa
   * ---------------------------------------------------------------------- */

  function setupKnowledgeUploads() {
    const input = $('#knowledgeInput');
    if (!input) return;
    let pending = null;

    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-upload-target]');
      if (!trigger) return;
      pending = { target: trigger.getAttribute('data-upload-target'), projectId: Number(trigger.getAttribute('data-project-id') || 0) };
      input.value = '';
      input.click();
    });

    input.addEventListener('change', () => {
      if (!pending || !input.files || !input.files.length) return;
      uploadMany(Array.from(input.files), pending.target, pending.projectId);
    });

    $$('[data-dropzone]').forEach((zone) => {
      const target = zone.getAttribute('data-dropzone');
      const projectId = Number(zone.getAttribute('data-project-id') || 0);
      bindDrop(zone, (files) => uploadMany(files, target, projectId));
    });
  }

  function bindDrop(zone, onFiles) {
    let depth = 0;
    zone.addEventListener('dragenter', (event) => {
      if (!hasFiles(event)) return;
      event.preventDefault();
      depth++;
      zone.classList.add('is-dragging');
    });
    zone.addEventListener('dragover', (event) => { if (hasFiles(event)) event.preventDefault(); });
    zone.addEventListener('dragleave', () => {
      depth = Math.max(0, depth - 1);
      if (!depth) zone.classList.remove('is-dragging');
    });
    zone.addEventListener('drop', (event) => {
      if (!hasFiles(event)) return;
      event.preventDefault();
      depth = 0;
      zone.classList.remove('is-dragging');
      onFiles(Array.from(event.dataTransfer.files));
    });
  }

  function hasFiles(event) {
    return event.dataTransfer && Array.from(event.dataTransfer.types || []).indexOf('Files') !== -1;
  }

  async function uploadMany(files, target, projectId) {
    let uploaded = 0;
    for (const file of files) {
      setStatus('Subiendo ' + file.name + '…');
      const result = await uploadFile(file, { target: target, project_id: projectId });
      if (result.ok) {
        uploaded++;
        if (result.data.document && !result.data.document.ready) toast(result.data.message, 'error');
      } else {
        toast(file.name + ': ' + errorMessage(result.data, 'No pude subir el archivo.'), 'error');
      }
    }
    setStatus('');
    if (uploaded) window.location.reload();
  }

  function uploadFile(file, fields) {
    const maxBytes = (config.uploadMaxMb || 15) * 1024 * 1024;
    if (file.size > maxBytes) {
      return Promise.resolve({ ok: false, data: { error: 'El archivo supera ' + (config.uploadMaxMb || 15) + ' MB.' } });
    }
    const form = new FormData();
    form.append('document', file);
    Object.keys(fields).forEach((key) => form.append(key, String(fields[key])));
    return api('upload_document', { body: form }).catch(() => ({ ok: false, data: { error: 'No pude conectar con el servidor.' } }));
  }

  function setupDocumentDeletes() {
    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-delete-document]');
      if (!button) return;
      const id = Number(button.getAttribute('data-delete-document'));
      const name = button.getAttribute('data-document-name') || 'este documento';
      const ok = await confirmAction('¿Eliminar “' + name + '”?', 'La IA dejará de consultarlo. Esta acción no se puede deshacer.', 'Eliminar');
      if (!ok) return;
      const result = await api('delete_document', { body: { document_id: id } });
      if (!result.ok) { toast(errorMessage(result.data), 'error'); return; }
      $$('[data-document-row="' + id + '"]').forEach((row) => row.remove());
      toast('Documento eliminado.', 'success');
    });
  }

  /* ------------------------------------------------------------------------
   * Compositor
   * ---------------------------------------------------------------------- */

  function setupComposer() {
    const form = $('#composer');
    if (!form) return;
    const prompt = $('#prompt');
    const send = $('#sendButton');

    const resize = () => {
      prompt.style.height = 'auto';
      prompt.style.height = Math.min(prompt.scrollHeight, window.innerWidth <= 560 ? 160 : 240) + 'px';
      send.disabled = state.busy || !prompt.value.trim();
    };
    prompt.addEventListener('input', resize);
    prompt.addEventListener('input', updateSlash);
    $$('#slashMenu [data-slash]').forEach((item) => {
      item.addEventListener('click', () => {
        const command = item.getAttribute('data-slash');
        hideSlash();
        if (command === '/memoria') { prompt.value = ''; resize(); openMemory(state.conversationId); return; }
        prompt.value = command + ' ';
        resize();
        prompt.focus();
      });
    });
    prompt.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        if (!send.disabled) form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });
    resize();
    setupComposer.resize = resize;

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const text = prompt.value.trim();
      if (!text || state.busy) return;
      prompt.value = '';
      resize();
      hideSlash();
      if (/^\/memoria\b/i.test(text)) { openMemory(state.conversationId); return; }
      if (/^\/(compact|compactar)\b/i.test(text) && !state.conversationId) {
        toast('Todavía no hay conversación que compactar.', 'error');
        return;
      }
      sendMessage(text);
    });

    // Adjuntar al chat
    const fileInput = $('#fileInput');
    const attachAction = $('#attachAction');
    if (attachAction && fileInput) {
      attachAction.addEventListener('click', () => { closeMenus(); fileInput.value = ''; fileInput.click(); });
      fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files[0]) attachToChat(fileInput.files[0]);
      });
    }
    bindDrop(form, (files) => { if (files[0]) attachToChat(files[0]); });

    const exportAction = $('#exportAction');
    if (exportAction) exportAction.addEventListener('click', () => { closeMenus(); openExport(''); });

    const guidedButton = $('#guidedButton');
    if (guidedButton) guidedButton.addEventListener('click', () => startGuided(state.skill));

    $$('[data-start-skill]').forEach((button) => {
      button.addEventListener('click', () => {
        const skill = skillsBySlug[button.getAttribute('data-start-skill')];
        if (!skill) return;
        setSkill(skill.slug);
        if (skill.starter) sendMessage(skill.starter);
      });
    });

    setupMicrophone();
    updateSkillButton();
    if (config.view === 'chat' && !state.conversationId) prompt.focus();
  }

  function updateSlash() {
    const menu = $('#slashMenu');
    const prompt = $('#prompt');
    if (!menu || !prompt) return;
    const value = prompt.value;
    if (config.view !== 'chat' || !/^\/\S*$/.test(value)) { hideSlash(); return; }
    let visible = 0;
    $$('[data-slash]', menu).forEach((item) => {
      const match = item.getAttribute('data-slash').indexOf(value.toLowerCase()) === 0;
      item.hidden = !match;
      if (match) visible++;
    });
    menu.hidden = visible === 0;
  }

  function hideSlash() {
    const menu = $('#slashMenu');
    if (menu) menu.hidden = true;
  }

  function renderSkillMenu() {
    const menu = $('#skillMenu');
    if (!menu) return;
    const skills = (config.skills || []).filter((s) => s.show_in_picker);
    const groups = [
      ['documento', 'Generar documentos'],
      ['analisis', 'Analizar'],
    ];
    let html = '<button class="menu-item' + (state.skill ? '' : ' is-selected') + '" type="button" role="menuitem" data-skill="">' + icon('chat') +
      '<span class="menu-item-text"><strong>Conversación libre</strong><small>Consultas normativas y análisis sin un flujo fijo</small></span>' +
      (state.skill ? '' : '<span class="menu-check">' + icon('check') + '</span>') + '</button>';
    groups.forEach(([category, label]) => {
      const items = skills.filter((s) => s.category === category);
      if (!items.length) return;
      html += '<div class="menu-heading">' + escapeHtml(label) + '</div>';
      items.forEach((skill) => {
        const selected = state.skill === skill.slug;
        html += '<button class="menu-item' + (selected ? ' is-selected' : '') + '" type="button" role="menuitem" data-skill="' + escapeHtml(skill.slug) + '">' +
          icon(skill.icon) +
          '<span class="menu-item-text"><strong>' + escapeHtml(skill.name) + '</strong><small>' + escapeHtml(skill.description) + '</small></span>' +
          (selected ? '<span class="menu-check">' + icon('check') + '</span>' : '') + '</button>';
      });
    });
    menu.innerHTML = html;
    $$('[data-skill]', menu).forEach((item) => {
      item.addEventListener('click', () => {
        const slug = item.getAttribute('data-skill');
        closeMenus();
        const changed = slug !== state.skill;
        setSkill(slug);
        if (!changed) return;
        if (state.conversationId) {
          api('chat_update', { body: { op: 'skill', conversation_id: state.conversationId, skill: slug } });
        }
        const skill = skillsBySlug[slug];
        const prompt = $('#prompt');
        if (skill && skill.starter && prompt && !prompt.value.trim()) {
          prompt.value = skill.starter;
          if (setupComposer.resize) setupComposer.resize();
          prompt.focus();
          prompt.setSelectionRange(prompt.value.length, prompt.value.length);
        }
        toast(skill ? 'Habilidad activa: ' + skill.name : 'Conversación libre', 'success');
      });
    });
  }

  function setSkill(slug) {
    state.skill = skillsBySlug[slug] ? slug : '';
    updateSkillButton();
  }

  function updateSkillButton() {
    const button = $('#skillButton');
    const label = $('#skillButtonLabel');
    const guided = $('#guidedButton');
    if (!button || !label) return;
    const skill = skillsBySlug[state.skill];
    label.textContent = skill ? skill.name : 'Habilidades';
    button.classList.toggle('is-active', !!skill);
    if (guided) guided.hidden = !(skill && skill.guided_questions && skill.guided_questions.length);
  }

  async function attachToChat(file) {
    const tray = $('#attachTray');
    const chip = document.createElement('span');
    chip.className = 'file-chip is-uploading';
    chip.innerHTML = icon('doc') + '<span class="file-chip-name"></span><span class="file-chip-meta">Subiendo…</span>';
    chip.querySelector('.file-chip-name').textContent = file.name;
    if (tray) { tray.hidden = false; tray.appendChild(chip); }

    const result = await uploadFile(file, { target: 'chat', conversation_id: state.conversationId, project_id: state.projectId });
    chip.classList.remove('is-uploading');
    if (!result.ok) {
      chip.remove();
      if (tray && !tray.children.length) tray.hidden = true;
      toast(errorMessage(result.data, 'No pude subir el archivo.'), 'error');
      return;
    }

    const doc = result.data.document || {};
    chip.querySelector('.file-chip-meta').textContent = doc.ready ? 'Indexado' : 'Sin texto';
    if (!doc.ready) chip.classList.add('is-warning');
    toast(result.data.message, doc.ready ? 'success' : 'error');

    if (config.view === 'project') {
      // En la página del proyecto, adjuntar abre directamente el chat creado.
      window.location.href = 'index.php?c=' + result.data.conversation_id;
      return;
    }
    if (result.data.conversation_id && !state.conversationId) adoptConversation(result.data.conversation_id, 'Revisión de ' + file.name);
    const prompt = $('#prompt');
    if (prompt && !prompt.value.trim()) {
      prompt.value = 'Revisa el documento adjunto y dime los hallazgos principales.';
      if (setupComposer.resize) setupComposer.resize();
    }
    if (prompt) prompt.focus();
  }

  function adoptConversation(id, title) {
    state.conversationId = Number(id);
    try { window.history.replaceState(null, '', 'index.php?c=' + state.conversationId); } catch (e) { /* ignorar */ }
    addRecentRow(state.conversationId, title || 'Nueva conversación');
    if (title) {
      const heading = $('#pageTitle');
      if (heading) heading.textContent = title.length > 80 ? title.slice(0, 80) : title;
      document.title = (title.length > 60 ? title.slice(0, 60) + '…' : title) + ' · Suilaft';
    }
  }

  function addRecentRow(id, title) {
    if (state.projectId) return; // los chats de proyecto no van en la lista suelta, como en ChatGPT
    const emptyNote = $('#recentEmpty');
    if (emptyNote) emptyNote.remove();
    const group = $('#sgRecent') ? $('#sgRecent').parentElement : null;
    if (!group || $('[data-chat-row="' + id + '"]', group)) return;
    let list = $('#recentList');
    if (!list) {
      const empty = $('.sidebar-empty', group);
      if (empty) empty.remove();
      list = document.createElement('ul');
      list.className = 'sidebar-list';
      list.id = 'recentList';
      group.appendChild(list);
    }
    $$('.side-row.is-active', list).forEach((row) => { row.classList.remove('is-active'); row.removeAttribute('aria-current'); });
    const short = title.length > 60 ? title.slice(0, 60) + '…' : title;
    const item = document.createElement('li');
    item.className = 'side-item';
    item.setAttribute('data-chat-row', String(id));
    item.setAttribute('data-filter-text', fold(title));
    item.innerHTML = '<a class="side-row is-active" aria-current="page" href="index.php?c=' + id + '"><span class="side-row-text"><span class="side-row-title" data-chat-title-text></span></span></a>' +
      '<button class="icon-btn icon-btn-sm side-more" type="button" data-chat-menu="' + id + '" data-chat-project="' + (state.projectId || 0) + '" aria-haspopup="menu" aria-label="Opciones del chat">' +
      '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg></button>';
    $('[data-chat-title-text]', item).textContent = short;
    $('[data-chat-menu]', item).setAttribute('data-chat-title', short);
    list.insertBefore(item, list.firstChild);
  }

  /* ------------------------------------------------------------------------
   * Conversación
   * ---------------------------------------------------------------------- */

  function setupThread() {
    $$('.msg-ai').forEach((article) => {
      const md = $('[data-md]', article);
      if (!md) return;
      const raw = md.textContent;
      let meta = {};
      try { meta = JSON.parse(article.getAttribute('data-meta') || '{}') || {}; } catch (e) { meta = {}; }
      renderAnswer(article, raw, meta, false);
    });
    scrollThread(false);
  }

  function threadEl() { return $('#thread'); }

  function scrollThread(smooth) {
    const scroller = $('#threadScroll');
    if (!scroller) return;
    if (smooth === false) scroller.style.scrollBehavior = 'auto';
    scroller.scrollTop = scroller.scrollHeight;
    if (smooth === false) window.setTimeout(() => { scroller.style.scrollBehavior = ''; }, 50);
  }

  function enterThreadMode() {
    const chat = $('#chatView');
    if (chat && chat.classList.contains('is-empty')) {
      chat.classList.remove('is-empty');
      chat.classList.add('has-thread');
      const welcome = $('#welcome');
      if (welcome) welcome.remove();
      const suggestions = $('#suggestions');
      if (suggestions) {
        const note = document.createElement('p');
        note.className = 'dock-note';
        note.textContent = 'La IA puede equivocarse. Valida las referencias normativas en la fuente oficial antes de aplicarlas.';
        suggestions.replaceWith(note);
      }
      const heading = $('#pageTitle');
      if (heading) heading.hidden = false;
    }
  }

  function addUserMessage(text) {
    enterThreadMode();
    const article = document.createElement('article');
    article.className = 'msg msg-user';
    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';
    bubble.textContent = text;
    article.appendChild(bubble);
    threadEl().appendChild(article);
    scrollThread();
  }

  function addWorkingMessage() {
    enterThreadMode();
    const article = document.createElement('article');
    article.className = 'msg msg-ai';
    article.innerHTML = '<div class="msg-body"><div class="working" role="status"><span class="working-dots" aria-hidden="true"><span></span><span></span><span></span></span><span class="working-text">' +
      escapeHtml(STAGES.queued) + '</span></div></div>';
    threadEl().appendChild(article);
    scrollThread();
    return article;
  }

  function setWorkingStage(article, stage) {
    const label = $('.working-text', article);
    if (!label) return;
    let text = STAGES[stage] || STAGES.processing;
    if (stage === 'drafting' && skillsBySlug[state.skill]) text = 'Redactando con la habilidad ' + skillsBySlug[state.skill].name + '…';
    label.textContent = text;
    setStatus(text.replace('…', ''));
  }

  async function sendMessage(text) {
    if (state.busy) return;
    setBusy(true);

    if (config.view === 'project') {
      const result = await api('chat', { body: { prompt: text, conversation_id: 0, project_id: state.projectId, skill: state.skill } })
        .catch(() => ({ ok: false, data: { error: 'No pude conectar con el servidor.' } }));
      if (!result.ok) {
        setBusy(false);
        toast(errorMessage(result.data), 'error');
        $('#prompt').value = text;
        return;
      }
      window.location.href = 'index.php?c=' + result.data.conversation_id;
      return;
    }

    addUserMessage(text);
    const article = addWorkingMessage();

    const result = await api('chat', {
      body: { prompt: text, conversation_id: state.conversationId, project_id: state.projectId, skill: state.skill },
    }).catch(() => ({ ok: false, data: { error: 'No pude conectar con el servidor.' } }));

    if (!result.ok || !result.data.job_id) {
      showError(article, errorMessage(result.data, 'No pude enviar el mensaje.'), result.data && result.data.details, text);
      setBusy(false);
      return;
    }

    if (!state.conversationId && result.data.conversation_id) adoptConversation(result.data.conversation_id, text);
    await followJob(result.data.job_id, article, text);
  }

  function resumePendingJob() {
    const pending = config.pendingJobId;
    if (!pending || config.view !== 'chat') return;
    const article = addWorkingMessage();
    setBusy(true);
    followJob(pending, article, '');
  }

  /**
   * Sigue un trabajo en segundo plano: dispara el procesamiento desde el navegador
   * (por si el hosting no tiene cron ni fastcgi_finish_request) y consulta la etapa.
   */
  async function followJob(jobId, article, originalText) {
    let finished = false;
    let lastPayload = null;

    const finish = (payload) => {
      if (finished) return;
      finished = true;
      if (payload.status === 'done') {
        if (payload.meta && payload.meta.skill !== undefined) setSkill(payload.meta.skill || state.skill);
        renderAnswer(article, payload.answer || 'Respuesta completada.', payload.meta || {}, true);
      } else {
        showError(article, 'No pude generar la respuesta.', payload.error || 'El trabajo falló.', originalText);
      }
      setBusy(false);
      scrollThread();
    };

    const trigger = () => api('process_job', { body: { job_id: jobId } })
      .then((result) => {
        if (result.ok && (result.data.status === 'done' || result.data.status === 'failed')) finish(result.data);
      })
      .catch(() => { /* la consulta de estado sigue */ });

    trigger();

    for (let attempt = 0; attempt < 260 && !finished; attempt++) {
      await sleep(attempt < 10 ? 1500 : 2500);
      if (finished) break;
      const result = await api('job_status', { query: '&job_id=' + encodeURIComponent(jobId) }).catch(() => null);
      if (!result) continue;
      if (!result.ok) {
        if (result.status === 404 || result.status === 401) { finish({ status: 'failed', error: errorMessage(result.data) }); break; }
        continue;
      }
      lastPayload = result.data;
      if (result.data.status === 'done' || result.data.status === 'failed') { finish(result.data); break; }
      setWorkingStage(article, result.data.stage || result.data.status);
      if ((result.data.status === 'queued' && attempt % 10 === 9) || (result.data.status === 'processing' && attempt > 60 && attempt % 25 === 0)) {
        trigger();
      }
    }

    if (!finished) {
      const status = lastPayload ? lastPayload.status : 'desconocido';
      finish({
        status: 'failed',
        error: status === 'processing'
          ? 'La IA sigue trabajando, pero tardó más que el límite de la pantalla. Recarga el chat en unos minutos para ver la respuesta guardada.'
          : 'El servidor no tomó el trabajo a tiempo. Recarga la página e inténtalo de nuevo.',
      });
    }
  }

  function setBusy(busy) {
    state.busy = busy;
    const send = $('#sendButton');
    const prompt = $('#prompt');
    const mic = $('#micButton');
    if (send) send.disabled = busy || !(prompt && prompt.value.trim());
    if (mic) mic.disabled = busy;
    if (!busy) {
      setStatus('');
      if (prompt) prompt.focus();
    }
  }

  function showError(article, message, detail, retryText) {
    article.classList.add('msg-error');
    const body = $('.msg-body', article);
    body.innerHTML = '<div class="error-box">' + icon('alert') + '<div><strong></strong>' +
      (detail ? '<details><summary>Detalle técnico</summary><p></p></details>' : '') + '</div></div>';
    $('strong', body).textContent = message;
    if (detail) $('details p', body).textContent = typeof detail === 'string' ? detail : JSON.stringify(detail);
    if (retryText) {
      const actions = document.createElement('div');
      actions.className = 'msg-actions';
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'act-btn act-btn-accent';
      retry.innerHTML = icon('retry') + '<span>Reintentar</span>';
      retry.addEventListener('click', () => {
        article.remove();
        const lastUser = $$('.msg-user').pop();
        if (lastUser && lastUser.textContent.trim() === retryText.trim()) lastUser.remove();
        sendMessage(retryText);
      });
      actions.appendChild(retry);
      body.appendChild(actions);
    }
    setStatus('');
  }

  function renderAnswer(article, raw, meta, fresh) {
    const body = $('.msg-body', article);
    body.innerHTML = '';

    const md = document.createElement('div');
    md.className = 'md';
    md.innerHTML = formatAnswer(raw);
    body.appendChild(md);

    const exportType = exportTypeFor(raw, meta.skill !== undefined ? meta.skill : state.skill);
    const metaRow = buildMetaRow(meta);
    if (metaRow) body.appendChild(metaRow);
    const sources = buildSources(meta.sources || []);
    if (sources) body.appendChild(sources);
    body.appendChild(buildActions(raw, exportType));

    state.lastAnswer = { raw: raw, exportType: exportType };
    if (fresh && state.voice) speak(raw);
  }

  function buildMetaRow(meta) {
    const chips = [];
    if (meta.verdict === 'out_of_scope' || meta.verdict === 'blocked') {
      chips.push('<span class="meta-chip meta-chip-guard" title="El agente de triaje detuvo una solicitud fuera del alcance">' + icon('shield') + 'Límite de alcance aplicado</span>');
    } else if (meta.verdict === 'mixed' || meta.reviewed) {
      chips.push('<span class="meta-chip meta-chip-guard" title="Se atendió solo la parte de cumplimiento">' + icon('shield') + 'Solo la parte de cumplimiento</span>');
    }
    if (meta.compact) {
      chips.push('<span class="meta-chip meta-chip-accent">' + icon('sparkle') + 'Memoria actualizada</span>');
    }
    if (meta.skill_label) {
      chips.push('<span class="meta-chip meta-chip-accent">' + icon('sparkle') + escapeHtml(meta.skill_label) + '</span>');
    }
    if (!chips.length) return null;
    const row = document.createElement('div');
    row.className = 'msg-meta';
    row.innerHTML = chips.join('');
    return row;
  }

  function buildSources(sources) {
    if (!sources || !sources.length) return null;
    const details = document.createElement('details');
    details.className = 'sources';
    let html = '<summary>' + icon('library') + '<span>' + sources.length + (sources.length === 1 ? ' fuente consultada' : ' fuentes consultadas') + '</span>' +
      '<svg class="icon icon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></summary><ol>';
    sources.forEach((source) => {
      html += '<li value="' + Number(source.n || 0) + '">' + escapeHtml(source.title || 'Documento') + ' <span>· ' + escapeHtml(source.scope || '') + '</span></li>';
    });
    details.innerHTML = html + '</ol>';
    return details;
  }

  function buildActions(raw, exportType) {
    const actions = document.createElement('div');
    actions.className = 'msg-actions';

    const copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'act-btn';
    copy.innerHTML = icon('copy') + '<span>Copiar</span>';
    copy.addEventListener('click', () => {
      const text = cleanForCopy(raw);
      const done = () => {
        copy.innerHTML = icon('check') + '<span>Copiado</span>';
        window.setTimeout(() => { copy.innerHTML = icon('copy') + '<span>Copiar</span>'; }, 1600);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
      } else {
        fallbackCopy(text, done);
      }
    });
    actions.appendChild(copy);

    if ('speechSynthesis' in window) {
      const listen = document.createElement('button');
      listen.type = 'button';
      listen.className = 'act-btn';
      listen.setAttribute('data-listen', '');
      listen.innerHTML = icon('speaker') + '<span>Escuchar</span>';
      listen.addEventListener('click', () => {
        if (listen.classList.contains('is-active')) { stopSpeaking(); return; }
        speak(raw, listen);
      });
      actions.appendChild(listen);
    }

    if (exportType) {
      const pdf = document.createElement('button');
      pdf.type = 'button';
      pdf.className = 'act-btn act-btn-accent';
      pdf.innerHTML = icon('download') + '<span>' + (exportType === 'matriz' ? 'Descargar matriz PDF' : 'Exportar documento') + '</span>';
      pdf.addEventListener('click', () => {
        state.lastAnswer = { raw: raw, exportType: exportType };
        if (exportType === 'metodologia') { downloadDocument('metodologia', 'pdf', raw, {}); return; }
        openExport(exportType);
      });
      actions.appendChild(pdf);
    }

    return actions;
  }

  function fallbackCopy(text, done) {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); done(); } catch (e) { toast('No pude copiar el texto.', 'error'); }
    area.remove();
  }

  function cleanForCopy(raw) {
    return String(raw || '')
      .replace(/<!--\s*MATRIZ_JSON\s*-->[\s\S]*?<!--\s*\/MATRIZ_JSON\s*-->/g, '')
      .replace(/<!--\s*DOCUMENTO_FINAL\s*-->/g, '')
      .trim();
  }

  function exportTypeFor(raw, skillSlug) {
    const text = String(raw || '');
    if (/<!--\s*MATRIZ_JSON\s*-->/.test(text)) return 'matriz';
    if (/<!--\s*DOCUMENTO_FINAL\s*-->/.test(text)) {
      const skill = skillsBySlug[skillSlug];
      return skill && skill.export_type && skill.export_type !== 'matriz' ? skill.export_type : 'manual';
    }
    return '';
  }

  /* ------------------------------------------------------------------------
   * Markdown ligero (sin dependencias, con escape previo)
   * ---------------------------------------------------------------------- */

  function formatAnswer(text) {
    const lines = cleanForCopy(text).replace(/\r\n/g, '\n').split('\n');
    let html = '';
    let list = null;
    let inCode = false;
    let code = [];

    const closeList = () => { if (list) { html += '</' + list + '>'; list = null; } };

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      const trimmed = line.trim();

      if (/^```/.test(trimmed)) {
        if (inCode) {
          html += '<pre><code>' + escapeHtml(code.join('\n')) + '</code></pre>';
          code = [];
          inCode = false;
        } else {
          closeList();
          inCode = true;
        }
        continue;
      }
      if (inCode) { code.push(line); continue; }

      if (!trimmed) { closeList(); continue; }

      if (/^(-{3,}|\*{3,}|_{3,})$/.test(trimmed)) { closeList(); html += '<hr>'; continue; }

      if (isTableLine(trimmed) && lines[i + 1] && isTableSeparator(lines[i + 1])) {
        closeList();
        const rows = [splitRow(trimmed)];
        i += 2;
        while (i < lines.length && isTableLine(lines[i].trim())) {
          if (!isTableSeparator(lines[i])) rows.push(splitRow(lines[i]));
          i++;
        }
        i--;
        html += renderTable(rows);
        continue;
      }

      const heading = trimmed.match(/^(#{1,6})\s+(.+)$/);
      if (heading) {
        closeList();
        const level = Math.min(4, Math.max(2, heading[1].length));
        html += '<h' + level + '>' + inline(heading[2]) + '</h' + level + '>';
        continue;
      }

      const quote = trimmed.match(/^>\s?(.*)$/);
      if (quote) { closeList(); html += '<blockquote><p>' + inline(quote[1]) + '</p></blockquote>'; continue; }

      const bullet = trimmed.match(/^[-*•]\s+(.+)$/);
      if (bullet) {
        if (list !== 'ul') { closeList(); html += '<ul>'; list = 'ul'; }
        html += '<li>' + inline(bullet[1]) + '</li>';
        continue;
      }

      const numbered = trimmed.match(/^(\d+)[.)]\s+(.+)$/);
      if (numbered) {
        if (list !== 'ol') { closeList(); html += '<ol start="' + Number(numbered[1]) + '">'; list = 'ol'; }
        html += '<li>' + inline(numbered[2]) + '</li>';
        continue;
      }

      closeList();
      html += '<p>' + inline(trimmed) + '</p>';
    }

    if (inCode && code.length) html += '<pre><code>' + escapeHtml(code.join('\n')) + '</code></pre>';
    closeList();
    return html;
  }

  function inline(text) {
    return escapeHtml(text)
      .replace(/`([^`\n]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[\s(])\*([^*\n]+)\*(?=[\s).,;:!?]|$)/g, '$1<em>$2</em>')
      .replace(/\[Fuente\s+(\d+)([^\]]*)\]/gi, (m, n, rest) => '<span class="cite" title="Fuente ' + n + escapeAttr(rest) + '">' + n + '</span>');
  }

  function renderTable(rows) {
    if (!rows.length) return '';
    let html = '<div class="table-wrap"><table><thead><tr>';
    rows[0].forEach((cell) => { html += '<th>' + inline(cell) + '</th>'; });
    html += '</tr></thead><tbody>';
    rows.slice(1).forEach((row) => {
      html += '<tr>';
      row.forEach((cell) => { html += '<td>' + inline(cell) + '</td>'; });
      html += '</tr>';
    });
    return html + '</tbody></table></div>';
  }

  function splitRow(line) {
    return line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((cell) => cell.trim());
  }

  function isTableLine(line) {
    return /^\|.+\|$/.test(line.trim()) && line.split('|').length > 2;
  }

  function isTableSeparator(line) {
    return /^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/.test(String(line).trim());
  }

  /* ------------------------------------------------------------------------
   * Exportación a PDF / Word
   * ---------------------------------------------------------------------- */

  function setupExport() {
    const pdf = $('#exportPdf');
    const docx = $('#exportDocx');
    const type = $('#exportDocType');
    if (!pdf || !docx || !type) return;

    const syncButtons = () => { docx.disabled = type.value === 'matriz'; };
    type.addEventListener('change', syncButtons);
    syncButtons();

    const run = (format, button) => {
      if (!state.lastAnswer) { toast('Primero genera una respuesta para exportarla.', 'error'); return; }
      const meta = {
        company: $('#exportCompany').value.trim(),
        ruc: $('#exportRuc').value.trim(),
        code: $('#exportCode').value.trim(),
        version: $('#exportVersion').value.trim(),
        author: $('#exportAuthor').value.trim(),
      };
      button.disabled = true;
      downloadDocument(type.value, format, state.lastAnswer.raw, meta).finally(() => {
        button.disabled = false;
        syncButtons();
      });
    };
    pdf.addEventListener('click', () => run('pdf', pdf));
    docx.addEventListener('click', () => run('docx', docx));
  }

  function openExport(type) {
    if (!state.lastAnswer) { toast('Primero genera una respuesta para exportarla.', 'error'); return; }
    const select = $('#exportDocType');
    const wanted = type || state.lastAnswer.exportType;
    if (select && wanted && Array.from(select.options).some((o) => o.value === wanted)) {
      select.value = wanted;
      select.dispatchEvent(new Event('change'));
    }
    openSheet('exportSheet');
  }

  async function downloadDocument(type, format, content, meta) {
    setStatus(format === 'docx' ? 'Generando Word' : 'Generando PDF');
    try {
      const response = await fetch('index.php?action=' + (format === 'docx' ? 'export_docx' : 'export_pdf'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrf || '' },
        body: JSON.stringify(Object.assign({ document_type: type, content: content, title: '' }, meta || {})),
      });
      const contentType = response.headers.get('Content-Type') || '';
      if (response.ok && !/json/.test(contentType)) {
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = exportFilename(type, format);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1500);
        const dialog = $('#exportSheet');
        if (dialog && dialog.open) dialog.close();
        toast('Descarga lista.', 'success');
        return;
      }
      let data = {};
      try { data = JSON.parse(await response.text()); } catch (e) { data = {}; }
      toast(errorMessage(data, 'No pude generar el documento.'), 'error');
    } catch (e) {
      toast('No pude conectar con el servidor para exportar.', 'error');
    } finally {
      setStatus('');
    }
  }

  function exportFilename(type, format) {
    const names = { manual: 'manual_cumplimiento', informe: 'informe_actividades', plan: 'plan_anual_cumplimiento', metodologia: 'metodologia', matriz: 'matriz_riesgo' };
    const now = new Date();
    const pad = (v) => (v < 10 ? '0' + v : String(v));
    return (names[type] || 'documento') + '_' + now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) + '.' + format;
  }

  /* ------------------------------------------------------------------------
   * Guía paso a paso
   * ---------------------------------------------------------------------- */

  const guided = { skill: null, index: 0, answers: {} };

  function setupGuided() {
    const form = $('#guidedForm');
    if (!form) return;
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      saveGuided();
      const questions = guided.skill.guided_questions;
      if (guided.index < questions.length - 1) {
        guided.index++;
        renderGuided();
      } else {
        finishGuided();
      }
    });
    $('#guidedBack').addEventListener('click', () => {
      saveGuided();
      if (guided.index > 0) { guided.index--; renderGuided(); }
    });
  }

  function startGuided(slug) {
    const skill = skillsBySlug[slug];
    if (!skill || !skill.guided_questions || !skill.guided_questions.length) return;
    guided.skill = skill;
    guided.index = 0;
    guided.answers = {};
    $('#guidedSkill').textContent = skill.name;
    openSheet('guidedSheet');
    renderGuided();
  }

  function renderGuided() {
    const questions = guided.skill.guided_questions;
    const question = questions[guided.index];
    const total = questions.length;
    $('#guidedStep').textContent = 'Paso ' + (guided.index + 1) + ' de ' + total;
    $('#guidedProgress').style.width = Math.round(((guided.index + 1) / total) * 100) + '%';
    $('#guidedLabel').textContent = question.label;
    $('#guidedHint').textContent = question.hint || '';
    $('#guidedHint').hidden = !question.hint;
    $('#guidedBack').disabled = guided.index === 0;
    $('#guidedNext').textContent = guided.index === total - 1 ? 'Generar documento' : 'Siguiente';

    const wrap = $('#guidedInputWrap');
    wrap.innerHTML = '';
    const value = guided.answers[question.id] || '';
    let focusable;

    if (question.options && question.options.length) {
      const list = document.createElement('div');
      list.className = 'option-list';
      list.setAttribute('role', 'radiogroup');
      list.setAttribute('aria-labelledby', 'guidedLabel');
      question.options.forEach((option, index) => {
        const label = document.createElement('label');
        label.className = 'option';
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'guidedOption';
        radio.value = option;
        radio.checked = value ? value === option : index === 0;
        label.appendChild(radio);
        label.appendChild(document.createTextNode(option));
        list.appendChild(label);
        if (radio.checked) focusable = radio;
      });
      wrap.appendChild(list);
      $('#guidedLabel').removeAttribute('for');
    } else {
      const input = document.createElement(question.multiline ? 'textarea' : 'input');
      input.id = 'guidedInput';
      input.className = 'guided-input';
      if (!question.multiline) input.type = 'text';
      else input.rows = 4;
      input.placeholder = question.hint || '';
      input.value = value;
      wrap.appendChild(input);
      $('#guidedLabel').setAttribute('for', 'guidedInput');
      focusable = input;
    }
    if (focusable) window.setTimeout(() => focusable.focus(), 30);
  }

  function saveGuided() {
    const question = guided.skill.guided_questions[guided.index];
    const radio = $('#guidedInputWrap input[type=radio]:checked');
    const input = $('#guidedInput');
    guided.answers[question.id] = radio ? radio.value : (input ? input.value : '');
  }

  function finishGuided() {
    const lines = [];
    guided.skill.guided_questions.forEach((question) => {
      const answer = String(guided.answers[question.id] || '').trim();
      if (answer) lines.push('- ' + question.label + ': ' + answer);
    });
    $('#guidedSheet').close();
    setSkill(guided.skill.slug);
    sendMessage('Genera el documento con los siguientes datos. Si falta algún dato usa [PENDIENTE] o un valor estándar y continúa; no me vuelvas a preguntar:\n\n' + lines.join('\n'));
  }

  /* ------------------------------------------------------------------------
   * Voz: dictado y lectura
   * ---------------------------------------------------------------------- */

  function setupVoice() {
    const toggle = $('#voiceToggle');
    if (!toggle) return;
    if (!('speechSynthesis' in window)) { toggle.hidden = true; return; }
    const sync = () => {
      toggle.setAttribute('aria-pressed', state.voice ? 'true' : 'false');
      toggle.title = state.voice ? 'Lectura en voz alta activada' : 'Leer respuestas en voz alta';
    };
    sync();
    toggle.addEventListener('click', () => {
      state.voice = !state.voice;
      savePref('suilaft_voice_enabled', state.voice ? '1' : '0');
      if (!state.voice) stopSpeaking();
      sync();
      toast(state.voice ? 'Leeré las nuevas respuestas en voz alta.' : 'Lectura en voz alta desactivada.');
    });
  }

  /*
   * Lectura en voz alta. Chrome tiene varios fallos conocidos que la dejaban muda:
   * descarta speak() si va justo después de cancel(), corta textos de más de ~15 s,
   * entrega la lista de voces vacía al inicio y libera utterances sin referencia.
   * Por eso se lee por frases en cola, se esperan las voces y se guardan referencias.
   */
  const speech = { queue: [], current: null, button: null, token: 0 };

  function voicesReady() {
    return new Promise((resolve) => {
      const voices = window.speechSynthesis.getVoices();
      if (voices.length) { resolve(voices); return; }
      let done = false;
      const finish = () => { if (!done) { done = true; resolve(window.speechSynthesis.getVoices()); } };
      window.speechSynthesis.addEventListener('voiceschanged', finish, { once: true });
      window.setTimeout(finish, 1500);
    });
  }

  function speechChunks(text) {
    const sentences = text.match(/[^.!?¿¡:;\n]+[.!?:;]*|[¿¡][^?!]+[?!]/g) || [text];
    const chunks = [];
    let current = '';
    sentences.forEach((sentence) => {
      const piece = sentence.trim();
      if (!piece) return;
      if ((current + ' ' + piece).length > 180 && current) { chunks.push(current); current = piece; }
      else current = current ? current + ' ' + piece : piece;
    });
    if (current) chunks.push(current);
    return chunks;
  }

  async function speak(text, button) {
    if (!('speechSynthesis' in window)) { toast('Tu navegador no permite leer en voz alta.', 'error'); return; }
    const clean = cleanForCopy(text)
      .replace(/```[\s\S]*?```/g, ' ')
      .replace(/\|/g, ', ')
      .replace(/[#*_`>]+/g, ' ')
      .replace(/\[Fuente\s+\d+[^\]]*\]/gi, ' ')
      .replace(/^-{3,}$/gm, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (!clean) return;

    stopSpeaking();
    const token = ++speech.token;
    const voices = await voicesReady();
    if (token !== speech.token) return;
    if (!voices.length) {
      toast('No hay voces instaladas en este equipo. En Linux instala speech-dispatcher o usa otro navegador.', 'error');
      return;
    }
    const voice = voices.filter((v) => /^es[-_]?(EC|419|MX|US|CO)/i.test(v.lang))[0]
      || voices.filter((v) => /^es([-_]|$)/i.test(v.lang))[0] || null;

    speech.queue = speechChunks(clean);
    speech.button = button || null;
    setSpeakingUi(true);
    // Pausa breve: Chrome ignora un speak() inmediatamente posterior a cancel().
    window.setTimeout(() => { if (token === speech.token) speakNext(voice, token); }, 120);
  }

  function speakNext(voice, token) {
    if (token !== speech.token) return;
    const chunk = speech.queue.shift();
    if (!chunk) { speech.current = null; setSpeakingUi(false); return; }
    const utterance = new SpeechSynthesisUtterance(chunk);
    utterance.lang = voice ? voice.lang : 'es-ES';
    if (voice) utterance.voice = voice;
    utterance.rate = 1;
    utterance.onend = () => speakNext(voice, token);
    utterance.onerror = (event) => {
      if (event.error === 'interrupted' || event.error === 'canceled') return;
      speech.queue = [];
      setSpeakingUi(false);
      toast('No pude reproducir la voz (' + (event.error || 'error') + ').', 'error');
    };
    speech.current = utterance; // referencia viva: evita que el navegador la descarte
    window.speechSynthesis.speak(utterance);
  }

  function setSpeakingUi(active) {
    const toggle = $('#voiceToggle');
    if (toggle) toggle.classList.toggle('is-speaking', active);
    $$('.act-btn[data-listen]').forEach((button) => {
      const on = active && button === speech.button;
      button.classList.toggle('is-active', on);
      button.innerHTML = icon('speaker') + '<span>' + (on ? 'Detener' : 'Escuchar') + '</span>';
    });
  }

  function stopSpeaking() {
    speech.token++;
    speech.queue = [];
    speech.current = null;
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    setSpeakingUi(false);
  }

  function setupMicrophone() {
    const mic = $('#micButton');
    const prompt = $('#prompt');
    if (!mic || !prompt) return;
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Recognition) { mic.hidden = true; return; }

    const recognition = new Recognition();
    recognition.lang = 'es-EC';
    recognition.continuous = false;
    recognition.interimResults = true;
    let listening = false;
    let finalText = '';
    let base = '';

    recognition.onstart = () => {
      listening = true;
      finalText = '';
      base = prompt.value.trim() ? prompt.value.trim() + ' ' : '';
      mic.classList.add('is-listening');
      mic.setAttribute('aria-label', 'Detener dictado');
      setStatus('Escuchando');
    };
    recognition.onresult = (event) => {
      let interim = '';
      for (let i = event.resultIndex; i < event.results.length; i++) {
        if (event.results[i].isFinal) finalText += event.results[i][0].transcript + ' ';
        else interim += event.results[i][0].transcript;
      }
      prompt.value = base + (finalText + interim).trim();
      if (setupComposer.resize) setupComposer.resize();
    };
    const stop = () => {
      listening = false;
      mic.classList.remove('is-listening');
      mic.setAttribute('aria-label', 'Dictar por voz');
      if (!state.busy) setStatus('');
      prompt.focus();
    };
    recognition.onerror = stop;
    recognition.onend = stop;

    mic.addEventListener('click', () => {
      if (state.busy) return;
      if (listening) { recognition.stop(); return; }
      stopSpeaking();
      try { recognition.start(); } catch (e) { stop(); }
    });
  }
})();
