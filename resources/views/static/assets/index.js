const docs = JSON.parse(document.getElementById('docs-data').textContent);
const labels = {api:'API', database:'DB', code:'Code'};
const activeTab = document.body.dataset.activeTab || 'api';
let state = {tab: ['api','database','code'].includes(activeTab) ? activeTab : 'api', selected: 0};
const sidebar = document.getElementById('sidebar');
const content = document.getElementById('content');
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const block = value => `<pre>${esc(JSON.stringify(value ?? {}, null, 2))}</pre>`;
const table = (headers, rows) => rows.length ? `<table><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(cell=>`<td>${esc(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>` : '<p class="muted">None.</p>';
const list = value => Array.isArray(value) ? value : Object.values(value || {});
function activate(index){state.selected=index;render();}
function render(){renderSidebar();renderContent();}
function renderSidebar(){
  const entries = entriesFor(state.tab);
  if (!entries.length){sidebar.innerHTML=`<div class="empty">No ${labels[state.tab]} documentation found.</div>`;return;}
  if (state.tab === 'code' || state.tab === 'database') { renderGroupedSidebar(entries, state.tab); return; }
  sidebar.innerHTML = entries.map((entry,index)=>`<div class="group">${entry.group?`<h2>${esc(entry.group)}</h2>`:''}<button class="item ${index===state.selected?'active':''}" onclick="activate(${index})">${entry.sidebar}</button></div>`).join('');
}
function renderGroupedSidebar(entries, tab){
  const groups = sidebarGroups(entries);
  sidebar.innerHTML = groups.map(group => `<details class="namespace-group" ${group.open ? 'open' : ''}><summary>${group.badge ? typeBadge(group.badge) : ''}<span class="mono">${esc(group.name)}</span></summary><div class="namespace-items">${group.entries.map(entry => `<button class="item ${entry.index===state.selected?'active':''}" onclick="activate(${entry.index})">${entry.sidebar}</button>`).join('')}</div></details>`).join('');
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
  if (tab === 'database') return (docs.database.tables||[]).map(table => ({group:'Tables',item:table,sidebar:`<span class="mono">${esc(table.name)}</span><div class="muted">${(table.columns||[]).length} columns</div>`}));
  return (docs.code.classes||[]).map(type => ({group:type.namespace||'Global',groupType:'namespace',item:type,sidebar:`<div class="type-row">${typeBadge(type.type)}<span><span class="mono">${esc(type.name)}</span><div class="muted">${esc(typeLabel(type.type))}</div></span></div>`}));
}
function typeLabel(type){return ({class:'Class',interface:'Interface',trait:'Trait',enum:'Enum',namespace:'Namespace'}[type]||type||'Type');}
function typeIcon(type){return ({class:'C',interface:'I',trait:'T',enum:'E',namespace:'N'}[type]||'C');}
function typeBadge(type){const label=typeLabel(type);return `<span class="type-badge" title="${esc(label)}" aria-label="${esc(label)}">${typeIcon(type)}</span>`;}
function apiDetail(endpoint){
  return `<h1>${esc(endpoint.name || endpoint.uri)}</h1><p><span class="method">${esc(endpoint.method)}</span> <span class="uri">${esc(endpoint.uri)}</span></p><p>${esc(endpoint.description || '')}</p><p class="muted">Controller: ${esc(endpoint.controller || 'Not available')} &middot; Auth: ${endpoint.authenticated ? 'Required' : 'Not specified'}</p><h2>Request Parameters</h2>${block(endpoint.parameters)}<h2>Request Body</h2>${block(endpoint.body)}<h2>Responses</h2>${block(endpoint.responses)}`;
}
function dbDetail(tableInfo){
  return `<h1>${esc(tableInfo.name)}</h1><h2>Columns</h2>${table(['Name','Type','Nullable','Default','Primary'],list(tableInfo.columns).map(c=>[c.name,c.type,c.nullable?'yes':'no',c.default,c.primary?'yes':'no']))}<h2>Primary Keys</h2>${table(['Column'],list(tableInfo.primary_keys).map(k=>[k]))}<h2>Foreign Keys</h2>${table(['Name','Column','References'],list(tableInfo.foreign_keys).map(k=>[k.name,k.column,`${k.references_table}.${k.references_column}`]))}<h2>Indexes</h2>${table(['Name','Unique','Columns'],list(tableInfo.indexes).map(i=>[i.name,i.unique?'yes':'no',list(i.columns).join(', ')]))}`;
}
function codeDetail(type){
  return `<div class="type-row">${typeBadge(type.type)}<div><h1>${esc(type.name)}</h1><p class="muted">${typeLabel(type.type)} in <span class="mono">${esc(type.namespace || 'Global namespace')}</span></p></div></div><p>${esc(type.summary || '')}</p><div class="meta-grid"><strong>Namespace</strong><span class="mono">${esc(type.namespace || 'Global namespace')}</span><strong>Parent class</strong><span>${esc(type.parent || 'None')}</span><strong>Interfaces</strong><span>${esc((type.interfaces||[]).join(', ') || 'None')}</span></div><h2>Methods</h2>${methodList(type.methods||[])}<h2>Properties</h2>${table(['Name','Type','Description'],(type.properties||[]).map(p=>[p.name,p.type,p.summary]))}`;
}
function methodList(methods){
  if (!methods.length) return '<p class="muted">No public methods found.</p>';
  return `<div class="method-list">${methods.map(method=>`<div class="method-block"><div class="signature mono">${esc(methodSignature(method))}</div><p>${esc(method.summary || '')}</p></div>`).join('')}</div>`;
}
function methodSignature(method){
  const params = (method.parameters||[]).map(p => `${p.type ? p.type + ' ' : ''}$${p.name || 'parameter'}${p.default ? ' = ' + p.default : ''}`).join(', ');
  return `${method.name || 'method'}(${params})${method.return_type ? ': ' + method.return_type : ''}`;
}
render();
