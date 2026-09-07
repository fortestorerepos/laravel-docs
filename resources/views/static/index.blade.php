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
<div class="brand">Laravel Docs</div>
<nav class="tabs" aria-label="Documentation sections">
<a href="api.html" @class(['active' => $activeTab === 'api'])>API</a>
<a href="database.html" @class(['active' => $activeTab === 'database'])>DB</a>
<a href="code.html" @class(['active' => $activeTab === 'code'])>Code</a>
</nav>
</header>
<div class="layout">
<aside id="sidebar"></aside>
<main><section id="content" class="detail"></section></main>
</div>
<script id="docs-data" type="application/json">{!! $docsJson !!}</script>
<script src="assets/index.js"></script>
</body>
</html>
