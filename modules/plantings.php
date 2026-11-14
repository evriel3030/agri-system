<?php

require __DIR__ . '/../includes/bootstrap.php';
require_login();

$db = db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$self = url('modules/plantings.php');

const PLANTING_SELECT = 'p.planting_id, p.farm_id, p.crop_id, p.planting_date, p.area_planted, p.seed_quantity,
                         p.expected_harvest_date, p.status_id, p.created_at, p.updated_at,
                         f.farm_name, f.area_hectares, fr.first_name, fr.last_name,
                         ct.crop_name, c.variety, s.status_name';
const PLANTING_FROM   = 'planting p JOIN farm f ON f.farm_id = p.farm_id
                                    JOIN farmer fr ON fr.farmer_id = f.farmer_id
                                    JOIN crop c ON c.crop_id = p.crop_id
                                    JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                                    JOIN planting_status s ON s.status_id = p.status_id';

function load_planting(int $id): array
{
    $st = db()->prepare('SELECT ' . PLANTING_SELECT . ' FROM ' . PLANTING_FROM . ' WHERE p.planting_id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Planting record not found.');
        redirect('modules/plantings.php');
    }
    return $row;
}

// ===================================================================== DELETE
if ($action === 'delete') {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('modules/plantings.php');
    }
    csrf_verify();
    $row = load_planting($id);
    try {
        $db->prepare('DELETE FROM planting WHERE planting_id = ?')->execute([$id]);
        flash('success', 'Planting record #' . $id . ' was deleted successfully.');
    } catch (PDOException $ex) {
        flash('danger', db_error_message($ex, 'This planting record cannot be deleted because it has harvest records. Delete those harvest records first.'));
    }
    redirect('modules/plantings.php');
}

