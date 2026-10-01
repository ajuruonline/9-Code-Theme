(function () {
    'use strict';

    function boot() {
    var root = document.querySelector('[data-nce-ai-editor]');
    if (!root || typeof nceAiEditor === 'undefined') { return; }

    var state = {
        kind: 'post', type: '', id: 0, schema: null, workspace: null,
        mode: 'content', activeGroup: '', searchTimer: 0, saveTimer: 0, saving: false,
        pendingSaves: [], searchSequence: 0, loadSequence: 0, commitSequence: 0, committing: false,
        runSequence: 0, aiRunning: false
    };
    var el = function (selector, scope) { return (scope || root).querySelector(selector); };
    var all = function (selector, scope) { return Array.prototype.slice.call((scope || root).querySelectorAll(selector)); };
    var cssEscape = function (value) { if (window.CSS && typeof window.CSS.escape === 'function') { return window.CSS.escape(String(value)); } return String(value).replace(/[^a-zA-Z0-9_-]/g, function (character) { return '\\' + character.charCodeAt(0).toString(16) + ' '; }); };
    var escapeHtml = function (value) { var node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML; };
    var fieldMap = function () {
        var map = {};
        (state.schema ? state.schema.groups : []).forEach(function (group) { group.fields.forEach(function (field) { map[field.id] = field; }); });
        return map;
    };
    var storageKey = function () { return 'nce-ai-workspace-u-' + nceAiEditor.workspaceScope + '-' + state.kind + '-' + state.type + '-' + state.id; };
    function clearUnsafeLegacyWorkspaceKeys() {
        try {
            for (var index = localStorage.length - 1; index >= 0; index--) {
                var key = localStorage.key(index);
                if (/^nce-ai-workspace-(post|term)-/.test(key || '')) { localStorage.removeItem(key); }
            }
        } catch (ignore) {}
    }
    var setStatus = function (message, tone) {
        var status = el('[data-nce-save-state]'); status.textContent = message; status.dataset.tone = tone || '';
    };
    var clearReview = function () { var panel = el('[data-nce-review]'); if (panel) { panel.remove(); } };
    var setCommitButtons = function (disabled) { all('[data-nce-save-draft], [data-nce-save-content], [data-nce-publish]').forEach(function (button) { button.disabled = disabled; }); };
    function invalidateCommit() { state.commitSequence++; state.committing = false; setCommitButtons(false); }
    var setRunButton = function (disabled) { var button = el('[data-nce-run]'); if (button) { button.disabled = disabled; } };
    function invalidateAiRun() { state.runSequence++; state.aiRunning = false; setRunButton(false); }
    function updateSelectionUrl() {
        history.replaceState(null, '', window.location.pathname + '?page=' + encodeURIComponent(nceAiEditor.pageSlug || 'nine-code-ultra') + '&kind=' + encodeURIComponent(state.kind) + '&type=' + encodeURIComponent(state.type));
    }
    function unloadTarget() {
        clearReview(); invalidateCommit(); invalidateAiRun(); state.loadSequence++;
        state.id = 0; state.schema = null; state.workspace = null; state.activeGroup = '';
        el('[data-nce-empty]').hidden = false; el('[data-nce-loaded]').hidden = true;
        updateSelectionUrl(); setStatus('Choose content to begin', '');
    }
    var selectedSignature = function (selected) { return (selected || []).slice().sort().join('\n'); };
    function workspaceRequestSignature() {
        if (!state.workspace) { return ''; }
        var selected = (state.workspace.selected || []).slice().sort();
        return JSON.stringify({
            selected: selected,
            values: selected.map(function (id) { return [id, state.workspace.values[id]]; }),
            globalPrompt: state.workspace.global_prompt || '',
            groupPrompts: state.workspace.group_prompts || {},
            fieldPrompts: selected.map(function (id) { return [id, (state.workspace.field_prompts || {})[id] || '']; })
        });
    }
    function currentReviewContext() {
        return {
            kind: state.kind, type: state.type, id: Number(state.id),
            baseState: state.schema ? state.schema.state_sha256 : '',
            selected: selectedSignature(state.workspace ? state.workspace.selected : []),
            requestSignature: workspaceRequestSignature()
        };
    }
    function reviewContextMatches(context) {
        var current = currentReviewContext();
        return context && context.kind === current.kind && context.type === current.type && Number(context.id) === current.id && context.baseState === current.baseState && context.selected === current.selected && context.requestSignature === current.requestSignature;
    }
    var ajax = function (action, data) {
        var body = new URLSearchParams(); body.append('action', action); body.append('nonce', nceAiEditor.nonce);
        Object.keys(data || {}).forEach(function (key) { body.append(key, data[key]); });
        return fetch(nceAiEditor.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
            .then(function (response) { return response.json().catch(function () { throw new Error(nceAiEditor.strings.error); }); })
            .then(function (payload) { if (!payload.success) { var error = new Error(payload.data && payload.data.message ? payload.data.message : nceAiEditor.strings.error); error.conflict = payload.data && payload.data.conflict; throw error; } return payload.data; });
    };
    var currentCatalog = function () { return state.kind === 'post' ? nceAiEditor.postTypes : nceAiEditor.taxonomies; };

    function syncNewTargetButton() {
        var button = el('[data-nce-new-target]');
        if (!button) { return; }
        var type = currentCatalog().filter(function (item) { return item.name === state.type; })[0];
        button.hidden = state.kind !== 'post' || !type;
        if (type) { button.textContent = 'Create new ' + (type.singular || type.label) + ' draft'; }
    }

    function renderTypes() {
        var select = el('[data-nce-type]'); select.innerHTML = '';
        currentCatalog().forEach(function (type) { var option = document.createElement('option'); option.value = type.name; option.textContent = type.label; select.appendChild(option); });
        state.type = select.value || ''; el('[data-nce-search]').value = ''; syncNewTargetButton(); searchTargets();
    }

    function searchTargets() {
        var results = el('[data-nce-results]');
        syncNewTargetButton();
        if (!state.type) { results.innerHTML = '<p>No editable content types are available.</p>'; return; }
        var requestId = ++state.searchSequence; var requestKind = state.kind; var requestType = state.type; var requestSearch = el('[data-nce-search]').value;
        results.innerHTML = '<p class="nce-ai-loading">' + escapeHtml(nceAiEditor.strings.loading) + '</p>';
        ajax('nce_ai_editor_search', { kind: requestKind, type: requestType, search: requestSearch })
            .then(function (data) {
                if (requestId !== state.searchSequence || requestKind !== state.kind || requestType !== state.type) { return; }
                results.innerHTML = data.items.length ? data.items.map(function (item) {
                    return '<button type="button" data-nce-target-id="' + Number(item.id) + '"><b>' + escapeHtml(item.label) + '</b><span>' + escapeHtml(item.meta) + '</span></button>';
                }).join('') : '<p>No matching items.</p>';
            }).catch(function (error) { if (requestId === state.searchSequence && requestKind === state.kind && requestType === state.type) { results.innerHTML = '<p>' + escapeHtml(error.message) + '</p>'; } });
    }

    function createTarget() {
        if (state.kind !== 'post' || !state.type || state.saving) { return; }
        var button = el('[data-nce-new-target]');
        if (button) { button.disabled = true; }
        setStatus('Creating a protected draft…', 'working');
        ajax('nce_ai_editor_create', { kind: 'post', type: state.type, title: '' }).then(function (data) {
            if (data && data.id) { loadTarget(data.id); return; }
            throw new Error('WordPress did not return the new draft.');
        }).catch(function (error) { setStatus(error.message, 'error'); if (button) { button.disabled = false; } });
    }

    function initialWorkspace(schema) {
        var selected = [], values = {};
        schema.groups.forEach(function (group) { group.fields.forEach(function (field) { values[field.id] = field.value; if (field.ai_default !== false) { selected.push(field.id); } }); });
        var workspace = { selected: selected, values: values, group_prompts: {}, field_prompts: {}, global_prompt: '', connector: 'export', updated_at: Date.now() };
        var saved = schema.workspace || {};
        if (saved && Object.prototype.hasOwnProperty.call(saved, 'updated_at')) { ['selected', 'values', 'group_prompts', 'field_prompts', 'global_prompt', 'connector', 'updated_at'].forEach(function (key) { if (Object.prototype.hasOwnProperty.call(saved, key)) { workspace[key] = saved[key]; } }); }
        try {
            var local = JSON.parse(localStorage.getItem(storageKey()) || 'null');
            if (local && Number(local.updated_at || 0) > Number(workspace.updated_at || 0)) { workspace = Object.assign(workspace, local); setStatus('Recovered a newer browser draft', 'success'); }
        } catch (ignore) {}
        var validFields = {}, validGroups = {};
        schema.groups.forEach(function (group) { validGroups[group.id] = true; group.fields.forEach(function (field) { validFields[field.id] = true; }); });
        workspace.selected = Array.from(new Set(Array.isArray(workspace.selected) ? workspace.selected : [])).filter(function (id) { return validFields[id]; });
        workspace.values = Object.keys(validFields).reduce(function (out, id) {
            out[id] = workspace.values && Object.prototype.hasOwnProperty.call(workspace.values, id) ? workspace.values[id] : values[id]; return out;
        }, {});
        workspace.group_prompts = Object.keys(workspace.group_prompts && typeof workspace.group_prompts === 'object' ? workspace.group_prompts : {}).reduce(function (out, id) { if (validGroups[id]) { out[id] = workspace.group_prompts[id]; } return out; }, {});
        workspace.field_prompts = Object.keys(workspace.field_prompts && typeof workspace.field_prompts === 'object' ? workspace.field_prompts : {}).reduce(function (out, id) { if (validFields[id]) { out[id] = workspace.field_prompts[id]; } return out; }, {});
        workspace.updated_at = Number(workspace.updated_at || Date.now());
        return workspace;
    }

    function loadTarget(id) {
        clearTimeout(state.saveTimer); saveWorkspace(); unloadTarget();
        var requestId = ++state.loadSequence; var requestKind = state.kind; var requestType = state.type; var requestTargetId = Number(id);
        state.id = requestTargetId; setStatus(nceAiEditor.strings.loading, 'working');
        ajax('nce_ai_editor_load', { kind: requestKind, type: requestType, id: requestTargetId }).then(function (schema) {
            if (requestId !== state.loadSequence || requestKind !== state.kind || requestType !== state.type || requestTargetId !== state.id) { return; }
            state.schema = schema; state.activeGroup = schema.groups.length ? schema.groups[0].id : ''; state.workspace = initialWorkspace(schema);
            el('[data-nce-empty]').hidden = true; el('[data-nce-loaded]').hidden = false;
            el('[data-nce-target-type]').textContent = schema.context.label + ' · ' + (state.kind === 'post' ? 'Content' : 'Taxonomy term');
            el('[data-nce-target-title]').textContent = schema.target.label;
            el('[data-nce-target-goal]').textContent = schema.context.goal;
            var viewLink = el('[data-nce-view-site]'); if (viewLink) { viewLink.href = schema.target.view_url || schema.target.edit_url || '#'; viewLink.hidden = !schema.target.view_url && !schema.target.edit_url; }
            el('[data-nce-publish]').hidden = state.kind !== 'post';
            renderConnectorPicker(); renderAiGroups(); renderContentTabs(); setMode('content'); syncNeighborButtons();
            el('[data-nce-global-prompt]').value = state.workspace.global_prompt || '';
            updateSelectAll(); setStatus('Protected draft ready', 'success');
            history.replaceState(null, '', window.location.pathname + '?page=' + encodeURIComponent(nceAiEditor.pageSlug || 'nine-code-ultra') + '&kind=' + encodeURIComponent(state.kind) + '&type=' + encodeURIComponent(state.type) + '&id=' + state.id);
        }).catch(function (error) { if (requestId === state.loadSequence && requestKind === state.kind && requestType === state.type && requestTargetId === state.id) { setStatus(error.message, 'error'); } });
    }


    function syncNeighborButtons() {
        if (!state.schema || !state.schema.target) { return; }
        var previous = el('[data-nce-previous]');
        var next = el('[data-nce-next]');
        if (previous) {
            previous.disabled = !Number(state.schema.target.previous_id || 0);
            previous.dataset.nceNeighborId = Number(state.schema.target.previous_id || 0) || '';
        }
        if (next) {
            next.disabled = !Number(state.schema.target.next_id || 0);
            next.dataset.nceNeighborId = Number(state.schema.target.next_id || 0) || '';
        }
    }

    function exportExcel() {
        if (!state.type || !nceAiEditor.excelExportBase) { setStatus('Choose a content type first.', 'error'); return; }
        var url = new URL(nceAiEditor.excelExportBase, window.location.origin);
        url.searchParams.set('kind', 'post');
        url.searchParams.set('post_type', state.type);
        url.searchParams.set('scope', state.type);
        url.searchParams.set('format', 'xlsx');
        window.location.href = url.toString();
    }

    function chooseMedia(fieldId, fileMode) {
        if (!window.wp || !wp.media || !state.workspace) { setStatus('WordPress Media Library is unavailable on this screen.', 'error'); return; }
        var frame = wp.media({ title: fileMode ? 'Choose file' : 'Choose image', button: { text: fileMode ? 'Use file' : 'Use image' }, multiple: false, library: fileMode ? {} : { type: 'image' } });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            state.workspace.values[fieldId] = Number(attachment.id || 0);
            var field = findField(fieldId);
            if (field) { field.preview_url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : (attachment.url || ''); field.file_url = attachment.url || ''; }
            clearReview(); scheduleSave(); renderContentFields(); renderAiGroups();
        });
        frame.open();
    }

    function renderConnectorPicker() {
        var picker = el('[data-nce-connector-picker]'); var html = '<label><span>Delivery method</span><select data-nce-connector>';
        Object.keys(state.schema.connectors || {}).forEach(function (id) {
            var row = state.schema.connectors[id]; var disabled = id !== 'export' && (!row.enabled || !row.configured);
            html += '<option value="' + escapeHtml(id) + '" ' + (disabled ? 'disabled' : '') + '>' + escapeHtml(row.label) + (disabled ? ' — configure below' : '') + '</option>';
        });
        picker.innerHTML = html + '</select></label><a href="#nce-ai-connections">Configure connections</a>';
        var select = el('[data-nce-connector]', picker); select.value = state.workspace.connector && !select.querySelector('option[value="' + cssEscape(state.workspace.connector) + '"]:disabled') ? state.workspace.connector : 'export';
        select.addEventListener('change', function () { state.workspace.connector = select.value; scheduleSave(); });
    }

    function groupSelection(group) {
        var selected = group.fields.filter(function (field) { return state.workspace.selected.indexOf(field.id) !== -1; }).length;
        return { count: selected, all: selected === group.fields.length, some: selected > 0 && selected < group.fields.length };
    }

    function renderAiGroups() {
        var wrap = el('[data-nce-ai-groups]');
        wrap.innerHTML = state.schema.groups.map(function (group, index) {
            var selection = groupSelection(group); var prompt = state.workspace.group_prompts[group.id] || '';
            var fields = group.fields.map(function (field) {
                var checked = state.workspace.selected.indexOf(field.id) !== -1; var fieldPrompt = state.workspace.field_prompts[field.id] || '';
                return '<article class="nce-ai-field-row" data-field-id="' + escapeHtml(field.id) + '"><label class="nce-ai-field-select"><input type="checkbox" data-nce-field-select ' + (checked ? 'checked' : '') + '><span><b>' + escapeHtml(field.label) + '</b><small>' + escapeHtml(valuePreview(state.workspace.values[field.id])) + '</small></span></label><button type="button" class="button-link" data-nce-field-prompt-toggle>' + (fieldPrompt ? 'Edit prompt' : 'Add prompt') + '</button><div class="nce-ai-inline-prompt" data-nce-field-prompt-wrap ' + (fieldPrompt ? '' : 'hidden') + '><textarea rows="3" data-nce-field-prompt placeholder="Tell AI exactly what to do with this field.">' + escapeHtml(fieldPrompt) + '</textarea></div></article>';
            }).join('');
            return '<details class="nce-ai-group" data-group-id="' + escapeHtml(group.id) + '" ' + (index === 0 ? 'open' : '') + '><summary><label><input type="checkbox" data-nce-group-select ' + (selection.all ? 'checked' : '') + '><span><b>' + escapeHtml(group.label) + '</b><small>' + selection.count + ' of ' + group.fields.length + ' fields selected</small></span></label><button type="button" class="button-link" data-nce-group-prompt-toggle>' + (prompt ? 'Edit section prompt' : 'Section prompt') + '</button></summary><div class="nce-ai-group-body"><p>' + escapeHtml(group.description || '') + '</p><div class="nce-ai-inline-prompt" data-nce-group-prompt-wrap ' + (prompt ? '' : 'hidden') + '><textarea rows="4" data-nce-group-prompt placeholder="Instruction for this entire section.">' + escapeHtml(prompt) + '</textarea></div>' + fields + '</div></details>';
        }).join('');
        all('[data-nce-group-select]', wrap).forEach(function (input) { var group = input.closest('[data-group-id]'); input.indeterminate = groupSelection(findGroup(group.dataset.groupId)).some; });
    }

    function valuePreview(value) {
        if (Array.isArray(value)) { return value.length ? value.join(', ') : 'Empty'; }
        if (value && typeof value === 'object') { return JSON.stringify(value).slice(0, 120); }
        var text = String(value == null ? '' : value).replace(/<[^>]*>/g, '').trim(); return text ? text.slice(0, 120) : 'Empty';
    }
    function findGroup(id) { return state.schema.groups.filter(function (group) { return group.id === id; })[0]; }
    function findField(id) { return fieldMap()[id]; }

    function renderContentTabs() {
        var tabs = el('[data-nce-content-tabs]');
        tabs.innerHTML = state.schema.groups.map(function (group) { return '<button type="button" class="' + (group.id === state.activeGroup ? 'is-active' : '') + '" data-nce-content-tab="' + escapeHtml(group.id) + '">' + escapeHtml(group.label) + '</button>'; }).join('');
        renderContentFields();
    }

    function renderContentFields() {
        var group = findGroup(state.activeGroup); var wrap = el('[data-nce-content-fields]');
        if (!group) { wrap.innerHTML = '<p>No editable fields.</p>'; return; }
        wrap.innerHTML = '<header class="nce-content-group-head"><div><h3>' + escapeHtml(group.label) + '</h3><p>' + escapeHtml(group.description || '') + '</p></div><button type="button" class="button" data-nce-switch-ai-group>Prompt these fields</button></header><div class="nce-content-field-grid">' + group.fields.map(renderInput).join('') + '</div>';
    }

    function renderInput(field) {
        var value = state.workspace.values[field.id]; var control = '';
        if (field.type === 'textarea') { control = '<textarea rows="10" data-nce-content-input>' + escapeHtml(value || '') + '</textarea>'; }
        else if (field.type === 'json') { control = '<textarea rows="8" data-nce-content-input data-json="1">' + escapeHtml(typeof value === 'string' ? value : JSON.stringify(value == null ? {} : value, null, 2)) + '</textarea>'; }
        else if (field.type === 'select') { control = '<select data-nce-content-input>' + (field.options || []).map(function (option) { return '<option value="' + escapeHtml(option.value) + '" ' + (String(value) === String(option.value) ? 'selected' : '') + '>' + escapeHtml(option.label) + '</option>'; }).join('') + '</select>'; }
        else if (field.type === 'multiselect') { var values = Array.isArray(value) ? value.map(String) : []; control = '<select multiple size="7" data-nce-content-input>' + (field.options || []).map(function (option) { return '<option value="' + escapeHtml(option.value) + '" ' + (values.indexOf(String(option.value)) !== -1 ? 'selected' : '') + '>' + escapeHtml(option.label) + '</option>'; }).join('') + '</select><small>Use Ctrl/Cmd to choose more than one.</small>'; }
        else if (field.type === 'checkbox') { control = '<label class="nce-content-toggle"><input type="checkbox" data-nce-content-input ' + (value ? 'checked' : '') + '><span>Enabled</span></label>'; }
        else if (field.type === 'media' || field.type === 'file') {
            var preview = field.preview_url || '';
            var mediaId = Number(value || 0);
            var isFile = field.type === 'file';
            control = '<div class="nce-content-media">' + (preview && !isFile ? '<img src="' + escapeHtml(preview) + '" alt="">' : '') + '<div><input type="number" min="0" data-nce-content-input value="' + mediaId + '"><div class="nce-content-media__actions"><button type="button" class="button" data-nce-media-choose data-nce-media-field="' + escapeHtml(field.id) + '" data-nce-media-file="' + (isFile ? '1' : '0') + '">' + (isFile ? 'Choose file' : 'Choose image') + '</button><button type="button" class="button-link-delete" data-nce-media-clear data-nce-media-field="' + escapeHtml(field.id) + '" ' + (!mediaId ? 'disabled' : '') + '>Clear</button></div>' + (isFile && field.file_url ? '<small>' + escapeHtml(field.file_url) + '</small>' : '') + '</div></div>';
        }
        else { control = '<input type="' + (field.type === 'number' ? 'number' : 'text') + '" data-nce-content-input value="' + escapeHtml(value == null ? '' : value) + '">'; }
        return '<label class="nce-content-field" data-field-id="' + escapeHtml(field.id) + '"><span><b>' + escapeHtml(field.label) + '</b>' + (field.instructions ? '<small>' + escapeHtml(field.instructions) + '</small>' : '') + '</span>' + control + '</label>';
    }

    function readContentInput(input) {
        if (input.type === 'checkbox') { return input.checked ? 1 : 0; }
        if (input.multiple) { return Array.prototype.slice.call(input.selectedOptions).map(function (option) { return option.value; }); }
        if (input.dataset.json) { try { return JSON.parse(input.value); } catch (ignore) { return input.value; } }
        return input.value;
    }

    function setMode(mode) {
        state.mode = mode; el('[data-nce-ai-mode]').hidden = mode !== 'ai'; el('[data-nce-content-mode]').hidden = mode !== 'content';
        all('[data-nce-mode]').forEach(function (button) { var active = button.dataset.nceMode === mode; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); });
        if (mode === 'content') { renderContentTabs(); } else { renderAiGroups(); updateSelectAll(); }
    }

    function scheduleSave() {
        if (!state.workspace) { return; } state.workspace.updated_at = Date.now();
        try { localStorage.setItem(storageKey(), JSON.stringify(state.workspace)); } catch (ignore) {}
        setStatus('Draft protected in this browser · saving to site…', 'working'); clearTimeout(state.saveTimer); state.saveTimer = window.setTimeout(saveWorkspace, 1200);
    }

    function saveWorkspace() {
        if (!state.workspace) { return; }
        var snapshot = { kind: state.kind, type: state.type, id: state.id, storageKey: storageKey(), updatedAt: Number(state.workspace.updated_at || 0), workspace: JSON.stringify(state.workspace) };
        if (state.saving) { queueWorkspaceSnapshot(snapshot); return; }
        sendWorkspaceSnapshot(snapshot);
    }

    function queueWorkspaceSnapshot(snapshot) {
        for (var index = 0; index < state.pendingSaves.length; index++) {
            if (state.pendingSaves[index].storageKey === snapshot.storageKey) { state.pendingSaves[index] = snapshot; return; }
        }
        state.pendingSaves.push(snapshot);
    }

    function sendWorkspaceSnapshot(snapshot) {
        state.saving = true;
        ajax('nce_ai_editor_autosave', { kind: snapshot.kind, type: snapshot.type, id: snapshot.id, workspace: snapshot.workspace })
            .then(function (data) {
                state.saving = false;
                if (snapshot.storageKey === storageKey() && state.workspace && Number(state.workspace.updated_at || 0) <= snapshot.updatedAt && Number(data.updated_at || 0) > 0) {
                    state.workspace.updated_at = Number(data.updated_at); try { localStorage.setItem(storageKey(), JSON.stringify(state.workspace)); } catch (ignore) {}
                }
                if (state.pendingSaves.length) { sendWorkspaceSnapshot(state.pendingSaves.shift()); return; }
                if (snapshot.storageKey === storageKey()) { setStatus(nceAiEditor.strings.saved + ' · ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), 'success'); }
            })
            .catch(function (error) {
                state.saving = false;
                if (state.pendingSaves.length) { sendWorkspaceSnapshot(state.pendingSaves.shift()); return; }
                if (snapshot.storageKey === storageKey()) { setStatus('Browser draft safe · site save failed: ' + error.message, 'error'); }
            });
    }

    function updateSelectAll() {
        var input = el('[data-nce-select-all]'); if (!state.schema) { return; }
        var total = Object.keys(fieldMap()).length; var count = state.workspace.selected.length; input.checked = total > 0 && count === total; input.indeterminate = count > 0 && count < total;
    }

    function buildPackage() {
        var map = fieldMap(), selected = state.workspace.selected.filter(function (id) { return map[id]; });
        return {
            format: '9-code-ai-editor-package', schema: 1, generated_at: new Date().toISOString(),
            target: state.schema.target, context: state.schema.context, base_state_sha256: state.schema.state_sha256,
            workflow: { name: '9Core Unified AI Data Manager', mode: state.mode, selected_field_count: selected.length, total_field_count: Object.keys(map).length, manual_values_are_authoritative: true, review_before_commit: true },
            selected_field_ids: selected.slice(),
            instructions: { global: state.workspace.global_prompt || '', groups: state.workspace.group_prompts || {}, fields: state.workspace.field_prompts || {} },
            fields: selected.map(function (id) { var field = map[id]; return { id: id, group: groupForField(id), label: field.label, type: field.type, storage: field.storage, source_key: field.key, editable: field.editable !== false, current_value: state.workspace.values[id], instructions: field.instructions || '', prompt: state.workspace.field_prompts[id] || '' }; }),
            reference_values: Object.keys(map).reduce(function (out, id) { out[id] = { label: map[id].label, value: state.workspace.values[id], selected: selected.indexOf(id) !== -1 }; return out; }, {}),
            result_contract: { format: '9-code-ai-editor-result', schema: 1, target: state.schema.target, base_state_sha256: state.schema.state_sha256, changes: { field_id: 'replacement value' }, summary: 'short explanation' },
            safety: ['Change only selected field IDs.', 'Preserve verified facts and manually prefilled values unless instructed.', 'Do not return executable code outside a selected content field.', 'Do not publish; every result is reviewed in 9 Code first.']
        };
    }
    function groupForField(id) { var match = ''; state.schema.groups.some(function (group) { if (group.fields.some(function (field) { return field.id === id; })) { match = group.id; return true; } return false; }); return match; }
    function downloadPackage() {
        if (!state.workspace.selected.length) { setStatus('Select at least one field before exporting', 'error'); return; }
        var payload = JSON.stringify(buildPackage(), null, 2); var blob = new Blob([payload], { type: 'application/json' }); var link = document.createElement('a');
        link.href = URL.createObjectURL(blob); link.download = '9-code-ai-' + state.type + '-' + state.id + '-' + new Date().toISOString().slice(0, 10) + '.json'; link.click(); window.setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000); setStatus('AI package exported', 'success');
    }

    function reviewChanges(changes, summary, context) {
        context = context || currentReviewContext();
        if (!reviewContextMatches(context)) { setStatus('AI result discarded because the target or selected fields changed while it was processing.', 'error'); return false; }
        var map = fieldMap(); var ids = Object.keys(changes || {}).filter(function (id) { return map[id] && state.workspace.selected.indexOf(id) !== -1; });
        if (!ids.length) { setStatus('The AI result contains no recognized field changes', 'error'); return false; }
        var before = ids.reduce(function (values, id) { values[id] = JSON.stringify(state.workspace.values[id]); return values; }, {});
        clearReview();
        var panel = document.createElement('section'); panel.className = 'nce-ai-review'; panel.dataset.nceReview = '';
        panel.innerHTML = '<header><div><p>REVIEW BEFORE APPLYING</p><h3>AI proposed ' + ids.length + ' field change' + (ids.length === 1 ? '' : 's') + '</h3><span>' + escapeHtml(summary || 'Nothing has been saved or published.') + '</span></div><button type="button" class="button-link" data-nce-review-close>Close</button></header><div class="nce-ai-review-list">' + ids.map(function (id) { return '<label><input type="checkbox" data-nce-review-field="' + escapeHtml(id) + '" checked><span><b>' + escapeHtml(map[id].label) + '</b><small>Before: ' + escapeHtml(valuePreview(state.workspace.values[id])) + '</small><em>After: ' + escapeHtml(valuePreview(changes[id])) + '</em></span></label>'; }).join('') + '</div><footer><button type="button" class="button" data-nce-review-close>Cancel</button><button type="button" class="button button-primary" data-nce-apply-review>Apply selected to workspace</button></footer>';
        el('.nce-ai-target-header').after(panel); panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        all('[data-nce-review-close]', panel).forEach(function (button) { button.addEventListener('click', function () { panel.remove(); }); });
        el('[data-nce-apply-review]', panel).addEventListener('click', function () {
            if (!reviewContextMatches(context)) { panel.remove(); setStatus('Review expired because the target, saved content or selected fields changed. Run or import the AI result again.', 'error'); return; }
            var chosen = all('[data-nce-review-field]:checked', panel);
            var changedSinceReview = chosen.some(function (input) { return before[input.dataset.nceReviewField] !== JSON.stringify(state.workspace.values[input.dataset.nceReviewField]); });
            if (changedSinceReview) { panel.remove(); setStatus('Review expired because a field was manually edited after the result arrived. Run or import the AI result again.', 'error'); return; }
            if (!chosen.length) { setStatus('Select at least one reviewed field to apply', 'error'); return; }
            chosen.forEach(function (input) { state.workspace.values[input.dataset.nceReviewField] = changes[input.dataset.nceReviewField]; });
            panel.remove(); scheduleSave(); renderAiGroups(); if (state.mode === 'content') { renderContentFields(); } setStatus('AI changes applied to protected draft · review and save when ready', 'success');
        });
        return true;
    }

    function runAi() {
        if (state.aiRunning || !state.workspace || !state.schema) { return; }
        if (!state.workspace.selected.length) { setStatus('Select at least one field before running AI', 'error'); return; }
        var provider = state.workspace.connector || 'export'; if (provider === 'export') { downloadPackage(); return; }
        var aiPackage = buildPackage(); var reviewContext = currentReviewContext(); var requestId = ++state.runSequence;
        state.aiRunning = true; setRunButton(true); setStatus('AI is processing the selected fields…', 'working');
        ajax('nce_ai_editor_run', { kind: reviewContext.kind, type: reviewContext.type, id: reviewContext.id, provider: provider, payload: JSON.stringify(aiPackage) })
            .then(function (data) { if (requestId !== state.runSequence) { return; } state.aiRunning = false; setRunButton(false); if (reviewChanges(data.changes, data.summary, reviewContext)) { setStatus('AI result ready for review', 'success'); } })
            .catch(function (error) { if (requestId !== state.runSequence) { return; } state.aiRunning = false; setRunButton(false); setStatus(error.message, 'error'); });
    }

    function importResult(file) {
        if (!file || file.size > nceAiEditor.maxPackageBytes) { setStatus('Choose a JSON result under 1 MB', 'error'); return; }
        var importContext = currentReviewContext(); var reader = new FileReader(); reader.onload = function () {
            try {
                if ( ! reviewContextMatches(importContext) ) { throw new Error('This import expired because the selected content or fields changed. Choose the item again and re-import the result.'); }
                var data = JSON.parse(reader.result);
                if (data.format !== '9-code-ai-editor-result' || Number(data.schema) !== 1 || !data.changes || Object.prototype.toString.call(data.changes) !== '[object Object]') { throw new Error('This is not a valid 9Core AI Data Manager result file.'); }
                if (!data.target || Number(data.target.id) !== state.id || data.target.type !== state.type || data.target.kind !== state.kind) { throw new Error('This result does not prove that it belongs to the selected content.'); }
                if (!data.base_state_sha256 || data.base_state_sha256 !== state.schema.state_sha256) { throw new Error('This result is stale or does not identify the saved content state.'); }
                reviewChanges(data.changes, data.summary || 'Imported AI result', currentReviewContext());
            } catch (error) { setStatus(error.message, 'error'); }
        }; reader.readAsText(file);
    }

    function commit(intent) {
        if (state.committing || !state.schema || !state.workspace) { return; }
        intent = intent === 'publish' || intent === 'draft' ? intent : 'save';
        var requestId = ++state.commitSequence;
        var context = {
            kind: state.kind, type: state.type, id: Number(state.id),
            baseState: state.schema.state_sha256,
            values: JSON.stringify(state.workspace.values), intent: intent
        };
        state.committing = true; setCommitButtons(true);
        setStatus(intent === 'publish' ? 'Publishing content…' : (intent === 'draft' ? 'Saving as draft…' : 'Saving content…'), 'working');
        ajax('nce_ai_editor_commit', { kind: context.kind, type: context.type, id: context.id, base_state_sha256: context.baseState, values: context.values, intent: context.intent })
            .then(function (data) {
                if (requestId !== state.commitSequence) { return; }
                state.committing = false; setCommitButtons(false);
                if (context.kind !== state.kind || context.type !== state.type || context.id !== Number(state.id) || !state.schema || context.baseState !== state.schema.state_sha256) { return; }
                state.schema.state_sha256 = data.state_sha256;
                if (data.target) { state.schema.target = Object.assign(state.schema.target || {}, data.target); syncNeighborButtons(); }
                setStatus(data.message, 'success'); saveWorkspace();
            })
            .catch(function (error) {
                if (requestId !== state.commitSequence) { return; }
                state.committing = false; setCommitButtons(false);
                if (context.kind !== state.kind || context.type !== state.type || context.id !== Number(state.id)) { return; }
                setStatus(error.message, 'error'); if (error.conflict) { el('[data-nce-save-state]').insertAdjacentHTML('beforeend', ' · Reload this item'); }
            });
    }

    root.addEventListener('click', function (event) {
        var button = event.target.closest('button'); if (!button) { return; }
        if (button.dataset.nceKind) { clearTimeout(state.saveTimer); saveWorkspace(); state.kind = button.dataset.nceKind; all('[data-nce-kind]').forEach(function (item) { item.classList.toggle('is-active', item === button); }); renderTypes(); unloadTarget(); }
        else if (button.dataset.nceTargetId) { loadTarget(button.dataset.nceTargetId); }
        else if (button.hasAttribute('data-nce-new-target')) { createTarget(); }
        else if (button.dataset.nceMode) { setMode(button.dataset.nceMode); }
        else if (button.hasAttribute('data-nce-global-prompt-toggle')) { var global = el('[data-nce-global-prompt-wrap]'); global.hidden = !global.hidden; if (!global.hidden) { el('textarea', global).focus(); } }
        else if (button.hasAttribute('data-nce-export')) { downloadPackage(); }
        else if (button.hasAttribute('data-nce-run')) { runAi(); }
        else if (button.hasAttribute('data-nce-group-prompt-toggle')) { event.preventDefault(); var groupWrap = button.closest('[data-group-id]'); var promptWrap = el('[data-nce-group-prompt-wrap]', groupWrap); promptWrap.hidden = !promptWrap.hidden; if (!promptWrap.hidden) { el('textarea', promptWrap).focus(); } }
        else if (button.hasAttribute('data-nce-field-prompt-toggle')) { var row = button.closest('[data-field-id]'); var fieldWrap = el('[data-nce-field-prompt-wrap]', row); fieldWrap.hidden = !fieldWrap.hidden; if (!fieldWrap.hidden) { el('textarea', fieldWrap).focus(); } }
        else if (button.dataset.nceContentTab) { state.activeGroup = button.dataset.nceContentTab; renderContentTabs(); }
        else if (button.hasAttribute('data-nce-switch-ai-group')) { setMode('ai'); var group = el('[data-group-id="' + cssEscape(state.activeGroup) + '"]'); if (group) { group.open = true; group.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
        else if (button.hasAttribute('data-nce-media-choose')) { chooseMedia(button.dataset.nceMediaField, button.dataset.nceMediaFile === '1'); }
        else if (button.hasAttribute('data-nce-media-clear')) { var mediaField = button.dataset.nceMediaField; state.workspace.values[mediaField] = 0; var mediaSchema = findField(mediaField); if (mediaSchema) { mediaSchema.preview_url = ''; mediaSchema.file_url = ''; } clearReview(); scheduleSave(); renderContentFields(); renderAiGroups(); }
        else if (button.hasAttribute('data-nce-excel-export')) { exportExcel(); }
        else if (button.dataset.nceNeighborId) { loadTarget(Number(button.dataset.nceNeighborId)); }
        else if (button.hasAttribute('data-nce-save-draft') && !state.committing && window.confirm(nceAiEditor.strings.confirmDraft)) { commit('draft'); }
        else if (button.hasAttribute('data-nce-save-content')) { commit('save'); }
        else if (button.hasAttribute('data-nce-publish') && !state.committing && window.confirm(nceAiEditor.strings.confirmPublish)) { commit('publish'); }
    });

    root.addEventListener('change', function (event) {
        var input = event.target;
        if (input.matches('[data-nce-type]')) { clearTimeout(state.saveTimer); saveWorkspace(); state.type = input.value; unloadTarget(); searchTargets(); }
        else if (input.matches('[data-nce-select-all]')) { clearReview(); state.workspace.selected = input.checked ? Object.keys(fieldMap()) : []; renderAiGroups(); updateSelectAll(); scheduleSave(); }
        else if (input.matches('[data-nce-group-select]')) { clearReview(); var group = findGroup(input.closest('[data-group-id]').dataset.groupId); group.fields.forEach(function (field) { var index = state.workspace.selected.indexOf(field.id); if (input.checked && index === -1) { state.workspace.selected.push(field.id); } else if (!input.checked && index !== -1) { state.workspace.selected.splice(index, 1); } }); renderAiGroups(); updateSelectAll(); scheduleSave(); }
        else if (input.matches('[data-nce-field-select]')) { clearReview(); var id = input.closest('[data-field-id]').dataset.fieldId; var at = state.workspace.selected.indexOf(id); if (input.checked && at === -1) { state.workspace.selected.push(id); } else if (!input.checked && at !== -1) { state.workspace.selected.splice(at, 1); } renderAiGroups(); updateSelectAll(); scheduleSave(); }
        else if (input.matches('[data-nce-content-input]')) { state.workspace.values[input.closest('[data-field-id]').dataset.fieldId] = readContentInput(input); scheduleSave(); }
        else if (input.matches('[data-nce-import]')) { importResult(input.files[0]); input.value = ''; }
    });
    root.addEventListener('input', function (event) {
        var input = event.target;
        if (input.matches('[data-nce-search]')) { clearTimeout(state.searchTimer); state.searchTimer = window.setTimeout(searchTargets, 250); }
        else if (input.matches('[data-nce-global-prompt]')) { state.workspace.global_prompt = input.value; scheduleSave(); }
        else if (input.matches('[data-nce-group-prompt]')) { state.workspace.group_prompts[input.closest('[data-group-id]').dataset.groupId] = input.value; scheduleSave(); }
        else if (input.matches('[data-nce-field-prompt]')) { state.workspace.field_prompts[input.closest('[data-field-id]').dataset.fieldId] = input.value; scheduleSave(); }
        else if (input.matches('[data-nce-content-input]') && !input.multiple && input.type !== 'checkbox') { state.workspace.values[input.closest('[data-field-id]').dataset.fieldId] = readContentInput(input); scheduleSave(); }
    });

    window.addEventListener('beforeunload', function () { if (state.workspace) { try { localStorage.setItem(storageKey(), JSON.stringify(state.workspace)); } catch (ignore) {} } });
    clearUnsafeLegacyWorkspaceKeys(); renderTypes();
    var params = new URLSearchParams(window.location.search); var requestedKind = params.get('kind'), requestedType = params.get('type'), requestedId = Number(params.get('id'));
    if ((requestedKind === 'post' || requestedKind === 'term') && requestedType && requestedId) {
        state.kind = requestedKind; all('[data-nce-kind]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.nceKind === state.kind); }); renderTypes();
        var typeSelect = el('[data-nce-type]'); if (typeSelect.querySelector('option[value="' + cssEscape(requestedType) + '"]')) { typeSelect.value = requestedType; state.type = requestedType; searchTargets(); loadTarget(requestedId); }
    }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot, { once: true }); } else { boot(); }
}());
