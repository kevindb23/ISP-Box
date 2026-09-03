<?php

$nxViteDevServer = rtrim((string) (getenv('VITE_DEV_SERVER_URL') ?: ''), '/');

if ($nxViteDevServer !== ''):
?>
    <script type="module" src="<?= htmlspecialchars($nxViteDevServer . '/@vite/client', ENT_QUOTES, 'UTF-8') ?>"></script>
    <script type="module" src="<?= htmlspecialchars($nxViteDevServer . '/resources/js/app.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
    return;
endif;

$nxViteManifestPath = BASE_PATH . '/public/build/.vite/manifest.json';
$nxViteManifest = [];

if (is_file($nxViteManifestPath)) {
    $nxViteManifest = json_decode((string) file_get_contents($nxViteManifestPath), true) ?: [];
}

$nxViteEntry = $nxViteManifest['resources/js/app.js'] ?? [];
$nxViteVersion = is_file($nxViteManifestPath) ? (string) filemtime($nxViteManifestPath) : (string) time();

foreach (($nxViteEntry['css'] ?? []) as $nxViteCss):
?>
    <link rel="stylesheet" href="/build/<?= htmlspecialchars(ltrim((string) $nxViteCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxViteVersion, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach;

if (!empty($nxViteEntry['file'])):
?>
    <script type="module" src="/build/<?= htmlspecialchars(ltrim((string) $nxViteEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxViteVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif;