// ============================================================ CREATE / EDIT
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit';
    $form = ['farm_id' => $_GET['farm_id'] ?? '', 'crop_id' => $_GET['crop_id'] ?? '', 'planting_date' => date('Y-m-d'),
             'area_planted' => '', 'seed_quantity' => '', 'expected_harvest_date' => '', 'status_id' => ''];
    if ($editing) {
        $form = array_merge($form, load_planting($id));
    }
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $v = new Validator($_POST);
        $farmOk = $v->exists('farm_id', 'farm', 'farm', 'farm_id');
        $v->exists('crop_id', 'crop', 'crop', 'crop_id');
        $v->date('planting_date', 'Planting date');
        $areaOk = $v->decimal('area_planted', 'Area planted (ha)', 0, 999999.99, true);
        $v->decimal('seed_quantity', 'Seed quantity', 0, 999999.99, true);
        $v->date('expected_harvest_date', 'Expected harvest date');
        $v->dateNotBefore('planting_date', 'expected_harvest_date', 'Expected harvest date cannot be earlier than the planting date.');
        $v->exists('status_id', 'status', 'planting_status', 'status_id');

        // Planted area cannot be larger than the farm itself
        if ($farmOk && $areaOk) {
            $st = $db->prepare('SELECT area_hectares FROM farm WHERE farm_id = ?');
            $st->execute([(int)$v->get('farm_id')]);
            $farmArea = (float)$st->fetchColumn();
            if ((float)$v->get('area_planted') > $farmArea) {
                $v->add('area_planted', 'Area planted cannot be larger than the farm area (' . fmt_num($farmArea) . ' ha).');
            }
        }
        // On edit: an existing harvest cannot be earlier than a new planting date
        if ($editing && !isset($v->errors['planting_date'])) {
            $st = $db->prepare('SELECT MIN(harvest_date) FROM harvest WHERE planting_id = ?');
            $st->execute([$id]);
            $firstHarvest = $st->fetchColumn();
            if ($firstHarvest && $v->get('planting_date') > $firstHarvest) {
                $v->add('planting_date', 'Planting date cannot be after an existing harvest (' . fmt_date($firstHarvest) . ').');
            }
        }

        if (!$v->fails()) {
            try {
                $values = [(int)$v->get('farm_id'), (int)$v->get('crop_id'), $v->get('planting_date'), $v->get('area_planted'),
                           $v->get('seed_quantity'), $v->get('expected_harvest_date'), (int)$v->get('status_id'), current_user_id()];
                if ($editing) {
                    $db->prepare('UPDATE planting SET farm_id=?, crop_id=?, planting_date=?, area_planted=?, seed_quantity=?,
                                         expected_harvest_date=?, status_id=?, updated_by=? WHERE planting_id=?')
                       ->execute(array_merge($values, [$id]));
                    flash('success', 'Planting record was updated successfully.');
                } else {
                    $db->prepare('INSERT INTO planting (farm_id, crop_id, planting_date, area_planted, seed_quantity,
                                         expected_harvest_date, status_id, updated_by, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                       ->execute(array_merge($values, [current_user_id()]));
                    flash('success', 'Planting record was added successfully.');
                }
                redirect('modules/plantings.php');
            } catch (PDOException $ex) {
                flash('danger', db_error_message($ex));
            }
        } else {
            flash('danger', 'Please fix the errors below.');
        }
        $errors = $v->errors;
        $form = array_merge($form, array_map('trim', array_filter($_POST, 'is_string')));
    }

    $farms = choices("SELECT fm.farm_id AS id, fm.farm_name || ' — ' || fr.last_name || ' (' || fm.area_hectares || ' ha)' AS label
                        FROM farm fm JOIN farmer fr ON fr.farmer_id = fm.farmer_id ORDER BY fm.farm_name");
    $crops = crop_choices();
    $cropAttrs = [];
    foreach ($db->query('SELECT crop_id, growing_period_days FROM crop') as $c) {
        $cropAttrs[$c['crop_id']] = ['data-days' => $c['growing_period_days']];
    }

    render_header($editing ? 'Edit Planting Record' : 'Add Planting Record', 'plantings');
    page_title($editing ? 'Edit Planting Record' : 'Add Planting Record');
    ?>
    <div class="card"><div class="card-body">
        <form method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <?php
            f_select(['name' => 'farm_id', 'label' => 'Farm', 'value' => $form['farm_id'], 'choices' => $farms, 'required' => true], $errors);
            f_select(['name' => 'crop_id', 'label' => 'Crop', 'value' => $form['crop_id'], 'choices' => $crops, 'optAttrs' => $cropAttrs, 'required' => true], $errors);
            f_input(['name' => 'planting_date', 'label' => 'Planting Date', 'type' => 'date', 'value' => $form['planting_date'], 'required' => true], $errors);
            f_input(['name' => 'expected_harvest_date', 'label' => 'Expected Harvest Date', 'type' => 'date', 'value' => $form['expected_harvest_date'], 'required' => true,
                     'help' => 'Suggested automatically from the crop\'s growing period. You can change it.'], $errors);
            f_input(['name' => 'area_planted', 'label' => 'Area Planted (hectares)', 'type' => 'number', 'value' => $form['area_planted'], 'required' => true,
                     'attrs' => ['min' => 0.01, 'max' => 999999.99, 'step' => '0.01'], 'help' => 'Cannot be larger than the farm area.'], $errors);
            f_input(['name' => 'seed_quantity', 'label' => 'Seed Quantity', 'type' => 'number', 'value' => $form['seed_quantity'], 'required' => true,
                     'attrs' => ['min' => 0.01, 'max' => 999999.99, 'step' => '0.01']], $errors);
            f_select(['name' => 'status_id', 'label' => 'Status', 'value' => $form['status_id'], 'choices' => lookup('planting_status', 'status_id', 'status_name'), 'required' => true], $errors);
            f_buttons($self, $editing ? 'Update Planting' : 'Save Planting');
            ?>
        </form>
    </div></div>
    <?php
    render_footer();
    exit;
}

// ======================================================================= VIEW
if ($action === 'view') {
    $p = load_planting($id);
    $st = $db->prepare(
        'SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg, g.grade_name, r.remark_description
           FROM harvest h JOIN quality_grade g ON g.quality_grade_id = h.quality_grade_id
           JOIN harvest_remark r ON r.remark_id = h.remark_id
          WHERE h.planting_id = ? ORDER BY h.harvest_date'
    );
    $st->execute([$id]);
    $harvests = $st->fetchAll();

    render_header('Planting Details', 'plantings');
    page_title('Planting Details');
    ?>
    <div class="card mb-3"><div class="card-body">
        <dl class="row detail-list mb-0">
            <dt class="col-sm-3">Planting ID</dt><dd class="col-sm-9"><?= e($p['planting_id']) ?></dd>
            <dt class="col-sm-3">Farm</dt>
            <dd class="col-sm-9"><a href="<?= e(url('modules/farms.php?action=view&id=' . $p['farm_id'])) ?>"><?= e($p['farm_name']) ?></a>
                (<?= e($p['first_name'] . ' ' . $p['last_name']) ?>)</dd>
            <dt class="col-sm-3">Crop</dt>
            <dd class="col-sm-9"><a href="<?= e(url('modules/crops.php?action=view&id=' . $p['crop_id'])) ?>"><?= e($p['crop_name'] . ' — ' . $p['variety']) ?></a></dd>
            <dt class="col-sm-3">Planting Date</dt><dd class="col-sm-9"><?= e(fmt_date($p['planting_date'])) ?></dd>
            <dt class="col-sm-3">Expected Harvest</dt><dd class="col-sm-9"><?= e(fmt_date($p['expected_harvest_date'])) ?></dd>
            <dt class="col-sm-3">Area Planted</dt><dd class="col-sm-9"><?= e(fmt_num($p['area_planted'])) ?> ha (farm: <?= e(fmt_num($p['area_hectares'])) ?> ha)</dd>
            <dt class="col-sm-3">Seed Quantity</dt><dd class="col-sm-9"><?= e(fmt_num($p['seed_quantity'])) ?></dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= status_badge($p['status_name']) ?></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($p['created_at'])) ?></dd>
            <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($p['updated_at'])) ?></dd>
        </dl>
    </div></div>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            Harvests (<?= count($harvests) ?>)
            <a class="btn btn-sm btn-outline-success" href="<?= e(url('modules/harvests.php?action=create&planting_id=' . $id)) ?>"><i class="bi bi-plus-lg"></i> Add harvest</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th class="text-end">Qty (kg)</th><th class="text-end">Price/kg</th><th>Grade</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php foreach ($harvests as $h): ?>
                    <tr>
                        <td><?= e(fmt_date($h['harvest_date'])) ?></td>
                        <td class="text-end"><?= e(fmt_num($h['quantity_kg'])) ?></td>
                        <td class="text-end"><?= e(fmt_money($h['selling_price_per_kg'])) ?></td>
                        <td><?= e($h['grade_name']) ?></td>
                        <td><?= e($h['remark_description']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$harvests): ?><tr><td colspan="5" class="text-center text-muted">No harvest recorded yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <a href="<?= e($self) ?>" class="btn btn-outline-secondary">Back to list</a>
    <a href="<?= e($self . '?action=edit&id=' . $id) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit</a>
    <?php
    render_footer();
    exit;
}

