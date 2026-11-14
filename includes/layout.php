<?php


function render_header(string $title, string $active = ''): void
{
    $user = current_user();
    $nav = [
        'dashboard' => ['Dashboard', 'dashboard.php', 'bi-speedometer2'],
        'farmers'   => ['Farmers', 'modules/farmers.php', 'bi-people'],
        'farms'     => ['Farms', 'modules/farms.php', 'bi-geo-alt'],
        'crops'     => ['Crops', 'modules/crops.php', 'bi-flower1'],
        'plantings' => ['Planting', 'modules/plantings.php', 'bi-database-add'],
        'harvests'  => ['Harvest', 'modules/harvests.php', 'bi-basket'],
        'reports'   => ['Reports', 'modules/reports.php', 'bi-bar-chart-line'],
    ];
    if (is_admin()) {
        $nav['users'] = ['Users', 'modules/users.php', 'bi-person-gear'];
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Agriculture Information System</title>
    <link rel="stylesheet" href="<?= e(url("assets/bootstrap/bootstrap/bootstrap.min.css")) ?>">
    <link rel="stylesheet" href="<?= e(url("assets/bootstrap/bootstrap-icons/bootstrap-icons.min.css")) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success-dark sticky-top no-print">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= e(url('dashboard.php')) ?>">
            <i class="bi bi-tree-fill"></i> AgriInfo System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($nav as $key => [$label, $link, $icon]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $key === $active ? ' active' : '' ?>"
                           <?= $key === $active ? 'aria-current="page"' : '' ?>
                           href="<?= e(url($link)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($user): ?>
                <div class="d-flex align-items-center gap-2 text-white">
                    <span class="small text-end">
                        <i class="bi bi-person-circle"></i> <?= e($user['full_name']) ?>
                        <span class="badge bg-light text-success ms-1"><?= e($user['role_name']) ?></span>
                    </span>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(url('logout.php')) ?>">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container-fluid py-4 px-3 px-lg-4">
    <?php foreach (take_flash() as $m): ?>
        <div class="alert alert-<?= e($m['type']) ?> alert-dismissible fade show no-print" role="alert">
            <?= e($m['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
<?php
}

/** @param string[] $scripts extra <script> tags (full HTML) to add before </body> */
function render_footer(array $scripts = []): void
{
    ?>
</main>

<!-- Delete confirmation dialog (shared by all modules) -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" id="deleteForm" action="">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="deleteModalLabel"><i class="bi bi-exclamation-triangle text-danger"></i> Confirm Delete</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to permanently delete <strong id="deleteName">this record</strong>?
                This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<footer class="text-center text-muted small py-3 no-print">
    Cebu Technological University &middot; AP3 Agriculture Information System
</footer>

<script src="<?= e(url("assets/vendor/bootstrap/bootstrap.bundle.min.js")) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php foreach ($scripts as $s) { echo $s, "\n"; } ?>
</body>
</html>
<?php
}
