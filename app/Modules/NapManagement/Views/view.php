<div class="container-fluid nx-page">

<div class="card border-0 shadow-sm mb-3">
<div class="card-body">
<h5 class="fw-semibold mb-0">NAP Visualization</h5>
<small class="text-muted">Splitters and Ports</small>
</div>
</div>

<?php foreach($splitters as $s): ?>

<div class="card border-0 shadow-sm mb-4">

<div class="card-body">

<h6 class="fw-semibold mb-3">
<?= htmlspecialchars($s['splitter_label']) ?> 
<span class="text-muted">(<?= htmlspecialchars((string)$s['splitter_ratio'], ENT_QUOTES, 'UTF-8') ?>)</span>
</h6>

<div class="d-flex flex-wrap gap-2">

<?php foreach($s['ports'] as $p): ?>

<?php
$status = $p['status'];

$color = match($status){
    'AVAILABLE' => '#22c55e',
    'USED' => '#ef4444',
    'RESERVED' => '#f59e0b',
    'FAULTY' => '#374151',
    default => '#9ca3af'
};
?>

<div 
style="
width:40px;
height:40px;
border-radius:3px;
display:flex;
align-items:center;
justify-content:center;
color:white;
font-size:12px;
font-weight:600;
background:<?= $color ?>;
cursor:pointer;
"
title="Port <?= htmlspecialchars((string)$p['port_number'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8') ?>)"
>
<?= htmlspecialchars((string)$p['port_number'], ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endforeach; ?>

</div>

</div>

</div>

<?php endforeach; ?>

</div>
