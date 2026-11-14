<?php

require __DIR__ . '/../includes/bootstrap.php';
require_login();

$db = db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$self = url('modules/crops.php');

const CROP_SELECT = 'c.crop_id, c.crop_type_id, ct.crop_name, c.variety, ct.category_id, c.growing_period_days, c.season_id,
                     c.created_at, c.updated_at, cc.category_name, se.season_name';
const CROP_FROM   = 'crop c JOIN crop_type ct ON ct.crop_type_id = c.crop_type_id
                          JOIN crop_category cc ON cc.category_id = ct.category_id
                          JOIN season se ON se.season_id = c.season_id';

function load_crop(int $id): array
{
    $st = db()->prepare('SELECT ' . CROP_SELECT . ' FROM ' . CROP_FROM . ' WHERE c.crop_id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Crop not found.');
        redirect('modules/crops.php');
    }
    return $row;
}

function delete_crop_type_if_unused(PDO $db, int $cropTypeId): void
{
    $db->prepare(
        'DELETE FROM crop_type
          WHERE crop_type_id = ?
            AND NOT EXISTS (SELECT 1 FROM crop WHERE crop_type_id = ?)'
    )->execute([$cropTypeId, $cropTypeId]);
}

// ===================================================================== DELETE
if ($action === 'delete') {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('modules/crops.php');
    }
    csrf_verify();
    $row = load_crop($id);
    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM crop WHERE crop_id = ?')->execute([$id]);
        delete_crop_type_if_unused($db, (int)$row['crop_type_id']);
        $db->commit();
        flash('success', 'Crop "' . $row['crop_name'] . ' (' . $row['variety'] . ')" was deleted successfully.');
    } catch (PDOException $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash('danger', db_error_message($ex, 'This crop cannot be deleted because it is used in planting records.'));
    }
    redirect('modules/crops.php');
}

