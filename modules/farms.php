<?php

require __DIR__ . '/../includes/bootstrap.php';
require_login();

$db = db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$self = url('modules/farms.php');

const FARM_SELECT = 'fm.farm_id, fm.farmer_id, fm.farm_name, fm.location, fm.area_hectares, fm.soil_type_id,
                     fm.irrigation_type_id, fm.created_at, fm.updated_at,
                     fr.first_name, fr.last_name, s.soil_type_name, i.irrigation_type_name';
const FARM_FROM   = 'farm fm JOIN farmer fr ON fr.farmer_id = fm.farmer_id
                             JOIN soil_type s ON s.soil_type_id = fm.soil_type_id
                             JOIN irrigation_type i ON i.irrigation_type_id = fm.irrigation_type_id';

function load_farm(int $id): array
{
    $st = db()->prepare('SELECT ' . FARM_SELECT . ' FROM ' . FARM_FROM . ' WHERE fm.farm_id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Farm not found.');
        redirect('modules/farms.php');
    }
    return $row;
}

// ===================================================================== DELETE
if ($action === 'delete') {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('modules/farms.php');
    }
    csrf_verify();
    $row = load_farm($id);
    try {
        $db->prepare('DELETE FROM farm WHERE farm_id = ?')->execute([$id]);
        flash('success', 'Farm "' . $row['farm_name'] . '" was deleted successfully.');
    } catch (PDOException $ex) {
        flash('danger', db_error_message($ex, 'This farm cannot be deleted because it has planting records. Delete those planting records first.'));
    }
    redirect('modules/farms.php');
}

