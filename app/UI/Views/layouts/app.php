<?php

require_once BASE_PATH . '/app/Core/Branding/branding.php';

$appBranding = \App\Core\Branding::get();
$appCompanyName = trim((string)($appBranding['company_name'] ?? $appBranding['client_name'] ?? 'ISP-in-a-Box'));
$appLogoPath = trim((string)($appBranding['logo_path'] ?? ''));
$appPrimaryColor = trim((string)($appBranding['primary_color'] ?? '#2563EB'));

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $appPrimaryColor)) {
    $appPrimaryColor = '#2563EB';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <title><?= htmlspecialchars($appCompanyName) ?> · Operations console</title>
    <?php if ($appLogoPath !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($appLogoPath) ?>">
    <?php endif; ?>

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('nexusbox.theme') || 'light';

                if (theme !== 'light' && theme !== 'dark') {
                    theme = 'light';
                }

                document.documentElement.setAttribute('data-theme', theme);

                if (JSON.parse(localStorage.getItem('nexusbox.sidebar.collapsed') || 'false')) {
                    document.documentElement.classList.add('sidebar-collapsed-preload');
                }
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/icons/bootstrap-icons.css">
    <?php require BASE_PATH . '/app/UI/Views/layouts/vite.php'; ?>
    <style>:root {
        --nx-primary: <?= htmlspecialchars($appPrimaryColor) ?>;
        --primary: <?= htmlspecialchars($appPrimaryColor) ?>;
        --ring: <?= htmlspecialchars($appPrimaryColor) ?>;
    }

    /* Shared switch contract. This intentionally loads after Bootstrap and the
       application theme so module toggles retain a visible sliding state. */
    body#nxApplication #nxRectilinearTheme .form-switch {
        min-height: 1.5rem;
        padding-left: 3.25rem;
    }
    body#nxApplication #nxRectilinearTheme .form-switch .form-check-input {
        width: 2.75rem !important;
        height: 1.5rem !important;
        margin-top: 0 !important;
        margin-left: -3.25rem !important;
        border: 1px solid var(--input, #cbd5e1) !important;
        border-radius: 999px !important;
        background-color: var(--secondary, #e5e7eb) !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23ffffff'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: left center !important;
        background-size: 1.25rem 1.25rem !important;
        box-shadow: inset 0 0 0 1px rgb(15 23 42 / 8%), 0 1px 2px rgb(15 23 42 / 12%) !important;
        cursor: pointer;
        transition: background-position .18s ease, background-color .18s ease, border-color .18s ease, box-shadow .18s ease !important;
    }
    body#nxApplication #nxRectilinearTheme .form-switch .form-check-input:checked {
        border-color: var(--nx-primary, #2563eb) !important;
        background-color: var(--nx-primary, #2563eb) !important;
        background-position: right center !important;
    }
    body#nxApplication #nxRectilinearTheme .form-switch .form-check-input:focus-visible {
        outline: 0;
        box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--ring, #2563eb) 25%, transparent), inset 0 0 0 1px rgb(15 23 42 / 8%) !important;
    }
    body#nxApplication #nxRectilinearTheme .form-switch .form-check-input:disabled {
        cursor: not-allowed;
        filter: grayscale(.25);
        opacity: .55;
    }
    body#nxApplication #nxRectilinearTheme .form-switch .form-check-label {
        cursor: pointer;
        user-select: none;
    }
    body#nxApplication #nxRectilinearTheme .form-switch .form-check-input:disabled + .form-check-label {
        cursor: not-allowed;
    }
    body#nxApplication .nx-search-suggestion.is-active {
        background: var(--secondary, #f1f5f9) !important;
        outline: 2px solid color-mix(in srgb, var(--ring, #2563eb) 35%, transparent) !important;
        outline-offset: -2px;
    }
    body#nxApplication .nx-search-suggestion > span {
        display: flex;
        min-width: 0;
        flex-direction: column;
    }
    body#nxApplication .nx-search-suggestion-label,
    body#nxApplication .nx-search-suggestion-section {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    body#nxApplication .nx-search-suggestion-section {
        color: var(--muted-foreground, #64748b);
        font-size: 10px;
    }

    /* Shared table contract: every module gets the same responsive overflow,
       readable density, compact action layout, and theme-token surfaces. */
    body#nxApplication #nxRectilinearTheme .table-responsive,
    body#nxApplication #nxRectilinearTheme .nx-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        overscroll-behavior-inline: contain;
        scrollbar-gutter: stable;
        -webkit-overflow-scrolling: touch;
    }
    body#nxApplication #nxRectilinearTheme .table-responsive > table,
    body#nxApplication #nxRectilinearTheme .nx-table-wrap > table,
    body#nxApplication #nxRectilinearTheme .nx-table-wrap > .table {
        width: 100% !important;
        margin-bottom: 0 !important;
    }
    body#nxApplication #nxRectilinearTheme table thead th,
    body#nxApplication #nxRectilinearTheme .table thead th {
        padding: .75rem .875rem !important;
        vertical-align: middle;
        white-space: nowrap;
        line-height: 1.25;
    }
    body#nxApplication #nxRectilinearTheme table tbody td,
    body#nxApplication #nxRectilinearTheme .table tbody td {
        padding: .75rem .875rem !important;
        vertical-align: middle;
        line-height: 1.35;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }
    body#nxApplication #nxRectilinearTheme table td code,
    body#nxApplication #nxRectilinearTheme table td .font-monospace,
    body#nxApplication #nxRectilinearTheme table td [data-nowrap] {
        white-space: nowrap !important;
        overflow-wrap: normal !important;
    }

    /* Theme surface contract. The compiled legacy bundle still exposes a dark
       --card-surface in light mode, so structural cards must use the canonical
       theme tokens directly rather than inheriting that obsolete variable. */
    html:not([data-theme="dark"]) body#nxApplication {
        --card-surface: var(--card, #fff);
        --card-foreground: var(--foreground, #171717);
    }
    html[data-theme="dark"] body#nxApplication {
        --card-surface: var(--card, #252728);
        --card-foreground: var(--foreground, #f5f5f5);
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .card:not(.nx-page-header-card):not(.nx-toolbar-card),
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .nx-summary-card,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .summary-item,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .audit-summary-card,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .users-summary-card,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .nx-section-card,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .nx-content-card,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .table-container,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content [class$="-card"]:not([class*="page-header"]):not([class*="toolbar"]):not([class*="hero"]),
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content [class*="-card "]:not([class*="page-header"]):not([class*="toolbar"]):not([class*="hero"]) {
        background-color: var(--card, #fff) !important;
        background-image: none !important;
        color: var(--foreground, #171717) !important;
        border-color: var(--border, #d5d5d2) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .card:not(.nx-page-header-card):not(.nx-toolbar-card),
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .nx-summary-card,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .summary-item,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .audit-summary-card,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .users-summary-card,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .nx-section-card,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .nx-content-card,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .table-container,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content [class$="-card"]:not([class*="page-header"]):not([class*="toolbar"]):not([class*="hero"]),
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content [class*="-card "]:not([class*="page-header"]):not([class*="toolbar"]):not([class*="hero"]) {
        background-color: var(--card, #252728) !important;
        background-image: none !important;
        color: var(--foreground, #f5f5f5) !important;
        border-color: var(--border, #414344) !important;
    }
    /* Final structural-card boundary. Several module view styles are emitted
       after the compiled theme, so anchor this rule to the shared content ID. */
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #nxMainContent.main-content .card:not(.nx-page-header-card):not(.nx-toolbar-card):not([class*="hero"]),
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #nxMainContent.main-content [class$="-panel"],
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #nxMainContent.main-content [class*="-panel "] {
        background-color: var(--card, #fff) !important;
        background-image: none !important;
        color: var(--foreground, #171717) !important;
        border-color: var(--border, #d5d5d2) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #nxMainContent.main-content .card:not(.nx-page-header-card):not(.nx-toolbar-card):not([class*="hero"]),
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #nxMainContent.main-content [class$="-panel"],
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #nxMainContent.main-content [class*="-panel "] {
        background-color: var(--card, #252728) !important;
        background-image: none !important;
        color: var(--foreground, #f5f5f5) !important;
        border-color: var(--border, #414344) !important;
    }
    /* Page-scoped inline styles may carry their own page ID. Repeating the
       stable content anchor keeps the shared theme authoritative without
       coupling this contract to every current and future module ID. */
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #nxMainContent#nxMainContent .card:not(.nx-page-header-card):not(.nx-toolbar-card):not([class*="hero"]),
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #nxMainContent#nxMainContent [class$="-panel"],
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #nxMainContent#nxMainContent [class*="-panel "] {
        background-color: var(--card, #fff) !important;
        background-image: none !important;
        color: var(--foreground, #171717) !important;
        border-color: var(--border, #d5d5d2) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #nxMainContent#nxMainContent .card:not(.nx-page-header-card):not(.nx-toolbar-card):not([class*="hero"]),
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #nxMainContent#nxMainContent [class$="-panel"],
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #nxMainContent#nxMainContent [class*="-panel "] {
        background-color: var(--card, #252728) !important;
        background-image: none !important;
        color: var(--foreground, #f5f5f5) !important;
        border-color: var(--border, #414344) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .nx-summary-card :is(.nx-summary-label, .nx-summary-value, .nx-summary-text),
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .summary-item :is(.summary-label, .summary-value, .summary-text),
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .users-summary-card :is(.users-summary-label, .users-summary-value),
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .audit-summary-card :is(.audit-summary-label, .audit-summary-value) {
        color: var(--foreground, #171717) !important;
    }

    /* Shared modal theme contract. Module dialogs keep the page visible behind
       them and use explicit surface/text pairs for both static form controls
       and detail-value boxes injected after the modal opens. */
    body#nxApplication #nxRectilinearTheme .main-content .modal.show {
        background: transparent !important;
    }
    html[data-theme="dark"] body#nxApplication:has(#nxRectilinearTheme .main-content .modal.show) > .modal-backdrop.show {
        background-color: #000 !important;
        opacity: .28 !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal-content,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal-content > form,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal-header,
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal-footer {
        background-color: var(--card, #fff) !important;
        color: var(--foreground, #171717) !important;
        border-color: var(--border, #d5d5d2) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal-body {
        background-color: var(--background, #fff) !important;
        color: var(--foreground, #171717) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal-body .nx-section-card {
        background-color: var(--card, #fff) !important;
        color: var(--foreground, #171717) !important;
        border-color: var(--border, #d5d5d2) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal :is(.modal-title, .nx-section-title, .nx-field > div) {
        color: #0f172a !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal .nx-field > div {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
        -webkit-text-fill-color: #0f172a !important;
        opacity: 1 !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content .modal :is(.nx-field > label, .form-label, .text-muted) {
        color: var(--muted-foreground, #737373) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal-content,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal-content > form,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal-header,
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal-footer {
        background-color: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
        border-color: var(--border, #414344) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal-body {
        background-color: var(--muted, #202122) !important;
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal-body .nx-section-card {
        background-color: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
        border: 1px solid var(--border, #414344) !important;
        box-shadow: none !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal :is(.modal-title, .nx-section-title, .nx-field > div) {
        color: #f8fafc !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal .nx-field > div {
        background-color: #202122 !important;
        border-color: #414344 !important;
        -webkit-text-fill-color: #f8fafc !important;
        opacity: 1 !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal :is(.nx-field > label, .form-label, .text-muted) {
        color: var(--muted-foreground, #a3a3a3) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content .modal-header .btn-close {
        filter: invert(1) grayscale(1);
        opacity: .8;
    }
    body#nxApplication #nxRectilinearTheme table td:last-child .d-flex,
    body#nxApplication #nxRectilinearTheme table td:last-child [class*="actions"] {
        max-width: 100%;
        flex-wrap: wrap !important;
        gap: .35rem !important;
    }
    body#nxApplication #nxRectilinearTheme table .nx-icon-btn,
    body#nxApplication #nxRectilinearTheme table .btn-icon {
        flex: 0 0 auto;
        min-width: 2.125rem !important;
        width: 2.125rem !important;
        height: 2.125rem !important;
        padding: 0 !important;
    }
    body#nxApplication #nxRectilinearTheme .table-responsive:not(:has(thead th:nth-child(6))) > table,
    body#nxApplication #nxRectilinearTheme .nx-table-wrap:not(:has(thead th:nth-child(6))) > table {
        min-width: 100% !important;
    }
    body#nxApplication #nxRectilinearTheme .table-responsive:has(thead th:nth-child(6)):not(:has(thead th:nth-child(9))) > table,
    body#nxApplication #nxRectilinearTheme .nx-table-wrap:has(thead th:nth-child(6)):not(:has(thead th:nth-child(9))) > table {
        min-width: 760px !important;
        table-layout: fixed !important;
    }
    body#nxApplication #nxRectilinearTheme .table-responsive:has(thead th:nth-child(9)):not(:has(thead th:nth-child(12))) > table,
    body#nxApplication #nxRectilinearTheme .nx-table-wrap:has(thead th:nth-child(9)):not(:has(thead th:nth-child(12))) > table {
        min-width: 980px !important;
    }
    body#nxApplication #nxRectilinearTheme .table-responsive:has(thead th:nth-child(12)) > table,
    body#nxApplication #nxRectilinearTheme .nx-table-wrap:has(thead th:nth-child(12)) > table {
        min-width: 1200px !important;
    }
    /* Data-dense network inventories need stable semantic columns. They remain
       responsive through their wrapper instead of crushing values together. */
    body#nxApplication #nxRectilinearTheme #oltManagementPage .table-responsive > table,
    body#nxApplication #nxRectilinearTheme #oltManagementPage .nx-table-wrap > table {
        min-width: 1080px !important;
        table-layout: auto !important;
    }
    body#nxApplication #nxRectilinearTheme #ontDevicesPage .table-responsive > table,
    body#nxApplication #nxRectilinearTheme #ontDevicesPage .nx-table-wrap > table {
        min-width: 1000px !important;
        table-layout: auto !important;
    }
    body#nxApplication #nxRectilinearTheme #napManagementApp .nap-nodes-table,
    body#nxApplication #nxRectilinearTheme #napManagementApp .table-responsive > table:has(th:nth-child(8)) {
        min-width: 1080px !important;
        table-layout: auto !important;
    }
    body#nxApplication #nxRectilinearTheme #cgnatManagementApp .table-responsive > table:has(th:nth-child(9)),
    body#nxApplication #nxRectilinearTheme #cgnatManagementApp .nx-table-wrap > table:has(th:nth-child(9)) {
        min-width: 1180px !important;
        table-layout: auto !important;
    }
    /* Workflow tables contain long identifiers, subjects, assignees and action
       groups. Preserve semantic widths and scroll the wrapper instead of
       forcing those values into a fixed desktop grid. */
    body#nxApplication #nxRectilinearTheme #ticketsDataTable table {
        min-width: 1200px !important;
        table-layout: auto !important;
    }
    body#nxApplication #nxRectilinearTheme #workOrdersDataTable table {
        min-width: 1320px !important;
        table-layout: auto !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme table thead th {
        background: var(--table-head-surface, #f1f1ef) !important;
        color: var(--muted-foreground, #64748b) !important;
        box-shadow: inset 0 -1px 0 var(--border, #d5d5d2) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme table tbody td {
        background: var(--card, #fff) !important;
        color: var(--foreground, #171717) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme table thead th {
        background: var(--table-head-surface, #353738) !important;
        color: var(--muted-foreground, #a3a3a3) !important;
        box-shadow: inset 0 -1px 0 var(--border, #414344) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme table tbody td {
        background: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme table tbody tr:nth-child(even) td {
        background: var(--table-row-alt, #202122) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content table tbody tr:nth-child(odd) > td {
        background: var(--card, #fff) !important;
        color: var(--foreground, #171717) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme .main-content table tbody tr:nth-child(even) > td {
        background: var(--table-row-alt, #f7f7f5) !important;
        color: var(--foreground, #171717) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content table tbody tr:nth-child(odd) > td {
        background: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme .main-content table tbody tr:nth-child(even) > td {
        background: var(--table-row-alt, #202122) !important;
        color: var(--foreground, #f5f5f5) !important;
    }
    @media (max-width: 767.98px) {
        body#nxApplication #nxRectilinearTheme table thead th,
        body#nxApplication #nxRectilinearTheme .table thead th,
        body#nxApplication #nxRectilinearTheme table tbody td,
        body#nxApplication #nxRectilinearTheme .table tbody td {
            padding: .625rem .75rem !important;
        }
        body#nxApplication #nxRectilinearTheme .nx-table-footer {
            align-items: flex-start;
            flex-direction: column;
            gap: .75rem;
        }
        body#nxApplication #nxRectilinearTheme .nx-table-footer__controls {
            width: 100%;
            justify-content: space-between;
            flex-wrap: wrap;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        body#nxApplication #nxRectilinearTheme .form-switch .form-check-input {
            transition: none !important;
        }
    }

    /* Table actions use a single compact icon contract across both legacy
       Bootstrap modules and the Vue/Tailwind migration. */
    body#nxApplication #nxRectilinearTheme table .nx-table-icon-action {
        width: 2.25rem !important;
        min-width: 2.25rem !important;
        height: 2.25rem !important;
        min-height: 2.25rem !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: .5rem !important;
        line-height: 1 !important;
        vertical-align: middle;
    }
    body#nxApplication #nxRectilinearTheme table .nx-table-icon-action .nx-table-action-icon {
        display: inline-grid;
        place-items: center;
        width: 1rem;
        height: 1rem;
        font-size: .95rem;
        line-height: 1;
        pointer-events: none;
    }
    body#nxApplication #nxRectilinearTheme table td .nx-table-icon-action + .nx-table-icon-action {
        margin-left: .25rem;
    }
    body#nxApplication #nxRectilinearTheme table .nx-table-icon-action:not(.bg-red-600):not(.btn-danger):not(.btn-outline-danger) {
        color: var(--nx-primary, #2563eb) !important;
        background: var(--nx-surface, var(--card, #fff)) !important;
        border: 1px solid var(--nx-border, var(--border, #d7dee8)) !important;
        box-shadow: none !important;
    }
    body#nxApplication #nxRectilinearTheme table .nx-table-icon-action:not(.bg-red-600):not(.btn-danger):not(.btn-outline-danger):hover {
        background: color-mix(in srgb, var(--nx-primary, #2563eb) 8%, var(--nx-surface, #fff)) !important;
        border-color: color-mix(in srgb, var(--nx-primary, #2563eb) 45%, var(--nx-border, #d7dee8)) !important;
    }
    </style>

</head>

<body id="nxApplication">
<div id="nxTopLoader"></div>

<?php require BASE_PATH.'/app/UI/Views/layouts/sidebar.php'; ?>
<button type="button" class="sidebar-scrim" id="sidebarScrim" aria-label="Close navigation" aria-hidden="true"></button>

<div class="main-wrapper" id="nxRectilinearTheme">

    <?php require BASE_PATH.'/app/UI/Views/layouts/header.php'; ?>

    <div class="main-content" id="nxMainContent" data-nx-main-content>

        <?= $content ?>

    </div>

    <!-- <style>
        /* Final dark-mode surface normalization. Keep ordinary panels neutral grey,
           even when a module stylesheet supplies a legacy navy token. */
        html[data-theme="dark"] body#nxApplication,
        html[data-theme="dark"] body#nxApplication .main-wrapper,
        html[data-theme="dark"] body#nxApplication .main-content {
            /* background: #252f41 !important; */
            color: #f1f3f5 !important;
        }
        html[data-theme="dark"] body#nxApplication .main-content .card,
        html[data-theme="dark"] body#nxApplication .main-content [class*="-card"],
        html[data-theme="dark"] body#nxApplication .main-content [class*="-panel"] {
            /* background-color: #344055 !important; */
            background-image: none !important;
            border-color: #334155 !important;
            box-shadow: none !important;
        }
        /* Remove decorative all-caps eyebrow/badge labels from page content. */
        body#nxApplication .main-content [class*="-hero-badge"],
        body#nxApplication .main-content [class*="-eyebrow"] {
            display: none !important;
        }
        html[data-theme="dark"] body#nxApplication .main-content .table,
        html[data-theme="dark"] body#nxApplication .main-content .table thead,
        html[data-theme="dark"] body#nxApplication .main-content .table thead th {
            /* background-color: #182235 !important; */
            border-color: #334155 !important;
        }
        html[data-theme="dark"] body#nxApplication .main-content .table tbody tr:hover,
        html[data-theme="dark"] body#nxApplication .main-content .table tbody tr:hover td {
            /* background-color: #202d44 !important; */
        }
        html[data-theme="dark"] body#nxApplication #dashboardPage .dashboard-section,
        html[data-theme="dark"] body#nxApplication #dashboardPage .summary-strip,
        html[data-theme="dark"] body#nxApplication #dashboardPage .service-row,
        html[data-theme="dark"] body#nxApplication #dashboardPage .activity-table-wrap,
        html[data-theme="dark"] body#nxApplication #dashboardPage .traffic-chart-wrap {
            /* background-color: #344055 !important; */
            background-image: none !important;
            border-color: #334155 !important;
        }
        html[data-theme="dark"] body#nxApplication .sidebar,
        html[data-theme="dark"] body#nxApplication .topbar {
            /* background-color: #101827 !important; */
            background-image: none !important;
            border-color: #334155 !important;
            box-shadow: 0 2px 8px rgba(0,0,0,.28) !important;
        }
        html[data-theme="dark"] body#nxApplication .topbar,
        html[data-theme="dark"] body#nxApplication .sidebar {
            border-bottom-color: #334155 !important;
            border-right-color: #334155 !important;
        }
        /* Normalize later-loaded module descendants that otherwise reintroduce navy. */
        html[data-theme="dark"] body#nxApplication .main-content .table tbody tr,
        html[data-theme="dark"] body#nxApplication .main-content .table tbody td,
        html[data-theme="dark"] body#nxApplication .main-content .activity-table tbody tr,
        html[data-theme="dark"] body#nxApplication .main-content .activity-table tbody td,
        html[data-theme="dark"] body#nxApplication .main-content [class*="provisioning-"]:not(.btn),
        html[data-theme="dark"] body#nxApplication .main-content [class*="sp-step"],
        html[data-theme="dark"] body#nxApplication .main-content [class*="sp-modern"],
        html[data-theme="dark"] body#nxApplication .main-content .billing-tabs,
        html[data-theme="dark"] body#nxApplication .main-content .billing-tabs-wrap,
        html[data-theme="dark"] body#nxApplication .main-content .billing-workspace,
        html[data-theme="dark"] body#nxApplication .main-content [class*="service-setup"],
        html[data-theme="dark"] body#nxApplication .main-content [class*="billing-tab"],
        html[data-theme="dark"] body#nxApplication .main-content .nav-tabs,
        html[data-theme="dark"] body#nxApplication .main-content .nav-tabs .nav-link {
            /* background-color: #182235 !important; */
            background-image: none !important;
            border-color: #334155 !important;
        }
        html[data-theme="dark"] body#nxApplication .main-content .billing-tabs .active,
        html[data-theme="dark"] body#nxApplication .main-content .billing-tabs .is-active {
            /* background: #202d44 !important; */
            border-color: #475569 !important;
            color: #f1f3f5 !important;
        }
        html[data-theme="dark"] body#nxApplication .main-content .table tbody tr:nth-child(even),
        html[data-theme="dark"] body#nxApplication .main-content .table tbody tr:nth-child(odd) {
            /* background-color: #182235 !important; */
        }
        html[data-theme="dark"] body#nxApplication .main-content .topbar-search,
        html[data-theme="dark"] body#nxApplication .main-content .global-search,
        html[data-theme="dark"] body#nxApplication .topbar input,
        html[data-theme="dark"] body#nxApplication .topbar button {
            /* background-color: #202d44 !important; */
            border-color: transparent !important;
        }
        html[data-theme="dark"] body#nxApplication .main-content input,
        html[data-theme="dark"] body#nxApplication .main-content textarea,
        html[data-theme="dark"] body#nxApplication .main-content select,
        html[data-theme="dark"] body#nxApplication .main-content .form-control,
        html[data-theme="dark"] body#nxApplication .main-content .form-select {
            /* background-color: #202d44 !important; */
            border-color: #475569 !important;
            color: #f1f3f5 !important;
        }
        /* Temporarily remove decorative dark-mode fills; preserve the shell,
           controls, status badges, and action buttons. */
        html[data-theme="dark"] body#nxApplication .main-content .card,
        html[data-theme="dark"] body#nxApplication .main-content [class*="-card"],
        html[data-theme="dark"] body#nxApplication .main-content [class*="-panel"],
        html[data-theme="dark"] body#nxApplication .main-content .dashboard-section,
        html[data-theme="dark"] body#nxApplication .main-content .summary-strip,
        html[data-theme="dark"] body#nxApplication .main-content .service-row,
        html[data-theme="dark"] body#nxApplication .main-content .table,
        html[data-theme="dark"] body#nxApplication .main-content .table thead,
        html[data-theme="dark"] body#nxApplication .main-content .table tbody tr {
            background-color: transparent !important;
            background-image: none !important;
        }
        /* Dark panels use tonal separation rather than visible outlines. */
        html[data-theme="dark"] body#nxApplication .main-content .card,
        html[data-theme="dark"] body#nxApplication .main-content [class*="-card"],
        html[data-theme="dark"] body#nxApplication .main-content [class*="-panel"],
        html[data-theme="dark"] body#nxApplication #dashboardPage .dashboard-section,
        html[data-theme="dark"] body#nxApplication #dashboardPage .summary-strip {
            border-color: transparent !important;
            box-shadow: 0 1px 3px rgba(0,0,0,.28), 0 6px 14px rgba(0,0,0,.16) !important;
        }
        html[data-theme="dark"] body#nxApplication .main-content .card,
        html[data-theme="dark"] body#nxApplication .main-content [class*="-card"],
        html[data-theme="dark"] body#nxApplication .main-content [class*="-panel"],
        html[data-theme="dark"] body#nxApplication #dashboardPage .dashboard-section,
        html[data-theme="dark"] body#nxApplication #dashboardPage .summary-strip {
            background: transparent !important;
            background-image: none !important;
        }
        html[data-theme="dark"] body#nxApplication #dashboardPage .summary-item,
        html[data-theme="dark"] body#nxApplication #dashboardPage .service-row,
        html[data-theme="dark"] body#nxApplication #dashboardPage .activity-table-wrap {
            background: transparent !important;
            background-image: none !important;
        }
    </style> -->

    <?php require BASE_PATH.'/app/UI/Views/layouts/footer.php'; ?>

</div>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/chart/chart.umd.min.js"></script>
<script src="/assets/js/sweetalert2.all.min.js"></script>
<script src="/assets/leaflet/leaflet.js"></script>
<script>
    (function () {
        var nativeFetch = window.fetch;
        var tokenNode = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = tokenNode ? tokenNode.getAttribute('content') : '';

        if (!nativeFetch || !csrfToken) return;

        window.fetch = function (input, init) {
            init = init || {};
            var method = String(init.method || 'GET').toUpperCase();

            if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                var headers = new Headers(init.headers || {});

                if (!headers.has('X-CSRF-Token')) {
                    headers.set('X-CSRF-Token', csrfToken);
                }

                init.headers = headers;
            }

            return nativeFetch.call(this, input, init);
        };
    })();
</script>
<script>
    (function () {
        'use strict';

        var actionIcons = [
            [/(delete|remove|void|cancel)/, 'bi-trash3'],
            [/(revoke|disable|deactivate|disconnect)/, 'bi-slash-circle'],
            [/(approve|confirm|complete|resolve|activate|enable)/, 'bi-check-lg'],
            [/(reject|fail|decline)/, 'bi-x-lg'],
            [/(edit|update|manage access|manage|configure|settings)/, 'bi-pencil-square'],
            [/(view|inspect|details|show|open)/, 'bi-eye'],
            [/(assign|dispatch)/, 'bi-person-check'],
            [/(retry|refresh|reload|sync|check acs)/, 'bi-arrow-clockwise'],
            [/(start|run|execute|provision|apply|push)/, 'bi-play-fill'],
            [/(stop|suspend|pause)/, 'bi-pause-fill'],
            [/(print|receipt|invoice)/, 'bi-printer'],
            [/(download|export)/, 'bi-download'],
            [/(upload|import)/, 'bi-upload'],
            [/(copy|duplicate)/, 'bi-copy'],
            [/(reply|respond|message|note)/, 'bi-chat-left-text'],
            [/(schedule|calendar)/, 'bi-calendar-event'],
            [/(payment|pay)/, 'bi-credit-card'],
            [/(password)/, 'bi-key'],
            [/\bports?\b/, 'bi-ethernet'],
            [/\bprofiles?\b/, 'bi-layers'],
            [/(history|audit|logs)/, 'bi-clock-history'],
            [/(save)/, 'bi-floppy'],
            [/(add|create|new)/, 'bi-plus-lg'],
            [/(reset)/, 'bi-arrow-counterclockwise'],
            [/(map|location)/, 'bi-geo-alt'],
            [/(test)/, 'bi-activity']
        ];

        function enhanceTableActions(scope) {
            var root = scope && scope.querySelectorAll ? scope : document;
            root.querySelectorAll('table button:not([role="switch"]), table td:last-child a[href], table a.btn, table a[role="button"]').forEach(function (control) {
                if (control.matches('.page-link, [data-bs-toggle="dropdown"], [role="tab"]')) return;

                var currentIcon = control.querySelector('.nx-table-action-icon');
                if (currentIcon && control.textContent.trim() === '') return;

                var label = String(control.getAttribute('aria-label') || control.getAttribute('title') || control.textContent || '')
                    .replace(/\s+/g, ' ')
                    .trim();
                if (!label) return;

                var key = label.toLowerCase().replace(/[.…]+$/g, '').trim();
                var icon = 'bi-three-dots';
                for (var i = 0; i < actionIcons.length; i += 1) {
                    if (actionIcons[i][0].test(key)) {
                        icon = actionIcons[i][1];
                        break;
                    }
                }

                control.setAttribute('aria-label', label);
                control.setAttribute('title', label);
                control.classList.add('nx-table-icon-action');
                control.innerHTML = '<i class="bi ' + icon + ' nx-table-action-icon" aria-hidden="true"></i>';
            });
        }

        function schedule(scope) {
            window.requestAnimationFrame(function () { enhanceTableActions(scope || document); });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { schedule(document); }, { once: true });
        } else {
            schedule(document);
        }

        document.addEventListener('nx:page-load', function (event) {
            schedule(event.detail && event.detail.content ? event.detail.content : document);
        });

        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i += 1) {
                if (mutations[i].addedNodes.length || mutations[i].type === 'characterData') {
                    schedule(document);
                    break;
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true, characterData: true });
    })();
</script>
    <script src="/assets/js/nx.js?v=13"></script>

</body>
</html>
