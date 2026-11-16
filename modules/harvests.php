<?php

require __DIR__ . '/../includes/bootstrap.php';
require_login();

$db = db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$self = url('modules/harvests.php');

const HARVEST_SELECT = 'h.harvest_id, h.planting_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                        h.quality_grade_id, h.remark_id, h.created_at, h.updated_at,
                        (h.quantity_kg * h.selling_price_per_kg) AS est_value,
                        g.grade_name, r.remark_description,
                        p.planting_date, f.farm_name, ct.crop_name, c.variety';
const HARVEST_FROM   = 'harvest h JOIN planting p ON p.planting_id = h.planting_id
                                  JOIN farm f ON f.farm_id = p.farm_id
                                  JOIN crop c ON c.crop_id = p.crop_id
                                  JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                                  JOIN quality_grade g ON g.quality_grade_id = h.quality_grade_id
                                  JOIN harvest_remark r ON r.remark_id = h.remark_id';

function load_harvest(int $id): array
{
    $st = db()->prepare('SELECT ' . HARVEST_SELECT . ' FROM ' . HARVEST_FROM . ' WHERE h.harvest_id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Harvest record not found.');
        redirect('modules/harvests.php');
    }
    return $row;
}

// ===================================================================== DELETE
if ($action === 'delete') {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('modules/harvests.php');
    }
    csrf_verify();
    load_harvest($id);
    try {
        $db->prepare('DELETE FROM harvest WHERE harvest_id = ?')->execute([$id]);
        flash('success', 'Harvest record #' . $id . ' was deleted successfully.');
    } catch (PDOException $ex) {
        flash('danger', db_error_message($ex));
    }
    redirect('modules/harvests.php');
}

