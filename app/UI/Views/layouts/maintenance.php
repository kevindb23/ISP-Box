<?php

require_once BASE_PATH . '/app/Core/Branding/branding.php';

$maintenanceBranding = \App\Core\Branding::get();
$maintenanceCompanyName = trim((string)($maintenanceBranding['company_name'] ?? $maintenanceBranding['client_name'] ?? 'ISP-in-a-Box'));
$maintenanceLogoPath = trim((string)($maintenanceBranding['logo_path'] ?? ''));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($maintenanceCompanyName) ?> · System maintenance</title>
    <?php if ($maintenanceLogoPath !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($maintenanceLogoPath) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/icons/bootstrap-icons.css">
    <?php require BASE_PATH . '/app/UI/Views/layouts/vite.php'; ?>
    <style>
        :root { color-scheme: light; }
        html, body { min-height: 100%; }
        body.nx-maintenance-layout {
            min-height: 100vh;
            margin: 0;
            background: #f8fafc;
        }
        .nx-maintenance-layout__main {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .nx-maintenance-layout__main > .container-fluid {
            width: 100%;
            min-height: auto !important;
            padding: 0;
        }
        .nx-maintenance-layout__main #subscriberMaintenancePage {
            min-height: auto !important;
            padding: 0;
        }
        .nx-maintenance-layout__main #subscriberMaintenancePage > .card {
            width: min(680px, 100%);
            margin: 0 auto;
        }
    </style>
</head>
<body class="nx-maintenance-layout">
    <main class="nx-maintenance-layout__main" aria-label="System maintenance">
        <?= $content ?>
    </main>
</body>
</html>
