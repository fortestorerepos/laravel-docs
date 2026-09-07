<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Generators;

use Illuminate\Filesystem\Filesystem;

class StaticSiteGenerator
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  array{api: array<string, mixed>, database: array<string, mixed>, code: array<string, mixed>}  $data
     */
    public function generate(array $data, string $outputPath): void
    {
        $this->files->ensureDirectoryExists($outputPath);
        $this->files->ensureDirectoryExists($outputPath.'/assets');

        $this->files->put($outputPath.'/index.html', $this->html($data));
    }

    /**
     * @param  array{api: array<string, mixed>, database: array<string, mixed>, code: array<string, mixed>}  $data
     */
    private function html(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laravel Docs</title>
<style>
:root{color-scheme:light;--bg:#f7f8fa;--panel:#fff;--line:#d9dee7;--text:#18202f;--muted:#667085;--accent:#b42318;--soft:#f2f4f7;--code:#344054}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
header{height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid var(--line);background:var(--panel);position:sticky;top:0;z-index:2}
.brand{font-weight:700;font-size:16px}
.tabs{display:inline-flex;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:var(--soft)}
.tabs button{min-width:68px;border:0;border-right:1px solid var(--line);background:transparent;padding:8px 14px;font:inherit;font-weight:650;color:var(--muted);cursor:pointer}
.tabs button:last-child{border-right:0}
.tabs button.active{background:var(--panel);color:var(--accent)}
.layout{display:grid;grid-template-columns:300px 1fr;min-height:calc(100vh - 58px)}
aside{border-right:1px solid var(--line);background:var(--panel);padding:16px;overflow:auto}
main{padding:24px;overflow:auto}
.group{margin-bottom:18px}
.group h2{margin:0 0 8px;color:var(--muted);font-size:12px;text-transform:uppercase;font-weight:750}
.item{width:100%;display:block;text-align:left;border:0;border-radius:6px;background:transparent;color:var(--text);padding:8px 10px;cursor:pointer;overflow-wrap:anywhere}
.item:hover,.item.active{background:var(--soft)}
.line{display:flex;gap:8px;align-items:center;min-width:0}
.method{font:700 11px/1 ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--accent);min-width:44px}
.uri,.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--code)}
.muted{color:var(--muted)}
.detail{max-width:980px}
h1{font-size:28px;line-height:1.2;margin:0 0 8px}
h2{font-size:16px;margin:24px 0 8px}
h3{font-size:14px;margin:18px 0 6px}
p{margin:0 0 12px}
table{width:100%;border-collapse:collapse;margin:8px 0 18px;background:var(--panel);border:1px solid var(--line)}
th,td{text-align:left;vertical-align:top;border-bottom:1px solid var(--line);padding:8px 10px}
th{font-size:12px;color:var(--muted);background:var(--soft);font-weight:750}
pre{white-space:pre-wrap;background:#101828;color:#f9fafb;border-radius:8px;padding:12px;overflow:auto}
.empty{color:var(--muted);padding:24px;border:1px dashed var(--line);border-radius:8px;background:var(--panel)}
@media (max-width:760px){header{padding:0 12px}.tabs button{min-width:auto;padding:8px 10px}.layout{grid-template-columns:1fr}aside{border-right:0;border-bottom:1px solid var(--line);max-height:42vh}main{padding:16px}}
</style>
</head>
<body>
<header>
<div class="brand">Laravel Docs</div>
<nav class="tabs" aria-label="Documentation sections">
<button type="button" data-tab="api">API</button>
<button type="button" data-tab="database">DB</button>
<button type="button" data-tab="code">Code</button>
</nav>
</header>
<div class="layout">
<aside id="sidebar"></aside>
<main><section id="content" class="detail"></section></main>
</div>
<script id="docs-data" type="application/json">{$json}</script>
<script>
const docs = JSON.parse(document.getElementById('docs-data').textContent);
const labels = {api:'API', database:'DB', code:'Code'};
let state = {tab: location.hash.replace('#','') || localStorage.getItem('laravel-docs-tab') || 'api', selected: 0};
if (!['api','database','code'].includes(state.tab)) state.tab = 'api';
const sidebar = document.getElementById('sidebar');
const content = document.getElementById('content');
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const block = value => `<pre>\${esc(JSON.stringify(value ?? {}, null, 2))}</pre>`;
const table = (headers, rows) => rows.length ? `<table><thead><tr>\${headers.map(h=>`<th>\${esc(h)}</th>`).join('')}</tr></thead><tbody>\${rows.map(row=>`<tr>\${row.map(cell=>`<td>\${esc(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>` : '<p class="muted">None.</p>';
document.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => selectTab(button.dataset.tab)));
function selectTab(tab){state={tab,selected:0};localStorage.setItem('laravel-docs-tab',tab);location.hash=tab;render();}
function activate(index){state.selected=index;render();}
function render(){document.querySelectorAll('[data-tab]').forEach(button=>button.classList.toggle('active',button.dataset.tab===state.tab));renderSidebar();renderContent();}
function renderSidebar(){
  const entries = entriesFor(state.tab);
  if (!entries.length){sidebar.innerHTML=`<div class="empty">No \${labels[state.tab]} documentation found.</div>`;return;}
  sidebar.innerHTML = entries.map((entry,index)=>`<div class="group">\${entry.group?`<h2>\${esc(entry.group)}</h2>`:''}<button class="item \${index===state.selected?'active':''}" onclick="activate(\${index})">\${entry.sidebar}</button></div>`).join('');
}
function renderContent(){
  const entries = entriesFor(state.tab);
  if (!entries.length){content.innerHTML=`<div class="empty">Run docs:generate after configuring the \${labels[state.tab]} documentation source.</div>`;return;}
  const item = entries[Math.min(state.selected, entries.length - 1)].item;
  content.innerHTML = state.tab === 'api' ? apiDetail(item) : state.tab === 'database' ? dbDetail(item) : codeDetail(item);
}
function entriesFor(tab){
  if (tab === 'api') return (docs.api.groups||[]).flatMap(group => (group.endpoints||[]).map(endpoint => ({group:group.name,item:endpoint,sidebar:`<div class="line"><span class="method">\${esc(endpoint.method)}</span><span class="uri">\${esc(endpoint.uri)}</span></div><div class="muted">\${esc(endpoint.name||'Untitled endpoint')}</div>`})));
  if (tab === 'database') return (docs.database.tables||[]).map(table => ({group:'Tables',item:table,sidebar:`<span class="mono">\${esc(table.name)}</span><div class="muted">\${(table.columns||[]).length} columns</div>`}));
  return (docs.code.classes||[]).map(type => ({group:type.namespace||'Global',item:type,sidebar:`<span class="mono">\${esc(type.name)}</span><div class="muted">\${esc(type.type)}</div>`}));
}
function apiDetail(endpoint){
  return `<h1>\${esc(endpoint.name || endpoint.uri)}</h1><p><span class="method">\${esc(endpoint.method)}</span> <span class="uri">\${esc(endpoint.uri)}</span></p><p>\${esc(endpoint.description || '')}</p><p class="muted">Controller: \${esc(endpoint.controller || 'Not available')} &middot; Auth: \${endpoint.authenticated ? 'Required' : 'Not specified'}</p><h2>Request Parameters</h2>\${block(endpoint.parameters)}<h2>Request Body</h2>\${block(endpoint.body)}<h2>Responses</h2>\${block(endpoint.responses)}`;
}
function dbDetail(tableInfo){
  return `<h1>\${esc(tableInfo.name)}</h1><h2>Columns</h2>\${table(['Name','Type','Nullable','Default','Primary'],(tableInfo.columns||[]).map(c=>[c.name,c.type,c.nullable?'yes':'no',c.default,c.primary?'yes':'no']))}<h2>Primary Keys</h2>\${table(['Column'],(tableInfo.primary_keys||[]).map(k=>[k]))}<h2>Foreign Keys</h2>\${table(['Name','Column','References'],(tableInfo.foreign_keys||[]).map(k=>[k.name,k.column,`\${k.references_table}.\${k.references_column}`]))}<h2>Indexes</h2>\${table(['Name','Unique','Columns'],(tableInfo.indexes||[]).map(i=>[i.name,i.unique?'yes':'no',(i.columns||[]).join(', ')]))}`;
}
function codeDetail(type){
  return `<h1>\${esc(type.name)}</h1><p class="muted">\${esc(type.type)} &middot; \${esc(type.namespace || 'Global namespace')}</p><p>\${esc(type.summary || '')}</p><h2>Inheritance</h2><p>Parent: \${esc(type.parent || 'None')}</p><p>Interfaces: \${esc((type.interfaces||[]).join(', ') || 'None')}</p><h2>Methods</h2>\${(type.methods||[]).length ? (type.methods||[]).map(method=>`<h3><span class="mono">\${esc(method.name)}(\${(method.parameters||[]).map(p=>esc((p.type? p.type+' ' : '') + '$' + p.name)).join(', ')})\${method.return_type ? ': ' + esc(method.return_type) : ''}</span></h3><p>\${esc(method.summary || '')}</p>`).join('') : '<p class="muted">No public methods found.</p>'}<h2>Properties</h2>\${table(['Name','Type','Description'],(type.properties||[]).map(p=>[p.name,p.type,p.summary]))}`;
}
window.addEventListener('hashchange',()=>{const tab=location.hash.replace('#','');if(['api','database','code'].includes(tab)){state.tab=tab;state.selected=0;render();}});
render();
</script>
</body>
</html>
HTML;
    }
}
