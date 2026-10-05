<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-09-06 01:25:00 UTC] */
/* [AI:GPT-5.6 Sol | 2026-09-06 01:40:00 UTC] */
/* [AI:GPT-5.6 Sol | 2026-09-06 01:00:00 UTC] */

/**
 * Contact Controller
 */
class contact extends controller
{
    /**
     * Public contact form.
     */
    public function index(): void
    {
        $model = $this->model('contact_model');
        $databaseState = $model->database_state();

        if ($databaseState !== 'current') {
            http_response_code(503);
            $this->view(
                'contact/index',
                [
                    'available' => false,
                    'database_state' => $databaseState,
                    'departments' => [],
                ]
            );
            return;
        }

        $departments = $model->get_active_departments();
        $config = $model->get_config();
        $available = $this->contact_available($config, $departments);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->require_csrf();

            $honeypot = trim((string) ($_POST['website'] ?? ''));

            if ($honeypot !== '') {
                // Deliberately appear successful. Do not store or send anything.
                header('Location: /contact?sent=1');
                exit;
            }

            if (!$available) {
                http_response_code(503);
                $this->view(
                    'contact/index',
                    [
                        'available' => false,
                        'database_state' => 'current',
                        'departments' => $departments,
                    ]
                );
                return;
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $departmentSlug = strtolower(trim((string) ($_POST['department'] ?? '')));
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));
            $department = $model->get_active_department($departmentSlug);

            if (
                $name === ''
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || $subject === ''
                || $message === ''
                || !is_array($department)
            ) {
                http_response_code(422);
                $this->view(
                    'contact/index',
                    [
                        'available' => true,
                        'departments' => $departments,
                        'error' => 'Please complete all required fields and select a valid department.',
                    ]
                );
                return;
            }

            if (
                $this->contains_url($name)
                || $this->contains_url($subject)
                || $this->contains_url($message)
            ) {
                $this->reject_public_submission(
                    $departments,
                    'Links are not permitted in contact submissions.'
                );
            }

            if (mb_strlen($message) < 20) {
                $this->reject_public_submission(
                    $departments,
                    'Please provide a more complete message.'
                );
            }

            if (preg_match('/[A-Za-z]{2,}/', $message) !== 1) {
                $this->reject_public_submission(
                    $departments,
                    'Please provide a valid message.'
                );
            }

            $model->create_inquiry(
                [
                    'name' => $name,
                    'email' => $email,
                    'department' => (string) $department['slug'],
                    'subject' => $subject,
                    'message' => $message,
                    'min_level' => 1,
                ]
            );

            $this->send_internal_notification(
                $department,
                $name,
                $email,
                $subject,
                $message
            );

            $this->send_confirmation(
                $config,
                $department,
                $name,
                $email
            );

