<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laravel Docs</title>
<link rel="stylesheet" href="assets/index.css">
</head>
<body data-active-tab="{{ $activeTab }}">
<header>
<div class="brand-group"><div class="brand">Laravel Docs</div><button id="theme-toggle" class="theme-toggle" type="button" aria-label="Switch theme" title="Switch theme">Light</button></div>
<input id="global-search" class="global-search" type="search" placeholder="Search documentation..." aria-label="Search documentation" aria-haspopup="dialog" autocomplete="off">
<div class="header-actions">
<nav class="tabs" aria-label="Documentation sections">
<a href="api.html" @class(['active' => $activeTab === 'api'])>API</a>
<a href="database.html" @class(['active' => $activeTab === 'database'])>DB</a>
<a href="code.html" @class(['active' => $activeTab === 'code'])>Code</a>
</nav>
<button id="pdf-download" class="pdf-button" type="button">Download PDF</button>
</div>
</header>
<div class="layout">
<aside id="sidebar"></aside>
<main><section id="content" class="detail"></section></main>
</div>
<dialog id="search-palette" class="search-palette" aria-label="Search documentation">
<div class="palette-heading"><input id="palette-query" type="search" placeholder="Search documentation..." aria-label="Search documentation" role="combobox" aria-expanded="true" aria-autocomplete="list" aria-controls="palette-results" autocomplete="off" spellcheck="false"><button id="palette-close" type="button" aria-label="Close search" title="Close search">&times;</button></div>
<div id="palette-status" class="palette-status" role="status" aria-live="polite"></div>
<div id="palette-results" class="palette-results" role="listbox" aria-label="Search results"></div>
</dialog>
<script id="docs-data" type="application/json">{!! $docsJson !!}</script>
<script src="assets/index.js"></script>
<script src="assets/search.js"></script>
</body>
</html>
