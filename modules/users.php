<?php

require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$db = db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$self = url('modules/users.php');

function load_user(int $id): array
{
    $st = db()->prepare('SELECT u.user_id, u.username, u.full_name, u.role_id, u.is_active, u.created_at, r.role_name
                           FROM app_user u JOIN role r ON r.role_id = u.role_id WHERE u.user_id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'User not found.');
        redirect('modules/users.php');
    }
    return $row;
}

// ============================================================ CREATE / EDIT
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit';
    $form = ['username' => '', 'full_name' => '', 'role_id' => '', 'is_active' => '1'];
    if ($editing) {
        $form = array_merge($form, load_user($id));
        $form['is_active'] = $form['is_active'] ? '1' : '0';
    }
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $v = new Validator($_POST);
        $v->pattern('username', 'Username', '/^[A-Za-z0-9_.]{3,50}$/', 'Username must be 3–50 characters: letters, numbers, dot or underscore only.');
        $v->text('full_name', 'Full name', 1, 100);
        $v->exists('role_id', 'role', 'role', 'role_id');
        $v->inList('is_active', 'status', ['0', '1']);

        // Password: required when creating, optional when editing
        $password = $_POST['password'] ?? '';
        if (!$editing || $password !== '') {
            if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
                $v->add('password', 'Password must be at least 8 characters and contain letters and numbers.');
            } elseif (strlen($password) > 72) {
                $v->add('password', 'Password must not be longer than 72 characters.');
            }
        }

        if (!isset($v->errors['username'])) {
            $st = $db->prepare('SELECT 1 FROM app_user WHERE lower(username) = lower(?) AND user_id <> ?');
            $st->execute([$v->get('username'), $editing ? $id : 0]);
            if ($st->fetchColumn()) {
                $v->add('username', 'This username is already taken.');
            }
        }
        // Protect yourself: an admin cannot lock themselves out
        if ($editing && $id === current_user_id()) {
            if ($v->get('is_active') !== '1') {
                $v->add('is_active', 'You cannot deactivate your own account.');
            }
            if (!isset($v->errors['role_id']) && (int)$v->get('role_id') !== 1) {
                $v->add('role_id', 'You cannot remove your own Administrator role.');
            }
        }

        if (!$v->fails()) {
            try {
                $active = $v->get('is_active') === '1';
                if ($editing) {
                    $sql = 'UPDATE app_user SET username=?, full_name=?, role_id=?, is_active=?';
                    $args = [$v->get('username'), $v->get('full_name'), (int)$v->get('role_id'), $active ? 'true' : 'false'];
                    if ($password !== '') {
                        $sql .= ', password_hash=?';
                        $args[] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    $db->prepare($sql . ' WHERE user_id=?')->execute(array_merge($args, [$id]));
                    flash('success', 'User was updated successfully.');
                } else {
                    $db->prepare('INSERT INTO app_user (username, password_hash, full_name, role_id, is_active) VALUES (?,?,?,?,?)')
                       ->execute([$v->get('username'), password_hash($password, PASSWORD_BCRYPT), $v->get('full_name'),
                                  (int)$v->get('role_id'), $active ? 'true' : 'false']);
                    flash('success', 'User was added successfully.');
                }
                redirect('modules/users.php');
            } catch (PDOException $ex) {
                flash('danger', db_error_message($ex));
            }
        } else {
            flash('danger', 'Please fix the errors below.');
        }
        $errors = $v->errors;
        $form = array_merge($form, array_map('trim', array_filter($_POST, 'is_string')));
    }

    render_header($editing ? 'Edit User' : 'Add User', 'users');
    page_title($editing ? 'Edit User' : 'Add User');
    ?>
    <div class="card"><div class="card-body">
        <form method="post" class="row g-3" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <?php
            f_input(['name' => 'username', 'label' => 'Username', 'value' => $form['username'], 'required' => true, 'attrs' => ['maxlength' => 50, 'autocomplete' => 'off']], $errors);
            f_input(['name' => 'full_name', 'label' => 'Full Name', 'value' => $form['full_name'], 'required' => true, 'attrs' => ['maxlength' => 100]], $errors);
            f_select(['name' => 'role_id', 'label' => 'Role', 'value' => $form['role_id'], 'choices' => lookup('role', 'role_id', 'role_name'), 'required' => true,
                      'help' => 'Administrator: full access. Staff: can add, view and edit, but cannot delete or manage users.'], $errors);
            f_select(['name' => 'is_active', 'label' => 'Account Status', 'value' => $form['is_active'], 'choices' => ['1' => 'Active', '0' => 'Inactive (cannot log in)'], 'required' => true, 'placeholder' => '-- Select --'], $errors);
            f_input(['name' => 'password', 'label' => $editing ? 'New Password' : 'Password', 'type' => 'password', 'value' => '', 'required' => !$editing,
                     'attrs' => ['maxlength' => 72, 'autocomplete' => 'new-password'],
                     'help' => $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters, with letters and numbers.'], $errors);
            f_buttons($self, $editing ? 'Update User' : 'Save User');
            ?>
        </form>
    </div></div>
    <?php
    render_footer();
    exit;
}

// ======================================================================= LIST
$where = [];
$params = [];
add_search($where, $params, ['u.username', 'u.full_name'], $_GET['q'] ?? '');
if (($_GET['role_id'] ?? '') !== '' && ctype_digit((string)$_GET['role_id'])) {
    $where[] = 'u.role_id = ?';
    $params[] = (int)$_GET['role_id'];
}
$list = list_query(
    'u.user_id, u.username, u.full_name, u.is_active, u.created_at, r.role_name',
    'app_user u JOIN role r ON r.role_id = u.role_id',
    $where, $params,
    ['id' => 'u.user_id', 'username' => 'u.username', 'name' => 'u.full_name', 'role' => 'r.role_name', 'created' => 'u.created_at'],
    'id'
);

render_header('Users', 'users');
page_title('User Accounts', url('modules/users.php?action=create'), 'Add User');
?>
<div class="card mb-3"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search username or name…"
                   value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search users">
        </div>
        <?php f_filter_select('role_id', 'All roles', lookup('role', 'role_id', 'role_name')); ?>
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
                <?= sort_th('Username', 'username', $list) ?>
                <?= sort_th('Full Name', 'name', $list) ?>
                <?= sort_th('Role', 'role', $list) ?>
                <th scope="col">Status</th>
                <?= sort_th('Created', 'created', $list) ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['user_id']) ?></td>
                    <td><?= e($r['username']) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['role_name']) ?></td>
                    <td><?= $r['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                    <td><?= e(fmt_date($r['created_at'])) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e($self . '?action=edit&id=' . $r['user_id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted"><?= e(list_summary($list)) ?></small>
        <?php render_pagination($list); ?>
    </div>
</div>
<?php render_footer();