            header('Location: /contact?sent=1');
            exit;
        }

        $this->view(
            'contact/index',
            [
                'available' => $available,
                'departments' => $departments,
            ]
        );
    }

    /**
     * Admin dashboard and module-owned lifecycle controls.
     *
     * @param array<int, string> $params
     */
    public function admin($params = []): void
    {
        $this->require_admin(1);

        $model = $this->model('contact_model');
        $databaseState = $model->database_state();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->require_csrf();

                $action = strtolower(trim((string) ($_POST['action'] ?? '')));
                $allowedActions = [
                    'install_sql',
                    'update_sql',
                    'delete_data',
                    'save_config',
                    'save_ticket',
                    'delete_ticket',
                    'add_department',
                    'update_department',
                    'delete_department',
                ];

                if (!in_array($action, $allowedActions, true)) {
                    throw new RuntimeException('Unsupported Contact Admin action.');
                }

                if ($action === 'install_sql') {
                    $model->install_schema();
                    $this->set_admin_status('success', 'SQL Installed');
                    $this->redirect_admin();
                }

                if ($action === 'update_sql') {
                    $model->update_schema();
                    $this->set_admin_status('success', 'SQL Updated');
                    $this->redirect_admin();
                }

                if ($databaseState !== 'current') {
                    throw new RuntimeException(
                        'Contact database schema must be current before this action can run.'
                    );
                }

                if ($action === 'delete_data') {
                    $model->delete_data();
                    $this->set_admin_status('success', 'Contact Data Deleted');
                    $this->redirect_admin();
                }

                if ($action === 'save_config') {
                    $this->save_config($model);
                    $this->set_admin_status('success', 'Acknowledgement Saved');
                    $this->redirect_admin();
                }

                if ($action === 'save_ticket') {
                    $status = $this->save_ticket($model);
                    $this->set_admin_status('success', $status);
                    $this->redirect_admin();
                }

                if ($action === 'delete_ticket') {
                    $id = (int) ($_POST['id'] ?? 0);

                    if ($id < 1) {
                        throw new RuntimeException('Invalid Contact inquiry ID.');
                    }

                    $model->delete_ticket($id);
                    $this->set_admin_status('success', 'Inquiry Deleted');
                    $this->redirect_admin();
                }

                if ($action === 'add_department') {
                    [$slug, $name, $emailAddress, $active, $sortOrder] = $this->department_input();
                    $model->create_department($slug, $name, $emailAddress, $active, $sortOrder);
                    $this->set_admin_status('success', 'Department Added');
                    $this->redirect_admin();
                }

                if ($action === 'update_department') {
                    $id = (int) ($_POST['id'] ?? 0);

                    if ($id < 1) {
                        throw new RuntimeException('Invalid Contact department ID.');
                    }

                    [$slug, $name, $emailAddress, $active, $sortOrder] = $this->department_input();
                    $model->update_department($id, $slug, $name, $emailAddress, $active, $sortOrder);
                    $this->set_admin_status('success', 'Department Updated');
                    $this->redirect_admin();
                }

                if ($action === 'delete_department') {
                    $id = (int) ($_POST['id'] ?? 0);

                    if ($id < 1) {
                        throw new RuntimeException('Invalid Contact department ID.');
                    }

                    $model->delete_department($id);
                    $this->set_admin_status('success', 'Department Deleted');
                    $this->redirect_admin();
                }
            } catch (Throwable $exception) {
                $message = trim($exception->getMessage());

                if ($message === '') {
                    $message = 'Contact Admin action failed.';
                }

                $this->set_admin_status('danger', $message);
                $this->redirect_admin();
            }
        }

        $data = [
            'database_state' => $databaseState,
            'items' => [],
            'departments' => [],
            'config' => [],
            'status' => $this->consume_admin_status(),
            'user_level' => (int) ($_SESSION['user_level'] ?? 0),
        ];

        if ($databaseState === 'current') {
            $data['items'] = $model->get_visible_inquiries($data['user_level']);
            $data['departments'] = $model->get_departments();
            $data['config'] = $model->get_config();
        }

        $this->view('admin/contact', $data);
    }

    private function contains_url(string $value): bool
    {
        return preg_match('~(?:https?://|www\.)~i', $value) === 1;
    }

    /**
     * Reject a public Contact submission without storing or sending it.
     *
     * @param array<int, array<string, mixed>> $departments
     */
    private function reject_public_submission(array $departments, string $message): void
    {
        http_response_code(422);
        $this->view(
            'contact/index',
            [
                'available' => true,
                'departments' => $departments,
                'error' => $message,
            ]
        );
        exit;
    }

    private function save_config(contact_model $model): void
    {
        $confirmationSubject = trim((string) ($_POST['confirmation_subject'] ?? ''));
        $confirmationMessage = trim((string) ($_POST['confirmation_message'] ?? ''));

        if ($confirmationSubject === '') {
            throw new RuntimeException('Contact confirmation subject is required.');
        }

        if ($confirmationMessage === '') {
            throw new RuntimeException('Contact confirmation message is required.');
        }

        $model->save_config(
            $confirmationSubject,
            $confirmationMessage
        );
    }

    private function save_ticket(contact_model $model): string
    {
        $ticketId = (int) ($_POST['id'] ?? 0);
        $reply = trim((string) ($_POST['reply_content'] ?? ''));
        $minLevel = max(1, min(10, (int) ($_POST['min_level'] ?? 1)));

        if ($ticketId < 1) {
            throw new RuntimeException('Invalid Contact inquiry ID.');
        }

        $model->update_ticket(
            $ticketId,
            [
                'min_level' => $minLevel,
                'reply_content' => $reply,
                'status' => $reply !== '' ? 'replied' : 'new',
            ]
        );

        if ($reply === '') {
            return 'Inquiry Saved';
        }

        $ticket = $model->get_inquiry($ticketId);

        if (!is_array($ticket)) {
            throw new RuntimeException('Message Send Failed: inquiry could not be reloaded.');
        }

        $department = $model->get_active_department((string) $ticket['department']);

        try {
            $mail = (new mailer())->create();
            $mail->addAddress((string) $ticket['email'], (string) $ticket['name']);

            if (is_array($department)) {
                $mail->addReplyTo(
                    (string) $department['email_address'],
                    (string) $department['name']
                );
            }

            $mail->isHTML(false);
            $mail->Subject = 'Re: ' . (string) $ticket['subject'];
            $mail->Body = $reply;
            $mail->send();
        } catch (Throwable $exception) {
            $detail = trim($exception->getMessage());

            if ($detail === '') {
                $detail = 'Mail delivery failed without an error message.';
            }

            throw new RuntimeException(
                'Message Send Failed: ' . $detail,
                0,
                $exception
            );
        }

        return 'Message Sent';
    }

    /**
     * @return array{0:string,1:string,2:string,3:bool,4:int}
     */
    private function department_input(): array
    {
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $emailAddress = trim((string) ($_POST['email_address'] ?? ''));
        $active = isset($_POST['active']) && (string) $_POST['active'] === '1';
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $slug) !== 1) {
            throw new RuntimeException(
                'Department slug must contain only lowercase letters, numbers, hyphens, or underscores.'
            );
        }

        if ($name === '') {
            throw new RuntimeException('Department name is required.');
        }

        if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Department email address is invalid.');
        }

        return [$slug, $name, $emailAddress, $active, $sortOrder];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, array<string, mixed>> $departments
     */
    private function contact_available(array $config, array $departments): bool
    {
        return $departments !== []
            && trim((string) ($config['confirmation_subject'] ?? '')) !== ''
            && trim((string) ($config['confirmation_message'] ?? '')) !== ''
            && $this->mailer_available();
    }

    private function mailer_available(): bool
    {
        try {
            (new mailer())->create();
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $department
     */
    private function send_internal_notification(
        array $department,
        string $name,
        string $email,
        string $subject,
        string $message
    ): void {
        try {
            $mail = (new mailer())->create();
            $mail->addAddress(
                (string) $department['email_address'],
                (string) $department['name']
            );
            $mail->addReplyTo($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'NEW CONTACT: ' . $subject;
            $mail->Body = "
                <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                    <h2>New Contact Inquiry</h2>
                    <p><strong>From:</strong> "
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . ' ('
                . htmlspecialchars($email, ENT_QUOTES, 'UTF-8')
                . ")</p>
                    <p><strong>Department:</strong> "
                . htmlspecialchars((string) $department['name'], ENT_QUOTES, 'UTF-8')
                . "</p>
                    <p><strong>Subject:</strong> "
                . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
                . "</p>
                    <hr>
                    <div>"
                . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
                . "</div>
                    <hr>
                    <p><a href='"
                . htmlspecialchars((string) URLROOT, ENT_QUOTES, 'UTF-8')
                . "/admin/contact'>Open Contact Admin</a></p>
                </div>";
            $mail->send();
        } catch (Exception $exception) {
            // Mail failure does not roll back a successfully stored inquiry.
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $department
     */
    private function send_confirmation(
        array $config,
        array $department,
        string $name,
        string $email
    ): void {
        try {
            $mail = (new mailer())->create();
            $mail->addAddress($email, $name);
            $mail->addReplyTo(
                (string) $department['email_address'],
                (string) $department['name']
            );
            $mail->isHTML(false);
            $mail->Subject = (string) $config['confirmation_subject'];
            $mail->Body = (string) $config['confirmation_message'];
            $mail->send();
        } catch (Exception $exception) {
            // Mail failure does not roll back a successfully stored inquiry.
        }
    }

    /**
     * Store a one-request Admin status message.
     */
    private function set_admin_status(string $type, string $message): void
    {
        $_SESSION['contact_admin_status'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Return and clear the current Admin status message.
     *
     * @return array{type: string, message: string}|null
     */
    private function consume_admin_status(): ?array
    {
        $status = $_SESSION['contact_admin_status'] ?? null;
        unset($_SESSION['contact_admin_status']);

        if (!is_array($status)) {
            return null;
        }

        $type = (string) ($status['type'] ?? 'info');
        $message = trim((string) ($status['message'] ?? ''));

        if ($message === '') {
            return null;
        }

        return [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Redirect back to Contact Admin after a state-changing action.
     */
    private function redirect_admin(): never
    {
        header('Location: /admin/contact');
        exit;
    }


}

/* [End AI:GPT-5.6 Sol] */
/* [End AI:GPT-5.6 Sol] */
