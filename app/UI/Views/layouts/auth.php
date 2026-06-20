<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($branding['client_name'] ?? 'Portal') ?></title>

<link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="/assets/icons/bootstrap-icons.css">

</head>

<body>

<?= $content ?>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/js/sweetalert2.all.min.js"></script>

</body>
</html>
