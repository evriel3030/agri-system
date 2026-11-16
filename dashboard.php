<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();

// ---- Summary counts (all values come from the database) ----
$counts = $db->query(
    'SELECT (SELECT COUNT(*) FROM farmer)   AS farmers,
            (SELECT COUNT(*) FROM farm)     AS farms,
            (SELECT COUNT(*) FROM crop)     AS crops,
            (SELECT COUNT(*) FROM planting) AS plantings,
            (SELECT COUNT(*) FROM harvest)  AS harvests'
)->fetch();

// ---- Harvest totals ----
$totals = $db->query(
    'SELECT COALESCE(SUM(quantity_kg), 0) AS total_kg,
            COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) AS total_value
       FROM harvest'
)->fetch();

// ---- Plantings by status (LEFT JOIN so statuses with 0 records still show) ----
$byStatus = $db->query(
    'SELECT s.status_name, COUNT(p.planting_id) AS total
       FROM planting_status s
       LEFT JOIN planting p ON p.status_id = s.status_id
      GROUP BY s.status_id, s.status_name
      ORDER BY s.status_id'
)->fetchAll();

// ---- Harvested quantity by crop (for the bar chart) ----
$byCrop = $db->query(
    'SELECT ct.crop_name, SUM(h.quantity_kg) AS total_kg
       FROM harvest h
       JOIN planting p ON p.planting_id = h.planting_id
       JOIN crop c     ON c.crop_id = p.crop_id
       JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
      GROUP BY ct.crop_name
      ORDER BY total_kg DESC
      LIMIT 8'
)->fetchAll();

// ---- Recent records ----
$recentPlantings = $db->query(
    'SELECT p.planting_id, p.planting_date, p.area_planted, f.farm_name, ct.crop_name, s.status_name
       FROM planting p
       JOIN farm f ON f.farm_id = p.farm_id
       JOIN crop c ON c.crop_id = p.crop_id
       JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
       JOIN planting_status s ON s.status_id = p.status_id
      ORDER BY p.planting_date DESC, p.planting_id DESC
      LIMIT 5'
)->fetchAll();

$recentHarvests = $db->query(
    'SELECT h.harvest_id, h.harvest_date, h.quantity_kg, f.farm_name, ct.crop_name, g.grade_name
       FROM harvest h
       JOIN planting p ON p.planting_id = h.planting_id
       JOIN farm f ON f.farm_id = p.farm_id
       JOIN crop c ON c.crop_id = p.crop_id
       JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
       JOIN quality_grade g ON g.quality_grade_id = h.quality_grade_id
      ORDER BY h.harvest_date DESC, h.harvest_id DESC
      LIMIT 5'
)->fetchAll();

// ---- Cards: label, number, icon, link ----
$cards = [
    ['Farmers',          $counts['farmers'],   'bi-people',       'modules/farmers.php'],
    ['Farms',            $counts['farms'],     'bi-geo-alt',      'modules/farms.php'],
    ['Crops',            $counts['crops'],     'bi-flower1',      'modules/crops.php'],
    ['Planting Records', $counts['plantings'], 'bi-database-add', 'modules/plantings.php'],
    ['Harvest Records',  $counts['harvests'],  'bi-basket',       'modules/harvests.php'],
];

render_header('Dashboard', 'dashboard');
?>
<h1 class="h3 mb-3">Dashboard</h1>

<!-- Summary cards: each one links to its module -->
<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $number, $icon, $link]): ?>
        <div class="col-6 col-md-4 col-xl">
            <a href="<?= e(url($link)) ?>" class="text-decoration-none text-reset">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small"><?= e($label) ?></div>
                            <div class="stat-number"><?= e(number_format((int)$number)) ?></div>
                        </div>
                        <i class="bi <?= e($icon) ?> stat-icon"></i>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-box-seam stat-icon text-success"></i>
                <div>
                    <div class="text-muted small">Total Harvested Quantity</div>
                    <div class="stat-number"><?= e(fmt_num($totals['total_kg'])) ?> <span class="fs-6 text-muted">kg</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-cash-coin stat-icon text-success"></i>
                <div>
                    <div class="text-muted small">Estimated Harvest Value</div>
                    <div class="stat-number"><?= e(fmt_money($totals['total_value'])) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Plantings by Status</div>
            <div class="card-body">
                <?php if (array_sum(array_column($byStatus, 'total')) > 0): ?>
                    <div class="chart-box"><canvas id="statusChart" aria-label="Plantings by status chart" role="img"></canvas></div>
                <?php else: ?>
                    <p class="text-muted mb-0">No planting records yet.</p>
                <?php endif; ?>
                <ul class="list-inline mt-3 mb-0 small">
                    <?php foreach ($byStatus as $row): ?>
                        <li class="list-inline-item me-3"><?= status_badge($row['status_name']) ?> <?= e($row['total']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Harvested Quantity by Crop (kg)</div>
            <div class="card-body">
                <?php if ($byCrop): ?>
                    <div class="chart-box"><canvas id="cropChart" aria-label="Harvested quantity by crop chart" role="img"></canvas></div>
                <?php else: ?>
                    <p class="text-muted mb-0">No harvest records yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent records -->
<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                Recent Planting Records
                <a class="btn btn-sm btn-outline-success" href="<?= e(url('modules/plantings.php')) ?>">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Farm</th><th>Crop</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPlantings as $r): ?>
                        <tr>
                            <td><?= e(fmt_date($r['planting_date'])) ?></td>
                            <td><?= e($r['farm_name']) ?></td>
                            <td><?= e($r['crop_name']) ?></td>
                            <td><?= status_badge($r['status_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentPlantings): ?><tr><td colspan="4" class="text-center text-muted">No records yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                Recent Harvest Records
                <a class="btn btn-sm btn-outline-success" href="<?= e(url('modules/harvests.php')) ?>">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Farm</th><th>Crop</th><th class="text-end">Qty (kg)</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentHarvests as $r): ?>
                        <tr>
                            <td><?= e(fmt_date($r['harvest_date'])) ?></td>
                            <td><?= e($r['farm_name']) ?></td>
                            <td><?= e($r['crop_name']) ?></td>
                            <td class="text-end"><?= e(fmt_num($r['quantity_kg'])) ?></td>
                            <td><?= e($r['grade_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentHarvests): ?><tr><td colspan="5" class="text-center text-muted">No records yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Chart data is passed from PHP (database) to JavaScript as JSON.
$statusLabels = json_encode(array_column($byStatus, 'status_name'));
$statusData   = json_encode(array_map('intval', array_column($byStatus, 'total')));
$cropLabels   = json_encode(array_column($byCrop, 'crop_name'));
$cropData     = json_encode(array_map('floatval', array_column($byCrop, 'total_kg')));
$chartSrc     = e(url('assets/bootstrap/chartjs/chart.umd.min.js'));

$chartJs = <<<HTML
<script src="{$chartSrc}"></script>
<script>
(function () {
    var s = document.getElementById('statusChart');
    if (s) {
        new Chart(s, {
            type: 'doughnut',
            data: { labels: $statusLabels,
                    datasets: [{ data: $statusData, backgroundColor: ['#0dcaf0', '#15803d', '#1e3a8a', '#dc3545'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }
    var c = document.getElementById('cropChart');
    if (c) {
        new Chart(c, {
            type: 'bar',
            data: { labels: $cropLabels, datasets: [{ label: 'Kilograms', data: $cropData, backgroundColor: '#2563eb' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                       scales: { y: { beginAtZero: true } } }
        });
    }
})();
</script>
HTML;
render_footer([$chartJs]);