// ============================================================ CREATE / EDIT
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit';
    $form = ['planting_id' => $_GET['planting_id'] ?? '', 'harvest_date' => date('Y-m-d'), 'quantity_kg' => '',
             'selling_price_per_kg' => '', 'quality_grade_id' => '', 'remark_id' => ''];
    if ($editing) {
        $form = array_merge($form, load_harvest($id));
    }
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $v = new Validator($_POST);
        $plantingOk = $v->exists('planting_id', 'planting record', 'planting', 'planting_id');
        $dateOk = $v->date('harvest_date', 'Harvest date');
        $v->decimal('quantity_kg', 'Quantity (kg)', 0, 99999999.99, true);
        $v->decimal('selling_price_per_kg', 'Selling price per kg', 0, 99999999.99, false);
        $v->exists('quality_grade_id', 'quality grade', 'quality_grade', 'quality_grade_id');
        $v->exists('remark_id', 'remark', 'harvest_remark', 'remark_id');

        // Logical date check: cannot harvest before planting
        if ($plantingOk && $dateOk) {
            $st = $db->prepare('SELECT planting_date FROM planting WHERE planting_id = ?');
            $st->execute([(int)$v->get('planting_id')]);
            $plantedOn = $st->fetchColumn();
            if ($v->get('harvest_date') < $plantedOn) {
                $v->add('harvest_date', 'Harvest date cannot be earlier than the planting date (' . fmt_date($plantedOn) . ').');
            }
        }

        if (!$v->fails()) {
            try {
                $values = [(int)$v->get('planting_id'), $v->get('harvest_date'), $v->get('quantity_kg'), $v->get('selling_price_per_kg'),
                           (int)$v->get('quality_grade_id'), (int)$v->get('remark_id'), current_user_id()];
                if ($editing) {
                    $db->prepare('UPDATE harvest SET planting_id=?, harvest_date=?, quantity_kg=?, selling_price_per_kg=?,
                                         quality_grade_id=?, remark_id=?, updated_by=? WHERE harvest_id=?')
                       ->execute(array_merge($values, [$id]));
                    flash('success', 'Harvest record was updated successfully.');
                } else {
                    $db->prepare('INSERT INTO harvest (planting_id, harvest_date, quantity_kg, selling_price_per_kg,
                                         quality_grade_id, remark_id, updated_by, created_by) VALUES (?,?,?,?,?,?,?,?)')
                       ->execute(array_merge($values, [current_user_id()]));
                    flash('success', 'Harvest record was added successfully.');
                }
                redirect('modules/harvests.php');
            } catch (PDOException $ex) {
                flash('danger', db_error_message($ex));
            }
        } else {
            flash('danger', 'Please fix the errors below.');
        }
        $errors = $v->errors;
        $form = array_merge($form, array_map('trim', array_filter($_POST, 'is_string')));
    }

    $plantings = choices(
        "SELECT p.planting_id AS id,
                '#' || p.planting_id || ' — ' || f.farm_name || ' / ' || ct.crop_name || ' (planted ' || to_char(p.planting_date, 'YYYY-MM-DD') || ')' AS label
           FROM planting p JOIN farm f ON f.farm_id = p.farm_id JOIN crop c ON c.crop_id = p.crop_id
           JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
          ORDER BY p.planting_id"
    );

    render_header($editing ? 'Edit Harvest Record' : 'Add Harvest Record', 'harvests');
    page_title($editing ? 'Edit Harvest Record' : 'Add Harvest Record');
    ?>
    <div class="card"><div class="card-body">
        <form method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <?php
            f_select(['name' => 'planting_id', 'label' => 'Planting Record', 'value' => $form['planting_id'], 'choices' => $plantings, 'required' => true, 'col' => 'col-12'], $errors);
            f_input(['name' => 'harvest_date', 'label' => 'Harvest Date', 'type' => 'date', 'value' => $form['harvest_date'], 'required' => true], $errors);
            f_input(['name' => 'quantity_kg', 'label' => 'Quantity (kg)', 'type' => 'number', 'value' => $form['quantity_kg'], 'required' => true,
                     'attrs' => ['min' => 0.01, 'max' => 99999999.99, 'step' => '0.01']], $errors);
            f_input(['name' => 'selling_price_per_kg', 'label' => 'Selling Price per kg (₱)', 'type' => 'number', 'value' => $form['selling_price_per_kg'], 'required' => true,
                     'attrs' => ['min' => 0, 'max' => 99999999.99, 'step' => '0.01']], $errors);
            f_select(['name' => 'quality_grade_id', 'label' => 'Quality Grade', 'value' => $form['quality_grade_id'], 'choices' => lookup('quality_grade', 'quality_grade_id', 'grade_name'), 'required' => true], $errors);
            f_select(['name' => 'remark_id', 'label' => 'Remarks', 'value' => $form['remark_id'], 'choices' => choices('SELECT remark_id AS id, remark_description AS label FROM harvest_remark ORDER BY remark_id'), 'required' => true], $errors);
            ?>
            <div class="col-md-6">
                <div class="form-label">Estimated Value</div>
                <div class="fs-5 fw-semibold text-success" id="estimated_value">—</div>
            </div>
            <?php f_buttons($self, $editing ? 'Update Harvest' : 'Save Harvest'); ?>
        </form>
    </div></div>
    <?php
    render_footer();
    exit;
}

// ======================================================================= VIEW
if ($action === 'view') {
    $h = load_harvest($id);
    render_header('Harvest Details', 'harvests');
    page_title('Harvest Details');
    ?>
    <div class="card mb-3"><div class="card-body">
        <dl class="row detail-list mb-0">
            <dt class="col-sm-3">Harvest ID</dt><dd class="col-sm-9"><?= e($h['harvest_id']) ?></dd>
            <dt class="col-sm-3">Planting Record</dt>
            <dd class="col-sm-9"><a href="<?= e(url('modules/plantings.php?action=view&id=' . $h['planting_id'])) ?>">#<?= e($h['planting_id']) ?></a>
                — <?= e($h['farm_name']) ?> / <?= e($h['crop_name'] . ' (' . $h['variety'] . ')') ?>, planted <?= e(fmt_date($h['planting_date'])) ?></dd>
            <dt class="col-sm-3">Harvest Date</dt><dd class="col-sm-9"><?= e(fmt_date($h['harvest_date'])) ?></dd>
            <dt class="col-sm-3">Quantity</dt><dd class="col-sm-9"><?= e(fmt_num($h['quantity_kg'])) ?> kg</dd>
            <dt class="col-sm-3">Selling Price</dt><dd class="col-sm-9"><?= e(fmt_money($h['selling_price_per_kg'])) ?> per kg</dd>
            <dt class="col-sm-3">Estimated Value</dt><dd class="col-sm-9"><?= e(fmt_money($h['est_value'])) ?></dd>
            <dt class="col-sm-3">Quality Grade</dt><dd class="col-sm-9"><?= e($h['grade_name']) ?></dd>
            <dt class="col-sm-3">Remarks</dt>
            <dd class="col-sm-9"><?= e($h['remark_description']) ?> <small class="text-muted">(stored as code <?= e($h['remark_id']) ?>)</small></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($h['created_at'])) ?></dd>
            <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($h['updated_at'])) ?></dd>
        </dl>
    </div></div>
    <a href="<?= e($self) ?>" class="btn btn-outline-secondary">Back to list</a>
    <a href="<?= e($self . '?action=edit&id=' . $id) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit</a>
    <?php
    render_footer();
    exit;
}