// ============================================================ CREATE / EDIT
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit';
    $form = ['farmer_id' => $_GET['farmer_id'] ?? '', 'farm_name' => '', 'location' => '', 'area_hectares' => '',
             'soil_type_id' => '', 'irrigation_type_id' => ''];
    if ($editing) {
        $form = array_merge($form, load_farm($id));
    }
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $v = new Validator($_POST);
        $v->exists('farmer_id', 'farmer', 'farmer', 'farmer_id');
        $v->text('farm_name', 'Farm name', 1, 100);
        $v->text('location', 'Location', 1, 150);
        $v->decimal('area_hectares', 'Area (hectares)', 0, 999999.99, true);
        $v->exists('soil_type_id', 'soil type', 'soil_type', 'soil_type_id');
        $v->exists('irrigation_type_id', 'irrigation type', 'irrigation_type', 'irrigation_type_id');

        if (!isset($v->errors['farmer_id']) && !isset($v->errors['farm_name'])) {
            $st = $db->prepare('SELECT 1 FROM farm WHERE farmer_id = ? AND lower(farm_name) = lower(?) AND farm_id <> ?');
            $st->execute([(int)$v->get('farmer_id'), $v->get('farm_name'), $editing ? $id : 0]);
            if ($st->fetchColumn()) {
                $v->add('farm_name', 'This farmer already has a farm with that name.');
            }
        }
        // Make sure the farm is not made smaller than the area already planted
        if ($editing && !isset($v->errors['area_hectares'])) {
            $st = $db->prepare('SELECT COALESCE(MAX(area_planted), 0) FROM planting WHERE farm_id = ?');
            $st->execute([$id]);
            $maxPlanted = (float)$st->fetchColumn();
            if ((float)$v->get('area_hectares') < $maxPlanted) {
                $v->add('area_hectares', 'Area cannot be smaller than an existing planting record (' . fmt_num($maxPlanted) . ' ha).');
            }
        }

        if (!$v->fails()) {
            try {
                $values = [(int)$v->get('farmer_id'), $v->get('farm_name'), $v->get('location'), $v->get('area_hectares'),
                           (int)$v->get('soil_type_id'), (int)$v->get('irrigation_type_id'), current_user_id()];
                if ($editing) {
                    $db->prepare('UPDATE farm SET farmer_id=?, farm_name=?, location=?, area_hectares=?, soil_type_id=?,
                                         irrigation_type_id=?, updated_by=? WHERE farm_id=?')->execute(array_merge($values, [$id]));
                    flash('success', 'Farm was updated successfully.');
                } else {
                    $db->prepare('INSERT INTO farm (farmer_id, farm_name, location, area_hectares, soil_type_id,
                                         irrigation_type_id, updated_by, created_by) VALUES (?,?,?,?,?,?,?,?)')
                       ->execute(array_merge($values, [current_user_id()]));
                    flash('success', 'Farm was added successfully.');
                }
                redirect('modules/farms.php');
            } catch (PDOException $ex) {
                flash('danger', db_error_message($ex));
            }
        } else {
            flash('danger', 'Please fix the errors below.');
        }
        $errors = $v->errors;
        $form = array_merge($form, array_map('trim', array_filter($_POST, 'is_string')));
    }

    $farmers = choices("SELECT farmer_id AS id, last_name || ', ' || first_name AS label FROM farmer ORDER BY last_name, first_name");

    render_header($editing ? 'Edit Farm' : 'Add Farm', 'farms');
    page_title($editing ? 'Edit Farm' : 'Add Farm');
    ?>
    <div class="card"><div class="card-body">
        <form method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <?php
            f_select(['name' => 'farmer_id', 'label' => 'Farmer (Owner)', 'value' => $form['farmer_id'], 'choices' => $farmers, 'required' => true], $errors);
            f_input(['name' => 'farm_name', 'label' => 'Farm Name', 'value' => $form['farm_name'], 'required' => true, 'attrs' => ['maxlength' => 100]], $errors);
            f_input(['name' => 'location', 'label' => 'Location', 'value' => $form['location'], 'required' => true, 'attrs' => ['maxlength' => 150]], $errors);
            f_input(['name' => 'area_hectares', 'label' => 'Area (hectares)', 'type' => 'number', 'value' => $form['area_hectares'], 'required' => true,
                     'attrs' => ['min' => 0.01, 'max' => 999999.99, 'step' => '0.01']], $errors);
            f_select(['name' => 'soil_type_id', 'label' => 'Soil Type', 'value' => $form['soil_type_id'], 'choices' => lookup('soil_type', 'soil_type_id', 'soil_type_name'), 'required' => true], $errors);
            f_select(['name' => 'irrigation_type_id', 'label' => 'Irrigation Type', 'value' => $form['irrigation_type_id'], 'choices' => lookup('irrigation_type', 'irrigation_type_id', 'irrigation_type_name'), 'required' => true], $errors);
            f_buttons($self, $editing ? 'Update Farm' : 'Save Farm');
            ?>
        </form>
    </div></div>
    <?php
    render_footer();
    exit;
}

