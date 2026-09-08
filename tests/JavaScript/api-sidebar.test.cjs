const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/static/assets/index.js'), 'utf8');

function apiSidebar(apiGroupBy = 'endpoints'){
  const storage = new Map([['laravel-docs-api-group-by', apiGroupBy]]);
  const elements = {
    'docs-data': {textContent: JSON.stringify({
      api: {groups: [
        {name: 'Defeitos', endpoints: [
          {name: 'Listar defeitos agrupados por fornecedor com Excel', method: 'GET', uri: '/api/integration/defeitos/listar-enviar-fornecedor'},
          {name: 'Marcar defeitos como enviados para o fornecedor', method: 'POST', uri: '/api/integration/defeitos/marcar-enviados-fornecedor', body: {ids: [1, 2]}, responses: {'200': {description: 'OK', body: {sent: true}}}},
        ]},
        {name: 'Endpoints', endpoints: [
          {name: 'POST api/documento/stock/verificar', method: 'POST', uri: '/api/documento/stock/verificar'},
        ]},
      ]},
      database: {tables: [], relationships: [], constraints: []},
      code: {classes: []},
    })},
    sidebar: {innerHTML: ''},
    content: {innerHTML: '', classList: {toggle: () => {}}},
    'theme-toggle': null,
    'pdf-download': null,
  };
  const context = vm.createContext({
    document: {
      body: {dataset: {activeTab: 'api'}},
      documentElement: {dataset: {}},
      getElementById: id => elements[id] ?? null,
    },
    localStorage: {
      getItem: key => storage.get(key) ?? null,
      setItem: (key, value) => storage.set(key, value),
    },
    window: {location: {hash: ''}},
    navigator: {clipboard: {writeText: async () => {}}},
    setTimeout,
    requestAnimationFrame: callback => callback(),
  });

  vm.runInContext(source.slice(0, source.indexOf('function dbContent')), context);
  vm.runInContext('renderSidebar()', context);

  return {context, elements, storage};
}

test('API sidebar uses collapsible endpoint groups by default', () => {
  const {elements} = apiSidebar();

  assert.match(elements.sidebar.innerHTML, /API sidebar grouping/);
  assert.match(elements.sidebar.innerHTML, /setApiGroupBy\('endpoints'\)/);
  assert.match(elements.sidebar.innerHTML, /setApiGroupBy\('requests'\)/);
  assert.match(elements.sidebar.innerHTML, /<summary><span class="mono">Defeitos<\/span><\/summary>/);
  assert.match(elements.sidebar.innerHTML, /<summary><span class="mono">Endpoints<\/span><\/summary>/);
});

test('API sidebar can group requests by HTTP method', () => {
  const {context, elements, storage} = apiSidebar();

  vm.runInContext("setApiGroupBy('requests')", context);

  assert.equal(storage.get('laravel-docs-api-group-by'), 'requests');
  assert.match(elements.sidebar.innerHTML, /<summary><span class="mono">GET<\/span><\/summary>/);
  assert.match(elements.sidebar.innerHTML, /<summary><span class="mono">POST<\/span><\/summary>/);
  assert.doesNotMatch(elements.sidebar.innerHTML, /<summary><span class="mono">Defeitos<\/span><\/summary>/);
});

test('API detail renders a Scribe-like tryout and copyable curl example', () => {
  const {context, elements} = apiSidebar();

  vm.runInContext('state.selected = 1; renderContent()', context);

  assert.match(elements.content.innerHTML, /class="api-page"/);
  assert.match(elements.content.innerHTML, /Try it out/);
  assert.match(elements.content.innerHTML, /Send Request/);
  assert.match(elements.content.innerHTML, /class="method method-post"/);
  assert.match(elements.content.innerHTML, /curl --request POST/);
  assert.match(elements.content.innerHTML, /--url &quot;{{baseUrl}}\/api\/integration\/defeitos\/marcar-enviados-fornecedor&quot;/);
  assert.match(elements.content.innerHTML, /--data/);
  assert.match(elements.content.innerHTML, /Copy/);
  assert.match(elements.content.innerHTML, /Example response \(200\):/);
  assert.match(elements.content.innerHTML, /Example body:<\/h2><pre class="code-sample api-example-body">/);
  assert.match(elements.content.innerHTML, /&quot;ids&quot;: \[/);
  assert.match(elements.content.innerHTML, /&quot;sent&quot;: true/);
});

test('Scribe export renders path examples, bearer header and the actual error response', () => {
  const {context} = apiSidebar();
  const html = vm.runInContext(`apiDetail({
    name: 'Obter excel prePedido', method: 'GET',
    uri: '/api/compras/pedidos/{prepedido_id}/export', authenticated: true,
    headers: {Authorization: 'Bearer {YOUR_BEARER_TOKEN}'},
    parameters: [{name: 'prepedido_id', in: 'path', example: 16}],
    responses: [{status: 401, headers: {'content-type': 'application/json'}, body: '{"message":"Unauthorized"}'}]
  })`, context);

  assert.match(html, /Obter excel prePedido/);
  assert.match(html, /Auth: Required/);
  assert.match(html, /--url &quot;{{baseUrl}}\/api\/compras\/pedidos\/16\/export&quot;/);
  assert.match(html, /Authorization: Bearer \{YOUR_BEARER_TOKEN\}/);
  assert.match(html, /Example response \(401\)/);
  assert.match(html, /Show headers/);
  assert.match(html, /Unauthorized/);
  assert.equal(vm.runInContext("apiBodyPayload({mode: 'raw', raw: '{\"ids\":[1]}'})", context), '{"ids":[1]}');
});

test('Headers appear for both auth states and body fields preserve required metadata', () => {
  const {context} = apiSidebar();
  for (const authenticated of [true, false]) {
    const html = vm.runInContext(`apiDetail({method:'POST', authenticated:${authenticated}})`, context);
    for (const name of ['Authorization', 'Content-Type', 'Accept']) assert.ok(html.includes(`data-api-header="${name}"`));
    assert.match(html, /Example: <code>Bearer \{YOUR_BEARER_TOKEN\}/);
  }
  const html = vm.runInContext(`apiBodyFields({body_schema:{content:{'application/json':{schema:{required:['transSerial'],properties:{transSerial:{type:'string',example:'architecto'},chave:{type:'string',example:'architecto'}}}}}}})`, context);
  assert.match(html, /Body Parameters/);
  assert.match(html, /data-api-body="transSerial" data-type="string" required/);
  assert.match(html, /optional/);
});

test('saved base URL and authorization carry across endpoints and can be cleared', () => {
  const {context, elements} = apiSidebar();
  vm.runInContext(`saveApiSetting('base-url', 'https://api.example.test/'); saveApiSetting('authorization', 'Bearer saved-token'); state.selected=1; renderContent()`, context);
  assert.match(elements.content.innerHTML, /value="https:\/\/api.example.test\/"/);
  assert.match(elements.content.innerHTML, /value="Bearer saved-token"/);
  assert.match(elements.content.innerHTML, /--url &quot;https:\/\/api.example.test\/api\//);
  vm.runInContext(`saveApiSetting('authorization', ''); state.selected=0; renderContent()`, context);
  assert.match(elements.content.innerHTML, /data-api-header="Authorization" value=""/);
  assert.doesNotMatch(elements.content.innerHTML, /saved-token/);
  vm.runInContext(`window.location.pathname='/another-project/api.html'`, context);
  assert.equal(vm.runInContext(`apiSavedSetting('base-url')`, context), null);
});
