<?php
declare(strict_types=1);

/** Tiny form-rendering helpers: bootstrap markup + old values + inline errors. */
function form_set(array $values, array $errors = []): void { $GLOBALS['__form'] = ['v' => $values, 'e' => $errors]; }
function fv(string $k, mixed $d = ''): mixed { return $GLOBALS['__form']['v'][$k] ?? $d; }
function fe(string $k): ?string { return $GLOBALS['__form']['e'][$k] ?? null; }

function f_input(string $name, string $label, array $o = []): void
{
    $type = $o['type'] ?? 'text';
    $col = $o['col'] ?? 'col-md-6';
    $err = fe($name);
    $id = 'f_' . $name;
    echo '<div class="' . e($col) . '"><label class="form-label" for="' . e($id) . '">' . e($label)
        . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<input class="form-control' . ($err ? ' is-invalid' : '') . '" id="' . e($id) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e(fv($name, $o['default'] ?? '')) . '"';
    foreach (['required', 'readonly', 'autofocus'] as $b) {
        if (!empty($o[$b])) echo ' ' . $b;
    }
    foreach (['maxlength', 'minlength', 'placeholder', 'step', 'min', 'max', 'pattern', 'inputmode', 'autocomplete'] as $a) {
        if (isset($o[$a])) echo ' ' . $a . '="' . e($o[$a]) . '"';
    }
    echo '>';
    if ($err) echo '<div class="invalid-feedback">' . e($err) . '</div>';
    if (!empty($o['help'])) echo '<div class="form-text">' . e($o['help']) . '</div>';
    echo '</div>';
}

function f_select(string $name, string $label, array $options, array $o = []): void
{
    $col = $o['col'] ?? 'col-md-6';
    $err = fe($name);
    $id = 'f_' . $name;
    $cur = (string)fv($name, $o['default'] ?? '');
    echo '<div class="' . e($col) . '"><label class="form-label" for="' . e($id) . '">' . e($label)
        . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<select class="form-select' . ($err ? ' is-invalid' : '') . '" id="' . e($id) . '" name="' . e($name) . '"' . (!empty($o['required']) ? ' required' : '') . '>';
    if (array_key_exists('blank', $o)) echo '<option value="">' . e($o['blank']) . '</option>';
    foreach ($options as $v => $l) {
        echo '<option value="' . e($v) . '"' . selected($cur, (string)$v) . '>' . e($l) . '</option>';
    }
    echo '</select>';
    if ($err) echo '<div class="invalid-feedback">' . e($err) . '</div>';
    if (!empty($o['help'])) echo '<div class="form-text">' . e($o['help']) . '</div>';
    echo '</div>';
}

function f_textarea(string $name, string $label, array $o = []): void
{
    $col = $o['col'] ?? 'col-12';
    $err = fe($name);
    $id = 'f_' . $name;
    echo '<div class="' . e($col) . '"><label class="form-label" for="' . e($id) . '">' . e($label)
        . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<textarea class="form-control' . ($err ? ' is-invalid' : '') . '" id="' . e($id) . '" name="' . e($name) . '" rows="' . (int)($o['rows'] ?? 3) . '"'
        . (isset($o['maxlength']) ? ' maxlength="' . (int)$o['maxlength'] . '"' : '') . (!empty($o['required']) ? ' required' : '') . '>'
        . e(fv($name, '')) . '</textarea>';
    if ($err) echo '<div class="invalid-feedback">' . e($err) . '</div>';
    echo '</div>';
}
