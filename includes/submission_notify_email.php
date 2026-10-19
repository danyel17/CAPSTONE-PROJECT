<?php
/**
 * Send submission confirmation to the user's email (Gmail via email_config.php + PHPMailer).
 * Never throws; logs errors only so submissions are never blocked by mail failures.
 */

if (!function_exists('dpm_mail_send_html')) {
    /**
     * @return bool true if PHPMailer reported send OK
     */
    function dpm_mail_send_html(string $toEmail, string $toName, string $subject, string $html, string $plain): bool
    {
        $root = dirname(__DIR__);
        $cfgFile = $root . DIRECTORY_SEPARATOR . 'email_config.php';
        if (!is_file($cfgFile)) {
            error_log('dpm_mail_send_html: email_config.php missing');
            return false;
        }
        $email_config = require $cfgFile;
        $smtp_host = $email_config['smtp_host'] ?? 'smtp.gmail.com';
        $smtp_port = (int)($email_config['smtp_port'] ?? 587);
        $smtp_username = trim((string)($email_config['smtp_username'] ?? ''));
        $smtp_password = (string)($email_config['smtp_password'] ?? '');
        $from_email = trim((string)($email_config['from_email'] ?? $smtp_username));
        $from_name = (string)($email_config['from_name'] ?? 'Daet Public Market Office');
        $reply_to_email = trim((string)($email_config['reply_to_email'] ?? ''));
        $reply_to_name = (string)($email_config['reply_to_name'] ?? $from_name);
        $smtp_encryption = strtolower(trim((string)($email_config['smtp_encryption'] ?? 'tls')));

        if ($smtp_username === '' || $smtp_password === '' || $from_email === '') {
            error_log('dpm_mail_send_html: SMTP not configured');
            return false;
        }

        $pm = $root . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PHPMailer.php';
        if (!is_file($pm)) {
            error_log('dpm_mail_send_html: PHPMailer not found');
            return false;
        }
        require_once $root . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php';
        require_once $pm;
        require_once $root . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'SMTP.php';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            // Prefer configured encryption; default to STARTTLS (Gmail port 587).
            if ($smtp_encryption === 'ssl' || $smtp_port === 465) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = $smtp_port;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPDebug = 0;
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = false;
            // Helps Gmail treat mail as authentic transactional mail from this mailbox.
            $fromDomain = 'gmail.com';
            if (strpos($from_email, '@') !== false) {
                $fromDomain = substr(strrchr($from_email, '@'), 1) ?: 'gmail.com';
            }
            $mail->Hostname = $fromDomain;
            $mail->MessageID = '<' . bin2hex(random_bytes(12)) . '@' . $fromDomain . '>';
            $mail->XMailer = 'Daet Public Market Clearance System';
            $mail->Priority = 3; // normal — avoid "urgent" spam signals
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            // From must match the authenticated Gmail mailbox (same as other MCS notifies).
            $mail->setFrom($from_email, $from_name);
            $mail->Sender = $from_email;
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $replyEmail = $reply_to_email !== '' ? $reply_to_email : $from_email;
            $replyName = $reply_to_name !== '' ? $reply_to_name : $from_name;
            $mail->addReplyTo($replyEmail, $replyName);
            // Mark as auto-generated transactional mail (not marketing / spam).
            $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            $mail->addCustomHeader('X-MCS-Notification', 'transactional');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $plain !== '' ? $plain : strip_tags($html);
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('dpm_mail_send_html: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('dpm_registered_recipient')) {
    /**
     * Email and name saved when the account was created on registrationform.php.
     *
     * @return array{email:string,name:string}
     */
    function dpm_registered_recipient(int $userId): array
    {
        $out = ['email' => '', 'name' => ''];
        if ($userId <= 0 || !function_exists('getDBConnection')) {
            return $out;
        }
        $conn = getDBConnection();
        if (!$conn) {
            return $out;
        }
        $stmt = $conn->prepare('SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row) {
                $email = trim((string)($row['email'] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $out['email'] = $email;
                }
                $out['name'] = trim((string)($row['full_name'] ?? ''));
            }
        }
        $conn->close();
        return $out;
    }
}

if (!function_exists('dpm_notify_submission_email')) {
    /**
     * @return bool true if SMTP reported send OK
     */
    function dpm_notify_submission_email(string $kind, string $toEmail, string $toName, array $vars = []): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('dpm_notify_submission_email: skip — invalid or empty email');
            return false;
        }

        $root = dirname(__DIR__);

        $action = ($vars['action'] ?? 'submitted') === 'updated' ? 'updated' : 'submitted';
        $ids = trim((string)($vars['application_ids'] ?? ''));

        switch ($kind) {
            case 'application':
                $subject = $action === 'updated'
                    ? 'Application updated — Daet Public Market'
                    : 'Application received — Daet Public Market';
                $head = $action === 'updated'
                    ? 'Your application was updated'
                    : 'We received your application';
                $p1 = $action === 'updated'
                    ? 'Your market clearance application has been updated and is <strong>pending review</strong> by the office.'
                    : 'Thank you. Your market clearance application has been received and is <strong>pending review</strong>.';
                break;
            case 'renewal':
                $subject = $action === 'updated'
                    ? 'Renewal updated — Daet Public Market'
                    : 'Renewal received — Daet Public Market';
                $head = $action === 'updated'
                    ? 'Your renewal was updated'
                    : 'We received your renewal';
                $p1 = $action === 'updated'
                    ? 'Your renewal application has been updated and is <strong>pending review</strong>.'
                    : 'Thank you. Your renewal application has been received and is <strong>pending review</strong>.';
                break;
            case 'receipt':
                $subject = $action === 'updated'
                    ? 'Receipt updated — Daet Public Market'
                    : 'Receipt received — Daet Public Market';
                $head = $action === 'updated'
                    ? 'We received your updated receipt'
                    : 'We received your receipt';
                $rid = (int)($vars['receipt_id'] ?? 0);
                $p1 = $action === 'updated'
                    ? 'Thank you. Your updated payment receipt has been received. The market office will review it again.'
                    : 'Thank you. Your payment receipt upload has been received. The market office will review it.';
                if ($rid > 0) {
                    $p1 .= ' Reference ID: <strong>#' . $rid . '</strong>.';
                }
                break;
            default:
                error_log('dpm_notify_submission_email: unknown kind ' . $kind);
                return false;
        }

        $idsBlock = '';
        if ($kind !== 'receipt' && $ids !== '') {
            $idsBlock = '<p><strong>Application ID(s):</strong> ' . htmlspecialchars($ids, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $receiptSubmittedP = '';
        if ($kind === 'receipt') {
            $subDisp = trim((string)($vars['submitted_display'] ?? ''));
            if ($subDisp !== '') {
                $receiptSubmittedP = '<p style="margin:12px 0 0 0;"><strong>Date submitted:</strong> '
                    . htmlspecialchars($subDisp, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }

        $extra = '';
        if ($kind === 'receipt' && !empty($vars['detail_line'])) {
            $extra = '<p style="color:#555;font-size:14px;">' . htmlspecialchars((string)$vars['detail_line'], ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $receiptSchedule = '';
        if ($kind === 'receipt') {
            $expDisp = trim((string)($vars['expires_display'] ?? ''));
            if ($expDisp !== '') {
                $receiptSchedule = '<div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:10px;padding:16px;margin:16px 0;">'
                    . '<p style="margin:0 0 8px 0;"><strong>Due date:</strong> ' . htmlspecialchars($expDisp, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p style="margin:12px 0 0 0;font-size:13px;color:#444;">The review period is one month from when you sent the receipt. You will get an email each day from 7 days before the due date down to the due date.</p>'
                    . '</div>';
            }
        }

        $logo_html = '';
        $logo_path = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imgs' . DIRECTORY_SEPARATOR . 'logo.jpeg';
        if (is_file($logo_path) && is_readable($logo_path)) {
            $fs = @filesize($logo_path);
            if ($fs && $fs < 200000) {
                $data = @file_get_contents($logo_path);
                if ($data !== false && $data !== '') {
                    $b64 = base64_encode($data);
                    if (strlen($b64) < 300000) {
                        $logo_html = "<img src='data:image/jpeg;base64," . $b64 . "' alt='Daet Public Market' style='max-width:140px;height:auto;margin-bottom:12px;display:block;' />";
                    }
                }
            }
        }

        $safeName = htmlspecialchars($toName !== '' ? $toName : 'Vendor', ENT_QUOTES, 'UTF-8');
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='margin:0;font-family:Arial,sans-serif;background:#f4f4f4;'>
        <div style='max-width:600px;margin:0 auto;padding:24px;'>
        <div style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:24px;border-radius:12px 12px 0 0;text-align:center;'>
        {$logo_html}<h1 style='margin:0;font-size:22px;'>{$head}</h1></div>
        <div style='background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 12px 12px;'>
        <p>Hello {$safeName},</p>
        <p>{$p1}</p>
        {$receiptSubmittedP}
        {$receiptSchedule}
        {$idsBlock}
        {$extra}
        <p style='margin-top:20px;'>You can also check <strong>Notifications</strong> on the website for updates.</p>
        <p style='color:#666;font-size:13px;margin-top:24px;'>— Daet Public Market Office<br><span style='font-size:12px;'>This is an automated message.</span></p>
        </div></div></body></html>";

        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        $ok = dpm_mail_send_html($toEmail, $toName, $subject, $html, $plain);
        if ($ok) {
            error_log('dpm_notify_submission_email: sent ' . $kind . ' to ' . $toEmail);
        } else {
            error_log('dpm_notify_submission_email: failed ' . $kind . ' to ' . $toEmail);
        }
        return $ok;
    }
}

if (!function_exists('dpm_mail_logo_html')) {
    /**
     * Returns an inline base64 <img> for the office logo, or '' if unavailable.
     */
    function dpm_mail_logo_html(): string
    {
        $root = dirname(__DIR__);
        $logo_path = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imgs' . DIRECTORY_SEPARATOR . 'logo.jpeg';
        if (is_file($logo_path) && is_readable($logo_path)) {
            $fs = @filesize($logo_path);
            if ($fs && $fs < 200000) {
                $data = @file_get_contents($logo_path);
                if ($data !== false && $data !== '') {
                    $b64 = base64_encode($data);
                    if (strlen($b64) < 300000) {
                        return "<img src='data:image/jpeg;base64," . $b64 . "' alt='Daet Public Market' style='max-width:140px;height:auto;margin-bottom:12px;display:block;' />";
                    }
                }
            }
        }
        return '';
    }
}

if (!function_exists('dpm_notify_decision_email')) {
    /**
     * Admin decision email: approval / incomplete (application & renewal) and
     * approved / invalid (receipts). Never throws.
     *
     * @param string $kind      'application' | 'renewal' | 'receipt'
     * @param string $decision  'approved' | 'incomplete' | 'invalid'
     * @param array  $vars      location, reason, notes, receipt_id, application_ids
     * @return bool true if SMTP reported send OK
     */
    function dpm_notify_decision_email(string $kind, string $decision, string $toEmail, string $toName, array $vars = []): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('dpm_notify_decision_email: skip — invalid or empty email');
            return false;
        }

        $kind = in_array($kind, ['application', 'renewal', 'receipt'], true) ? $kind : 'application';
        $decision = in_array($decision, ['approved', 'incomplete', 'invalid'], true) ? $decision : 'approved';

        $label = $kind === 'renewal' ? 'renewal' : ($kind === 'receipt' ? 'receipt' : 'application');
        $labelCap = ucfirst($label);

        $location = trim((string)($vars['location'] ?? ''));
        $reason = trim((string)($vars['reason'] ?? ''));
        $notes = trim((string)($vars['notes'] ?? ''));
        $receiptId = (int)($vars['receipt_id'] ?? 0);
        $ids = trim((string)($vars['application_ids'] ?? ''));

        // Header color + subject + intro per decision.
        if ($decision === 'approved') {
            $accent = 'linear-gradient(135deg,#16a34a 0%,#15803d 100%)';
            $headline = $labelCap . ' approved';
            if ($kind === 'renewal') {
                $subject = 'Renewal approved — Daet Public Market';
                $intro = 'Good news! Your <strong>renewal</strong> has been <strong>approved</strong> by the market office.';
            } elseif ($kind === 'receipt') {
                $subject = 'Receipt approved — Daet Public Market';
                $intro = 'Good news! Your payment <strong>receipt</strong> has been <strong>approved</strong> by the market office.';
            } else {
                $subject = 'Application approved — Daet Public Market';
                $intro = 'Good news! Your <strong>application</strong> has been <strong>approved</strong> by the market office.';
            }
        } elseif ($decision === 'invalid') {
            // Same indigo header family as other MCS notifies so Gmail treats it like inbox mail, not promo/spam.
            $accent = 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)';
            $headline = 'Receipt marked invalid';
            $subject = 'Receipt marked invalid — Daet Public Market';
            $intro = 'Your payment <strong>receipt</strong> was reviewed and marked as <strong>invalid</strong>.';
        } else { // incomplete
            // Match subject/style of working "Application approved / Application received" emails
            // so Gmail delivers to All Mail / Primary instead of Spam.
            $accent = 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)';
            $headline = $labelCap . ' marked incomplete';
            $subject = ($kind === 'renewal' ? 'Renewal' : 'Application') . ' marked incomplete — Daet Public Market';
            $intro = 'Your <strong>' . $label . '</strong> was reviewed by the market office and marked as <strong>incomplete</strong>.';
        }

        // Detail box (location / reference / reason / notes).
        $rows = '';
        if ($location !== '') {
            $rows .= '<p style="margin:0 0 8px 0;"><strong>Stall:</strong> ' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($kind === 'receipt' && $receiptId > 0) {
            $rows .= '<p style="margin:0 0 8px 0;"><strong>Reference ID:</strong> #' . $receiptId . '</p>';
        }
        if ($kind !== 'receipt' && $ids !== '') {
            $rows .= '<p style="margin:0 0 8px 0;"><strong>Application ID(s):</strong> ' . htmlspecialchars($ids, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($reason !== '') {
            $rows .= '<p style="margin:0 0 8px 0;"><strong>Reason:</strong> ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($notes !== '') {
            $rows .= '<p style="margin:0;"><strong>Notes:</strong> ' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        // Soft detail boxes (same family as other MCS notifies — avoid loud promo colors that Gmail spam-filters).
        $boxBg = $decision === 'approved' ? '#f0fdf4' : '#f0f4ff';
        $boxBorder = $decision === 'approved' ? '#bbf7d0' : '#c7d2fe';
        $detailBox = $rows !== ''
            ? '<div style="background:' . $boxBg . ';border:1px solid ' . $boxBorder . ';border-radius:10px;padding:16px;margin:16px 0;">' . $rows . '</div>'
            : '';

        // Closing line / next step.
        if ($decision === 'approved') {
            if ($kind === 'application') {
                $closing = '<p>You may now proceed with the next steps for your stall. Check <strong>Notifications</strong> on the website for details.</p>';
            } elseif ($kind === 'renewal') {
                $closing = '<p>Your stall clearance renewal is now active. Check <strong>Notifications</strong> on the website for details.</p>';
            } else {
                $closing = '<p>Your payment has been recorded. Check <strong>Notifications</strong> on the website for details.</p>';
            }
        } elseif ($decision === 'invalid') {
            $closing = '<p>Please open <strong>Notifications</strong> on the website and use <strong>Edit</strong> to update and resubmit the same receipt.</p>';
        } else { // incomplete
            $closing = '<p>You can also check <strong>Notifications</strong> on the website and use <strong>Edit</strong> to complete and resubmit your ' . $label . '.</p>';
        }

        $logo_html = dpm_mail_logo_html();
        $safeName = htmlspecialchars($toName !== '' ? $toName : 'Vendor', ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;font-family:Arial,sans-serif;background:#f4f4f4;">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px;">'
            . '<div style="background:' . $accent . ';color:#fff;padding:24px;border-radius:12px 12px 0 0;text-align:center;">'
            . $logo_html . '<h1 style="margin:0;font-size:22px;">' . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . '</h1></div>'
            . '<div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 12px 12px;">'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>' . $intro . '</p>'
            . $detailBox
            . $closing
            . '<p style="color:#666;font-size:13px;margin-top:24px;">— Daet Public Market Office<br><span style="font-size:12px;">This is an automated message.</span></p>'
            . '</div></div></body></html>';

        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        $ok = dpm_mail_send_html($toEmail, $toName, $subject, $html, $plain);
        error_log('dpm_notify_decision_email: ' . ($ok ? 'sent ' : 'failed ') . $kind . '/' . $decision . ' to ' . $toEmail);
        return $ok;
    }
}

if (!function_exists('dpm_send_receipt_expiry_reminder_email')) {
    /**
     * Reminder: one email per day while a receipt is still pending and days left are 7 through 0.
     *
     * @return bool true if SMTP reported send OK
     */
    function dpm_send_receipt_expiry_reminder_email(string $toEmail, string $toName, int $receiptId, string $submittedDisplay, string $expiresDisplay, int $daysLeft): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $root = dirname(__DIR__);
        $daysLeft = max(0, $daysLeft);
        $unit = $daysLeft === 1 ? 'day' : 'days';
        $dueWhen = $expiresDisplay !== '' ? $expiresDisplay : 'your due date';
        $dueLine = 'Your due is ' . $daysLeft . ' ' . $unit . ' on ' . $dueWhen . '.';
        $subject = $dueLine . ' — Daet Public Market';
        $safeName = htmlspecialchars($toName !== '' ? $toName : 'Vendor', ENT_QUOTES, 'UTF-8');
        $rid = $receiptId > 0 ? '#' . $receiptId : '';
        $daysTxt = (string) $daysLeft;

        $logo_html = '';
        $logo_path = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imgs' . DIRECTORY_SEPARATOR . 'logo.jpeg';
        if (is_file($logo_path) && is_readable($logo_path)) {
            $fs = @filesize($logo_path);
            if ($fs && $fs < 200000) {
                $data = @file_get_contents($logo_path);
                if ($data !== false && $data !== '') {
                    $b64 = base64_encode($data);
                    if (strlen($b64) < 300000) {
                        $logo_html = "<img src='data:image/jpeg;base64," . $b64 . "' alt='Daet Public Market' style='max-width:140px;height:auto;margin-bottom:12px;display:block;' />";
                    }
                }
            }
        }

        $refPart = $rid !== '' ? '(Reference ' . htmlspecialchars($rid, ENT_QUOTES, 'UTF-8') . ') ' : '';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;font-family:Arial,sans-serif;background:#f4f4f4;">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px;">'
            . '<div style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);color:#fff;padding:24px;border-radius:12px 12px 0 0;text-align:center;">'
            . $logo_html . '<h1 style="margin:0;font-size:22px;">Your due is ' . $daysTxt . ' ' . $unit . '</h1></div>'
            . '<div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 12px 12px;">'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>Your due is <strong>' . $daysTxt . ' ' . $unit . '</strong> on <strong>' . htmlspecialchars($dueWhen, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>This is for your payment receipt ' . $refPart . 'that is still <strong>pending review</strong>.</p>'
            . '<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:16px;margin:16px 0;">'
            . '<p style="margin:0 0 8px 0;"><strong>Submitted:</strong> ' . htmlspecialchars($submittedDisplay, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Due date:</strong> ' . htmlspecialchars($expiresDisplay, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0;font-size:18px;color:#b45309;"><strong>' . htmlspecialchars($dueLine, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '</div>'
            . '<p>If you need help, contact the market office or use <strong>Facebook</strong> on the website support section.</p>'
            . '<p style="color:#666;font-size:13px;margin-top:24px;">— Daet Public Market Office<br><span style="font-size:12px;">This is an automated message.</span></p>'
            . '</div></div></body></html>';

        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        if (dpm_mail_send_html($toEmail, $toName, $subject, $html, $plain)) {
            error_log('dpm_send_receipt_expiry_reminder_email: sent to ' . $toEmail . ' receipt_id=' . $receiptId);
            return true;
        }
        return false;
    }
}

if (!function_exists('dpm_notify_registration_email')) {
    /**
     * Welcome email after a user account is created. Never throws.
     */
    function dpm_notify_registration_email(string $toEmail, string $toName): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('dpm_notify_registration_email: skip — invalid or empty email');
            return false;
        }

        $safeName = htmlspecialchars($toName !== '' ? $toName : 'there', ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8');
        $logo = function_exists('dpm_mail_logo_html') ? dpm_mail_logo_html() : '';
        $subject = 'Your account was created — Daet Public Market';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;font-family:Arial,sans-serif;background:#f4f4f4;">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px;">'
            . '<div style="background:linear-gradient(135deg,#4f46e5 0%,#6366f1 100%);color:#fff;padding:24px;border-radius:12px 12px 0 0;text-align:center;">'
            . $logo . '<h1 style="margin:0;font-size:22px;">Account created</h1></div>'
            . '<div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 12px 12px;">'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>Your Daet Public Market account was created successfully.</p>'
            . '<p>You can log in with <strong>' . $safeEmail . '</strong> to apply for a stall, renew a permit, and manage your market clearance.</p>'
            . '<p style="color:#666;font-size:13px;margin-top:24px;">— Daet Public Market Office<br><span style="font-size:12px;">This is an automated message. Please do not reply with your password.</span></p>'
            . '</div></div></body></html>';
        $plain = "Hello " . ($toName !== '' ? $toName : 'there') . ",\n\n"
            . "Your Daet Public Market account was created successfully.\n"
            . "You can log in with " . $toEmail . " to apply for a stall, renew a permit, and manage your market clearance.\n\n"
            . "— Daet Public Market Office";

        $ok = dpm_mail_send_html($toEmail, $toName, $subject, $html, $plain);
        if ($ok) {
            error_log('dpm_notify_registration_email: sent to ' . $toEmail);
        } else {
            error_log('dpm_notify_registration_email: failed to ' . $toEmail);
        }
        return $ok;
    }
}
