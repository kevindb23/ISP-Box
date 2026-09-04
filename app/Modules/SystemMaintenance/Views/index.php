<?php
$manifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json';
$manifest = is_file($manifestPath) ? (json_decode((string)file_get_contents($manifestPath), true) ?: []) : [];
$entry = $manifest['src/main.ts'] ?? [];
$version = is_file($manifestPath) ? (string)filemtime($manifestPath) : (string)time();
foreach (($entry['css'] ?? []) as $css):
?>
<link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$css, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>
<div class="container-fluid nx-page" data-nx-next-root="system-maintenance"></div>
<?php if (!empty($entry['file'])): ?>
<script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$entry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php else: ?>
<div class="alert alert-warning">The System Maintenance interface is not built.</div>
<?php endif; return; ?>
