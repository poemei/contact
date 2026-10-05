<?php
/* [AI:GPT-5.6 Sol | 2026-10-05 UTC] */

require APPROOT . '/views/inc/head.php';

$databaseState = (string) ($data['database_state'] ?? 'error');
if ($databaseState === 'invalid') {
    $databaseState = 'error';
}

$config = is_array($data['config'] ?? null) ? $data['config'] : [];
$status = is_array($data['status'] ?? null) ? $data['status'] : null;
$error = trim((string) ($data['error'] ?? ''));

if ($error !== '') {
    $status = ['type' => 'danger', 'message' => $error];
}

$statusType = is_array($status) ? (string) ($status['type'] ?? 'info') : 'info';
$statusMap = [
    'success' => 'success',
    'warning' => 'warning',
    'danger' => 'danger',
    'error' => 'danger',
    'failure' => 'danger',
    'info' => 'info',
];
$statusType = $statusMap[$statusType] ?? 'info';
$statusMessage = is_array($status) ? trim((string) ($status['message'] ?? '')) : '';
?>
<div class="container mt-3 mb-5">
    <div class="mb-3">
        <h2 class="h5 mb-1">Contact Management</h2>
        <div class="small text-secondary">
            Database state: <?= htmlspecialchars($databaseState, ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <?php if ($statusMessage !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($databaseState === 'missing'): ?>
        <div class="alert alert-warning">Contact database schema is not installed.</div>
        <form method="post">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="action" value="install_sql">
            <button type="submit" class="btn btn-primary">Install SQL</button>
        </form>
    <?php elseif ($databaseState === 'update'): ?>
        <div class="alert alert-warning">
            Contact database schema requires a packaged migration before normal operations can continue.
        </div>
        <form method="post">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="action" value="update_sql">
            <button type="submit" class="btn btn-primary">Update SQL</button>
        </form>
    <?php elseif ($databaseState === 'error'): ?>
        <div class="alert alert-danger">
            Contact detected an unsupported or incomplete database schema. No normal data operations were executed.
        </div>
    <?php else: ?>
        <ul class="nav nav-tabs mb-4" id="contact-admin-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="inquiries-tab" data-bs-toggle="tab" data-bs-target="#inquiries" type="button" role="tab" aria-controls="inquiries" aria-selected="true">Inquiries</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab" aria-controls="departments" aria-selected="false">Departments</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="configuration-tab" data-bs-toggle="tab" data-bs-target="#configuration" type="button" role="tab" aria-controls="configuration" aria-selected="false">Configuration</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lifecycle-tab" data-bs-toggle="tab" data-bs-target="#lifecycle" type="button" role="tab" aria-controls="lifecycle" aria-selected="false">Lifecycle</button>
            </li>
        </ul>

        <div class="tab-content" id="contact-admin-tab-content">
            <div class="tab-pane fade show active" id="inquiries" role="tabpanel" aria-labelledby="inquiries-tab" tabindex="0">
                <div class="card border-secondary mb-4">
                    <div class="card-body">
                        <h3 class="h6">Inquiries (Level <?= (int) ($data['user_level'] ?? 0) ?>)</h3>

                        <?php if (empty($data['items'])): ?>
                            <p class="text-secondary mb-0">No visible inquiries.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-dark table-hover border-secondary align-middle">
                                    <thead>
                                        <tr class="text-secondary small">
                                            <th>Date</th>
                                            <th>From</th>
                                            <th>Subject &amp; Message</th>
                                            <th>Lv</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($data['items'] as $item): ?>
                                        <tr>
                                            <td class="small text-secondary"><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                <small class="text-secondary"><?= htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8') ?></small>
                                            </td>
                                            <td>
                                                <div class="mb-1"><strong><?= htmlspecialchars((string) $item['subject'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                                                <div class="small text-secondary"><?= nl2br(htmlspecialchars((string) $item['message'], ENT_QUOTES, 'UTF-8')) ?></div>

                                                <form method="post" class="mt-2 border-top border-secondary pt-2">
                                                    <?= $this->csrf_field() ?>
                                                    <input type="hidden" name="action" value="save_ticket">
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                    <div class="d-flex gap-2">
                                                        <textarea name="reply_content" class="form-control form-control-sm bg-black text-white border-secondary" placeholder="Reply to the end user..."><?= htmlspecialchars((string) ($item['reply_content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        <div style="width: 120px;">
                                                            <select name="min_level" class="form-control form-control-sm bg-black text-white border-secondary mb-1">
                                                                <?php for ($level = 1; $level <= 10; $level++): ?>
                                                                    <option value="<?= $level ?>" <?= ((int) $item['min_level'] === $level) ? 'selected' : '' ?>>Lv. <?= $level ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-success w-100">Save &amp; Send</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-text">The saved reply text is sent directly to this inquiry's end user.</div>
                                                </form>
                                            </td>
                                            <td class="text-center"><span class="badge border border-secondary"><?= (int) $item['min_level'] ?></span></td>
                                            <td class="text-center">
                                                <form method="post" onsubmit="return confirm('Delete this inquiry?');">
                                                    <?= $this->csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_ticket">
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none" aria-label="Delete inquiry">×</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="departments" role="tabpanel" aria-labelledby="departments-tab" tabindex="0">
                <div class="card border-secondary mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h6 mb-0">Departments</h3>
                            <span class="small text-secondary">Public dropdown and internal routing</span>
                        </div>

                        <form method="post" class="row g-2 align-items-end mb-4">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="action" value="add_department">
                            <div class="col-md-2"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" placeholder="support" required></div>
                            <div class="col-md-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" placeholder="General Support" required></div>
                            <div class="col-md-3"><label class="form-label">Department Email</label><input type="email" name="email_address" class="form-control" placeholder="support@example.com" required></div>
                            <div class="col-md-1"><label class="form-label">Sort</label><input type="number" name="sort_order" class="form-control" min="0" value="0"></div>
                            <div class="col-md-1"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="active" value="1" id="new-department-active" checked><label class="form-check-label" for="new-department-active">Active</label></div></div>
                            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">Add</button></div>
                        </form>

                        <?php if (empty($data['departments'])): ?>
                            <p class="text-secondary mb-0">No departments configured.</p>
                        <?php else: ?>
                            <?php foreach ($data['departments'] as $department): ?>
                                <form method="post" class="row g-2 align-items-center border-top border-secondary py-2">
                                    <?= $this->csrf_field() ?>
                                    <input type="hidden" name="action" value="update_department">
                                    <input type="hidden" name="id" value="<?= (int) $department['id'] ?>">
                                    <div class="col-md-2"><input type="text" name="slug" class="form-control form-control-sm" value="<?= htmlspecialchars((string) $department['slug'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                    <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars((string) $department['name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                    <div class="col-md-3"><input type="email" name="email_address" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($department['email_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                    <div class="col-md-1"><input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="<?= (int) $department['sort_order'] ?>"></div>
                                    <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" <?= ((int) $department['active'] === 1) ? 'checked' : '' ?>></div></div>
                                    <div class="col-md-1"><button type="submit" class="btn btn-sm btn-outline-success w-100">Save</button></div>
                                    <div class="col-md-1"><button type="submit" name="action" value="delete_department" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Delete this department?');">×</button></div>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="configuration" role="tabpanel" aria-labelledby="configuration-tab" tabindex="0">
                <div class="card border-secondary mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h6 mb-0">End User Acknowledgement</h3>
                            <span class="small text-secondary">Delivery uses ChAoS MVC mailer::create()</span>
                        </div>
                        <p class="small text-secondary">SMTP transport and the From identity are installation-level settings managed by ChAoS MVC. Contact stores only the acknowledgement content sent to the end user.</p>
                        <form method="post">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="action" value="save_config">
                            <div class="mb-3"><label for="confirmation-subject" class="form-label">End User Confirmation Subject</label><input id="confirmation-subject" type="text" name="confirmation_subject" class="form-control" value="<?= htmlspecialchars((string) ($config['confirmation_subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                            <div class="mb-3"><label for="confirmation-message" class="form-label">End User Confirmation Message</label><textarea id="confirmation-message" name="confirmation_message" class="form-control" rows="7" required><?= htmlspecialchars((string) ($config['confirmation_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea><div class="form-text">The exact text saved here is sent to the end user after a successful submission.</div></div>
                            <button type="submit" class="btn btn-outline-primary">Save Acknowledgement</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="lifecycle" role="tabpanel" aria-labelledby="lifecycle-tab" tabindex="0">
                <div class="card border-danger mb-4">
                    <div class="card-body">
                        <h3 class="h6 text-danger">Data Lifecycle</h3>
                        <p class="small text-secondary">Delete Data removes stored Contact inquiries while preserving department routing, acknowledgement configuration, database schema, and module files. Nuke is handled by ChAoS MVC Core.</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <form method="post" onsubmit="return confirm('Delete all stored Contact inquiries?');">
                                <?= $this->csrf_field() ?>
                                <input type="hidden" name="action" value="delete_data">
                                <button type="submit" class="btn btn-outline-danger">Delete Data</button>
                            </form>
                            <form method="post" action="/admin/uninstall" onsubmit="return confirm('Nuke Contact and all verified module-owned tables?');">
                                <?= $this->csrf_field() ?>
                                <input type="hidden" name="module" value="contact">
                                <button type="submit" class="btn btn-danger">Nuke</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
require APPROOT . '/views/inc/foot.php';
/* [End AI:GPT-5.6 Sol] */
?>
