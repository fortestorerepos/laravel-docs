const docs = JSON.parse(document.getElementById('docs-data').textContent);
const labels = {api:'API', database:'DB', code:'Code'};
const externalDocs = docs._meta?.external_docs || {};
const activeTab = document.body.dataset.activeTab || 'api';
let state = {
  codeGroupBy: localStorage.getItem('laravel-docs-code-group-by') || 'namespaces',
  selected: 0,
  tab: ['api','database','code'].includes(activeTab) ? activeTab : 'api',
};
if (!['namespaces','types'].includes(state.codeGroupBy)) state.codeGroupBy = 'namespaces';
const sidebar = document.getElementById('sidebar');
const content = document.getElementById('content');
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const block = value => `<pre>${esc(JSON.stringify(value ?? {}, null, 2))}</pre>`;
const cell = value => value && typeof value === 'object' && 'html' in value ? value.html : esc(value);
const table = (headers, rows) => rows.length ? `<table><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(value=>`<td>${cell(value)}</td>`).join('')}</tr>`).join('')}</tbody></table>` : '<p class="muted">None.</p>';
const list = value => Array.isArray(value) ? value : Object.values(value || {});
const memberId = (kind, name) => `${kind}_${String(name || '').replace(/[^a-zA-Z0-9_-]+/g, '_')}`;
const basename = path => String(path || '').split(/[\\/]/).pop();
function activate(index){state.selected=index;render();}
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
  if (state.tab === 'code' || state.tab === 'database') { renderGroupedSidebar(entries, state.tab); return; }
  sidebar.innerHTML = entries.map((entry,index)=>`<div class="group">${entry.group?`<h2>${esc(entry.group)}</h2>`:''}<button class="item ${index===state.selected?'active':''}" onclick="activate(${index})">${entry.sidebar}</button></div>`).join('');
}
function renderGroupedSidebar(entries, tab){
  const groups = sidebarGroups(entries);
  const switcher = tab === 'code' ? codeSidebarSwitcher() : '';
  sidebar.innerHTML = switcher + groups.map(group => `<details class="namespace-group" ${group.open ? 'open' : ''}><summary>${group.badge ? typeBadge(group.badge) : ''}<span class="mono">${esc(group.name)}</span></summary><div class="namespace-items">${group.entries.map(entry => `<button class="item ${entry.index===state.selected?'active':''}" onclick="activate(${entry.index})">${entry.sidebar}</button>`).join('')}</div></details>`).join('');
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
  entries.forEach((entry,index) => {
    const name = entry.group || 'Global';
    if (!groups.has(name)) groups.set(name, {name, badge: entry.groupType || null, entries: [], open: false});
    const group = groups.get(name);
    group.entries.push({...entry,index});
    if (index === state.selected) group.open = true;
  });
  return Array.from(groups.values());
}
function renderContent(){
  const entries = entriesFor(state.tab);
  if (!entries.length){content.innerHTML=`<div class="empty">Run docs:generate after configuring the ${labels[state.tab]} documentation source.</div>`;return;}
  const item = entries[Math.min(state.selected, entries.length - 1)].item;
  content.innerHTML = state.tab === 'api' ? apiDetail(item) : state.tab === 'database' ? dbDetail(item) : codeDetail(item);
}
function entriesFor(tab){
  if (tab === 'api') return (docs.api.groups||[]).flatMap(group => (group.endpoints||[]).map(endpoint => ({group:group.name,item:endpoint,sidebar:`<div class="line"><span class="method">${esc(endpoint.method)}</span><span class="uri">${esc(endpoint.uri)}</span></div><div class="muted">${esc(endpoint.name||'Untitled endpoint')}</div>`})));
  if (tab === 'database') return (docs.database.tables||[]).map(table => ({group:'Tables',item:table,sidebar:`<span class="mono">${esc(table.name)}</span>${table.model ? `<span class="muted">: ${esc(table.model)}</span>` : ''}<div class="muted">${(table.columns||[]).length} columns</div>`}));
  return (docs.code.classes||[]).map(type => {
    const group = state.codeGroupBy === 'types' ? typeGroup(type.type) : type.namespace || 'Global';
    const groupType = state.codeGroupBy === 'types' ? type.type : 'namespace';

    return {group,groupType,item:type,sidebar:`<div class="type-row">${typeBadge(type.type)}<span><span class="mono">${esc(type.name)}</span><div class="muted">${esc(typeLabel(type.type))}</div></span></div>`};
  });
}
function typeGroup(type){return ({class:'Classes',interface:'Interfaces',trait:'Traits',enum:'Enums'}[type]||'Types');}
function typeLabel(type){return ({class:'Class',interface:'Interface',trait:'Trait',enum:'Enum',namespace:'Namespace',method:'Method',property:'Property'}[type]||type||'Type');}
function typeIcon(type){return ({class:'C',interface:'I',trait:'T',enum:'E',namespace:'N',method:'M',property:'P'}[type]||'C');}
function typeBadge(type){const label=typeLabel(type);return `<span class="type-badge" title="${esc(label)}" aria-label="${esc(label)}">${typeIcon(type)}</span>`;}
function apiDetail(endpoint){
  return `<h1>${esc(endpoint.name || endpoint.uri)}</h1><p><span class="method">${esc(endpoint.method)}</span> <span class="uri">${esc(endpoint.uri)}</span></p><p>${esc(endpoint.description || '')}</p><p class="muted">Controller: ${esc(endpoint.controller || 'Not available')} &middot; Auth: ${endpoint.authenticated ? 'Required' : 'Not specified'}</p><h2>Request Parameters</h2>${block(endpoint.parameters)}<h2>Request Body</h2>${block(endpoint.body)}<h2>Responses</h2>${block(endpoint.responses)}`;
}
function dbDetail(tableInfo){
  return `<h1>${esc(tableInfo.name)}${tableInfo.model ? `: ${dbModelLink(tableInfo)}` : ''}</h1><h2>Columns</h2>${table(['Name','Type','Model Type','Nullable','Default','Primary','Description'],list(tableInfo.columns).map(c=>[{html:`<span id="${memberId('column', c.name)}" class="column-anchor mono">${esc(c.name)}</span>`},c.type,c.model_type,c.nullable?'yes':'no',c.default,c.primary?'yes':'no',c.description]))}<h2>Primary Keys</h2>${table(['Column'],list(tableInfo.primary_keys).map(k=>[k]))}<h2>Foreign Keys</h2>${table(['Column','References'],list(tableInfo.foreign_keys).map(k=>[k.column,{html:dbReferenceLink(k.references_table,k.references_column)}]))}<h2>Indexes</h2>${table(['Name','Unique','Columns'],list(tableInfo.indexes).map(i=>[i.name,i.unique?'yes':'no',list(i.columns).join(', ')]))}`;
}
function dbModelLink(tableInfo){
  if (typeIndex(tableInfo.model_full_name || tableInfo.model) === null) return `<span class="mono">${esc(tableInfo.model)}</span>`;

  return `<a href="#" class="mono" onclick='activateCodeReference(${JSON.stringify(tableInfo.model_full_name || tableInfo.model)});return false;'>${esc(tableInfo.model)}</a>`;
}
function dbReferenceLink(tableName, columnName){
  const label = `${tableName}.${columnName}`;
  const index = entriesFor('database').findIndex(entry => entry.item.name === tableName);

  if (index === -1) return `<span class="mono">${esc(label)}</span>`;

  return `<a href="#${memberId('column', columnName)}" class="mono" onclick='activateDatabaseReference(${JSON.stringify(tableName)},${JSON.stringify(columnName)});return false;'>${esc(label)}</a>`;
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
