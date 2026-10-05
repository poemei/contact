<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-10-05 UTC] */

/**
 * Contact Controller
 */
final class contact extends controller
{
    private const LIFECYCLE_ACTIONS = [
        'install_sql',
        'update_sql',
        'delete_data',
    ];

    public function index(array $params = []): void
    {
        $model = $this->model('contact_model');
        $state = $model->databaseState();

        if ($state !== 'current') {
            http_response_code(503);
            $this->view('index', [
                'available' => false,
                'database_state' => $state,
                'departments' => [],
            ]);
            return;
        }

        $departments = $model->getActiveDepartments();
        $config = $model->getConfig();
        $available = $this->contactAvailable($config, $departments);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();

            if (trim((string) ($_POST['website'] ?? '')) !== '') {
                header('Location: /contact?sent=1');
                exit;
            }

            if (!$available) {
                http_response_code(503);
                $this->view('index', [
                    'available' => false,
                    'database_state' => 'current',
                    'departments' => $departments,
                ]);
                return;
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $departmentSlug = strtolower(trim((string) ($_POST['department'] ?? '')));
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));
            $department = $model->getActiveDepartment($departmentSlug);

            if (
                $name === ''
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || $subject === ''
                || $message === ''
                || !is_array($department)
            ) {
                $this->rejectPublicSubmission(
                    $departments,
                    'Please complete all required fields and select a valid department.'
                );
            }

            if (
                $this->containsUrl($name)
                || $this->containsUrl($subject)
                || $this->containsUrl($message)
            ) {
                $this->rejectPublicSubmission(
                    $departments,
                    'Links are not permitted in contact submissions.'
                );
            }

            if (mb_strlen($message) < 20) {
                $this->rejectPublicSubmission(
                    $departments,
                    'Please provide a more complete message.'
                );
            }

            if (preg_match('/[A-Za-z]{2,}/', $message) !== 1) {
                $this->rejectPublicSubmission(
                    $departments,
                    'Please provide a valid message.'
                );
            }

            $model->createInquiry([
                'name' => $name,
                'email' => $email,
                'department' => (string) $department['slug'],
                'subject' => $subject,
                'message' => $message,
                'min_level' => 1,
            ]);

            $this->sendInternalNotification($department, $name, $email, $subject, $message);
            $this->sendConfirmation($config, $department, $name, $email);