// ======================================================================= VIEW
if ($action === 'view') {
    $f = load_farm($id);
    $st = $db->prepare(
        'SELECT p.planting_id, p.planting_date, p.area_planted, p.expected_harvest_date, ct.crop_name, c.variety, s.status_name
           FROM planting p JOIN crop c ON c.crop_id = p.crop_id
           JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
           JOIN planting_status s ON s.status_id = p.status_id
          WHERE p.farm_id = ? ORDER BY p.planting_date DESC'
    );
    $st->execute([$id]);
    $plantings = $st->fetchAll();

    render_header('Farm Details', 'farms');
    page_title('Farm Details');
    ?>
    <div class="card mb-3"><div class="card-body">
        <dl class="row detail-list mb-0">
            <dt class="col-sm-3">Farm ID</dt><dd class="col-sm-9"><?= e($f['farm_id']) ?></dd>
            <dt class="col-sm-3">Farm Name</dt><dd class="col-sm-9"><?= e($f['farm_name']) ?></dd>
            <dt class="col-sm-3">Owner</dt>
            <dd class="col-sm-9"><a href="<?= e(url('modules/farmers.php?action=view&id=' . $f['farmer_id'])) ?>"><?= e($f['first_name'] . ' ' . $f['last_name']) ?></a></dd>
            <dt class="col-sm-3">Location</dt><dd class="col-sm-9"><?= e($f['location']) ?></dd>
            <dt class="col-sm-3">Area</dt><dd class="col-sm-9"><?= e(fmt_num($f['area_hectares'])) ?> hectares</dd>
            <dt class="col-sm-3">Soil Type</dt><dd class="col-sm-9"><?= e($f['soil_type_name']) ?></dd>
            <dt class="col-sm-3">Irrigation</dt><dd class="col-sm-9"><?= e($f['irrigation_type_name']) ?></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($f['created_at'])) ?></dd>
            <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($f['updated_at'])) ?></dd>
        </dl>
    </div></div>
    <div class="card mb-3">
        <div class="card-header">Crops planted on this farm (<?= count($plantings) ?>)</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Crop</th><th>Planted</th><th class="text-end">Area (ha)</th><th>Expected Harvest</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($plantings as $p): ?>
                    <tr>
                        <td><?= e($p['crop_name'] . ' (' . $p['variety'] . ')') ?></td>
                        <td><?= e(fmt_date($p['planting_date'])) ?></td>
                        <td class="text-end"><?= e(fmt_num($p['area_planted'])) ?></td>
                        <td><?= e(fmt_date($p['expected_harvest_date'])) ?></td>
                        <td><?= status_badge($p['status_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$plantings): ?><tr><td colspan="5" class="text-center text-muted">Nothing planted on this farm yet.</td></tr><?php endif; ?>
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
add_search($where, $params, ['fm.farm_name', 'fm.location', 'fr.first_name', 'fr.last_name', "fr.first_name || ' ' || fr.last_name"], $_GET['q'] ?? '');
foreach (['irrigation_type_id' => 'fm.irrigation_type_id', 'soil_type_id' => 'fm.soil_type_id', 'farmer_id' => 'fm.farmer_id'] as $get => $col) {
    if (($_GET[$get] ?? '') !== '' && ctype_digit((string)$_GET[$get])) {
        $where[] = "$col = ?";
        $params[] = (int)$_GET[$get];
    }
}

$list = list_query(
    FARM_SELECT, FARM_FROM, $where, $params,
    ['id' => 'fm.farm_id', 'name' => 'fm.farm_name', 'owner' => 'fr.last_name', 'location' => 'fm.location',
     'area' => 'fm.area_hectares', 'soil' => 's.soil_type_name', 'irrigation' => 'i.irrigation_type_name'],
    'id'
);

render_header('Farms', 'farms');
page_title('Farms', url('modules/farms.php?action=create'), 'Add Farm');
?>
<div class="card mb-3"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search farm, location, owner…"
                   value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search farms">
        </div>
        <?php
        f_filter_select('irrigation_type_id', 'All irrigation types', lookup('irrigation_type', 'irrigation_type_id', 'irrigation_type_name'));
        f_filter_select('soil_type_id', 'All soil types', lookup('soil_type', 'soil_type_id', 'soil_type_name'));
        ?>
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
                <?= sort_th('Farm', 'name', $list) ?>
                <?= sort_th('Owner', 'owner', $list) ?>
                <?= sort_th('Location', 'location', $list) ?>
                <?= sort_th('Area (ha)', 'area', $list) ?>
                <?= sort_th('Soil', 'soil', $list) ?>
                <?= sort_th('Irrigation', 'irrigation', $list) ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['farm_id']) ?></td>
                    <td><?= e($r['farm_name']) ?></td>
                    <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
                    <td><?= e($r['location']) ?></td>
                    <td class="text-end"><?= e(fmt_num($r['area_hectares'])) ?></td>
                    <td><?= e($r['soil_type_name']) ?></td>
                    <td><?= e($r['irrigation_type_name']) ?></td>
                    <td class="text-end"><?php row_actions('farms', (int)$r['farm_id'], $r['farm_name']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list['rows']): ?><tr><td colspan="8" class="text-center text-muted py-4">No farms found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted"><?= e(list_summary($list)) ?></small>
        <?php render_pagination($list); ?>
    </div>
</div>
<?php render_footer();
