<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>ISP-in-a-Box</title>

    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/nx.css">
    <link rel="stylesheet" href="/assets/leaflet/leaflet.css">

</head>

<body>

<?php require BASE_PATH.'/app/UI/Views/layouts/sidebar.php'; ?>

<div class="main-wrapper">

    <?php require BASE_PATH.'/app/UI/Views/layouts/header.php'; ?>

    <div class="main-content">

        <?= $content ?>

    </div>

    <?php require BASE_PATH.'/app/UI/Views/layouts/footer.php'; ?>

</div>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/chart/chart.umd.min.js"></script>
<script src="/assets/js/sweetalert2.all.min.js"></script>
<script src="/assets/leaflet/leaflet.js"></script>
<script src="/assets/js/nx.js"></script>


</body>
</html>