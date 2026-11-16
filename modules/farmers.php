<?php

require __DIR__ . '/../includes/bootstrap.php';
require_login();

$db = db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$self = url('modules/farmers.php');


function load_farmer(int $id): array
{
    $st = db()->prepare('SELECT * FROM farmer WHERE farmer_id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Farmer not found.');
        redirect('modules/farmers.php');
    }
    return $row;
}

// ===================================================================== DELETE
if ($action === 'delete') {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('modules/farmers.php');
    }
    csrf_verify();
    $row = load_farmer($id);
    try {
        $st = $db->prepare('DELETE FROM farmer WHERE farmer_id = ?');
        $st->execute([$id]);
        flash('success', 'Farmer "' . $row['first_name'] . ' ' . $row['last_name'] . '" was deleted successfully.');
    } catch (PDOException $ex) {
        flash('danger', db_error_message($ex, 'This farmer cannot be deleted because he/she still has farm records. Delete or reassign the farms first.'));
    }
    redirect('modules/farmers.php');
}

// ============================================================ CREATE / EDIT
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit';
    $form = ['first_name' => '', 'last_name' => '', 'contact_number' => '', 'address' => '',
             'date_registered' => date('Y-m-d')];
    if ($editing) {
        $form = array_merge($form, load_farmer($id));
    }
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $v = new Validator($_POST);
        $v->text('first_name', 'First name', 1, 50);
        $v->text('last_name', 'Last name', 1, 50);
        if ($v->pattern('contact_number', 'Contact number', '/^(09\d{9}|\+639\d{9})$/',
            'Contact number must look like 09171234567 or +639171234567.')) {
            $st = $db->prepare('SELECT 1 FROM farmer WHERE contact_number = ? AND farmer_id <> ?');
            $st->execute([$v->get('contact_number'), $editing ? $id : 0]);
            if ($st->fetchColumn()) {
                $v->add('contact_number', 'This contact number is already registered to another farmer.');
            }
        }
        $v->text('address', 'Address', 1, 150);
        $v->date('date_registered', 'Date registered', date('Y-m-d'));

        if (!$v->fails()) {
            try {
                $values = [$v->get('first_name'), $v->get('last_name'), $v->get('contact_number'),
                           $v->get('address'), $v->get('date_registered'), current_user_id()];
                if ($editing) {
                    $st = $db->prepare('UPDATE farmer SET first_name=?, last_name=?, contact_number=?, address=?,
                                               date_registered=?, updated_by=? WHERE farmer_id=?');
                    $st->execute(array_merge($values, [$id]));
                    flash('success', 'Farmer was updated successfully.');
                } else {
                    $st = $db->prepare('INSERT INTO farmer (first_name, last_name, contact_number, address,
                                               date_registered, created_by, updated_by) VALUES (?,?,?,?,?,?,?)');
                    $st->execute(array_merge($values, [current_user_id()]));
                    flash('success', 'Farmer was added successfully.');
                }
                redirect('modules/farmers.php');
            } catch (PDOException $ex) {
                flash('danger', db_error_message($ex));
            }
        } else {
            flash('danger', 'Please fix the errors below.');
        }
        $errors = $v->errors;
        $form = array_merge($form, array_map('trim', array_filter($_POST, 'is_string')));
    }

    render_header($editing ? 'Edit Farmer' : 'Add Farmer', 'farmers');
    page_title($editing ? 'Edit Farmer' : 'Add Farmer');
    ?>
    <div class="card"><div class="card-body">
        <form method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <?php
            f_input(['name' => 'first_name', 'label' => 'First Name', 'value' => $form['first_name'], 'required' => true, 'attrs' => ['maxlength' => 50]], $errors);
            f_input(['name' => 'last_name', 'label' => 'Last Name', 'value' => $form['last_name'], 'required' => true, 'attrs' => ['maxlength' => 50]], $errors);
            f_input(['name' => 'contact_number', 'label' => 'Contact Number', 'value' => $form['contact_number'], 'required' => true,
                     'attrs' => ['maxlength' => 13, 'placeholder' => '09171234567', 'inputmode' => 'tel'], 'help' => 'Philippine mobile number, e.g. 09171234567'], $errors);
            f_input(['name' => 'date_registered', 'label' => 'Date Registered', 'type' => 'date', 'value' => $form['date_registered'], 'required' => true,
                     'attrs' => ['max' => date('Y-m-d')]], $errors);
            f_input(['name' => 'address', 'label' => 'Address', 'value' => $form['address'], 'required' => true, 'col' => 'col-12', 'attrs' => ['maxlength' => 150]], $errors);
            f_buttons($self, $editing ? 'Update Farmer' : 'Save Farmer');
            ?>
        </form>
    </div></div>
    <?php
    render_footer();
    exit;
}