// ======================================================================= LIST
$where = [];
$params = [];
add_search($where, $params, ['f.farm_name', 'ct.crop_name', 'c.variety', 'fr.last_name', 'fr.first_name'], $_GET['q'] ?? '');
foreach (['status_id' => 'p.status_id', 'farm_id' => 'p.farm_id', 'crop_id' => 'p.crop_id'] as $get => $col) {
    if (($_GET[$get] ?? '') !== '' && ctype_digit((string)$_GET[$get])) {
        $where[] = "$col = ?";
        $params[] = (int)$_GET[$get];
    }
}
$from = $_GET['date_from'] ?? '';
$to = $_GET['date_to'] ?? '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'p.planting_date >= ?'; $params[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'p.planting_date <= ?'; $params[] = $to; }

$list = list_query(
    PLANTING_SELECT, PLANTING_FROM, $where, $params,
    ['id' => 'p.planting_id', 'farm' => 'f.farm_name', 'crop' => 'ct.crop_name', 'planted' => 'p.planting_date',
     'area' => 'p.area_planted', 'seed' => 'p.seed_quantity', 'expected' => 'p.expected_harvest_date', 'status' => 's.status_name'],
    'planted', 'desc'
);

render_header('Planting Records', 'plantings');
page_title('Planting Records', url('modules/plantings.php?action=create'), 'Add Planting');
?>
<div class="card mb-3"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-3">
            <label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" class="form-control form-control-sm" placeholder="Farm, crop, owner…"
                   value="<?= e($_GET['q'] ?? '') ?>">
        </div>
        <?php
        f_filter_select('status_id', 'All statuses', lookup('planting_status', 'status_id', 'status_name'), 'Status');
        f_filter_select('crop_id', 'All crops', crop_choices(), 'Crop');
        f_filter_select('farm_id', 'All farms', lookup('farm', 'farm_id', 'farm_name'), 'Farm');
        ?>
        <div class="col-6 col-md-auto">
            <label class="form-label small mb-1" for="date_from">Planted from</label>
            <input type="date" id="date_from" name="date_from" class="form-control form-control-sm" value="<?= e($from) ?>">
        </div>
        <div class="col-6 col-md-auto">
            <label class="form-label small mb-1" for="date_to">to</label>
            <input type="date" id="date_to" name="date_to" class="form-control form-control-sm" value="<?= e($to) ?>">
        </div>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-search"></i> Search</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e($self) ?>">Reset</a>
        </div>
    </form>
</div></div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead><tr>
                <?= sort_th('ID', 'id', $list) ?>
                <?= sort_th('Farm', 'farm', $list) ?>
                <?= sort_th('Crop', 'crop', $list) ?>
                <?= sort_th('Planted', 'planted', $list) ?>
                <?= sort_th('Area (ha)', 'area', $list) ?>
                <?= sort_th('Seed Qty', 'seed', $list) ?>
                <?= sort_th('Expected Harvest', 'expected', $list) ?>
                <?= sort_th('Status', 'status', $list) ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['planting_id']) ?></td>
                    <td><?= e($r['farm_name']) ?></td>
                    <td><?= e($r['crop_name']) ?> <small class="text-muted"><?= e($r['variety']) ?></small></td>
                    <td><?= e(fmt_date($r['planting_date'])) ?></td>
                    <td class="text-end"><?= e(fmt_num($r['area_planted'])) ?></td>
                    <td class="text-end"><?= e(fmt_num($r['seed_quantity'])) ?></td>
                    <td><?= e(fmt_date($r['expected_harvest_date'])) ?></td>
                    <td><?= status_badge($r['status_name']) ?></td>
                    <td class="text-end"><?php row_actions('plantings', (int)$r['planting_id'], 'planting #' . $r['planting_id'] . ' (' . $r['farm_name'] . ' – ' . $r['crop_name'] . ')'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list['rows']): ?><tr><td colspan="9" class="text-center text-muted py-4">No planting records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted"><?= e(list_summary($list)) ?></small>
        <?php render_pagination($list); ?>
    </div>
</div>
<?php render_footer();
