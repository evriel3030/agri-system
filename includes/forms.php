<?php

function f_input(array $o, array $errors = []): void
{
    $name = $o['name'];
    $type = $o['type'] ?? 'text';
    $col = $o['col'] ?? 'col-md-6';
    $err = $errors[$name] ?? null;
    $attrs = '';
    foreach (($o['attrs'] ?? []) as $k => $v) {
        $attrs .= ' ' . e($k) . '="' . e($v) . '"';
    }
    echo '<div class="' . e($col) . '">';
    echo '<label for="' . e($name) . '" class="form-label">' . e($o['label'])
       . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<input type="' . e($type) . '" class="form-control' . ($err ? ' is-invalid' : '') . '" id="' . e($name)
       . '" name="' . e($name) . '" value="' . e($o['value'] ?? '') . '"'
       . (!empty($o['required']) ? ' required' : '') . $attrs . '>';
    if ($err) {
        echo '<div class="invalid-feedback">' . e($err) . '</div>';
    }
    if (!empty($o['help'])) {
        echo '<div class="form-text">' . e($o['help']) . '</div>';
    }
    echo '</div>';
}

/**
 * Dropdown. The user SEES the label, the database stores the ID.
 * Options: name, label, value, choices [id => label], required, col, placeholder,
 *          optAttrs [id => ['data-days' => 100]]
 */
function f_select(array $o, array $errors = []): void
{
    $name = $o['name'];
    $col = $o['col'] ?? 'col-md-6';
    $err = $errors[$name] ?? null;
    $selected = (string)($o['value'] ?? '');
    echo '<div class="' . e($col) . '">';
    echo '<label for="' . e($name) . '" class="form-label">' . e($o['label'])
       . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<select class="form-select' . ($err ? ' is-invalid' : '') . '" id="' . e($name) . '" name="' . e($name) . '"'
       . (!empty($o['required']) ? ' required' : '') . '>';
    echo '<option value="">' . e($o['placeholder'] ?? '-- Select --') . '</option>';
    foreach ($o['choices'] as $id => $label) {
        $extra = '';
        foreach (($o['optAttrs'][$id] ?? []) as $k => $v) {
            $extra .= ' ' . e($k) . '="' . e($v) . '"';
        }
        echo '<option value="' . e($id) . '"' . ((string)$id === $selected ? ' selected' : '') . $extra . '>'
           . e($label) . '</option>';
    }
    echo '</select>';
    if ($err) {
        echo '<div class="invalid-feedback">' . e($err) . '</div>';
    }
    if (!empty($o['help'])) {
        echo '<div class="form-text">' . e($o['help']) . '</div>';
    }
    echo '</div>';
}

/** A small dropdown used in search/filter bars (no label, "All" option). */
function f_filter_select(string $name, string $allLabel, array $choices, string $label = ''): void
{
    $selected = (string)($_GET[$name] ?? '');
    echo '<div class="col-6 col-md-auto">';
    if ($label !== '') {
        echo '<label class="form-label small mb-1" for="f_' . e($name) . '">' . e($label) . '</label>';
    }
    echo '<select name="' . e($name) . '" id="f_' . e($name) . '" class="form-select form-select-sm" aria-label="' . e($allLabel) . '">';
    echo '<option value="">' . e($allLabel) . '</option>';
    foreach ($choices as $id => $text) {
        echo '<option value="' . e($id) . '"' . ((string)$id === $selected ? ' selected' : '') . '>' . e($text) . '</option>';
    }
    echo '</select></div>';
}

/** Form buttons: Save / Cancel */
function f_buttons(string $cancelUrl, string $saveLabel = 'Save'): void
{
    echo '<div class="col-12 d-flex gap-2 mt-4">'
       . '<button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> ' . e($saveLabel) . '</button>'
       . '<a href="' . e($cancelUrl) . '" class="btn btn-outline-secondary">Cancel</a></div>';
}

/** Row action buttons (View / Edit / Delete) for tables. */
function row_actions(string $page, int $id, string $deleteName): void
{
    echo '<div class="btn-group btn-group-sm" role="group" aria-label="Actions">';
    echo '<a class="btn btn-outline-secondary" title="View" href="' . e(url("modules/$page.php?action=view&id=$id")) . '"><i class="bi bi-eye"></i><span class="visually-hidden">View</span></a>';
    echo '<a class="btn btn-outline-primary" title="Edit" href="' . e(url("modules/$page.php?action=edit&id=$id")) . '"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>';
    if (is_admin()) {
        echo '<button type="button" class="btn btn-outline-danger js-delete" title="Delete" data-action="'
           . e(url("modules/$page.php?action=delete&id=$id")) . '" data-name="' . e($deleteName) . '">'
           . '<i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>';
    }
    echo '</div>';
}

/** Page header with title and an optional "Add new" button. */
function page_title(string $title, ?string $addUrl = null, string $addLabel = 'Add New'): void
{
    echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">'
       . '<h1 class="h3 mb-0">' . e($title) . '</h1>';
    if ($addUrl) {
        echo '<a href="' . e($addUrl) . '" class="btn btn-success"><i class="bi bi-plus-lg"></i> ' . e($addLabel) . '</a>';
    }
    echo '</div>';
}