            header('Location: /contact?sent=1');
            exit;
        }

        $this->view('index', [
            'available' => $available,
            'departments' => $departments,
        ]);
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(1);

        $model = $this->model('contact_model');
        $state = $model->databaseState();
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $action = strtolower(trim((string) ($_POST['action'] ?? '')));

            try {
                if (in_array($action, self::LIFECYCLE_ACTIONS, true)) {
                    $this->handleLifecycleAction($model, $state, $action);
                }

                if ($state !== 'current') {
                    throw new RuntimeException(
                        'Complete the database lifecycle action first.'
                    );
                }

                if ($action === 'save_config') {
                    $this->saveConfig($model);
                    $this->setAdminStatus('success', 'Acknowledgement Saved');
                    $this->redirectAdmin();
                }

                if ($action === 'save_ticket') {
                    $status = $this->saveTicket($model);
                    $this->setAdminStatus('success', $status);
                    $this->redirectAdmin();
                }

                if ($action === 'delete_ticket') {
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id < 1) {
                        throw new RuntimeException('Invalid Contact inquiry ID.');
                    }
                    $model->deleteTicket($id);
                    $this->setAdminStatus('success', 'Inquiry Deleted');
                    $this->redirectAdmin();
                }

                if ($action === 'add_department') {
                    [$slug, $name, $emailAddress, $active, $sortOrder] = $this->departmentInput();
                    $model->createDepartment($slug, $name, $emailAddress, $active, $sortOrder);
                    $this->setAdminStatus('success', 'Department Added');
                    $this->redirectAdmin();
                }

                if ($action === 'update_department') {
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id < 1) {
                        throw new RuntimeException('Invalid Contact department ID.');
                    }
                    [$slug, $name, $emailAddress, $active, $sortOrder] = $this->departmentInput();
                    $model->updateDepartment($id, $slug, $name, $emailAddress, $active, $sortOrder);
                    $this->setAdminStatus('success', 'Department Updated');
                    $this->redirectAdmin();
                }

                if ($action === 'delete_department') {
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id < 1) {
                        throw new RuntimeException('Invalid Contact department ID.');
                    }
                    $model->deleteDepartment($id);
                    $this->setAdminStatus('success', 'Department Deleted');
                    $this->redirectAdmin();
                }

                throw new InvalidArgumentException('Unsupported Contact Admin action.');
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = 'The Contact operation could not be completed.';
            }
        }

        $state = $model->databaseState();
        $data = [
            'module' => $model->getModuleInformation(),
            'database_state' => $state,
            'items' => [],
            'departments' => [],
            'config' => [],
            'status' => $this->consumeAdminStatus(),
            'error' => $error,
            'user_level' => (int) ($_SESSION['user_level'] ?? 0),
        ];

        if ($state === 'current') {
            $data['items'] = $model->getVisibleInquiries($data['user_level']);
            $data['departments'] = $model->getDepartments();
            $data['config'] = $model->getConfig();
        }

        $this->view('admin/index', $data);
    }

    private function handleLifecycleAction(
        contact_model $model,
        string $state,
        string $action
    ): never {
        if ($action === 'install_sql') {
            if ($state !== 'missing') {
                throw new RuntimeException('Schema is already installed.');
            }
            $model->installSchema();
            $this->setAdminStatus('success', 'SQL Installed');
            $this->redirectAdmin();
        }

        if ($action === 'update_sql') {
            if ($state !== 'update') {
                throw new RuntimeException('No schema update is pending.');
            }
            $model->updateSchema();
            $this->setAdminStatus('success', 'SQL Updated');
            $this->redirectAdmin();
        }

        if ($state !== 'current') {
            throw new RuntimeException('Complete the database lifecycle action first.');
        }

        if ($action === 'delete_data') {
            $model->deleteData();
            $this->setAdminStatus('success', 'Contact Data Deleted');
            $this->redirectAdmin();
        }

        throw new InvalidArgumentException('Invalid Contact lifecycle action.');
    }

    private function containsUrl(string $value): bool
    {
        return preg_match('~(?:https?://|www\.)~i', $value) === 1;
    }

    private function rejectPublicSubmission(array $departments, string $message): never
    {
        http_response_code(422);
        $this->view('index', [
            'available' => true,
            'departments' => $departments,
            'error' => $message,
        ]);
        exit;
    }

    private function saveConfig(contact_model $model): void
    {
        $subject = trim((string) ($_POST['confirmation_subject'] ?? ''));
        $message = trim((string) ($_POST['confirmation_message'] ?? ''));
        if ($subject === '' || $message === '') {
            throw new RuntimeException('Contact acknowledgement subject and message are required.');
        }
        $model->saveConfig($subject, $message);
    }

    private function saveTicket(contact_model $model): string
    {
        $ticketId = (int) ($_POST['id'] ?? 0);
        $reply = trim((string) ($_POST['reply_content'] ?? ''));
        $minLevel = max(1, min(10, (int) ($_POST['min_level'] ?? 1)));

        if ($ticketId < 1) {
            throw new RuntimeException('Invalid Contact inquiry ID.');
        }

        $model->updateTicket($ticketId, [
            'min_level' => $minLevel,
            'reply_content' => $reply,
            'status' => $reply !== '' ? 'replied' : 'new',
        ]);

        if ($reply === '') {
            return 'Inquiry Saved';
        }

        $ticket = $model->getInquiry($ticketId);
        if (!is_array($ticket)) {
            throw new RuntimeException('Message Send Failed: inquiry could not be reloaded.');
        }

        $department = $model->getActiveDepartment((string) $ticket['department']);
        try {
            $mail = (new mailer())->create();
            $mail->addAddress((string) $ticket['email'], (string) $ticket['name']);
            if (is_array($department)) {
                $mail->addReplyTo((string) $department['email_address'], (string) $department['name']);
            }
            $mail->isHTML(false);
            $mail->Subject = 'Re: ' . (string) $ticket['subject'];
            $mail->Body = $reply;
            $mail->send();
        } catch (Throwable $exception) {
            $detail = trim($exception->getMessage());
            throw new RuntimeException(
                'Message Send Failed: ' . ($detail !== '' ? $detail : 'Mail delivery failed without an error message.'),
                0,
                $exception
            );
        }
        return 'Message Sent';
    }

    private function departmentInput(): array
    {
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email_address'] ?? ''));
        $active = isset($_POST['active']) && (string) $_POST['active'] === '1';
        $sort = max(0, (int) ($_POST['sort_order'] ?? 0));

        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $slug) !== 1) {
            throw new RuntimeException('Department slug must contain only lowercase letters, numbers, hyphens, or underscores.');
        }
        if ($name === '') {
            throw new RuntimeException('Department name is required.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Department email address is invalid.');
        }
        return [$slug, $name, $email, $active, $sort];
    }

    private function contactAvailable(array $config, array $departments): bool
    {
        return $departments !== []
            && trim((string) ($config['confirmation_subject'] ?? '')) !== ''
            && trim((string) ($config['confirmation_message'] ?? '')) !== ''
            && $this->mailerAvailable();
    }

    private function mailerAvailable(): bool
    {
        try {
            (new mailer())->create();
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function sendInternalNotification(
        array $department,
        string $name,
        string $email,
        string $subject,
        string $message
    ): void {
        try {
            $mail = (new mailer())->create();
            $mail->addAddress((string) $department['email_address'], (string) $department['name']);
            $mail->addReplyTo($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'NEW CONTACT: ' . $subject;
            $mail->Body = '<div style="font-family:sans-serif;padding:20px;color:#333">'
                . '<h2>New Contact Inquiry</h2><p><strong>From:</strong> '
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' ('
                . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . ')</p>'
                . '<p><strong>Department:</strong> '
                . htmlspecialchars((string) $department['name'], ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Subject:</strong> '
                . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p><hr><div>'
                . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
                . '</div><hr><p><a href="'
                . htmlspecialchars((string) URLROOT, ENT_QUOTES, 'UTF-8')
                . '/admin/contact">Open Contact Admin</a></p></div>';
            $mail->send();
        } catch (Exception $exception) {
            // Stored inquiry remains authoritative if mail delivery fails.
        }
    }

    private function sendConfirmation(
        array $config,
        array $department,
        string $name,
        string $email
    ): void {
        try {
            $mail = (new mailer())->create();
            $mail->addAddress($email, $name);
            $mail->addReplyTo((string) $department['email_address'], (string) $department['name']);
            $mail->isHTML(false);
            $mail->Subject = (string) $config['confirmation_subject'];
            $mail->Body = (string) $config['confirmation_message'];
            $mail->send();
        } catch (Exception $exception) {
            // Stored inquiry remains authoritative if mail delivery fails.
        }
    }

    private function setAdminStatus(string $type, string $message): void
    {
        $_SESSION['contact_admin_status'] = ['type' => $type, 'message' => $message];
    }

    private function consumeAdminStatus(): ?array
    {
        $status = $_SESSION['contact_admin_status'] ?? null;
        unset($_SESSION['contact_admin_status']);
        if (!is_array($status) || trim((string) ($status['message'] ?? '')) === '') {
            return null;
        }
        return [
            'type' => (string) ($status['type'] ?? 'info'),
            'message' => trim((string) $status['message']),
        ];
    }

    private function redirectAdmin(): never
    {
        header('Location: /admin/contact');
        exit;
    }
}

/* [End AI:GPT-5.6 Sol] */
