<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/app/UI/Views/layouts/app.php');
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$check(str_contains($layout, '.form-switch .form-check-input:checked'), 'Shared checked switch styling is missing.');
$check(str_contains($layout, 'background-position: right center'), 'Checked switches do not move the thumb to the right.');
$check(str_contains($layout, 'transition: background-position'), 'Switch movement is not animated.');
$check(str_contains($layout, ':focus-visible') && str_contains($layout, ':disabled'), 'Switch focus or disabled styling is missing.');

$switchCount = 0;
foreach (glob($root . '/app/Modules/*/Views/*.php') ?: [] as $view) {
    $source = file_get_contents($view);
    $switchCount += substr_count($source, 'form-switch');
    if (!str_contains($source, 'form-switch')) continue;
    preg_match_all('/form-switch[^>]*>.*?<input\b[^>]*class="[^"]*form-check-input[^"]*"[^>]*>/is', $source, $inputs);
    foreach ($inputs[0] ?? [] as $input) {
        if (str_contains($input, 'type="checkbox"')) {
            $check(str_contains($input, 'role="switch"'), basename(dirname($view)) . ' has a switch without role="switch".');
        }
    }
}
$check($switchCount === 8, 'Unexpected switch inventory; update the global toggle contract when switches are added or removed.');

if ($failures) {
    fwrite(STDERR, "Global toggle contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "global_toggle_contract=PASS switches={$switchCount}\n";
