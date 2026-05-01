<?php
// PHPMailer email helper. Drop PHPMailer in vendor/ via Composer or PHPMailer/src/.

function nucleusLoadPHPMailer(): bool {
    if (class_exists("\\PHPMailer\\PHPMailer\\PHPMailer")) {
        return true;
    }

    $manualBase = __DIR__ . "/../PHPMailer/src";
    $manualFiles = [
        $manualBase . "/Exception.php",
        $manualBase . "/PHPMailer.php",
        $manualBase . "/SMTP.php",
    ];

    if (is_file($manualFiles[0]) && is_file($manualFiles[1]) && is_file($manualFiles[2])) {
        require_once $manualFiles[0];
        require_once $manualFiles[1];
        require_once $manualFiles[2];
    }

    return class_exists("\\PHPMailer\\PHPMailer\\PHPMailer");
}

function sendNucleusEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ""): bool {
    if (!nucleusLoadPHPMailer()) {
        error_log("PHPMailer is not installed. Email was not sent to {$toEmail}.");
        return false;
    }

    try {
        $mailClass = "\\PHPMailer\\PHPMailer\\PHPMailer";
        $mail = new $mailClass(true);

        $host = $_ENV["SMTP_HOST"] ?? "";
        if ($host !== "") {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = filter_var($_ENV["SMTP_AUTH"] ?? "true", FILTER_VALIDATE_BOOLEAN);
            $mail->Username = $_ENV["SMTP_USERNAME"] ?? "";
            $mail->Password = $_ENV["SMTP_PASSWORD"] ?? "";
            $mail->Port = (int) ($_ENV["SMTP_PORT"] ?? 587);

            $secure = strtolower((string) ($_ENV["SMTP_SECURE"] ?? "tls"));
            if (in_array($secure, ["ssl", "smtps"], true)) {
                $mail->SMTPSecure = "ssl";
            } elseif (in_array($secure, ["tls", "starttls"], true)) {
                $mail->SMTPSecure = "tls";
            }
        }

        $fromEmail = $_ENV["MAIL_FROM_ADDRESS"] ?? "noreply@nucleus.local";
        $fromName = $_ENV["MAIL_FROM_NAME"] ?? "Nucleus";

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody !== "" ? $plainBody : trim(strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $htmlBody)));

        return $mail->send();
    } catch (Throwable $e) {
        error_log("Email send failed: " . $e->getMessage());
        return false;
    }
}
