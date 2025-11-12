<?php

declare(strict_types=1);

namespace Infrastructure\Service;

use Domain\Repository\EmailNotificationRepositoryInterface;
use Exception;

/**
 * Email Service
 *
 * Сервис для отправки email уведомлений
 * Использует встроенную функцию PHP mail() - НЕ устанавливаем внешние библиотеки (по правилам)
 */
class EmailService
{
    private string $fromEmail;
    private string $fromName;
    private EmailNotificationRepositoryInterface $emailRepository;

    public function __construct(EmailNotificationRepositoryInterface $emailRepository)
    {
        $this->emailRepository = $emailRepository;
        
        // Загружаем конфигурацию
        $config = require __DIR__ . '/../../../config/email.php';
        $this->fromEmail = $config['from_email'] ?? 'noreply@healthcare-cms.local';
        $this->fromName = $config['from_name'] ?? 'Healthcare CMS';
    }

    /**
     * Отправить email верификации
     */
    public function sendVerificationEmail(string $email, string $verificationToken, string $verificationUrl = ''): bool
    {
        $subject = 'Верификация вашего email адреса';
        
        if (empty($verificationUrl)) {
            $verificationUrl = 'http://localhost/admin/verify-email.html?token=' . $verificationToken;
        }

        $html = $this->renderTemplate('verification', [
            'verificationUrl' => $verificationUrl,
            'verificationToken' => $verificationToken,
            'expiryHours' => 24,
        ]);

        return $this->send($email, $subject, $html, 'email_verification');
    }

    /**
     * Отправить приветственное письмо с данными входа
     */
    public function sendWelcomeEmail(string $email, string $username, string $tempPassword = ''): bool
    {
        $subject = 'Добро пожаловать в Healthcare CMS';

        $html = $this->renderTemplate('welcome', [
            'username' => $username,
            'tempPassword' => $tempPassword,
            'loginUrl' => 'http://localhost/admin',
        ]);

        return $this->send($email, $subject, $html, 'user_created');
    }

    /**
     * Отправить уведомление об изменении пароля
     */
    public function sendPasswordChangedEmail(string $email, string $username): bool
    {
        $subject = 'Пароль изменён';

        $html = $this->renderTemplate('password-changed', [
            'username' => $username,
            'timestamp' => date('d.m.Y H:i'),
        ]);

        return $this->send($email, $subject, $html, 'password_changed');
    }

    /**
     * Отправить уведомление об изменении роли
     */
    public function sendRoleChangedEmail(string $email, string $username, string $newRole): bool
    {
        $subject = 'Ваша роль в системе изменена';

        $roleLabels = [
            'super_admin' => 'Супер администратор',
            'admin' => 'Администратор',
            'editor' => 'Редактор',
            'viewer' => 'Просмотрщик',
        ];

        $html = $this->renderTemplate('role-changed', [
            'username' => $username,
            'newRole' => $roleLabels[$newRole] ?? $newRole,
        ]);

        return $this->send($email, $subject, $html, 'role_changed');
    }

    /**
     * Отправить уведомление о блокировке аккаунта
     */
    public function sendAccountLockedEmail(string $email, string $username): bool
    {
        $subject = 'Аккаунт заблокирован';

        $html = $this->renderTemplate('account-locked', [
            'username' => $username,
            'timestamp' => date('d.m.Y H:i'),
            'contactEmail' => 'admin@healthcare-cms.local',
        ]);

        return $this->send($email, $subject, $html, 'account_locked');
    }

    /**
     * Универсальный метод отправки email
     */
    public function send(string $toEmail, string $subject, string $htmlBody, string $type = 'generic'): bool
    {
        try {
            // Подготавливаем заголовки
            $headers = $this->prepareHeaders();

            // Логируем попытку отправки в БД
            $notificationId = $this->emailRepository->save([
                'recipient_email' => $toEmail,
                'subject' => $subject,
                'type' => $type,
                'status' => 'pending',
            ]);

            // Пытаемся отправить
            $sent = mail(
                $toEmail,
                $subject,
                $htmlBody,
                $headers,
                '-f ' . $this->fromEmail
            );

            if ($sent) {
                $this->emailRepository->markAsSent($notificationId);
                return true;
            } else {
                $this->emailRepository->markAsFailed($notificationId, 'PHP mail() returned false');
                return false;
            }
        } catch (Exception $e) {
            // Логируем ошибку в БД если возможно
            try {
                $notificationId = $this->emailRepository->save([
                    'recipient_email' => $toEmail,
                    'subject' => $subject,
                    'type' => $type,
                    'status' => 'pending',
                ]);
                $this->emailRepository->markAsFailed($notificationId, $e->getMessage());
            } catch (Exception $logException) {
                // Silent fail - не можем логировать ошибку
            }
            
            return false;
        }
    }

    /**
     * Подготовить заголовки письма
     */
    private function prepareHeaders(): string
    {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'From: ' . $this->formatAddress($this->fromEmail, $this->fromName);
        $headers[] = 'Reply-To: ' . $this->fromEmail;
        $headers[] = 'X-Mailer: Healthcare CMS Security Module';

        return implode("\r\n", $headers);
    }

    /**
     * Отформатировать адрес (email с именем)
     */
    private function formatAddress(string $email, string $name = ''): string
    {
        if (empty($name)) {
            return $email;
        }
        return '"' . addslashes($name) . '" <' . $email . '>';
    }

    /**
     * Отрендерить шаблон email
     */
    private function renderTemplate(string $templateName, array $data = []): string
    {
        $templateFile = __DIR__ . '/../../../templates/emails/' . $templateName . '.html';

        if (!file_exists($templateFile)) {
            // Fallback простой шаблон если файл не найден
            return $this->renderDefaultTemplate($data);
        }

        // Передаём переменные в шаблон
        extract($data);
        ob_start();
        include $templateFile;
        $html = ob_get_clean();

        return $html ?: '';
    }

    /**
     * Простой шаблон по умолчанию
     */
    private function renderDefaultTemplate(array $data): string
    {
        $subject = $data['subject'] ?? 'Healthcare CMS';
        
        $html = '<!DOCTYPE html>';
        $html .= '<html lang="ru">';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<style>body { font-family: Arial, sans-serif; color: #333; }</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<h2>' . htmlspecialchars($subject) . '</h2>';
        $html .= '<p>Это письмо было отправлено автоматически. Пожалуйста, не отвечайте на это письмо.</p>';
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }
}
