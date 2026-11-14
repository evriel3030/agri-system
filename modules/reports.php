<?php

require __DIR__ . '/../includes/bootstrap.php';
require_login();

$db = db();
$self = url('modules/reports.php');


$catalog = [
    'farmers_farms'   => ['title' => 'Farmers and Their Farms',        'icon' => 'bi-people',
                          'filters' => ['q', 'address', 'date'], 'dateLabel' => 'Registered'],
    'crops_per_farm'  => ['title' => 'Crops Planted per Farm',         'icon' => 'bi-geo-alt',
                          'filters' => ['farm_id', 'category_id', 'date'], 'dateLabel' => 'Planted'],
    'planting_status' => ['title' => 'Planting Records by Status',     'icon' => 'bi-database-add',
                          'filters' => ['status_id', 'date'], 'dateLabel' => 'Planted'],
    'harvests'        => ['title' => 'Harvest Records',                'icon' => 'bi-basket',
                          'filters' => ['quality_grade_id', 'crop_id', 'date'], 'dateLabel' => 'Harvested'],
    'total_quantity'  => ['title' => 'Total Harvested Quantity',       'icon' => 'bi-box-seam',
                          'filters' => ['category_id', 'date'], 'dateLabel' => 'Harvested'],
    'harvest_value'   => ['title' => 'Estimated Harvest Value',        'icon' => 'bi-cash-coin',
                          'filters' => ['crop_id', 'date'], 'dateLabel' => 'Harvested'],
];

$key = $_GET['report'] ?? 'farmers_farms';
if (!isset($catalog[$key])) {
    $key = 'farmers_farms';
}
$cfg = $catalog[$key];

// ------------------------------------------------------------ read filters
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$filterError = null;

$validDate = function (string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
};
if ($dateFrom !== '' && !$validDate($dateFrom)) {
    $filterError = 'The start date is not a valid date.';
}
if ($dateTo !== '' && !$validDate($dateTo)) {
    $filterError = 'The end date is not a valid date.';
}
if (!$filterError && $dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $filterError = 'The start date cannot be later than the end date.';
}

$where = [];
$params = [];
/** Add "col = ?" when the GET value is a whole number */
$addId = function (string $get, string $col) use (&$where, &$params) {
    if (($_GET[$get] ?? '') !== '' && ctype_digit((string)$_GET[$get])) {
        $where[] = "$col = ?";
        $params[] = (int)$_GET[$get];
    }
};
/** Add the date range on a column */
$addDates = function (string $col) use (&$where, &$params, $dateFrom, $dateTo, $filterError) {
    if ($filterError) {
        return;
    }
    if ($dateFrom !== '') { $where[] = "$col >= ?"; $params[] = $dateFrom; }
    if ($dateTo !== '')   { $where[] = "$col <= ?"; $params[] = $dateTo; }
};
$whereSql = function () use (&$where) {
    return $where ? 'WHERE ' . implode(' AND ', $where) : '';
};

// ------------------------------------------------------------- build report
$columns = [];      // key => [label, type]   type: text|int|num|money|date
$rows = [];
$totals = [];       // key => value (shown in the footer row)
$summary = [];      // label => text (shown above the table)

