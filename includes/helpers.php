<?php
/**
 * includes/helpers.php
 * Small reusable functions: escaping, redirects, flash messages,
 * CSRF protection, formatting, search/sort/pagination.
 */

/** Escape text before printing it in HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a URL inside this application, e.g. url('modules/farmers.php') */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// ---------------------------------------------------------------- flash ----
/** Store a one-time message (shown on the next page). type: success|danger|warning|info */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ----------------------------------------------------------------- CSRF ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stop the request if the form token is missing or wrong. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        flash('danger', 'Your session expired or the form was invalid. Please try again.');
        redirect('dashboard.php');
    }
}

// ----------------------------------------------------------- formatting ----
function fmt_date(?string $date): string
{
    return $date ? date('M d, Y', strtotime($date)) : '—';
}

function fmt_datetime(?string $dt): string
{
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '—';
}

function fmt_num($n, int $decimals = 2): string
{
    return number_format((float)$n, $decimals);
}

function fmt_money($n): string
{
    return '₱' . number_format((float)$n, 2);
}

/** Coloured badge for a planting status name. */
function status_badge(string $status): string
{
    $map = ['Planted' => 'info', 'Growing' => 'success', 'Harvested' => 'primary', 'Failed' => 'danger'];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $class . '">' . e($status) . '</span>';
}

// ------------------------------------------------------------- lookups ----
/**
 * Read a lookup table as [id => label] for a dropdown.
 * (table and column names are written by us in code, never by the user)
 */
function lookup(string $table, string $idCol, string $nameCol): array
{
    $rows = db()->query("SELECT $idCol AS id, $nameCol AS label FROM $table ORDER BY $nameCol")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['id']] = $r['label'];
    }
    return $out;
}

/** Run any query that returns (id, label) rows and give back [id => label]. */
function choices(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[$r['id']] = $r['label'];
    }
    return $out;
}

/** Read crops as [crop_id => "Crop name — Variety"] for dropdowns. */
function crop_choices(): array
{
    return choices(
        "SELECT c.crop_id AS id, ct.crop_name || ' — ' || c.variety AS label
           FROM crop c
           JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
          ORDER BY ct.crop_name, c.variety"
    );
}

// ------------------------------------------- search / sort / pagination ----
/** Current query string with some values changed.  qs(['page' => 2]) */
function qs(array $override = []): string
{
    $params = array_merge($_GET, $override);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '?';
}

/** Add a "match any of these columns" search condition. */
function add_search(array &$where, array &$params, array $columns, string $term): void
{
    $term = trim($term);
    if ($term === '') {
        return;
    }
    $like = '%' . addcslashes($term, '%_\\') . '%';
    $parts = [];
    foreach ($columns as $col) {
        $parts[] = "$col ILIKE ?";
        $params[] = $like;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}

/**
 * Run a paginated, sortable list query.
 *
 * @param string $select      columns to select
 * @param string $from        FROM + JOINs
 * @param array  $where       list of SQL conditions (with ? placeholders)
 * @param array  $params      values for the placeholders
 * @param array  $sortMap     ['url_key' => 'sql expression'] (whitelist!)
 * @return array{rows:array,total:int,page:int,pages:int,sort:string,dir:string,perPage:int}
 */
function list_query(string $select, string $from, array $where, array $params,
                    array $sortMap, string $defaultSort, string $defaultDir = 'asc',
                    int $perPage = 10): array
{
    $sort = $_GET['sort'] ?? $defaultSort;
    if (!isset($sortMap[$sort])) {
        $sort = $defaultSort;
    }
    $dir = strtolower($_GET['dir'] ?? $defaultDir) === 'desc' ? 'DESC' : 'ASC';
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $st = db()->prepare("SELECT COUNT(*) FROM $from $whereSql");
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT $select FROM $from $whereSql ORDER BY {$sortMap[$sort]} $dir, 1 $dir LIMIT $perPage OFFSET $offset";
    $st = db()->prepare($sql);
    $st->execute($params);

    return [
        'rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'sort' => $sort, 'dir' => strtolower($dir), 'perPage' => $perPage,
    ];
}

/** Clickable, sortable table heading. */
function sort_th(string $label, string $key, array $list): string
{
    $isCurrent = $list['sort'] === $key;
    $nextDir = ($isCurrent && $list['dir'] === 'asc') ? 'desc' : 'asc';
    $icon = '';
    if ($isCurrent) {
        $icon = $list['dir'] === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
    }
    return '<th scope="col"><a class="text-decoration-none text-reset" href="'
         . e(qs(['sort' => $key, 'dir' => $nextDir, 'page' => null])) . '">' . e($label) . $icon . '</a></th>';
}

/** Page number buttons. */
function render_pagination(array $list): void
{
    if ($list['pages'] <= 1) {
        return;
    }
    $page = $list['page'];
    $pages = $list['pages'];
    $from = max(1, $page - 2);
    $to = min($pages, $page + 2);

    echo '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">';
    $item = function (string $label, int $p, bool $disabled = false, bool $active = false) {
        echo '<li class="page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '') . '">'
           . '<a class="page-link" href="' . e(qs(['page' => $p])) . '">' . $label . '</a></li>';
    };
    $item('&laquo;', $page - 1, $page <= 1);
    if ($from > 1) {
        $item('1', 1);
        if ($from > 2) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($i = $from; $i <= $to; $i++) {
        $item((string)$i, $i, false, $i === $page);
    }
    if ($to < $pages) {
        if ($to < $pages - 1) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $item((string)$pages, $pages);
    }
    $item('&raquo;', $page + 1, $page >= $pages);
    echo '</ul></nav>';
}

/** "Showing 1-10 of 35 records" */
function list_summary(array $list): string
{
    if ($list['total'] === 0) {
        return 'No records found';
    }
    $start = ($list['page'] - 1) * $list['perPage'] + 1;
    $end = min($list['total'], $list['page'] * $list['perPage']);
    return "Showing {$start}–{$end} of {$list['total']} record" . ($list['total'] === 1 ? '' : 's');
}


function db_error_message(PDOException $ex, string $deleteBlockedMsg = 'This record is still used by other records.'): string
{
    $code = $ex->getCode();
    error_log('[AgriSystem] DB error ' . $code . ': ' . $ex->getMessage());
    switch ($code) {
        case '23503': return $deleteBlockedMsg;
        case '23505': return 'A record with the same value already exists. Please use a different value.';
        case '23514': return 'One of the values is not allowed (it breaks a database rule). Please check your input.';
        case '23502': return 'A required value is missing.';
        case '22003': return 'A number is too large to be saved.';
        default:      return 'The database could not complete the operation. Please try again.';
    }
}
