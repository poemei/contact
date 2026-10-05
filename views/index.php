<?php
/* [AI:GPT-5.6 Sol | 2026-10-05 UTC] */
require APPROOT . '/views/inc/head.php';
?>
<div class="container my-5">
    <h2>Contact Us</h2>

    <?php if (($data['available'] ?? false) !== true): ?>
        <div class="alert alert-warning">
            Contact is temporarily unavailable. An administrator must complete its database, mail, and department configuration.
        </div>
    <?php else: ?>
        <?php if (isset($_GET['sent'])): ?>
            <p class="text-success">Your message has been received.</p>
        <?php endif; ?>

        <?php if (!empty($data['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars((string) $data['error'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" class="card border-secondary p-4">
            <?= $this->csrf_field() ?>

            <div
                aria-hidden="true"
                style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;"
            >
                <label for="contact-website">Website</label>
                <input
                    id="contact-website"
                    type="text"
                    name="website"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>

            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="mb-3">
                    <label for="contact-name">Name</label>
                    <input id="contact-name" type="text" name="name" class="form-control border-secondary" required>
                </div>

                <div class="mb-3">
                    <label for="contact-email">Email</label>
                    <input id="contact-email" type="email" name="email" class="form-control border-secondary" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="contact-department">Department</label>
                <select id="contact-department" name="department" class="form-control border-secondary" required>
                    <option value="">Select a department</option>
                    <?php foreach (($data['departments'] ?? []) as $department): ?>
                        <option value="<?= htmlspecialchars((string) $department['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $department['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="contact-subject">Subject</label>
                <input id="contact-subject" type="text" name="subject" class="form-control border-secondary" required>
            </div>

            <div class="mb-3">
                <label for="contact-message">Message</label>
                <textarea id="contact-message" name="message" class="form-control border-secondary" rows="6" required></textarea>
                <div class="form-text">Links are not permitted in contact submissions.</div>
            </div>

            <button type="submit" class="btn btn-outline-primary">Send Inquiry</button>
        </form>
    <?php endif; ?>
</div>
<?php
require APPROOT . '/views/inc/foot.php';
/* [End AI:GPT-5.6 Sol] */
?>
