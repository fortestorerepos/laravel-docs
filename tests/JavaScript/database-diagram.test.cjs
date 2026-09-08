const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/static/assets/index.js'), 'utf8');
function diagram(database){
  const storage = new Map();
  const context = vm.createContext({
    docs: {database},
    list: value => Array.isArray(value) ? value : Object.values(value || {}),
    localStorage: {getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key)},
    diagramView: {scale: 1, offsetX: 0, offsetY: 0, initialized: false},
    location: {pathname: '/database.html'},
  });
  vm.runInContext(source.slice(source.indexOf('const diagramScene'), source.indexOf('function codeDetail')), context);
  vm.runInContext(source.slice(source.indexOf('function resetDiagramView'), source.indexOf('function setDiagramZoom')), context);
  vm.runInContext('function drawDatabaseDiagram(){ routeDiagramConnections(); }', context);
  vm.runInContext('prepareDiagram(); routeDiagramConnections();', context);
  return {context, ...vm.runInContext('({scene: diagramScene, blocked: diagramSegmentBlocked, anchor: diagramColumnAnchorY})', context)};
}
function schema(count = 24){
  const tables = Array.from({length: count}, (_, index) => ({
    name: ['orders', 'catalog', 'delivery'][index % 3] + '_' + index,
    columns: [{name: 'id', primary: true}, {name: 'parent_id'}, {name: 'account_id'}],
  }));
  const relationships = tables.slice(1).flatMap((table, index) => [{
    from_table: table.name, from_column: 'parent_id', to_table: tables[Math.max(0, index - 2)].name, to_column: 'id',
  }, {
    from_table: table.name, from_column: 'account_id', to_table: tables[0].name, to_column: 'id',
  }]);
  return {tables, relationships};
}
function assertClear(scene, blocked){
  const boxes = [...scene.boxes.values()];
  for (let i = 0; i < boxes.length; i++) for (let j = i + 1; j < boxes.length; j++) {
    const a = boxes[i], b = boxes[j];
    assert(!(a.x < b.x + b.width && a.x + a.width > b.x && a.y < b.y + b.height && a.y + a.height > b.y), 'cards overlap');
  }
  for (const route of scene.routes) for (let i = 1; i < route.points.length; i++) {
    assert(!blocked(route.points[i - 1], route.points[i], boxes), 'connection enters a card');
  }
}

test('initial layout separates cards and routes every connection outside all card interiors', () => {
  const database = schema();
  const {scene, blocked} = diagram(database);
  assert.equal(scene.boxes.size, database.tables.length);
  assert.equal(scene.routes.length, database.relationships.length);
  assertClear(scene, blocked);
  assert(new Set([...scene.boxes.values()].map(box => box.x)).size > 4);
  assert(new Set([...scene.boxes.values()].map(box => box.y)).size > 4);
});

test('duplicate metadata does not duplicate table cards or connection strokes', () => {
  const database = schema(8);
  database.tables.push(...database.tables);
  database.relationships.push(...database.relationships);
  const {scene} = diagram(database);
  assert.equal(scene.boxes.size, 8);
  assert.equal(scene.routes.length, 14);
});

test('layout is deterministic for the same schema', () => {
  const positions = scene => JSON.stringify([...scene.boxes].map(([name, box]) => [name, box.x, box.y]));
  assert.equal(positions(diagram(schema()).scene), positions(diagram(schema()).scene));
});

test('all relationship fields remain visible, including fields beyond the former five-row limit', () => {
  const database = schema(2);
  database.relationships = Array.from({length: 9}, (_, index) => ({
    from_table: database.tables[1].name, from_column: 'foreign_' + index,
    to_table: database.tables[0].name, to_column: 'id',
  }));
  const {scene, anchor} = diagram(database);
  const box = scene.boxes.get(database.tables[1].name);
  for (const edge of database.relationships) {
    assert(box.shownColumns.some(column => column.name === edge.from_column));
    assert(anchor(box, edge.from_column) < box.y + box.height - 26);
    assert.equal(box.references.get(edge.from_column)[0], edge.to_table + '.' + edge.to_column);
  }
});

test('moving a card preserves every edge and reattaches routes at its new field positions', () => {
  const {scene, context, blocked, anchor} = diagram(schema(12));
  const box = scene.boxes.values().next().value;
  const count = scene.routes.length;
  box.x = -1400;
  box.y = -700;
  vm.runInContext('diagramScene.dirty = true; routeDiagramConnections();', context);
  assert.equal(scene.routes.length, count);
  for (const route of scene.routes.filter(route => route.relation.to_table === box.table.name)) {
    assert.equal(route.points.at(-1).y, anchor(box, route.relation.to_column));
    assert([box.x, box.x + box.width].includes(route.points.at(-1).x));
  }
  assertClear(scene, blocked);
});

test('self references route around their own card', () => {
  const database = schema(1);
  database.relationships = [{from_table: database.tables[0].name, from_column: 'parent_id', to_table: database.tables[0].name, to_column: 'id'}];
  const {scene, blocked} = diagram(database);
  assert.equal(scene.routes.length, 1);
  assertClear(scene, blocked);
});

test('Reset restores the layout, routes and camera, and clears persisted table moves', () => {
  const {context, scene} = diagram(schema(12));
  const positions = () => JSON.stringify([...scene.boxes].map(([name, box]) => [name, box.x, box.y]));
  const originalPositions = positions();
  const originalRoutes = JSON.stringify(scene.routes);
  vm.runInContext(`
    const movedBox = diagramScene.boxes.values().next().value;
    movedBox.x = -900; movedBox.y = -600;
    localStorage.setItem(diagramStorageKey(), JSON.stringify({[movedBox.table.name]: {x: -900, y: -600}}));
    diagramView.scale = 2; diagramView.offsetX = 100; diagramView.offsetY = 200;
    resetDiagramView(false);
  `, context);
  assert.notEqual(positions(), originalPositions, 'initial camera setup must preserve saved moves');
  vm.runInContext('resetDiagramView()', context);
  assert.equal(positions(), originalPositions);
  assert.equal(JSON.stringify(scene.routes), originalRoutes);
  assert.equal(context.diagramView.scale, 1);
  assert.equal(context.diagramView.offsetX, 44 - scene.origin.x);
  assert.equal(context.diagramView.offsetY, 44 - scene.origin.y);
  assert.equal(vm.runInContext('localStorage.getItem(diagramStorageKey())', context), null);
  vm.runInContext('diagramScene.boxes = null; prepareDiagram()', context);
  assert.equal(positions(), originalPositions, 'old moves must not return when reloading');
});

test('empty schemas and references to unavailable tables do not crash', () => {
  assert.equal(diagram({tables: [], relationships: []}).scene.boxes.size, 0);
  const database = schema(1);
  database.relationships = [{from_table: database.tables[0].name, from_column: 'parent_id', to_table: 'missing', to_column: 'id'}];
  assert.equal(diagram(database).scene.routes.length, 0);
});