// ============================================================ CREATE / EDIT
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit';
    $form = ['crop_name' => '', 'category_id' => '', 'variety' => '', 'growing_period_days' => '', 'season_id' => ''];
    if ($editing) {
        $form = array_merge($form, load_crop($id));
    }
    $errors = [];
    $cropNames = lookup('crop_type', 'crop_type_id', 'crop_name');
    $categories = lookup('crop_category', 'category_id', 'category_name');
    $seasons = lookup('season', 'season_id', 'season_name');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $v = new Validator($_POST);
        $v->text('crop_name', 'Crop name', 1, 80);
        $v->exists('category_id', 'crop category', 'crop_category', 'category_id');
        $v->text('variety', 'Variety', 1, 80);
        $v->integer('growing_period_days', 'Growing period (days)', 1, 1000);
        $v->exists('season_id', 'recommended season', 'season', 'season_id');

        if (!$v->fails()) {
            try {
                $db->beginTransaction();
                $st = $db->prepare(
                    'SELECT ct.crop_type_id, ct.category_id, cc.category_name
                       FROM crop_type ct JOIN crop_category cc ON cc.category_id = ct.category_id
                      WHERE lower(ct.crop_name) = lower(?)'
                );
                $st->execute([$v->get('crop_name')]);
                $existingType = $st->fetch();

                if ($existingType && (int)$existingType['category_id'] !== (int)$v->get('category_id')) {
                    $v->add('category_id', 'This crop is already registered under the category ' . $existingType['category_name']);
                } else {
                    if ($existingType) {
                        $cropTypeId = (int)$existingType['crop_type_id'];
                    } else {
                        $userId = current_user_id();
                        $st = $db->prepare(
                            'INSERT INTO crop_type (crop_name, category_id, created_by, updated_by)
                             VALUES (?, ?, ?, ?) RETURNING crop_type_id'
                        );
                        $st->execute([$v->get('crop_name'), (int)$v->get('category_id'), $userId, $userId]);
                        $cropTypeId = (int)$st->fetchColumn();
                    }

                    $st = $db->prepare(
                        'SELECT 1 FROM crop
                          WHERE crop_type_id = ? AND lower(variety) = lower(?) AND crop_id <> ?'
                    );
                    $st->execute([$cropTypeId, $v->get('variety'), $editing ? $id : 0]);
                    if ($st->fetchColumn()) {
                        $v->add('variety', 'This crop and variety already exist.');
                    } else {
                        $userId = current_user_id();
                        $values = [$cropTypeId, $v->get('variety'), (int)$v->get('growing_period_days'),
                                   (int)$v->get('season_id'), $userId];
                        if ($editing) {
                            $oldCropTypeId = (int)$form['crop_type_id'];
                            $db->prepare('UPDATE crop SET crop_type_id=?, variety=?, growing_period_days=?,
                                                 season_id=?, updated_by=? WHERE crop_id=?')
                                ->execute(array_merge($values, [$id]));
                            if ($oldCropTypeId !== $cropTypeId) {
                                delete_crop_type_if_unused($db, $oldCropTypeId);
                            }
                            $successMessage = 'Crop was updated successfully.';
                        } else {
                            $db->prepare('INSERT INTO crop (crop_type_id, variety, growing_period_days, season_id, updated_by, created_by)
                                          VALUES (?,?,?,?,?,?)')->execute(array_merge($values, [$userId]));
                            $successMessage = 'Crop was added successfully.';
                        }
                        $db->commit();
                        flash('success', $successMessage);
                        redirect('modules/crops.php');
                    }
                }

                if ($v->fails() && $db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (PDOException $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                flash('danger', db_error_message($ex));
            }
        }
        if ($v->fails()) {
            flash('danger', 'Please fix the errors below.');
        }
        $errors = $v->errors;
        $form = array_merge($form, array_map('trim', array_filter($_POST, 'is_string')));
    }

    render_header($editing ? 'Edit Crop' : 'Add Crop', 'crops');
    page_title($editing ? 'Edit Crop' : 'Add Crop');
    ?>
    <div class="card"><div class="card-body">
        <form method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <?php
            f_input(['name' => 'crop_name', 'label' => 'Crop Name', 'value' => $form['crop_name'], 'required' => true,
                     'attrs' => ['maxlength' => 80, 'list' => 'crop_name_options']], $errors);
            ?>
            <datalist id="crop_name_options">
                <?php foreach (array_unique($cropNames) as $cropName): ?>
                    <option value="<?= e($cropName) ?>">
                <?php endforeach; ?>
            </datalist>
            <?php
            f_input(['name' => 'variety', 'label' => 'Variety', 'value' => $form['variety'], 'required' => true, 'attrs' => ['maxlength' => 80]], $errors);
            f_select(['name' => 'category_id', 'label' => 'Crop Category', 'value' => $form['category_id'], 'choices' => $categories, 'required' => true], $errors);
            f_input(['name' => 'growing_period_days', 'label' => 'Growing Period (days)', 'type' => 'number', 'value' => $form['growing_period_days'],
                     'required' => true, 'attrs' => ['min' => 1, 'max' => 1000, 'step' => 1], 'help' => 'Number of days from planting to harvest (1–1000).'], $errors);
            f_select(['name' => 'season_id', 'label' => 'Recommended Season', 'value' => $form['season_id'], 'choices' => $seasons, 'required' => true], $errors);
            f_buttons($self, $editing ? 'Update Crop' : 'Save Crop');
            ?>
        </form>
    </div></div>
    <?php
    render_footer();
    exit;
}

// ======================================================================= VIEW
if ($action === 'view') {
    $c = load_crop($id);
    $st = $db->prepare(
        'SELECT p.planting_id, p.planting_date, p.area_planted, p.expected_harvest_date, f.farm_name, s.status_name
           FROM planting p JOIN farm f ON f.farm_id = p.farm_id
           JOIN planting_status s ON s.status_id = p.status_id
          WHERE p.crop_id = ? ORDER BY p.planting_date DESC LIMIT 20'
    );
    $st->execute([$id]);
    $plantings = $st->fetchAll();

    render_header('Crop Details', 'crops');
    page_title('Crop Details');
    ?>
    <div class="card mb-3"><div class="card-body">
        <dl class="row detail-list mb-0">
            <dt class="col-sm-3">Crop ID</dt><dd class="col-sm-9"><?= e($c['crop_id']) ?></dd>
            <dt class="col-sm-3">Crop Name</dt><dd class="col-sm-9"><?= e($c['crop_name']) ?></dd>
            <dt class="col-sm-3">Variety</dt><dd class="col-sm-9"><?= e($c['variety']) ?></dd>
            <dt class="col-sm-3">Category</dt><dd class="col-sm-9"><?= e($c['category_name']) ?></dd>
            <dt class="col-sm-3">Growing Period</dt><dd class="col-sm-9"><?= e($c['growing_period_days']) ?> days</dd>
            <dt class="col-sm-3">Recommended Season</dt><dd class="col-sm-9"><?= e($c['season_name']) ?></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($c['created_at'])) ?></dd>
            <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($c['updated_at'])) ?></dd>
        </dl>
    </div></div>
    <div class="card mb-3">
        <div class="card-header">Planting records for this crop (latest 20)</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Farm</th><th>Planted</th><th class="text-end">Area (ha)</th><th>Expected Harvest</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($plantings as $p): ?>
                    <tr>
                        <td><?= e($p['farm_name']) ?></td>
                        <td><?= e(fmt_date($p['planting_date'])) ?></td>
                        <td class="text-end"><?= e(fmt_num($p['area_planted'])) ?></td>
                        <td><?= e(fmt_date($p['expected_harvest_date'])) ?></td>
                        <td><?= status_badge($p['status_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$plantings): ?><tr><td colspan="5" class="text-center text-muted">This crop has not been planted yet.</td></tr><?php endif; ?>
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
add_search($where, $params, ['ct.crop_name', 'c.variety'], $_GET['q'] ?? '');
if (($_GET['category_id'] ?? '') !== '' && ctype_digit((string)$_GET['category_id'])) {
    $where[] = 'ct.category_id = ?';
    $params[] = (int)$_GET['category_id'];
}
if (($_GET['season_id'] ?? '') !== '' && ctype_digit((string)$_GET['season_id'])) {
    $where[] = 'c.season_id = ?';
    $params[] = (int)$_GET['season_id'];
}

$list = list_query(
    CROP_SELECT, CROP_FROM, $where, $params,
    ['id' => 'c.crop_id', 'name' => 'ct.crop_name', 'variety' => 'c.variety', 'category' => 'cc.category_name',
     'days' => 'c.growing_period_days', 'season' => 'se.season_name'],
    'id'
);

render_header('Crops', 'crops');
page_title('Crops', url('modules/crops.php?action=create'), 'Add Crop');
?>
<div class="card mb-3"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search crop or variety…"
                   value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search crops">
        </div>
        <?php
        f_filter_select('category_id', 'All categories', lookup('crop_category', 'category_id', 'category_name'));
        f_filter_select('season_id', 'All seasons', lookup('season', 'season_id', 'season_name'));
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
                <?= sort_th('Crop', 'name', $list) ?>
                <?= sort_th('Variety', 'variety', $list) ?>
                <?= sort_th('Category', 'category', $list) ?>
                <?= sort_th('Growing Days', 'days', $list) ?>
                <?= sort_th('Season', 'season', $list) ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['crop_id']) ?></td>
                    <td><?= e($r['crop_name']) ?></td>
                    <td><?= e($r['variety']) ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td><?= e($r['growing_period_days']) ?></td>
                    <td><?= e($r['season_name']) ?></td>
                    <td class="text-end"><?php row_actions('crops', (int)$r['crop_id'], $r['crop_name'] . ' (' . $r['variety'] . ')'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">No crops found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted"><?= e(list_summary($list)) ?></small>
        <?php render_pagination($list); ?>
    </div>
</div>
<?php render_footer();