// ======================================================================= VIEW
if ($action === 'view') {
    $f = load_farmer($id);
    $st = $db->prepare(
        'SELECT fm.farm_id, fm.farm_name, fm.location, fm.area_hectares, s.soil_type_name, i.irrigation_type_name
           FROM farm fm
           JOIN soil_type s ON s.soil_type_id = fm.soil_type_id
           JOIN irrigation_type i ON i.irrigation_type_id = fm.irrigation_type_id
          WHERE fm.farmer_id = ? ORDER BY fm.farm_name'
    );
    $st->execute([$id]);
    $farms = $st->fetchAll();

    render_header('Farmer Details', 'farmers');
    page_title('Farmer Details');
    ?>
    <div class="card mb-3"><div class="card-body">
        <dl class="row detail-list mb-0">
            <dt class="col-sm-3">Farmer ID</dt><dd class="col-sm-9"><?= e($f['farmer_id']) ?></dd>
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= e($f['first_name'] . ' ' . $f['last_name']) ?></dd>
            <dt class="col-sm-3">Contact Number</dt><dd class="col-sm-9"><?= e($f['contact_number']) ?></dd>
            <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= e($f['address']) ?></dd>
            <dt class="col-sm-3">Date Registered</dt><dd class="col-sm-9"><?= e(fmt_date($f['date_registered'])) ?></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($f['created_at'])) ?></dd>
            <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($f['updated_at'])) ?></dd>
        </dl>
    </div></div>

    <div class="card mb-3">
        <div class="card-header">Farms owned (<?= count($farms) ?>)</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Farm</th><th>Location</th><th class="text-end">Area (ha)</th><th>Soil</th><th>Irrigation</th></tr></thead>
                <tbody>
                <?php foreach ($farms as $fm): ?>
                    <tr>
                        <td><a href="<?= e(url('modules/farms.php?action=view&id=' . $fm['farm_id'])) ?>"><?= e($fm['farm_name']) ?></a></td>
                        <td><?= e($fm['location']) ?></td>
                        <td class="text-end"><?= e(fmt_num($fm['area_hectares'])) ?></td>
                        <td><?= e($fm['soil_type_name']) ?></td>
                        <td><?= e($fm['irrigation_type_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$farms): ?><tr><td colspan="5" class="text-center text-muted">This farmer has no farms yet.</td></tr><?php endif; ?>
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
add_search($where, $params, ['fr.first_name', 'fr.last_name', "fr.first_name || ' ' || fr.last_name", 'fr.contact_number', 'fr.address'], $_GET['q'] ?? '');
if (($_GET['address'] ?? '') !== '') {
    $where[] = 'fr.address = ?';
    $params[] = $_GET['address'];
}

$list = list_query(
    'fr.farmer_id, fr.first_name, fr.last_name, fr.contact_number, fr.address, fr.date_registered,
     (SELECT COUNT(*) FROM farm x WHERE x.farmer_id = fr.farmer_id) AS farm_count',
    'farmer fr',
    $where, $params,
    ['id' => 'fr.farmer_id', 'name' => 'fr.last_name', 'contact' => 'fr.contact_number',
     'address' => 'fr.address', 'registered' => 'fr.date_registered', 'farms' => 'farm_count'],
    'id'
);
$addresses = choices('SELECT DISTINCT address AS id, address AS label FROM farmer ORDER BY address');

render_header('Farmers', 'farmers');
page_title('Farmers', url('modules/farmers.php?action=create'), 'Add Farmer');
?>
<div class="card mb-3"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search name, contact, address…"
                   value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search farmers">
        </div>
        <?php f_filter_select('address', 'All addresses', $addresses); ?>
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
                <?= sort_th('Name', 'name', $list) ?>
                <?= sort_th('Contact', 'contact', $list) ?>
                <?= sort_th('Address', 'address', $list) ?>
                <?= sort_th('Registered', 'registered', $list) ?>
                <?= sort_th('Farms', 'farms', $list) ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['farmer_id']) ?></td>
                    <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
                    <td><?= e($r['contact_number']) ?></td>
                    <td><?= e($r['address']) ?></td>
                    <td><?= e(fmt_date($r['date_registered'])) ?></td>
                    <td><?= e($r['farm_count']) ?></td>
                    <td class="text-end"><?php row_actions('farmers', (int)$r['farmer_id'], $r['first_name'] . ' ' . $r['last_name']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">No farmers found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted"><?= e(list_summary($list)) ?></small>
        <?php render_pagination($list); ?>
    </div>
</div>
<?php render_footer();