if (!$filterError) {
    switch ($key) {
        case 'farmers_farms':
            add_search($where, $params, ['fr.first_name', 'fr.last_name', "fr.first_name || ' ' || fr.last_name", 'fm.farm_name'], $_GET['q'] ?? '');
            if (($_GET['address'] ?? '') !== '') { $where[] = 'fr.address = ?'; $params[] = $_GET['address']; }
            $addDates('fr.date_registered');
            $columns = ['farmer' => ['Farmer', 'text'], 'contact' => ['Contact', 'text'], 'address' => ['Address', 'text'],
                        'farm' => ['Farm', 'text'], 'location' => ['Farm Location', 'text'], 'area' => ['Area (ha)', 'num'],
                        'soil' => ['Soil', 'text'], 'irrigation' => ['Irrigation', 'text']];
            $st = $db->prepare(
                "SELECT fr.last_name || ', ' || fr.first_name AS farmer, fr.contact_number AS contact, fr.address,
                        fm.farm_name AS farm, fm.location, fm.area_hectares AS area,
                        s.soil_type_name AS soil, i.irrigation_type_name AS irrigation
                   FROM farmer fr
                   LEFT JOIN farm fm ON fm.farmer_id = fr.farmer_id
                   LEFT JOIN soil_type s ON s.soil_type_id = fm.soil_type_id
                   LEFT JOIN irrigation_type i ON i.irrigation_type_id = fm.irrigation_type_id
                  {$whereSql()}
                  ORDER BY fr.last_name, fr.first_name, fm.farm_name"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            $totals = ['farm' => count(array_filter(array_column($rows, 'farm'))) . ' farms',
                       'area' => array_sum(array_column($rows, 'area'))];
            break;

        case 'crops_per_farm':
            $addId('farm_id', 'p.farm_id');
            $addId('category_id', 'ct.category_id');
            $addDates('p.planting_date');
            $columns = ['farm' => ['Farm', 'text'], 'owner' => ['Owner', 'text'], 'crop' => ['Crop', 'text'],
                        'variety' => ['Variety', 'text'], 'category' => ['Category', 'text'],
                        'plantings' => ['No. of Plantings', 'int'], 'area' => ['Total Area Planted (ha)', 'num']];
            $st = $db->prepare(
                "SELECT f.farm_name AS farm, fr.last_name || ', ' || fr.first_name AS owner, ct.crop_name AS crop,
                        c.variety, cc.category_name AS category, COUNT(*) AS plantings, SUM(p.area_planted) AS area
                   FROM planting p
                   JOIN farm f ON f.farm_id = p.farm_id
                   JOIN farmer fr ON fr.farmer_id = f.farmer_id
                   JOIN crop c ON c.crop_id = p.crop_id
                   JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                   JOIN crop_category cc ON cc.category_id = ct.category_id
                  {$whereSql()}
                  GROUP BY f.farm_id, f.farm_name, fr.last_name, fr.first_name, c.crop_id, ct.crop_name, c.variety, cc.category_name
                  ORDER BY f.farm_name, ct.crop_name"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            $totals = ['plantings' => array_sum(array_column($rows, 'plantings')), 'area' => array_sum(array_column($rows, 'area'))];
            break;

        case 'planting_status':
            $addId('status_id', 'p.status_id');
            $addDates('p.planting_date');
            $columns = ['status' => ['Status', 'text'], 'farm' => ['Farm', 'text'], 'crop' => ['Crop', 'text'],
                        'planted' => ['Planting Date', 'date'], 'area' => ['Area (ha)', 'num'], 'expected' => ['Expected Harvest', 'date']];
            $st = $db->prepare(
                "SELECT s.status_name AS status, f.farm_name AS farm, ct.crop_name || ' (' || c.variety || ')' AS crop,
                        p.planting_date AS planted, p.area_planted AS area, p.expected_harvest_date AS expected
                   FROM planting p
                   JOIN farm f ON f.farm_id = p.farm_id
                   JOIN crop c ON c.crop_id = p.crop_id
                   JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                   JOIN planting_status s ON s.status_id = p.status_id
                  {$whereSql()}
                  ORDER BY s.status_id, p.planting_date, p.planting_id"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            foreach ($rows as $r) {
                $summary[$r['status']] = ($summary[$r['status']] ?? 0) + 1;
            }
            $totals = ['status' => count($rows) . ' records', 'area' => array_sum(array_column($rows, 'area'))];
            break;

        case 'harvests':
            $addId('quality_grade_id', 'h.quality_grade_id');
            $addId('crop_id', 'p.crop_id');
            $addDates('h.harvest_date');
            $columns = ['date' => ['Harvest Date', 'date'], 'farm' => ['Farm', 'text'], 'crop' => ['Crop', 'text'],
                        'qty' => ['Quantity (kg)', 'num'], 'price' => ['Price per kg', 'money'], 'grade' => ['Quality Grade', 'text'],
                        'remarks' => ['Remarks', 'text']];
            $st = $db->prepare(
                "SELECT h.harvest_date AS date, f.farm_name AS farm, ct.crop_name || ' (' || c.variety || ')' AS crop,
                        h.quantity_kg AS qty, h.selling_price_per_kg AS price, g.grade_name AS grade, r.remark_description AS remarks
                   FROM harvest h
                   JOIN planting p ON p.planting_id = h.planting_id
                   JOIN farm f ON f.farm_id = p.farm_id
                   JOIN crop c ON c.crop_id = p.crop_id
                   JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                   JOIN quality_grade g ON g.quality_grade_id = h.quality_grade_id
                   JOIN harvest_remark r ON r.remark_id = h.remark_id
                  {$whereSql()}
                  ORDER BY h.harvest_date, h.harvest_id"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            $totals = ['date' => count($rows) . ' records', 'qty' => array_sum(array_column($rows, 'qty'))];
            break;

        case 'total_quantity':
            $addId('category_id', 'ct.category_id');
            $addDates('h.harvest_date');
            $columns = ['crop' => ['Crop', 'text'], 'variety' => ['Variety', 'text'], 'category' => ['Category', 'text'],
                        'harvests' => ['No. of Harvests', 'int'], 'total' => ['Total Quantity (kg)', 'num']];
            $st = $db->prepare(
                "SELECT ct.crop_name AS crop, c.variety, cc.category_name AS category,
                        COUNT(h.harvest_id) AS harvests, SUM(h.quantity_kg) AS total
                   FROM harvest h
                   JOIN planting p ON p.planting_id = h.planting_id
                   JOIN crop c ON c.crop_id = p.crop_id
                   JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                   JOIN crop_category cc ON cc.category_id = ct.category_id
                  {$whereSql()}
                  GROUP BY c.crop_id, ct.crop_name, c.variety, cc.category_name
                  ORDER BY total DESC, ct.crop_name"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            $totals = ['crop' => 'GRAND TOTAL', 'harvests' => array_sum(array_column($rows, 'harvests')), 'total' => array_sum(array_column($rows, 'total'))];
            break;

        case 'harvest_value':
            $addId('crop_id', 'p.crop_id');
            $addDates('h.harvest_date');
            $columns = ['date' => ['Harvest Date', 'date'], 'farm' => ['Farm', 'text'], 'crop' => ['Crop', 'text'],
                        'qty' => ['Quantity (kg)', 'num'], 'price' => ['Price per kg', 'money'], 'value' => ['Estimated Value', 'money']];
            $st = $db->prepare(
                "SELECT h.harvest_date AS date, f.farm_name AS farm, ct.crop_name || ' (' || c.variety || ')' AS crop,
                        h.quantity_kg AS qty, h.selling_price_per_kg AS price, (h.quantity_kg * h.selling_price_per_kg) AS value
                   FROM harvest h
                   JOIN planting p ON p.planting_id = h.planting_id
                   JOIN farm f ON f.farm_id = p.farm_id
                   JOIN crop c ON c.crop_id = p.crop_id
                   JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                  {$whereSql()}
                  ORDER BY h.harvest_date, h.harvest_id"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            $totals = ['date' => 'GRAND TOTAL', 'qty' => array_sum(array_column($rows, 'qty')), 'value' => array_sum(array_column($rows, 'value'))];
            break;
    }
}

/** Format a value for the screen by its column type. */
function report_cell($value, string $type): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    switch ($type) {
        case 'date':  return fmt_date($value);
        case 'num':   return fmt_num($value);
        case 'int':   return number_format((int)$value);
        case 'money': return fmt_money($value);
        default:      return (string)$value;
    }
}

// ------------------------------------------------------------- CSV export
if (($_GET['export'] ?? '') === 'csv' && !$filterError) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $key . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 correctly
    // Cells that start with = + - @ could run as formulas in Excel, so they get a leading apostrophe.
    $safe = function ($v) {
        $v = (string)$v;
        return ($v !== '' && strpbrk($v[0], '=+-@') !== false && !is_numeric($v)) ? "'" . $v : $v;
    };
    fputcsv($out, array_map(fn($c) => $c[0], array_values($columns)));
    foreach ($rows as $r) {
        $line = [];
        foreach ($columns as $k => $c) {
            $line[] = $safe($r[$k] ?? '');
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

// ----------------------------------------------------------------- display
render_header($cfg['title'] . ' - Reports', 'reports');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Reports</h1>
</div>

<ul class="nav nav-pills mb-3 gap-1 no-print">
    <?php foreach ($catalog as $k => $c): ?>
        <li class="nav-item">
            <a class="nav-link<?= $k === $key ? ' active bg-success' : ' text-success' ?>" href="<?= e($self . '?report=' . $k) ?>">
                <i class="bi <?= e($c['icon']) ?>"></i> <?= e($c['title']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card mb-3 no-print"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="report" value="<?= e($key) ?>">
        <?php
        if (in_array('q', $cfg['filters'], true)) {
            echo '<div class="col-12 col-md-3"><label class="form-label small mb-1" for="q">Search</label>'
               . '<input type="search" id="q" name="q" class="form-control form-control-sm" placeholder="Farmer or farm…" value="' . e($_GET['q'] ?? '') . '"></div>';
        }
        if (in_array('address', $cfg['filters'], true)) {
            f_filter_select('address', 'All addresses', choices('SELECT DISTINCT address AS id, address AS label FROM farmer ORDER BY address'), 'Farmer address');
        }
        if (in_array('farm_id', $cfg['filters'], true)) {
            f_filter_select('farm_id', 'All farms', lookup('farm', 'farm_id', 'farm_name'), 'Farm');
        }
        if (in_array('category_id', $cfg['filters'], true)) {
            f_filter_select('category_id', 'All categories', lookup('crop_category', 'category_id', 'category_name'), 'Crop category');
        }
        if (in_array('status_id', $cfg['filters'], true)) {
            f_filter_select('status_id', 'All statuses', lookup('planting_status', 'status_id', 'status_name'), 'Status');
        }
        if (in_array('quality_grade_id', $cfg['filters'], true)) {
            f_filter_select('quality_grade_id', 'All grades', lookup('quality_grade', 'quality_grade_id', 'grade_name'), 'Quality grade');
        }
        if (in_array('crop_id', $cfg['filters'], true)) {
            f_filter_select('crop_id', 'All crops', crop_choices(), 'Crop');
        }
        if (in_array('date', $cfg['filters'], true)): ?>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="date_from"><?= e($cfg['dateLabel']) ?> from</label>
                <input type="date" id="date_from" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="date_to">to</label>
                <input type="date" id="date_to" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
        <?php endif; ?>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-funnel"></i> Apply</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e($self . '?report=' . $key) ?>">Reset</a>
        </div>
    </form>
</div></div>

<?php if ($filterError): ?>
    <div class="alert alert-danger"><?= e($filterError) ?></div>
<?php else: ?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?= e($cfg['title']) ?>
            <small class="text-muted fw-normal ms-2">
                Generated <?= e(date('M d, Y h:i A')) ?>
                <?= ($dateFrom !== '' || $dateTo !== '') ? ' · ' . e($cfg['dateLabel']) . ': ' . e($dateFrom !== '' ? fmt_date($dateFrom) : 'any') . ' to ' . e($dateTo !== '' ? fmt_date($dateTo) : 'any') : '' ?>
            </small>
        </span>
        <span class="no-print d-flex gap-2">
            <a class="btn btn-sm btn-outline-success" href="<?= e(qs(['export' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> Export CSV</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </span>
    </div>

    <?php if ($summary): ?>
        <div class="card-body border-bottom py-2">
            <?php foreach ($summary as $label => $count): ?>
                <span class="me-3"><?= status_badge($label) ?> <?= e($count) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead><tr>
                <?php foreach ($columns as $k => [$label, $type]): ?>
                    <th scope="col" class="<?= in_array($type, ['num', 'int', 'money'], true) ? 'text-end' : '' ?>"><?= e($label) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php foreach ($columns as $k => [$label, $type]): ?>
                        <td class="<?= in_array($type, ['num', 'int', 'money'], true) ? 'text-end' : '' ?>"><?= e(report_cell($r[$k] ?? null, $type)) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= count($columns) ?>" class="text-center text-muted py-4">No records match the selected filters.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($rows && $totals): ?>
            <tfoot class="table-light fw-bold"><tr>
                <?php foreach ($columns as $k => [$label, $type]): ?>
                    <td class="<?= in_array($type, ['num', 'int', 'money'], true) ? 'text-end' : '' ?>">
                        <?= isset($totals[$k]) ? e(is_numeric($totals[$k]) ? report_cell($totals[$k], $type === 'text' ? 'int' : $type) : $totals[$k]) : '' ?>
                    </td>
                <?php endforeach; ?>
            </tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small"><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?> in this report</div>
</div>
<?php endif; ?>
<?php render_footer();
