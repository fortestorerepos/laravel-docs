function searchNormalize(value){
  return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}
function buildSearchIndex(){
  const records = [];
  const seen = new Set();
  const add = (tab, index, title, place, detail = '', anchor = '', method = '') => {
    const key = JSON.stringify([tab, title, place, anchor]);
    if (seen.has(key)) return;
    seen.add(key);
    const priority = place.startsWith('DB / Relations /') ? 0 : anchor ? 1 : 2;
    records.push({tab, index, title, place, detail, anchor, method, priority, text: searchNormalize(`${title} ${place} ${detail}`), name: searchNormalize(title)});
  };
  let apiIndex = 0;
  for (const group of docs.api.groups || []) for (const endpoint of group.endpoints || []) {
    add('api', apiIndex++, endpoint.name || endpoint.uri, `API / ${group.name} / ${endpoint.method} ${endpoint.uri}`, endpoint.description, '', endpoint.method);
  }
  entriesFor('database').forEach(({item, group}, index) => {
    if (item.kind === 'database-relations-diagram') add('database', index, 'Diagrams', 'DB / Diagrams');
    else if (item.kind === 'database-constraints') add('database', index, 'Constraints', 'DB / Constraints');
    else if (item.kind === 'database-relation') add('database', index, `${item.from_table}.${item.from_column}`, `DB / Relations / ${item.to_table}.${item.to_column}`);
    else {
      add('database', index, item.name, `DB / ${group}`, item.model || '');
      for (const column of list(item.columns)) add('database', index, column.name, `DB / ${item.name} / Columns`, `${column.type || ''} ${column.description || ''}`, memberId('column', column.name));
    }
  });
  (docs.code.classes || []).forEach((type, index) => {
    const place = `Code / ${type.namespace || 'Global'} / ${type.name}`;
    add('code', index, type.name, place, `${type.summary || ''} ${type.description || ''}`);
    for (const [kind, members] of Object.entries({method: type.methods, property: type.properties, case: type.cases})) {
      for (const member of list(members)) add('code', index, kind === 'method' ? `${member.name}()` : kind === 'property' ? `$${member.name}` : member.name, `${place} / ${typeLabel(kind)}`, `${member.summary || ''} ${member.description || ''}`, memberId(kind, member.name));
    }
  });
  return records;
}
function findSearchResults(records, query){
  const prefix = query.match(/^\s*(api|db|database|code)\s*:\s*/i);
  const scope = prefix ? ({db: 'database'}[prefix[1].toLowerCase()] || prefix[1].toLowerCase()) : null;
  const term = searchNormalize(prefix ? query.slice(prefix[0].length) : query).trim();
  const words = term.split(/\s+/).filter(Boolean);
  return records.filter(record => (!scope || record.tab === scope) && words.every(word => record.text.includes(word)))
    .map(record => ({...record, score: !term ? 0 : record.name === term ? 3 : record.name.startsWith(term) ? 2 : record.name.includes(term) ? 1 : 0}))
    .sort((a, b) => b.score - a.score || (b.priority || 0) - (a.priority || 0));
}
function searchResultUrl(result){
  return `${result.tab}.html?entry=${result.index}${result.anchor ? '#' + encodeURIComponent(result.anchor) : ''}`;
}
function initGlobalSearch(){
  const trigger = document.getElementById('global-search');
  const dialog = document.getElementById('search-palette');
  const input = document.getElementById('palette-query');
  const results = document.getElementById('palette-results');
  const status = document.getElementById('palette-status');
  if (!trigger || !dialog) return;
  let records;
  let matches = [];
  let selected = 0;
  const select = index => {
    selected = index;
    Array.from(results.children).forEach((row, rowIndex) => row.setAttribute('aria-selected', String(rowIndex === selected)));
    if (matches.length) {
      input.setAttribute('aria-activedescendant', `search-result-${selected}`);
      results.children[selected]?.scrollIntoView({block: 'nearest'});
    } else input.removeAttribute('aria-activedescendant');
  };
  const renderResults = () => {
    records ??= buildSearchIndex();
    const found = findSearchResults(records, input.value);
    matches = found.slice(0, 80);
    status.textContent = found.length ? `${found.length} results${found.length > 80 ? ' (first 80 shown)' : ''}` : 'No results';
    results.innerHTML = matches.map((result, index) => `<a id="search-result-${index}" class="palette-result" href="${esc(searchResultUrl(result))}" role="option" aria-selected="${index === 0}" tabindex="-1"><span class="palette-result-heading">${result.method ? `<span class="${methodClass(result.method)}">${esc(result.method)}</span>` : `<span class="palette-section">${esc(labels[result.tab])}</span>`}<strong>${esc(result.title)}</strong></span><span class="palette-place">${esc(result.place)}</span></a>`).join('');
    select(0);
  };
  const open = () => {
    if (dialog.open) return;
    input.value = trigger.value;
    dialog.showModal();
    renderResults();
    input.focus();
  };
  trigger.addEventListener('click', open);
  trigger.addEventListener('input', open);
  trigger.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === 'ArrowDown') { event.preventDefault(); open(); }
  });
  input.addEventListener('input', renderResults);
  input.addEventListener('keydown', event => {
    if (event.isComposing) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      dialog.close();
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (matches.length) select((selected + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length);
    } else if (event.key === 'Enter') {
      event.preventDefault();
      if (matches[selected]) window.location.assign(searchResultUrl(matches[selected]));
    }
  });
  document.getElementById('palette-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => {
    const rect = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
  });
  dialog.addEventListener('close', () => { trigger.value = ''; trigger.focus(); });
  document.addEventListener('keydown', event => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); open(); }
  });
  const entry = new URLSearchParams(window.location.search).get('entry');
  if (entry !== null && /^\d+$/.test(entry) && Number(entry) < entriesFor(state.tab).length) {
    activate(Number(entry));
    if (window.location.hash) {
      let anchor;
      try { anchor = decodeURIComponent(window.location.hash.slice(1)); } catch { return; }
      requestAnimationFrame(() => document.getElementById(anchor)?.scrollIntoView({block: 'start'}));
    }
  }
}
initGlobalSearch();
