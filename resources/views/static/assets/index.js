const docs = JSON.parse(document.getElementById('docs-data').textContent);
const labels = {api:'API', database:'DB', code:'Code'};
const externalDocs = docs._meta?.external_docs || {};
const activeTab = document.body.dataset.activeTab || 'api';
const themeToggle = document.getElementById('theme-toggle');
const pdfButton = document.getElementById('pdf-download');
let state = {
  apiGroupBy: localStorage.getItem('laravel-docs-api-group-by') || 'endpoints',
  codeGroupBy: localStorage.getItem('laravel-docs-code-group-by') || 'namespaces',
  theme: localStorage.getItem('laravel-docs-theme') || 'light',
  selected: 0,
  tab: ['api','database','code'].includes(activeTab) ? activeTab : 'api',
};
if (!['endpoints','requests'].includes(state.apiGroupBy)) state.apiGroupBy = 'endpoints';
if (!['namespaces','types'].includes(state.codeGroupBy)) state.codeGroupBy = 'namespaces';
if (!['light','dark'].includes(state.theme)) state.theme = 'light';
const sidebar = document.getElementById('sidebar');
const content = document.getElementById('content');
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const block = value => `<pre>${esc(JSON.stringify(value ?? {}, null, 2))}</pre>`;
const cell = value => value && typeof value === 'object' && 'html' in value ? value.html : esc(value);
const table = (headers, rows) => rows.length ? `<table><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(value=>`<td>${cell(value)}</td>`).join('')}</tr>`).join('')}</tbody></table>` : '<p class="muted">None.</p>';
const list = value => Array.isArray(value) ? value : Object.values(value || {});
const memberId = (kind, name) => `${kind}_${String(name || '').replace(/[^a-zA-Z0-9_-]+/g, '_')}`;
const basename = path => String(path || '').split(/[\\/]/).pop();
const diagramView = {scale: Number(localStorage.getItem('laravel-docs-database-diagram-scale') || '1'), offsetX: 0, offsetY: 0, initialized: false};
function activate(index){state.selected=index;render();}
function methodClass(method){return `method method-${esc(String(method || 'get').toLowerCase())}`;}
function applyTheme(){
  document.documentElement.dataset.theme = state.theme;
  if (themeToggle) themeToggle.textContent = state.theme === 'dark' ? 'Dark' : 'Light';
}
function toggleTheme(){
  state.theme = state.theme === 'dark' ? 'light' : 'dark';
  localStorage.setItem('laravel-docs-theme', state.theme);
  applyTheme();
}
themeToggle?.addEventListener('click', toggleTheme);
pdfButton?.addEventListener('click', downloadPdf);
applyTheme();
function activateCodeReference(value){
  const index = typeIndex(value);
  if (index === null) return;
  state.tab = 'code';
  state.selected = index;
  render();
}
function activateDatabaseReference(tableName, columnName){
  const index = entriesFor('database').findIndex(entry => entry.item.name === tableName);
  if (index === -1) return;
  state.selected = index;
  render();
  const id = memberId('column', columnName);
  window.location.hash = id;
  setTimeout(() => document.getElementById(id)?.scrollIntoView({block:'start'}), 0);
}
function render(){renderSidebar();renderContent();}
function renderSidebar(){
  const entries = entriesFor(state.tab);
  if (!entries.length){sidebar.innerHTML=`<div class="empty">No ${labels[state.tab]} documentation found.</div>`;return;}
  if (state.tab === 'api' || state.tab === 'code' || state.tab === 'database') { renderGroupedSidebar(entries, state.tab); return; }
  sidebar.innerHTML = entries.map((entry,index)=>`<div class="group">${entry.group?`<h2>${esc(entry.group)}</h2>`:''}<button class="item ${index===state.selected?'active':''}" onclick="activate(${index})">${entry.sidebar}</button></div>`).join('');
}
function renderGroupedSidebar(entries, tab){
  entries = entries.map((entry,index)=>({...entry,index: entry.index ?? index}));
  const standalone = entries.filter(entry => entry.group === null);
  const grouped = entries.filter(entry => entry.group !== null);
  const switcher = tab === 'api' ? apiSidebarSwitcher() : tab === 'code' ? codeSidebarSwitcher() : '';
  const pages = standalone.map(entry => `<button class="item top-item ${entry.index===state.selected?'active':''}" onclick="activate(${entry.index})">${entry.sidebar}</button>`).join('');
  sidebar.innerHTML = switcher + pages + sidebarGroups(grouped).map(group => `<details class="namespace-group" ${group.open ? 'open' : ''}><summary>${group.badge ? typeBadge(group.badge) : ''}<span class="mono">${esc(group.name)}</span></summary><div class="namespace-items">${group.entries.map(entry => `<button class="item ${entry.index===state.selected?'active':''}" onclick="activate(${entry.index})">${entry.sidebar}</button>`).join('')}</div></details>`).join('');
}
function apiSidebarSwitcher(){
  return `<div class="sidebar-switcher" aria-label="API sidebar grouping"><button type="button" class="${state.apiGroupBy === 'endpoints' ? 'active' : ''}" onclick="setApiGroupBy('endpoints')">Endpoints</button><button type="button" class="${state.apiGroupBy === 'requests' ? 'active' : ''}" onclick="setApiGroupBy('requests')">Requests</button></div>`;
}
function setApiGroupBy(groupBy){
  if (!['endpoints','requests'].includes(groupBy)) return;
  state.apiGroupBy = groupBy;
  state.selected = 0;
  localStorage.setItem('laravel-docs-api-group-by', groupBy);
  render();
}
function codeSidebarSwitcher(){
  return `<div class="sidebar-switcher" aria-label="Code sidebar grouping"><button type="button" class="${state.codeGroupBy === 'namespaces' ? 'active' : ''}" onclick="setCodeGroupBy('namespaces')">Namespaces</button><button type="button" class="${state.codeGroupBy === 'types' ? 'active' : ''}" onclick="setCodeGroupBy('types')">Types</button></div>`;
}
function setCodeGroupBy(groupBy){
  if (!['namespaces','types'].includes(groupBy)) return;
  state.codeGroupBy = groupBy;
  state.selected = 0;
  localStorage.setItem('laravel-docs-code-group-by', groupBy);
  render();
}
function sidebarGroups(entries){
  const groups = new Map();
  entries.forEach(entry => {
    const name = entry.group || 'Global';
    if (!groups.has(name)) groups.set(name, {name, badge: entry.groupType || null, entries: [], open: false});
    const group = groups.get(name);
    group.entries.push(entry);
    if (entry.index === state.selected) group.open = true;
  });
  return Array.from(groups.values());
}
function renderContent(){
  const entries = entriesFor(state.tab);
  if (!entries.length){content.innerHTML=`<div class="empty">Run docs:generate after configuring the ${labels[state.tab]} documentation source.</div>`;return;}
  const item = entries[Math.min(state.selected, entries.length - 1)].item;
  content.classList?.toggle('api-detail', state.tab === 'api');
  content.innerHTML = state.tab === 'api' ? apiDetail(item) : state.tab === 'database' ? dbContent(item) : codeDetail(item);
  if (item.kind === 'database-relations-diagram') requestAnimationFrame(() => drawDatabaseDiagram());
}
function entriesFor(tab){
  if (tab === 'api') return apiEntries();
  if (tab === 'database') return withEntryIndexes(databaseDiagramEntries().concat(databaseConstraintEntries(), databaseRelationEntries(), (docs.database.tables||[]).map(table => ({group:'Tables',item:table,sidebar:`<span class="mono">${esc(table.name)}</span>${table.model ? `<span class="muted">: ${esc(table.model)}</span>` : ''}<div class="muted">${(table.columns||[]).length} columns</div>`}))));
  return (docs.code.classes||[]).map(type => {
    const group = state.codeGroupBy === 'types' ? typeGroup(type.type) : type.namespace || 'Global';
    const groupType = state.codeGroupBy === 'types' ? type.type : 'namespace';

    return {group,groupType,item:type,sidebar:`<div class="type-row">${typeBadge(type.type)}<span><span class="mono">${esc(type.name)}</span><div class="muted">${esc(typeLabel(type.type))}</div></span></div>`};
  });
}
function apiEntries(){
  return (docs.api.groups||[]).flatMap(group => (group.endpoints||[]).map(endpoint => {
    const method = String(endpoint.method || 'GET').toUpperCase();

    return {group: state.apiGroupBy === 'requests' ? method : group.name || 'Endpoints',item:endpoint,sidebar:`<div class="line"><span class="${methodClass(method)}">${esc(method)}</span><span class="uri">${esc(endpoint.uri)}</span></div><div class="muted">${esc(endpoint.name||'Untitled endpoint')}</div>`};
  }));
}
function withEntryIndexes(entries){return entries.map((entry,index)=>({...entry,index}));}
function typeGroup(type){return ({class:'Classes',interface:'Interfaces',trait:'Traits',enum:'Enums'}[type]||'Types');}
function typeLabel(type){return ({class:'Class',interface:'Interface',trait:'Trait',enum:'Enum',namespace:'Namespace',method:'Method',property:'Property'}[type]||type||'Type');}
function typeIcon(type){return ({class:'C',interface:'I',trait:'T',enum:'E',namespace:'N',method:'M',property:'P'}[type]||'C');}
function typeBadge(type){const label=typeLabel(type);return `<span class="type-badge type-badge-${esc(type || 'type')}" title="${esc(label)}" aria-label="${esc(label)}">${typeIcon(type)}</span>`;}
function apiDetail(endpoint){
  const method = String(endpoint.method || 'GET').toUpperCase();
  const response = apiFirstResponse(endpoint.responses);
  const curl = apiCurlCommand(endpoint);
  const bodyText = apiBodyPayload(endpoint.body);
  const tryBody = method === 'GET' || method === 'HEAD' || apiBodyParameters(endpoint).length ? '' : `<label>Body<textarea class="api-try-body" spellcheck="false" oninput="updateApiCurl(this)">${esc(bodyText)}</textarea></label>`;
  const headerFields = `<section class="api-headers"><h2>Headers</h2>${Object.entries(apiRequestHeaders(endpoint)).map(([name, example]) => `<label><strong class="mono">${esc(name)}</strong><input type="text" data-api-header="${esc(name)}" value="${esc(name.toLowerCase() === 'authorization' ? apiSavedSetting('authorization') || '' : example)}" placeholder="${esc(example)}" autocomplete="off" spellcheck="false" oninput="updateApiCurl(this)"><span class="muted">Example: <code>${esc(example)}</code></span></label>`).join('')}</section>`;

  return `<div class="api-page"><section class="api-request-panel"><h1>${esc(endpoint.name || endpoint.uri)}</h1>${endpoint.description ? `<p class="lead">${esc(endpoint.description)}</p>` : ''}<p class="muted">Controller: ${esc(endpoint.controller || 'Not available')} &middot; Auth: ${endpoint.authenticated ? 'Required' : 'Not specified'}</p><div class="api-request-heading"><h2>Request</h2><button type="button" class="try-button" onclick="toggleApiTryout(this)">Try it out</button></div><div class="api-route"><span class="${methodClass(method)}">${esc(method)}</span><span class="uri">${esc(endpoint.uri)}</span></div>${headerFields}<div class="api-tryout" hidden><label>Base URL<input class="api-base-url" type="url" oninput="saveApiSetting(\'base-url\', this.value); updateApiCurl(this)" value="${esc(apiDefaultBaseUrl())}" placeholder="https://example.com"></label>${tryBody}<button type="button" class="api-send-button" onclick="runApiTryout(this)">Send Request</button><pre class="api-try-result">Ready.</pre></div><h2>Request Parameters</h2>${block(endpoint.parameters)}${apiBodyFields(endpoint)}</section><aside class="api-example-panel"><div class="code-tabs"><button type="button" class="active">bash</button></div><div class="code-block-title"><h2>Example request:</h2><button type="button" class="copy-button" onclick="copyApiExample(this)">Copy</button></div><pre class="code-sample language-bash">${esc(curl)}</pre><h2>Example body:</h2><pre class="code-sample api-example-body">${esc(apiFormatBody(bodyText))}</pre><h2>Example response (${esc(response.status)}):</h2>${apiResponseHeaders(response)}<pre class="code-sample">${esc(apiResponseBody(response))}</pre></aside></div>`;
}
function apiRequestHeaders(endpoint){
  const headers = {Authorization: 'Bearer {YOUR_BEARER_TOKEN}', 'Content-Type': 'application/json', Accept: 'application/json'};
  for (const [name, value] of Object.entries(endpoint.headers || {})) {
    const key = Object.keys(headers).find(key => key.toLowerCase() === name.toLowerCase()) || name;
    headers[key] = value;
  }
  return headers;
}
function apiBodyParameters(endpoint){
  const content = (endpoint.body_schema || endpoint.body)?.content;
  const media = content?.['application/json'] || Object.values(content || {})[0];
  const schema = media?.schema;
  if (schema?.properties) return Object.entries(schema.properties).map(([name, field]) => ({name, type: field.type || 'string', required: (schema.required || []).includes(name), description: field.description || '', example: field.example ?? field.default ?? (field.type === 'object' ? {} : field.type === 'array' ? [] : '')}));
  try {
    const payload = JSON.parse(apiBodyPayload(endpoint.body));
    if (payload && typeof payload === 'object' && !Array.isArray(payload)) return Object.entries(payload).map(([name, value]) => ({name, type: Array.isArray(value) ? 'array' : value === null ? 'string' : typeof value, example: value}));
  } catch {}
  return [];
}
function apiBodyFields(endpoint){
  const parameters = apiBodyParameters(endpoint);
  if (!parameters.length) return `<h2>Request Body</h2>${block(endpoint.body)}`;
  return `<section class="api-body-fields"><h2>Body Parameters</h2>${parameters.map(field => {
    const example = typeof field.example === 'object' ? JSON.stringify(field.example) : String(field.example);
    const attributes = `data-api-body="${esc(field.name)}" data-type="${esc(field.type)}" ${field.required ? 'required' : ''} oninput="updateApiCurl(this)"`;
    const control = field.type === 'boolean' ? `<select ${attributes}><option value="true" ${field.example === true ? 'selected' : ''}>true</option><option value="false" ${field.example !== true ? 'selected' : ''}>false</option></select>` : field.type === 'object' || field.type === 'array' ? `<textarea ${attributes} spellcheck="false">${esc(example)}</textarea>` : `<input ${attributes} type="${field.type === 'integer' || field.type === 'number' ? 'number' : 'text'}" ${field.type === 'integer' ? 'step="1"' : 'step="any"'} value="${esc(example)}">`;
    return `<label><span><strong class="mono">${esc(field.name)}</strong> <span class="muted">${esc(field.type)}</span>${field.required === false ? ' <em class="muted">optional</em>' : ''}</span>${field.description ? `<span>${esc(field.description)}</span>` : ''}${control}<span class="muted">Example: <code>${esc(example)}</code></span></label>`;
  }).join('')}</section>`;
}
function apiEnteredBody(panel){
  const inputs = Array.from(panel.querySelectorAll('[data-api-body]'));
  if (!inputs.length) return undefined;
  return Object.fromEntries(inputs.filter(input => input.required || input.value !== '').map(input => {
    let value = input.value;
    if (['array', 'object', 'boolean', 'integer', 'number'].includes(input.dataset.type)) value = JSON.parse(value);
    return [input.dataset.apiBody, value];
  }));
}
function apiEnteredHeaders(panel){
  return Object.fromEntries(Array.from(panel.querySelectorAll('[data-api-header]')).filter(input => input.value.trim()).map(input => [input.dataset.apiHeader, input.value.trim()]));
}
function updateApiCurl(input){
  if (input.dataset.apiHeader?.toLowerCase() === 'authorization') saveApiSetting('authorization', input.value);
  const page = input.closest('.api-page');
  const endpoint = entriesFor('api')[state.selected]?.item || {};
  try {
    const body = apiEnteredBody(page) ?? page.querySelector('.api-try-body')?.value;
    page.querySelector('.language-bash').textContent = apiCurlCommand(body === undefined ? endpoint : {...endpoint, body}, apiEnteredHeaders(page));
    page.querySelector('.api-example-body').textContent = apiFormatBody(apiBodyPayload(body === undefined ? endpoint.body : body));
    input.setCustomValidity('');
  } catch {
    input.setCustomValidity('Enter a valid ' + input.dataset.type + ' value.');
  }
}
function apiSettingKey(name){
  return `laravel-docs-api:${window.location.pathname?.replace(/[^/]*$/, '') || '/'}:${name}`;
}
function apiSavedSetting(name){
  try { return localStorage.getItem(apiSettingKey(name)); } catch { return null; }
}
function saveApiSetting(name, value){
  try { localStorage.setItem(apiSettingKey(name), value); } catch {}
}
function apiDefaultBaseUrl(){
  return apiSavedSetting('base-url') ?? (window.location.protocol === 'http:' || window.location.protocol === 'https:' ? window.location.origin : '');
}
function apiCurlCommand(endpoint, requestHeaders){
  const method = String(endpoint.method || 'GET').toUpperCase();
  const uri = apiExampleUri(endpoint);
  const body = apiBodyPayload(endpoint.body);
  const url = /^https?:\/\//i.test(uri) ? uri : (apiDefaultBaseUrl().replace(/\/+$/, '') || '{{baseUrl}}') + uri;
  const headers = requestHeaders ?? {...apiRequestHeaders(endpoint), Authorization: apiSavedSetting('authorization') ?? apiRequestHeaders(endpoint).Authorization};
  const lines = [
    `curl --request ${method} \\`,
    `  --url "${url}" \\`,
    ...Object.entries(headers).filter(([, value]) => value.trim()).map(([name, value]) => `  --header "${`${name}: ${value}`.replace(/[\\"$`]/g, '\\$&')}" \\`),
  ];
  lines[lines.length - 1] = lines[lines.length - 1].slice(0, -2);
  if (body && method !== 'GET' && method !== 'HEAD') {
    lines[lines.length - 1] += ' \\';
    lines.push(`  --data '${body.replaceAll("'", "'\\''")}'`);
  }

  return lines.join('\n');
}
function apiExampleUri(endpoint){
  let uri = String(endpoint.uri || '').replace(/^\{\{baseUrl\}\}/, '');
  const parameters = Array.isArray(endpoint.parameters) ? endpoint.parameters : [];
  if (!/^https?:\/\//i.test(uri)) uri = '/' + uri.replace(/^\/+/, '');
  for (const parameter of parameters) {
    if (parameter.in === 'path' && parameter.example !== undefined) {
      uri = uri.replaceAll(`{${parameter.name}}`, encodeURIComponent(String(parameter.example)));
    }
  }
  const query = parameters.filter(parameter => parameter.in === 'query' && parameter.example !== undefined);
  if (query.length && !uri.includes('?')) uri += '?' + query.map(parameter => `${encodeURIComponent(parameter.name)}=${encodeURIComponent(String(parameter.example))}`).join('&');
  return uri;
}
function apiFormatBody(body){
  try { return JSON.stringify(JSON.parse(body), null, 2); } catch { return body; }
}
function apiBodyPayload(body){
  if (!body || (Array.isArray(body) && body.length === 0)) return '';
  if (typeof body === 'string') return body;
  if (body.mode === 'raw') return body.raw || '';
  if (body.content && typeof body.content === 'object') {
    const json = body.content['application/json'] || Object.values(body.content)[0];
    const schema = json?.example || json?.examples || json?.schema || json;

    return JSON.stringify(schema, null, 2);
  }

  return JSON.stringify(body, null, 2);
}
function apiFirstResponse(responses){
  const entries = Array.isArray(responses) ? responses.map((response, index) => [response.status || response.statusCode || (index === 0 ? '200' : String(index)), response]) : Object.entries(responses || {});
  const [status, response] = entries[0] || ['200', {}];

  return {status, response: response || {}};
}
function apiResponseBody(example){
  const response = example.response;
  if (typeof response === 'string') return response;
  if (response.content && typeof response.content === 'object') {
    const json = response.content['application/json'] || Object.values(response.content)[0];
    const payload = json?.example || json?.examples || json?.schema || json;

    return JSON.stringify(payload, null, 2);
  }
  if ('body' in response) return typeof response.body === 'string' ? response.body : JSON.stringify(response.body, null, 2);
  if ('description' in response) return JSON.stringify({description: response.description}, null, 2);

  return JSON.stringify(response, null, 2);
}
function apiResponseHeaders(example){
  const headers = example.response?.headers;
  if (!headers || !Object.keys(headers).length) return '';

  return `<details class="api-response-headers"><summary>Show headers</summary>${block(headers)}</details>`;
}
function toggleApiTryout(button){
  const panel = button.closest('.api-request-panel')?.querySelector('.api-tryout');
  if (!panel) return;
  panel.hidden = !panel.hidden;
  button.closest('.api-request-panel').classList.toggle('is-trying', !panel.hidden);
  button.classList.toggle('active', !panel.hidden);
}
async function copyApiExample(button){
  const code = button.closest('.api-example-panel')?.querySelector('.code-sample')?.textContent || '';
  try {
    await navigator.clipboard.writeText(code);
    button.textContent = 'Copied';
    setTimeout(() => button.textContent = 'Copy', 1200);
  } catch {
    button.textContent = 'Copy failed';
    setTimeout(() => button.textContent = 'Copy', 1200);
  }
}
async function runApiTryout(button){
  const endpoint = entriesFor('api')[state.selected]?.item || {};
  const panel = button.closest('.api-tryout');
  const result = panel?.querySelector('.api-try-result');
  const base = panel?.querySelector('.api-base-url')?.value.replace(/\/+$/, '');
  const uri = apiExampleUri(endpoint);
  const method = String(endpoint.method || 'GET').toUpperCase();
  if (!panel || !result) return;
  if (!base) {
    result.textContent = 'Enter a base URL first.';
    return;
  }
  result.textContent = 'Sending...';
  try {
    const options = {method, headers: apiEnteredHeaders(button.closest('.api-request-panel'))};
    const requestPanel = button.closest('.api-request-panel');
    const invalid = Array.from(requestPanel.querySelectorAll('input, textarea, select')).find(input => !input.checkValidity());
    if (invalid) { invalid.reportValidity(); result.textContent = 'Check the request fields.'; return; }
    const enteredBody = apiEnteredBody(requestPanel);
    const body = enteredBody === undefined ? panel.querySelector('.api-try-body')?.value.trim() : JSON.stringify(enteredBody);
    if (body && method !== 'GET' && method !== 'HEAD') options.body = body;
    const response = await fetch(/^https?:\/\//i.test(uri) ? uri : base + uri, options);
    const text = await response.text();
    result.textContent = `${response.status} ${response.statusText}\n\n${text}`;
  } catch (error) {
    result.textContent = error?.message || String(error);
  }
}
function dbContent(item){
  if (item.kind === 'database-relations-diagram') return dbRelationsDiagram();
  if (item.kind === 'database-constraints') return dbConstraintsPage();
  if (item.kind === 'database-relation') return dbRelationDetail(item);

  return dbDetail(item);
}
function databaseDiagramEntries(){
  const relationships = diagramRelationships();
  return [{group:null,item:{kind:'database-relations-diagram'},sidebar:`<span class="mono">Diagrams</span><div class="muted">${relationships.length} relationships</div>`}];
}
function databaseConstraintEntries(){
  const constraints = list(docs.database.constraints);
  return [{group:null,item:{kind:'database-constraints'},sidebar:`<span class="mono">Constraints</span><div class="muted">${constraints.length} foreign keys</div>`}];
}
function databaseRelationEntries(){
  return list(docs.database.relationships).map(relation => ({group:'Relations',item:{kind:'database-relation',...relation},sidebar:`<span class="mono">${esc(relation.from_table)}.${esc(relation.from_column)}</span><div class="muted">references ${esc(relation.to_table)}.${esc(relation.to_column)}</div>`}));
}
function dbDetail(tableInfo){
  return `<h1>${esc(tableInfo.name)}${tableInfo.model ? `: ${dbModelLink(tableInfo)}` : ''}</h1><h2>Columns</h2>${table(['Name','Type','Model Type','Nullable','Default','Primary','Description'],list(tableInfo.columns).map(c=>[{html:`<span id="${memberId('column', c.name)}" class="column-anchor mono">${esc(c.name)}</span>`},c.type,c.model_type,c.nullable?'yes':'no',c.default,c.primary?'yes':'no',c.description]))}<h2>Primary Keys</h2>${table(['Column'],list(tableInfo.primary_keys).map(k=>[k]))}<h2>Foreign Keys</h2>${table(['Column','References'],list(tableInfo.foreign_keys).map(k=>[k.column,{html:dbReferenceLink(k.references_table,k.references_column)}]))}<h2>Indexes</h2>${table(['Name','Unique','Columns'],list(tableInfo.indexes).map(i=>[i.name,i.unique?'yes':'no',list(i.columns).join(', ')]))}`;
}
function dbRelationsDiagram(){
  const relationships = list(docs.database.relationships);

  return `<h1>Relations</h1>${databaseDiagramMarkup('database-relations-canvas')}<h2>Relationships</h2>${table(['Column','References'],relationships.map(relation=>[{html:dbColumnReferenceLink(relation.from_table,relation.from_column)}, {html:dbReferenceLink(relation.to_table,relation.to_column)}]))}`;
}
function databaseDiagramMarkup(canvasId){
  return `<div class="diagram-toolbar"><button type="button" onclick="setDiagramZoom(-0.1)">-</button><button type="button" onclick="resetDiagramView()">Reset</button><button type="button" onclick="setDiagramZoom(0.1)">+</button></div><div class="diagram-shell"><canvas id="${esc(canvasId)}"></canvas></div>`;
}
function dbConstraintsPage(){
  const constraints = list(docs.database.constraints);

  return `<h1>Constraints</h1><h2>${constraints.length} Foreign Key Constraints</h2><label class="search-label">Search:<input type="search" oninput="filterConstraintRows(this.value)" placeholder="Filter constraints"></label>${constraints.length ? `<table><thead><tr><th>Constraint Name</th><th>Child Column</th><th>Parent Column</th><th>Delete Rule</th></tr></thead><tbody>${constraints.map(constraintRow).join('')}</tbody></table>` : '<p class="muted">None.</p>'}`;
}
function constraintRow(constraint){
  const searchable = [constraint.name, constraint.child_table, constraint.child_column, constraint.parent_table, constraint.parent_column, constraint.on_delete].join(' ').toLowerCase();

  return `<tr class="constraint-row" data-search="${esc(searchable)}"><td class="mono">${esc(constraint.name)}</td><td>${dbColumnReferenceLink(constraint.child_table,constraint.child_column)}</td><td>${dbReferenceLink(constraint.parent_table,constraint.parent_column)}</td><td>${esc(deleteRule(constraint.on_delete))}</td></tr>`;
}
function filterConstraintRows(value){
  const query = String(value || '').trim().toLowerCase();
  document.querySelectorAll('.constraint-row').forEach(row => {
    row.hidden = query !== '' && !row.dataset.search.includes(query);
  });
}
function deleteRule(rule){
  const value = String(rule || '').trim();
  if (value === '') return 'Not specified';

  return `${value.charAt(0).toUpperCase()}${value.slice(1)} delete`;
}
function dbRelationDetail(relation){
  return `<h1>${esc(relation.from_table)}.${esc(relation.from_column)}</h1><p>References ${dbReferenceLink(relation.to_table, relation.to_column)}</p><div class="meta-grid"><strong>From table</strong><span>${dbTableReferenceLink(relation.from_table)}</span><strong>From column</strong><span class="mono">${esc(relation.from_column)}</span><strong>To table</strong><span>${dbTableReferenceLink(relation.to_table)}</span><strong>To column</strong><span class="mono">${esc(relation.to_column)}</span></div>`;
}
function dbModelLink(tableInfo){
  if (typeIndex(tableInfo.model_full_name || tableInfo.model) === null) return `<span class="mono">${esc(tableInfo.model)}</span>`;

  return `<a href="#" class="mono" onclick='activateCodeReference(${JSON.stringify(tableInfo.model_full_name || tableInfo.model)});return false;'>${esc(tableInfo.model)}</a>`;
}
function dbReferenceLink(tableName, columnName){
  const label = `${tableName}.${columnName}`;
  const index = (docs.database.tables || []).findIndex(table => table.name === tableName);

  if (index === -1) return `<span class="mono">${esc(label)}</span>`;

  return `<a href="#${memberId('column', columnName)}" class="mono" onclick='activateDatabaseReference(${JSON.stringify(tableName)},${JSON.stringify(columnName)});return false;'>${esc(label)}</a>`;
}
function dbColumnReferenceLink(tableName, columnName){
  return `<a href="#${memberId('column', columnName)}" class="mono" onclick='activateDatabaseReference(${JSON.stringify(tableName)},${JSON.stringify(columnName)});return false;'>${esc(tableName)}.${esc(columnName)}</a>`;
}
function dbTableReferenceLink(tableName){
  const index = entriesFor('database').findIndex(entry => entry.item.name === tableName);
  if (index === -1) return `<span class="mono">${esc(tableName)}</span>`;

  return `<a href="#" class="mono" onclick="activate(${index});return false;">${esc(tableName)}</a>`;
}
function drawDatabaseDiagram(canvasId = 'database-relations-canvas'){
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  prepareDiagram();
  routeDiagramConnections();
  const margin = 28;
  const boxes = diagramScene.boxes;
  const ratio = window.devicePixelRatio || 1;

  const minX = Math.min(0, ...Array.from(boxes.values()).map(box => box.x - margin));
  const minY = Math.min(0, ...Array.from(boxes.values()).map(box => box.y - margin));
  const maxX = Math.max(900, ...Array.from(boxes.values()).map(box => box.x + box.width + margin)) - minX;
  const maxY = Math.max(360, ...Array.from(boxes.values()).map(box => box.y + box.height + margin)) - minY;
  if (canvasId !== 'database-relations-canvas') {
    const printScale = Math.min(1, 8000 / maxX, 8000 / maxY, Math.sqrt(24000000 / (maxX * maxY)));
    canvas.style.width = `${maxX * printScale}px`;
    canvas.style.height = `${maxY * printScale}px`;
    canvas.width = Math.ceil(maxX * printScale);
    canvas.height = Math.ceil(maxY * printScale);

    const ctx = canvas.getContext('2d');
    ctx.setTransform(printScale, 0, 0, printScale, -minX * printScale, -minY * printScale);
    ctx.font = '14px ui-monospace, SFMono-Regular, Menlo, monospace';
    diagramScene.routes.forEach(route => drawRelationLine(ctx, route));
    boxes.forEach(box => drawTableBox(ctx, box));
    diagramScene.routes.forEach(route => drawRelationPorts(ctx, route));
    return;
  }

  const shell = canvas.closest('.diagram-shell');
  const rect = shell?.getBoundingClientRect();
  const width = Math.max(320, Math.floor(rect?.width || 900));
  const height = Math.max(320, Math.floor(rect?.height || 520));
  if (!diagramView.initialized) resetDiagramView(false);
  const scale = Math.min(3, Math.max(0.05, diagramView.scale || 1));
  diagramView.scale = scale;

  canvas.style.width = '100%';
  canvas.style.height = '100%';
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  makeDiagramInteractive(shell);

  const ctx = canvas.getContext('2d');
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.clearRect(0, 0, width, height);
  ctx.setTransform(ratio * scale, 0, 0, ratio * scale, diagramView.offsetX * ratio, diagramView.offsetY * ratio);
  ctx.font = '14px ui-monospace, SFMono-Regular, Menlo, monospace';
  ctx.lineWidth = 1.5;

  diagramScene.routes.forEach(route => drawRelationLine(ctx, route));
  boxes.forEach(box => drawTableBox(ctx, box));
  diagramScene.routes.forEach(route => drawRelationPorts(ctx, route));
}
function resetDiagramView(redraw = true){
  if (redraw) {
    try { localStorage.removeItem(diagramStorageKey()); } catch {}
    diagramScene.boxes = null;
    diagramScene.dirty = true;
    prepareDiagram(false);
  }
  diagramView.scale = 1;
  diagramView.offsetX = 44 - diagramScene.origin.x;
  diagramView.offsetY = 44 - diagramScene.origin.y;
  diagramView.initialized = true;
  try { localStorage.setItem('laravel-docs-database-diagram-scale', String(diagramView.scale)); } catch {}
  if (redraw) drawDatabaseDiagram();
}
function setDiagramZoom(delta, anchor = null){
  const previousScale = Math.min(3, Math.max(0.05, diagramView.scale || 1));
  const nextScale = Math.min(3, Math.max(0.05, previousScale + delta));
  const point = anchor || diagramViewportCenter();
  const worldX = (point.x - diagramView.offsetX) / previousScale;
  const worldY = (point.y - diagramView.offsetY) / previousScale;

  diagramView.scale = nextScale;
  diagramView.offsetX = point.x - worldX * nextScale;
  diagramView.offsetY = point.y - worldY * nextScale;
  diagramView.initialized = true;
  localStorage.setItem('laravel-docs-database-diagram-scale', String(diagramView.scale));
  drawDatabaseDiagram();
}
function diagramViewportCenter(){
  const shell = document.getElementById('database-relations-canvas')?.closest('.diagram-shell');
  const rect = shell?.getBoundingClientRect();

  return {x: (rect?.width || 900) / 2, y: (rect?.height || 520) / 2};
}
function downloadPdf(){
  const root = printRoot();
  const originalTitle = document.title;

  root.innerHTML = printContent();
  document.title = `Laravel Docs - ${labels[state.tab]} Documentation`;
  document.body.classList.add('printing');
  if (state.tab === 'database') requestAnimationFrame(() => drawDatabaseDiagram('print-database-relations-canvas'));
  window.addEventListener('afterprint', () => {
    document.body.classList.remove('printing');
    document.title = originalTitle;
    root.innerHTML = '';
  }, {once:true});
  setTimeout(() => window.print(), state.tab === 'database' ? 100 : 0);
}
function printRoot(){
  let root = document.getElementById('print-root');
  if (!root) {
    root = document.createElement('section');
    root.id = 'print-root';
    document.body.appendChild(root);
  }

  return root;
}
function printContent(){
  if (state.tab === 'code') return `<div class="print-document"><h1>Code Documentation</h1>${list(docs.code.classes).map(codeDetail).join('')}</div>`;
  if (state.tab === 'database') {
    return `<div class="print-document"><h1>Database Documentation</h1><section>${dbRelationsDiagram().replace('database-relations-canvas', 'print-database-relations-canvas')}</section><section>${dbConstraintsPage()}</section>${list(docs.database.tables).map(tableInfo => `<section>${dbDetail(tableInfo)}</section>`).join('')}</div>`;
  }

  return `<div class="print-document"><h1>API Documentation</h1>${list(docs.api.groups).map(group => `<section><h2>${esc(group.name || 'Endpoints')}</h2>${list(group.endpoints).map(apiDetail).join('')}</section>`).join('')}</div>`;
}
const diagramScene = {boxes: null, routes: [], dirty: true, frame: null, origin: {x: 0, y: 0}};
function diagramRelationships(){
  return [...new Map(list(docs.database.relationships).map(edge => [
    JSON.stringify([edge.from_table, edge.from_column, edge.to_table, edge.to_column]), edge,
  ])).values()];
}
function layoutDiagramTables(tables, relationships){
  tables = [...new Map(tables.map(table => [table.name, table])).values()];
  const connected = new Set(relationships.flatMap(edge => [edge.from_table, edge.to_table]));
  const visible = relationships.length ? tables.filter(table => connected.has(table.name)) : tables;
  const groups = new Map(), membership = new Map();
  visible.forEach(table => {
    const prefix = table.name.split('_')[0].replace(/s$/, '');
    membership.set(table.name, prefix);
    if (!groups.has(prefix)) groups.set(prefix, []);
    groups.get(prefix).push(table);
  });
  // Attach small lookup groups to their strongest related domain, leaving shared hubs separate.
  for (const [name, members] of groups) {
    if (members.length > 2) continue;
    const affinity = new Map();
    let degree = 0;
    relationships.forEach(edge => {
      const from = membership.get(edge.from_table), to = membership.get(edge.to_table);
      const other = from === name ? to : to === name ? from : null;
      if (!other || other === name) return;
      degree++;
      affinity.set(other, (affinity.get(other) || 0) + 1);
    });
    if (degree > 10) continue;
    const target = [...affinity].filter(([key]) => groups.get(key)?.length > 2).sort((a, b) => b[1] - a[1])[0]?.[0];
    if (!target) continue;
    groups.get(target).push(...members);
    members.forEach(table => membership.set(table.name, target));
    groups.delete(name);
  }
  const clusters = [...groups].sort((a, b) => b[1].length - a[1].length || a[0].localeCompare(b[0])).map(([name, members]) => {
    const names = new Set(members.map(table => table.name));
    const boxes = layoutDiagramCluster(members, relationships.filter(edge => names.has(edge.from_table) && names.has(edge.to_table)));
    return {name, boxes, columns: [], diagramWidth: Math.max(0, ...[...boxes.values()].map(box => box.x + box.width)) + 260,
      diagramHeight: Math.max(0, ...[...boxes.values()].map(box => box.y + box.height)) + 260};
  });
  const clusterEdges = relationships.map(edge => ({from_table: membership.get(edge.from_table), to_table: membership.get(edge.to_table)}));
  const positions = layoutDiagramCluster(clusters, clusterEdges);
  const result = new Map();
  clusters.forEach(cluster => {
    const position = positions.get(cluster.name);
    cluster.boxes.forEach((box, name) => {
      box.x += position.x; box.y += position.y;
      result.set(name, box);
    });
  });
  return result;
}
function layoutDiagramCluster(tables, relationships){
  const boxes = new Map();
  const neighbors = new Map(tables.map(table => [table.name, new Set()]));
  relationships.forEach(edge => {
    if (edge.from_table === edge.to_table) return;
    neighbors.get(edge.from_table)?.add(edge.to_table);
    neighbors.get(edge.to_table)?.add(edge.from_table);
  });
  const ordered = [...tables].sort((a, b) => neighbors.get(b.name).size - neighbors.get(a.name).size || a.name.localeCompare(b.name));
  ordered.forEach((table, index) => {
    const relatedColumns = new Set((table.diagramWidth ? relationships : diagramRelationships()).flatMap(edge => [
      ...(edge.from_table === table.name && edge.from_column ? [edge.from_column] : []),
      ...(edge.to_table === table.name && edge.to_column ? [edge.to_column] : []),
    ]));
    const shownColumns = list(table.columns).filter(column => column.primary || relatedColumns.has(column.name));
    relatedColumns.forEach(name => {
      if (!shownColumns.some(column => column.name === name)) shownColumns.push({name});
    });
    if (!shownColumns.length) shownColumns.push(...list(table.columns).slice(0, 3));
    const references = new Map();
    diagramRelationships().filter(edge => edge.from_table === table.name).forEach(edge => {
      if (!references.has(edge.from_column)) references.set(edge.from_column, []);
      references.get(edge.from_column).push(edge.to_table + '.' + edge.to_column);
    });
    const width = table.diagramWidth || Math.max(280, table.name.length * 8.5 + 32,
      ...shownColumns.map(column => Math.max(column.name.length * 8 + 76, (references.get(column.name) || []).join(', ').length * 6.7 + 64)));
    const radius = 450 * Math.sqrt(index);
    const angle = index * 2.3999632297;
    boxes.set(table.name, {table, shownColumns, references, width, height: table.diagramHeight || 42 + shownColumns.length * 48 + 26,
      x: Math.cos(angle) * radius, y: Math.sin(angle) * radius, fx: 0, fy: 0});
  });
  const nodes = [...boxes.values()];
  // Springs keep neighbors close; rectangle repulsion leaves room for both cards and routes.
  for (let step = 0; step < 500; step++) {
    nodes.forEach(box => { box.fx = 0; box.fy = 0; });
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const a = nodes[i], b = nodes[j];
        const dx = b.x - a.x || 0.01, dy = b.y - a.y || 0.01;
        const distance = Math.hypot(dx, dy);
        const clearanceX = (a.width + b.width) / 2 + 240;
        const clearanceY = (a.height + b.height) / 2 + 240;
        const force = Math.min(100, 150000 / (distance * distance));
        a.fx -= dx / distance * force; a.fy -= dy / distance * force;
        b.fx += dx / distance * force; b.fy += dy / distance * force;
        if (Math.abs(dx) < clearanceX && Math.abs(dy) < clearanceY) {
          const pushX = clearanceX - Math.abs(dx), pushY = clearanceY - Math.abs(dy);
          if (pushX < pushY) {
            const push = Math.sign(dx) * pushX * 0.3;
            a.fx -= push; b.fx += push;
          } else {
            const push = Math.sign(dy) * pushY * 0.3;
            a.fy -= push; b.fy += push;
          }
        }
      }
    }
    neighbors.forEach((names, name) => names.forEach(other => {
      if (name >= other || !boxes.has(other)) return;
      const a = boxes.get(name), b = boxes.get(other);
      const dx = b.x - a.x, dy = b.y - a.y, distance = Math.hypot(dx, dy) || 1;
      const degree = Math.max(neighbors.get(name).size, neighbors.get(other).size);
      const preferred = Math.max((a.width + b.width) / 2, (a.height + b.height) / 2) + 340 + Math.sqrt(degree) * 65;
      const force = (distance - preferred) * 0.025;
      a.fx += dx / distance * force; a.fy += dy / distance * force;
      b.fx -= dx / distance * force; b.fy -= dy / distance * force;
    }));
    const cooling = 1 - step / 560;
    nodes.forEach(box => {
      box.x += Math.max(-35, Math.min(35, box.fx)) * cooling;
      box.y += Math.max(-35, Math.min(35, box.fy)) * cooling;
    });
  }
  nodes.forEach(box => { box.x -= box.width / 2; box.y -= box.height / 2; });
  // Resolve residual collisions without snapping the graph to rows or columns.
  for (let pass = 0; pass < 200; pass++) {
    let collisions = 0;
    for (let i = 0; i < nodes.length; i++) for (let j = i + 1; j < nodes.length; j++) {
      const a = nodes[i], b = nodes[j];
      const dx = b.x + b.width / 2 - a.x - a.width / 2;
      const dy = b.y + b.height / 2 - a.y - a.height / 2;
      const overlapX = (a.width + b.width) / 2 + 220 - Math.abs(dx);
      const overlapY = (a.height + b.height) / 2 + 220 - Math.abs(dy);
      if (overlapX <= 0 || overlapY <= 0) continue;
      collisions++;
      if (overlapX < overlapY) {
        const shift = (overlapX + 1) / 2 * (dx < 0 ? -1 : 1);
        a.x -= shift; b.x += shift;
      } else {
        const shift = (overlapY + 1) / 2 * (dy < 0 ? -1 : 1);
        a.y -= shift; b.y += shift;
      }
    }
    if (!collisions) break;
  }
  const minX = Math.min(0, ...nodes.map(box => box.x));
  const minY = Math.min(0, ...nodes.map(box => box.y));
  nodes.forEach(box => { box.x -= minX; box.y -= minY; });
  return boxes;
}
function diagramStorageKey(){
  const schema = JSON.stringify([location.pathname, docs.database.tables, docs.database.relationships]);
  let hash = 0;
  for (let i = 0; i < schema.length; i++) hash = (hash * 31 + schema.charCodeAt(i)) | 0;
  return 'laravel-docs-table-positions-' + hash;
}
function prepareDiagram(restorePositions = true){
  if (diagramScene.boxes) return;
  const tables = list(docs.database.tables);
  diagramScene.boxes = layoutDiagramTables(tables, diagramRelationships());
  const first = diagramScene.boxes.values().next().value;
  diagramScene.origin = first ? {x: first.x, y: first.y} : {x: 0, y: 0};
  if (!restorePositions) return;
  try {
    const positions = JSON.parse(localStorage.getItem(diagramStorageKey()) || '{}');
    diagramScene.boxes.forEach((box, name) => {
      const point = positions[name];
      if (point && Number.isFinite(point.x) && Number.isFinite(point.y)) Object.assign(box, {x: point.x, y: point.y});
    });
  } catch {}
}
function diagramColumnAnchorY(box, columnName){
  const index = box.shownColumns.findIndex(column => column.name === columnName);
  return box.y + 58 + Math.max(0, index) * 48;
}
function diagramColumnPort(box, columnName, side){
  return {x: box.x + (side === 'right' ? box.width : 0), y: diagramColumnAnchorY(box, columnName)};
}
function diagramSegmentBlocked(a, b, obstacles){
  const dx = b.x - a.x, dy = b.y - a.y;
  const minX = Math.min(a.x, b.x), maxX = Math.max(a.x, b.x);
  const minY = Math.min(a.y, b.y), maxY = Math.max(a.y, b.y);
  for (const box of obstacles) {
    const left = box.x + 0.1, right = box.x + box.width - 0.1;
    const top = box.y + 0.1, bottom = box.y + box.height - 0.1;
    if (maxX <= left || minX >= right || maxY <= top || minY >= bottom) continue;
    let low = 0, high = 1;
    if (Math.abs(dx) > 0.000001) {
      const t1 = (left - a.x) / dx, t2 = (right - a.x) / dx;
      low = Math.max(low, Math.min(t1, t2)); high = Math.min(high, Math.max(t1, t2));
    }
    if (Math.abs(dy) > 0.000001) {
      const t1 = (top - a.y) / dy, t2 = (bottom - a.y) / dy;
      low = Math.max(low, Math.min(t1, t2)); high = Math.min(high, Math.max(t1, t2));
    }
    if (low < high) return true;
  }
  return false;
}
function diagramHeapPush(heap, entry){
  let index = heap.length;
  heap.push(entry);
  while (index > 0) {
    const parent = (index - 1) >> 1;
    if (heap[parent].score <= entry.score) break;
    heap[index] = heap[parent]; index = parent;
  }
  heap[index] = entry;
}
function diagramHeapPop(heap){
  const first = heap[0], last = heap.pop();
  if (!heap.length) return first;
  let index = 0;
  while (index * 2 + 1 < heap.length) {
    let child = index * 2 + 1;
    if (child + 1 < heap.length && heap[child + 1].score < heap[child].score) child++;
    if (heap[child].score >= last.score) break;
    heap[index] = heap[child]; index = child;
  }
  heap[index] = last;
  return first;
}
function routeDiagramConnections(){
  if (!diagramScene.dirty) return;
  const boxes = diagramScene.boxes;
  const obstacles = [...boxes.values()].map(box => ({x: box.x - 22, y: box.y - 22, width: box.width + 44, height: box.height + 44}));
  const corners = obstacles.flatMap(box => [
    {x: box.x, y: box.y}, {x: box.x + box.width, y: box.y},
    {x: box.x + box.width, y: box.y + box.height}, {x: box.x, y: box.y + box.height},
  ]).filter(point => !obstacles.some(box => point.x > box.x + 0.1 && point.x < box.x + box.width - 0.1 && point.y > box.y + 0.1 && point.y < box.y + box.height - 0.1));
  const adjacency = corners.map(() => []);
  for (let i = 0; i < corners.length; i++) for (let j = i + 1; j < corners.length; j++) {
    if (diagramSegmentBlocked(corners[i], corners[j], obstacles)) continue;
    const length = Math.hypot(corners[i].x - corners[j].x, corners[i].y - corners[j].y);
    adjacency[i].push([j, length]); adjacency[j].push([i, length]);
  }
  const usage = new Map();
  diagramScene.routes = diagramRelationships().map((relation, index) => {
    const from = boxes.get(relation.from_table), to = boxes.get(relation.to_table);
    if (!from || !to) return null;
    const right = from.x + from.width / 2 < to.x + to.width / 2;
    const start = diagramColumnPort(from, relation.from_column, right ? 'right' : 'left');
    const end = diagramColumnPort(to, relation.to_column, right ? 'left' : 'right');
    const source = {x: start.x + (right ? 22 : -22), y: start.y};
    const target = {x: end.x + (right ? -22 : 22), y: end.y};
    const points = [...corners, source, target];
    const graph = adjacency.map(edges => [...edges]);
    graph.push([], []);
    const sourceId = corners.length, targetId = sourceId + 1;
    for (const id of [sourceId, targetId]) {
      for (let other = 0; other < id; other++) {
        if (diagramSegmentBlocked(points[id], points[other], obstacles)) continue;
        const distance = Math.hypot(points[id].x - points[other].x, points[id].y - points[other].y);
        graph[id].push([other, distance]); graph[other].push([id, distance]);
      }
    }
    const cost = points.map(() => Infinity), previous = points.map(() => -1), visited = new Set();
    cost[sourceId] = 0;
    const heap = [{id: sourceId, score: 0}];
    while (heap.length) {
      const current = diagramHeapPop(heap).id;
      if (current === targetId) break;
      if (visited.has(current)) continue;
      visited.add(current);
      graph[current].forEach(([next, distance]) => {
        const key = Math.min(current, next) + ':' + Math.max(current, next);
        const congestion = current < corners.length && next < corners.length ? usage.get(key) || 0 : 0;
        const candidate = cost[current] + distance + 45 + congestion * 320;
        if (candidate < cost[next]) {
          cost[next] = candidate; previous[next] = current;
          diagramHeapPush(heap, {id: next, score: candidate + Math.hypot(points[next].x - target.x, points[next].y - target.y)});
        }
      });
    }
    const path = [];
    if (Number.isFinite(cost[targetId])) {
      let current = targetId;
      while (current !== -1) {
        path.unshift(points[current]);
        const parent = previous[current];
        if (parent !== -1) {
          const key = Math.min(parent, current) + ':' + Math.max(parent, current);
          usage.set(key, (usage.get(key) || 0) + 1);
        }
        current = parent;
      }
    } else {
      // A manually overlapped card can trap a port; keep the connection visible.
      path.push(source, target);
    }
    return {relation, index, points: [start, ...path, end], color: ['#2878b5','#a04885','#2c8774','#b26a24','#6858aa','#bc505a','#557a25'][index % 7]};
  }).filter(Boolean);
  diagramScene.dirty = false;
}
function drawRelationLine(ctx, route){
  const points = route.points;
  ctx.beginPath();
  ctx.moveTo(points[0].x, points[0].y);
  points.slice(1).forEach(point => ctx.lineTo(point.x, point.y));
  ctx.lineJoin = 'round';
  ctx.strokeStyle = '#fff';
  ctx.lineWidth = 5;
  ctx.stroke();
  ctx.strokeStyle = route.color;
  ctx.lineWidth = 1.8;
  ctx.stroke();
}
function drawRelationPorts(ctx, route){
  const start = route.points[0], end = route.points.at(-1), previous = route.points.at(-2);
  ctx.fillStyle = route.color;
  ctx.beginPath();
  ctx.arc(start.x, start.y, 4, 0, Math.PI * 2);
  ctx.fill();
  const angle = Math.atan2(end.y - previous.y, end.x - previous.x);
  ctx.beginPath();
  ctx.moveTo(end.x, end.y);
  ctx.lineTo(end.x - Math.cos(angle - 0.45) * 11, end.y - Math.sin(angle - 0.45) * 11);
  ctx.lineTo(end.x - Math.cos(angle + 0.45) * 11, end.y - Math.sin(angle + 0.45) * 11);
  ctx.closePath();
  ctx.fill();
}
function drawTableBox(ctx, box){
  ctx.fillStyle = '#fff';
  ctx.strokeStyle = '#667085';
  ctx.lineWidth = 1.2;
  ctx.fillRect(box.x, box.y, box.width, box.height);
  ctx.strokeRect(box.x, box.y, box.width, box.height);
  ctx.fillStyle = '#edf2f5';
  ctx.fillRect(box.x, box.y, box.width, 42);
  ctx.fillStyle = '#202c39';
  ctx.font = '700 14px ui-monospace, SFMono-Regular, Menlo, monospace';
  ctx.fillText(box.table.name, box.x + 14, box.y + 26);
  const foreign = new Set(list(docs.database.relationships).filter(edge => edge.from_table === box.table.name).map(edge => edge.from_column));
  box.shownColumns.forEach((column, index) => {
    const y = box.y + 42 + index * 48;
    ctx.strokeStyle = '#e4e7ec';
    ctx.beginPath(); ctx.moveTo(box.x, y); ctx.lineTo(box.x + box.width, y); ctx.stroke();
    ctx.font = '11px ui-monospace, SFMono-Regular, Menlo, monospace';
    ctx.fillStyle = column.primary ? '#9a6d13' : '#48759c';
    ctx.fillText(column.primary ? 'PK' : foreign.has(column.name) ? 'FK' : '', box.x + 14, y + 20);
    ctx.font = '13px ui-monospace, SFMono-Regular, Menlo, monospace';
    ctx.fillStyle = '#344054';
    ctx.fillText(column.name, box.x + 46, y + 20);
    ctx.font = '11px ui-monospace, SFMono-Regular, Menlo, monospace';
    ctx.fillStyle = '#667085';
    ctx.fillText((box.references.get(column.name) || []).join(', ') || column.type || '', box.x + 46, y + 37);
  });
  ctx.fillStyle = '#667085';
  ctx.font = '11px ui-monospace, SFMono-Regular, Menlo, monospace';
  const extra = Math.max(0, list(box.table.columns).length - box.shownColumns.length);
  ctx.fillText(extra ? '+' + extra + ' columns' : box.shownColumns.length + ' columns', box.x + 14, box.y + box.height - 9);
}
function requestDiagramDraw(){
  if (diagramScene.frame !== null) return;
  diagramScene.frame = requestAnimationFrame(() => {
    diagramScene.frame = null;
    drawDatabaseDiagram();
  });
}
function diagramWorldPoint(shell, event){
  const rect = shell.getBoundingClientRect();
  return {x: (event.clientX - rect.left - diagramView.offsetX) / diagramView.scale,
    y: (event.clientY - rect.top - diagramView.offsetY) / diagramView.scale};
}
function makeDiagramInteractive(shell){
  if (!shell || shell.dataset.interactive === 'true') return;
  shell.dataset.interactive = 'true';
  let dragging = false;
  let gesture = null;
  const stop = event => {
    if (gesture && event.pointerId !== gesture.pointerId) return;
    if (gesture?.box) {
      try {
        localStorage.setItem(diagramStorageKey(), JSON.stringify(Object.fromEntries(
          [...diagramScene.boxes].map(([name, box]) => [name, {x: box.x, y: box.y}])
        )));
      } catch {}
    }
    dragging = false;
    gesture = null;
    shell.classList.remove('dragging');
    if (shell.hasPointerCapture(event.pointerId)) shell.releasePointerCapture(event.pointerId);
  };
  shell.addEventListener('wheel', event => {
    event.preventDefault();
    const rect = shell.getBoundingClientRect();
    const units = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? rect.height : 1;
    setDiagramZoom(diagramView.scale * (Math.exp(-event.deltaY * units * 0.0015) - 1),
      {x: event.clientX - rect.left, y: event.clientY - rect.top});
  }, {passive:false});
  shell.addEventListener('pointerdown', event => {
    if (event.button !== 0 && event.button !== 1) return;
    if (gesture) return;
    event.preventDefault();
    const point = diagramWorldPoint(shell, event);
    const box = event.button === 0 ? [...diagramScene.boxes.values()].reverse().find(box =>
      point.x >= box.x && point.x <= box.x + box.width && point.y >= box.y && point.y <= box.y + box.height) : null;
    gesture = {pointerId: event.pointerId, box, point, x: event.clientX, y: event.clientY,
      originX: box ? box.x : diagramView.offsetX, originY: box ? box.y : diagramView.offsetY};
    dragging = true;
    shell.classList.add('dragging');
    shell.setPointerCapture(event.pointerId);
  });
  shell.addEventListener('pointermove', event => {
    if (!dragging || event.pointerId !== gesture.pointerId) return;
    if (gesture.box) {
      const point = diagramWorldPoint(shell, event);
      gesture.box.x = gesture.originX + point.x - gesture.point.x;
      gesture.box.y = gesture.originY + point.y - gesture.point.y;
      diagramScene.dirty = true;
    } else {
      diagramView.offsetX = gesture.originX + event.clientX - gesture.x;
      diagramView.offsetY = gesture.originY + event.clientY - gesture.y;
    }
    requestDiagramDraw();
  });
  shell.addEventListener('pointerup', stop);
  shell.addEventListener('pointercancel', stop);
  shell.addEventListener('lostpointercapture', stop);
  diagramScene.observer?.disconnect();
  diagramScene.observer = new ResizeObserver(requestDiagramDraw);
  diagramScene.observer.observe(shell);
}
function codeDetail(type){
  return `<div class="code-page"><article>${codeHeader(type)}${codeToc(type)}${memberSummary('Interfaces', type.interfaces || [], 'interface')}${memberSummary('Cases', type.cases || [], 'case')}${enumDetails(type)}${memberSummary('Properties', type.properties || [], 'property')}${memberSummary('Methods', type.methods || [], 'method')}${propertyDetails(type)}${methodDetails(type)}</article>${onThisPage(type)}</div>`;
}
function codeHeader(type){
  const segments = (type.namespace || '').split('\\').filter(Boolean);
  const breadcrumb = segments.length ? `<nav class="breadcrumbs">${segments.map(part=>`<span>${esc(part)}</span>`).join('<span>\\</span>')}</nav>` : '';
  const implementsText = (type.interfaces || []).length ? `<p class="implements">implements ${list(type.interfaces).map(linkReference).join(', ')}</p>` : '';
  const fileText = type.file ? `<a href="#source">${esc(basename(type.file))}</a>${type.line ? ` : ${esc(type.line)}` : ''}` : '';

  return `${breadcrumb}<div class="code-heading"><div>${typeBadge(type.type)}<h1>${esc(type.name)}</h1>${implementsText}</div><div class="source-link">${fileText}</div></div><div class="flags">${flag(type.type, typeLabel(type.type))}${type.final ? flag('final','Final') : ''}${type.abstract ? flag('abstract','Abstract') : ''}</div>${type.summary ? `<p class="lead">${esc(type.summary)}</p>` : ''}${type.description ? `<p>${esc(type.description)}</p>` : ''}<div id="source" class="meta-grid"><strong>Namespace</strong><span class="mono">${esc(type.namespace || 'Global namespace')}</span><strong>Parent class</strong><span>${type.parent ? linkReference(type.parent) : 'None'}</span>${type.type === 'enum' ? `<strong>Backing type</strong><span>${type.backing_type ? typeReference(type.backing_type) : 'None'}</span>` : ''}<strong>File</strong><span>${type.file ? `${esc(type.file)}${type.line ? `:${esc(type.line)}` : ''}` : 'Not available'}</span></div>`;
}
function codeToc(type){
  return `<section class="toc-section"><h2>Table of Contents</h2><div class="toc-grid">${tocBlock('Interfaces', list(type.interfaces).map(item => ({label: shortReference(item), href:'#interfaces'})))}${tocBlock('Cases', list(type.cases).map(item => ({label:item.name, href:`#${memberId('case', item.name)}`, detail:item.value ? esc(item.value) : '', summary:item.summary})))}${tocBlock('Properties', list(type.properties).map(item => ({label:`$${item.name}`, href:`#${memberId('property', item.name)}`, detail:item.type ? typeReference(item.type) : ''})))}${tocBlock('Methods', list(type.methods).map(item => ({label:methodTocName(item), href:`#${memberId('method', item.name)}`, detail:item.return_type ? typeReference(item.return_type) : '', summary:item.summary})))}</div></section>`;
}
function tocBlock(title, entries){
  if (!entries.length) return '';
  return `<div><h3>${esc(title)}</h3>${entries.map(entry=>`<p><a href="${entry.href}" class="mono">${esc(entry.label)}</a>${entry.detail ? ` : <span class="mono">${entry.detail}</span>` : ''}</p>${entry.summary ? `<p class="muted italic">${esc(entry.summary)}</p>` : ''}`).join('')}</div>`;
}
function memberSummary(title, entries, kind){
  entries = list(entries);
  if (!entries.length) return '';
  if (kind === 'interface') {
    return `<section id="interfaces"><h2>Interfaces</h2><div class="summary-list">${entries.map(item=>`<div class="summary-row">${typeBadge('interface')}<div><a href="#interfaces">${esc(shortReference(item))}</a></div></div>`).join('')}</div></section>`;
  }

  if (kind === 'case') {
    return `<section id="cases"><h2>${esc(title)}</h2><div class="summary-list">${entries.map(item=>`<div class="summary-row no-badge"><div>${caseSummaryLink(item)}${item.summary ? `<p class="italic">${esc(item.summary)}</p>` : ''}</div></div>`).join('')}</div></section>`;
  }

  return `<section id="${kind === 'property' ? 'properties' : 'methods'}"><h2>${esc(title)}</h2><div class="summary-list">${entries.map(item=>`<div class="summary-row">${typeBadge(kind)}<div>${kind === 'method' ? methodSummaryLink(item) : propertySummaryLink(item)}${item.summary ? `<p class="italic">${esc(item.summary)}</p>` : ''}</div></div>`).join('')}</div></section>`;
}
function enumDetails(type){
  const cases = list(type.cases);
  if (type.type !== 'enum' || !cases.length) return '';

  const declaration = `enum ${type.name || 'Enum'}${type.backing_type ? `: ${type.backing_type}` : ''}`;
  const rows = [];
  cases.forEach(item => {
    if (item.summary) rows.push(`<span class="enum-comment">// ${esc(item.summary)}</span>`);
    rows.push(`<span id="${memberId('case', item.name)}" class="enum-case">case ${esc(item.name)}${item.value ? ` = ${esc(item.value)}` : ''};</span>`);
  });

  return `<section><h2>Enum Cases</h2><pre class="enum-source"><span>${esc(declaration)}</span><span>{</span>${rows.map(row=>`<span>    ${row}</span>`).join('')}<span>}</span></pre></section>`;
}
function propertyDetails(type){
  const properties = list(type.properties);
  if (!properties.length) return '';

  return `<section><h2>Properties</h2>${properties.map(property=>`<article class="member-card" id="${memberId('property', property.name)}"><div class="member-title"><h3>$${esc(property.name)}</h3><div>${visibility(property.visibility)}${property.static ? flag('static','Static') : ''}${property.read_only ? flag('read-only','Read-only') : ''}</div></div>${memberSource(type, property)}${property.summary ? `<p>${esc(property.summary)}</p>` : ''}${property.description ? `<p>${esc(property.description)}</p>` : ''}<pre>${propertySignature(property)}</pre>${property.inherited_from ? `<p class="muted">Inherited from ${linkReference(property.inherited_from)}</p>` : ''}</article>`).join('')}</section>`;
}
function methodDetails(type){
  const methods = list(type.methods);
  if (!methods.length) return '';

  return `<section><h2>Methods</h2>${methods.map(method=>`<article class="member-card" id="${memberId('method', method.name)}"><div class="member-title"><h3>${esc(method.name)}()</h3><div>${visibility(method.visibility)}${method.static ? flag('static','Static') : ''}${method.final ? flag('final','Final') : ''}${method.abstract ? flag('abstract','Abstract') : ''}</div></div>${memberSource(type, method)}<pre>${methodSignature(method)}</pre>${method.summary ? `<p>${esc(method.summary)}</p>` : ''}${method.description ? `<p>${esc(method.description)}</p>` : ''}${parametersTable(method)}${returnBlock(method)}${method.inherited_from ? `<p class="muted">Inherited from ${linkReference(method.inherited_from)}</p>` : ''}</article>`).join('')}</section>`;
}
function parametersTable(method){
  const parameters = list(method.parameters);
  if (!parameters.length) return '';

  return `<h4>Parameters</h4><table><thead><tr><th>Name</th><th>Type</th><th>Default</th><th>Description</th></tr></thead><tbody>${parameters.map(parameter=>`<tr><td>$${esc(parameter.name)}</td><td>${typeReference(parameter.type)}</td><td>${esc(parameter.default)}</td><td>${esc(parameter.description)}</td></tr>`).join('')}</tbody></table>`;
}
function returnBlock(method){
  if (!method.return_type && !method.return_description) return '';

  return `<h4>Return values</h4><p>${method.return_type ? `<span class="mono">${typeReference(method.return_type)}</span>` : ''}${method.return_description ? ` ${esc(method.return_description)}` : ''}</p>`;
}
function memberSource(type, member){
  if (!type.file && !member.line) return '';

  return `<p class="source-link"><a href="#source">${esc(basename(type.file) || 'Source')}</a>${member.line ? ` : ${esc(member.line)}` : ''}</p>`;
}
function onThisPage(type){
  const interfaces = list(type.interfaces);
  const cases = list(type.cases);
  const properties = list(type.properties);
  const methods = list(type.methods);

  return `<nav class="on-page"><h2>On this page</h2><p>Table Of Contents</p>${interfaces.length ? '<a href="#interfaces">Interfaces</a>' : ''}${cases.length ? '<a href="#cases">Cases</a>' : ''}${properties.length ? '<a href="#properties">Properties</a>' : ''}${methods.length ? '<a href="#methods">Methods</a>' : ''}${cases.length ? `<h3>Cases</h3>${cases.map(item=>`<a href="#${memberId('case', item.name)}">${esc(item.name)}</a>`).join('')}` : ''}${properties.length ? `<h3>Properties</h3>${properties.map(property=>`<a href="#${memberId('property', property.name)}">$${esc(property.name)}</a>`).join('')}` : ''}${methods.length ? `<h3>Methods</h3>${methods.map(method=>`<a href="#${memberId('method', method.name)}">${esc(method.name)}()</a>`).join('')}` : ''}</nav>`;
}
function visibility(value){return value ? flag(value, value.charAt(0).toUpperCase() + value.slice(1)) : '';}
function flag(type, label){return `<span class="flag flag-${esc(type)}">${esc(label)}</span>`;}
function linkReference(value){
  const index = typeIndex(value);
  const url = externalTypeUrl(value);

  if (index === null && url === null) return `<span class="mono">${esc(displayReference(value))}</span>`;
  if (url !== null) return `<a href="${esc(url)}" target="_blank" rel="noreferrer">${esc(shortReference(value))}</a>`;

  return `<a href="#" onclick="activate(${index});return false;">${esc(shortReference(value))}</a>`;
}
function typeIndex(value){
  const normalized = String(value || '').replace(/^\\+/, '');
  const index = (docs.code.classes || []).findIndex(type => [type.full_name, `${type.namespace}\\${type.name}`, type.name].map(item => String(item || '').replace(/^\\+/, '')).includes(normalized));

  return index === -1 ? null : index;
}
function shortReference(value){const parts = String(value || '').replace(/^\\+/, '').split('\\'); return parts.pop() || value || '';}
function displayReference(value){return String(value || '').includes('\\') ? String(value) : shortReference(value);}
function propertySignature(property){
  return `${esc([property.visibility, property.static ? 'static' : ''].filter(Boolean).join(' '))}${property.visibility || property.static ? ' ' : ''}${property.type ? `${typeReference(property.type)} ` : ''}$${esc(property.name)}${property.default ? ` = ${esc(property.default)}` : ''}`;
}
function propertySummaryLink(property){
  return `<a href="#${memberId('property', property.name)}" class="mono">$${esc(property.name)}</a>${property.type ? ` : <span class="mono">${typeReference(property.type)}</span>` : ''}`;
}
function methodTocName(method){
  const params = (method.parameters||[]).map(p => `$${esc(p.name || 'parameter')}`).join(', ');

  return `${method.name || 'method'}(${params})`;
}
function methodSummaryLink(method){
  return `<a href="#${memberId('method', method.name)}" class="mono">${esc(methodTocName(method))}</a>${method.return_type ? ` : <span class="mono">${typeReference(method.return_type)}</span>` : ''}`;
}
function caseSummaryLink(item){
  return `<a href="#${memberId('case', item.name)}" class="mono">${esc(item.name)}</a>${item.value ? ` = <span class="mono">${esc(item.value)}</span>` : ''}`;
}
function methodSignature(method){
  const params = (method.parameters||[]).map(p => `${p.type ? typeReference(p.type) + ' ' : ''}$${esc(p.name || 'parameter')}${p.default ? ' = ' + esc(p.default) : ''}`).join(', ');
  const prefix = [method.visibility, method.static ? 'static' : ''].filter(Boolean).join(' ');

  return `${prefix ? `${esc(prefix)} ` : ''}${esc(method.name || 'method')}(${params})${method.return_type ? ': ' + typeReference(method.return_type) : ''}`;
}
function typeReference(type){
  return String(type || '').split(/(\\?[A-Z_][\\A-Za-z0-9_]*)/g).map(part => {
    if (!part) return '';

    const normalized = part.replace(/^\\+/, '');
    const builtIns = ['array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'object', 'self', 'static', 'string', 'true', 'void'];

    if (builtIns.includes(normalized.toLowerCase())) return esc(part);
    if (!part.includes('\\') && typeIndex(part) === null) return esc(part);

    return linkReference(part);
  }).join('');
}
function externalTypeUrl(value){
  if (externalDocs.enabled === false) return null;

  const normalized = String(value || '').replace(/^\\+/, '');

  if (!normalized.startsWith('Illuminate\\')) return null;

  return `https://api.laravel.com/docs/${externalDocs.laravel_api_version || 'master'}/${normalized.replaceAll('\\', '/')}.html`;
}
render();