// ======================================================================= LIST
$where = [];
$params = [];
add_search($where, $params, ['f.farm_name', 'ct.crop_name', 'c.variety'], $_GET['q'] ?? '');
foreach (['quality_grade_id' => 'h.quality_grade_id', 'remark_id' => 'h.remark_id', 'crop_id' => 'p.crop_id'] as $get => $col) {
    if (($_GET[$get] ?? '') !== '' && ctype_digit((string)$_GET[$get])) {
        $where[] = "$col = ?";
        $params[] = (int)$_GET[$get];
    }
}
$from = $_GET['date_from'] ?? '';
$to = $_GET['date_to'] ?? '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'h.harvest_date >= ?'; $params[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'h.harvest_date <= ?'; $params[] = $to; }

$list = list_query(
    HARVEST_SELECT, HARVEST_FROM, $where, $params,
    ['id' => 'h.harvest_id', 'date' => 'h.harvest_date', 'farm' => 'f.farm_name', 'crop' => 'ct.crop_name',
     'qty' => 'h.quantity_kg', 'price' => 'h.selling_price_per_kg', 'value' => 'est_value',
     'grade' => 'g.grade_name', 'remark' => 'h.remark_id'],
    'date', 'desc'
);

render_header('Harvest Records', 'harvests');
page_title('Harvest Records', url('modules/harvests.php?action=create'), 'Add Harvest');
?>
<div class="card mb-3"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-3">
            <label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" class="form-control form-control-sm" placeholder="Farm or crop…"
                   value="<?= e($_GET['q'] ?? '') ?>">
        </div>
        <?php
        f_filter_select('quality_grade_id', 'All grades', lookup('quality_grade', 'quality_grade_id', 'grade_name'), 'Quality grade');
        f_filter_select('remark_id', 'All remarks', choices('SELECT remark_id AS id, remark_description AS label FROM harvest_remark ORDER BY remark_id'), 'Remarks');
        f_filter_select('crop_id', 'All crops', crop_choices(), 'Crop');
        ?>
        <div class="col-6 col-md-auto">
            <label class="form-label small mb-1" for="date_from">Harvested from</label>
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
                <?= sort_th('Date', 'date', $list) ?>
                <?= sort_th('Farm', 'farm', $list) ?>
                <?= sort_th('Crop', 'crop', $list) ?>
                <?= sort_th('Qty (kg)', 'qty', $list) ?>
                <?= sort_th('Price/kg', 'price', $list) ?>
                <?= sort_th('Est. Value', 'value', $list) ?>
                <?= sort_th('Grade', 'grade', $list) ?>
                <?= sort_th('Remarks', 'remark', $list) ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['harvest_id']) ?></td>
                    <td><?= e(fmt_date($r['harvest_date'])) ?></td>
                    <td><?= e($r['farm_name']) ?></td>
                    <td><?= e($r['crop_name']) ?></td>
                    <td class="text-end"><?= e(fmt_num($r['quantity_kg'])) ?></td>
                    <td class="text-end"><?= e(fmt_money($r['selling_price_per_kg'])) ?></td>
                    <td class="text-end"><?= e(fmt_money($r['est_value'])) ?></td>
                    <td><?= e($r['grade_name']) ?></td>
                    <td><?= e($r['remark_description']) ?></td>
                    <td class="text-end"><?php row_actions('harvests', (int)$r['harvest_id'], 'harvest #' . $r['harvest_id'] . ' (' . $r['farm_name'] . ' – ' . $r['crop_name'] . ')'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list['rows']): ?><tr><td colspan="10" class="text-center text-muted py-4">No harvest records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted"><?= e(list_summary($list)) ?></small>
        <?php render_pagination($list); ?>
    </div>
</div>
<?php render_footer();
