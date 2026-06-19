<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function app_url(string $path = ''): string
{
    return rtrim(app_env('APP_URL', 'http://localhost:8000'), '/') . '/' . ltrim($path, '/');
}

function mail_was_sent(string $referenceKey): bool
{
    $stmt = pdo()->prepare('SELECT id FROM email_delivery_logs WHERE reference_key = ? AND status = ? LIMIT 1');
    $stmt->execute([$referenceKey, 'sent']);

    return (bool) $stmt->fetchColumn();
}

function log_email_delivery(string $type, array $recipient, string $subject, string $status, ?string $referenceKey, ?string $error = null): void
{
    $stmt = pdo()->prepare(
        'INSERT INTO email_delivery_logs
            (mail_type, recipient_email, user_id, reference_key, subject, status, error_message, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $type,
        (string) $recipient['email'],
        isset($recipient['id']) ? (int) $recipient['id'] : null,
        $referenceKey,
        $subject,
        $status,
        $error,
        $status === 'sent' ? date('Y-m-d H:i:s') : null,
    ]);
}

function send_platform_email(array $recipient, string $type, string $subject, string $html, string $text, ?string $referenceKey = null): bool
{
    if ($referenceKey !== null && mail_was_sent($referenceKey)) {
        return true;
    }

    $host = app_env('MAIL_HOST');
    $from = app_env('MAIL_FROM_ADDRESS');
    if (!class_exists(PHPMailer::class) || $host === '' || $from === '') {
        log_email_delivery($type, $recipient, $subject, 'failed', $referenceKey, 'SMTP configuration is incomplete.');
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) app_env('MAIL_PORT', '587');
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAuth = app_env('MAIL_USERNAME') !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = app_env('MAIL_USERNAME');
            $mail->Password = app_env('MAIL_PASSWORD');
        }
        $encryption = app_env('MAIL_ENCRYPTION', 'tls');
        if ($encryption !== 'none') {
            $mail->SMTPSecure = $encryption;
        }
        $mail->setFrom($from, app_env('MAIL_FROM_NAME', 'Travel Platform'));
        $mail->addAddress((string) $recipient['email'], (string) ($recipient['name'] ?? ''));
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();

        log_email_delivery($type, $recipient, $subject, 'sent', $referenceKey);
        return true;
    } catch (MailException $exception) {
        log_email_delivery($type, $recipient, $subject, 'failed', $referenceKey, $exception->getMessage());
        return false;
    }
}

function email_shell(string $heading, string $contentHtml, string $actionLabel, string $actionUrl): string
{
    return '<!doctype html><html lang="zh-Hant"><body style="font-family:Arial,sans-serif;color:#172033;background:#f5f7fb;padding:28px;">'
        . '<div style="max-width:600px;margin:auto;background:#fff;border:1px solid #dbe3ee;border-radius:8px;padding:28px;">'
        . '<p style="color:#0f766e;font-weight:700;">Travel Platform</p><h1 style="font-size:24px;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h1>'
        . $contentHtml
        . '<p style="margin-top:24px;"><a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#0f766e;color:#fff;padding:12px 18px;text-decoration:none;border-radius:8px;">'
        . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '</div></body></html>';
}
