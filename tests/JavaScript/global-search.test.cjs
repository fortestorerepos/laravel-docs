const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');
const context = vm.createContext({document: {getElementById: () => null}});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../resources/views/static/assets/search.js'), 'utf8'), context);
const records = [
  {tab: 'api', title: 'Compras', name: 'compras', text: 'compras api integracoes /pedidos', index: 3},
  {tab: 'database', title: 'compras', name: 'compras', text: 'compras db tables', index: 10},
  {tab: 'code', title: 'ComprasController', name: 'comprascontroller', text: 'comprascontroller code app http controllers', index: 1},
];

test('global search scopes prefixes without hiding unscoped results', () => {
  assert.equal(context.findSearchResults(records, 'compras').length, 3);
  for (const [prefix, tab] of [['api', 'api'], ['DB', 'database'], ['database', 'database'], ['code', 'code']]) {
    const results = context.findSearchResults(records, ` ${prefix}: compras`);
    assert.equal(results.length, 1);
    assert.equal(results[0].tab, tab);
  }
  assert.equal(context.findSearchResults(records, 'api:compras pedidos').length, 1);
  assert.equal(context.findSearchResults(records, 'api:missing').length, 0);
  assert.equal(context.searchNormalize('Integrações'), 'integracoes');
});

test('search links target the correct tab, entry and member', () => {
  assert.equal(context.searchResultUrl(records[0]), 'api.html?entry=3');
  assert.equal(context.searchResultUrl({...records[2], anchor: 'method_store'}), 'code.html?entry=1#method_store');
});
