<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
if (!session_start()) {
    http_response_code(503);
    exit('The contact form is temporarily unavailable. Please email woodhask.ediomu@woodhask.com.');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

const CONTACT_RECIPIENT = 'woodhask.ediomu@woodhask.com';
const CONTACT_SERVICES = [
    'Audit & assurance',
    'Tax advisory',
    'Accounting & outsourcing',
    'Advisory',
    'Other enquiry',
];

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectToContact(): never
{
    header('Location: contact.php#contact-form', true, 303);
    exit;
}

function flashContact(string $type, string $message, array $values = []): never
{
    $_SESSION['contact_flash'] = ['type' => $type, 'message' => $message];
    $_SESSION['contact_values'] = $values;
    redirectToContact();
}

function requiredEnvironment(string $key): string
{
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        throw new RuntimeException('Required server configuration is missing: ' . $key);
    }

    return trim($value);
}

function openDatabase(): PDO
{
    $host = requiredEnvironment('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    $database = requiredEnvironment('DB_NAME');
    $username = requiredEnvironment('DB_USER');
    $password = requiredEnvironment('DB_PASSWORD');

    if (!ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('DB_PORT must be a valid TCP port.');
    }
    if (!preg_match('/\A[a-zA-Z0-9_$-]+\z/', $database)) {
        throw new RuntimeException('DB_NAME contains unsupported characters.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        (int) $port,
        $database
    );

    $database = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $database->exec("SET time_zone = '+00:00'");

    return $database;
}

function connectMailer(): PHPMailer\PHPMailer\PHPMailer
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Mail dependencies are not installed. Run Composer on the server.');
    }
    require_once $autoload;

    $host = requiredEnvironment('SMTP_HOST');
    $username = requiredEnvironment('SMTP_USERNAME');
    $password = requiredEnvironment('SMTP_PASSWORD');
    $sender = requiredEnvironment('SMTP_FROM');
    $senderName = getenv('SMTP_FROM_NAME') ?: 'Woodhask Certified Public Accountants';
    $port = getenv('SMTP_PORT') ?: '587';
    $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');

    if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SMTP_FROM must be a valid sender email address.');
    }
    if (!ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('SMTP_PORT must be a valid TCP port.');
    }

    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->SMTPAuth = true;
    $mailer->Username = $username;
    $mailer->Password = $password;
    $mailer->Port = (int) $port;
    $mailer->Timeout = 15;
    $mailer->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
    $mailer->SMTPSecure = match ($encryption) {
        'tls' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
        'ssl' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
        default => throw new RuntimeException('SMTP_ENCRYPTION must be tls or ssl.'),
    };
    $mailer->setFrom($sender, $senderName);
    $mailer->addAddress(CONTACT_RECIPIENT);

    return $mailer;
}

$flash = $_SESSION['contact_flash'] ?? null;
$oldValues = $_SESSION['contact_values'] ?? [];
unset($_SESSION['contact_flash'], $_SESSION['contact_values']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($postedToken)
        || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $postedToken)
    ) {
        flashContact('error', 'Your form session expired. Please review your details and submit again.');
    }

    if (!empty($_POST['website'])) {
        flashContact('success', 'Thank you for contacting Woodhask.');
    }

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $service = $_POST['service'] ?? '';
    $message = $_POST['message'] ?? '';
    $requestId = $_POST['request_id'] ?? '';
    $values = [
        'name' => is_string($name) ? trim(preg_replace('/[\r\n]+/', ' ', $name)) : '',
        'email' => is_string($email) ? trim($email) : '',
        'phone' => is_string($phone) ? trim(preg_replace('/[\r\n]+/', ' ', $phone)) : '',
        'service' => is_string($service) ? trim($service) : '',
        'message' => is_string($message) ? trim($message) : '',
    ];

    $errors = [];
    if (!preg_match('/\A[a-f0-9]{64}\z/', is_string($requestId) ? $requestId : '')) {
        $errors[] = 'Please refresh the page and try again.';
    }
    if ($values['name'] === '' || mb_strlen($values['name']) > 120) {
        $errors[] = 'Enter your name (up to 120 characters).';
    }
    if (
        $values['email'] === ''
        || mb_strlen($values['email']) > 254
        || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Enter a valid email address.';
    }
    if (
        mb_strlen($values['phone']) > 40
        || ($values['phone'] !== '' && !preg_match('/\A[0-9+().\s-]+\z/', $values['phone']))
    ) {
        $errors[] = 'Enter a valid phone number using digits and standard phone punctuation.';
    }
    if ($values['service'] !== '' && !in_array($values['service'], CONTACT_SERVICES, true)) {
        $errors[] = 'Choose one of the listed services.';
    }
    if ($values['message'] === '' || mb_strlen($values['message']) > 5000) {
        $errors[] = 'Enter a message (up to 5,000 characters).';
    }

    if ($errors !== []) {
        flashContact('error', implode(' ', $errors), $values);
    }

    try {
        $database = openDatabase();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('A valid client IP address is required for contact form rate limiting.');
        }
        $appKey = requiredEnvironment('APP_KEY');
        if (strlen($appKey) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters.');
        }
        $ipHash = hash_hmac('sha256', $ipAddress, $appKey);
        $hashedRequestId = hash('sha256', $requestId);

        $existing = $database->prepare(
            'SELECT email_status FROM contact_submissions WHERE request_id = :request_id'
        );
        $existing->execute(['request_id' => $hashedRequestId]);
        $existingStatus = $existing->fetchColumn();
        if ($existingStatus !== false) {
            if ($existingStatus === 'sent') {
                flashContact('success', 'Your enquiry has already been sent to Woodhask.');
            }
            flashContact(
                'error',
                'This enquiry is already recorded but has not been emailed. Please use the email or phone details on this page.'
            );
        }

        $rateLimit = $database->prepare(
            'SELECT COUNT(*) FROM contact_submissions
             WHERE ip_hash = :ip_hash AND created_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE'
        );
        $rateLimit->execute(['ip_hash' => $ipHash]);
        if ((int) $rateLimit->fetchColumn() >= 5) {
            flashContact(
                'error',
                'Too many enquiries were sent from this connection. Please try again later or contact us directly.',
                $values
            );
        }

        $insert = $database->prepare(
            'INSERT INTO contact_submissions
                (request_id, name, email, phone, service, message, ip_hash, email_status)
             VALUES
                (:request_id, :name, :email, :phone, :service, :message, :ip_hash, :email_status)'
        );
        $insert->execute([
            'request_id' => $hashedRequestId,
            'name' => $values['name'],
            'email' => $values['email'],
            'phone' => $values['phone'] === '' ? null : $values['phone'],
            'service' => $values['service'] === '' ? 'Not specified' : $values['service'],
            'message' => $values['message'],
            'ip_hash' => $ipHash,
            'email_status' => 'pending',
        ]);
        $submissionId = (int) $database->lastInsertId();
    } catch (Throwable $exception) {
        error_log('Contact form database error: ' . $exception->getMessage());
        flashContact(
            'error',
            'We could not save your enquiry. Please email woodhask.ediomu@woodhask.com or call +256 784 766 769.',
            $values
        );
    }

    try {
        $mailer = connectMailer();
        $mailer->addReplyTo($values['email'], $values['name']);
        $mailer->Subject = 'Website enquiry: ' . $values['name'];
        $mailer->Body = implode("\n", [
            'A new website enquiry was received.',
            '',
            'Name: ' . $values['name'],
            'Email: ' . $values['email'],
            'Phone: ' . ($values['phone'] !== '' ? $values['phone'] : 'Not provided'),
            'Service: ' . ($values['service'] !== '' ? $values['service'] : 'Not specified'),
            '',
            'Message:',
            $values['message'],
        ]);
        $mailer->send();
    } catch (Throwable $exception) {
        error_log(sprintf(
            'Contact email delivery failed for submission %d: %s',
            $submissionId,
            $exception->getMessage()
        ));
        try {
            $failed = $database->prepare(
                "UPDATE contact_submissions SET email_status = 'failed' WHERE id = :id"
            );
            $failed->execute(['id' => $submissionId]);
        } catch (Throwable $databaseException) {
            error_log(sprintf(
                'Could not update email status for contact submission %d: %s',
                $submissionId,
                $databaseException->getMessage()
            ));
        }

        flashContact(
            'error',
            'Your enquiry was saved, but we could not email it. Please email woodhask.ediomu@woodhask.com or call +256 784 766 769.'
        );
    }

    try {
        $sent = $database->prepare(
            "UPDATE contact_submissions
             SET email_status = 'sent', sent_at = UTC_TIMESTAMP()
             WHERE id = :id"
        );
        $sent->execute(['id' => $submissionId]);
    } catch (Throwable $exception) {
        error_log(sprintf(
            'Email was accepted but contact submission %d could not be marked sent: %s',
            $submissionId,
            $exception->getMessage()
        ));
        flashContact(
            'success',
            'Thank you. Your enquiry was emailed to Woodhask; our system could not update the delivery record.'
        );
    }

    flashContact('success', 'Thank you. Your enquiry has been sent to Woodhask.');
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$requestId = bin2hex(random_bytes(32));
$selectedService = is_string($oldValues['service'] ?? null) ? $oldValues['service'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact Woodhask | Kampala, Uganda</title>
  <meta name="description" content="Contact Woodhask Certified Public Accountants in Kampala to discuss audit, tax, accounting and advisory services.">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css"><script src="script.js" defer></script>
</head>
<body>
  <header class="site-header"><div class="wrap bar"><a class="logo-crop" href="index.html" aria-label="Woodhask home"><img src="images/logo-wordmark.png" alt=""><span class="logo-descriptor">Certified Public Accountants</span></a><button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false">☰</button><nav class="site-nav" aria-label="Main navigation"><a href="about.html">About</a><a href="services.html">Services</a><a href="team.html">Team</a><a href="quality.html">Quality</a><a class="nav-cta" href="contact.php" aria-current="page">Contact us</a></nav></div></header>
  <main>
    <section class="page-hero"><div class="wrap"><div><p class="eyebrow">Get in touch</p><h1>Let's talk about what comes next.</h1><p>Tell us a little about what you need. Our team will be ready to pick up the conversation.</p></div><img src="images/workspaces/workspace-meeting.jpg" alt="A bright meeting room prepared for a client conversation"></div></section>
    <section class="section" id="contact"><div class="wrap contact-layout">
      <aside class="contact-details"><p class="eyebrow">Contact details</p><h2>We are here to help.</h2><p>Reach out directly, or send a message using the form. We look forward to hearing from you.</p><a href="mailto:woodhask.ediomu@woodhask.com">woodhask.ediomu@woodhask.com</a><a href="tel:+256784766769">+256 784 766 769</a><a href="tel:+256757671644">+256 757 671 644</a><p><strong>Visit us</strong><br>Kisasi – Kulambiro Ring Road, Plot 1207, Block 215<br>P.O. Box 200292, Kampala, Uganda</p><a href="https://www.woodhask.com">www.woodhask.com</a></aside>
      <form class="contact-form" id="contact-form" action="contact.php#contact-form" method="post">
        <?php if (is_array($flash)): ?>
          <p class="form-note full" role="<?= $flash['type'] === 'success' ? 'status' : 'alert' ?>"><?= escapeHtml((string) $flash['message']) ?></p>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= escapeHtml((string) $_SESSION['csrf_token']) ?>">
        <input type="hidden" name="request_id" value="<?= escapeHtml($requestId) ?>">
        <div class="form-honeypot" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="field"><label for="name">Your name *</label><input id="name" name="name" autocomplete="name" maxlength="120" required value="<?= escapeHtml((string) ($oldValues['name'] ?? '')) ?>"></div>
        <div class="field"><label for="email">Email address *</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?= escapeHtml((string) ($oldValues['email'] ?? '')) ?>"></div>
        <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="40" value="<?= escapeHtml((string) ($oldValues['phone'] ?? '')) ?>"></div>
        <div class="field"><label for="service">What can we help with?</label><select id="service" name="service"><option value="" <?= $selectedService === '' ? 'selected' : '' ?>>Choose a service</option><?php foreach (CONTACT_SERVICES as $serviceOption): ?><option value="<?= escapeHtml($serviceOption) ?>" <?= $selectedService === $serviceOption ? 'selected' : '' ?>><?= escapeHtml($serviceOption) ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label for="message">Your message *</label><textarea id="message" name="message" maxlength="5000" required><?= escapeHtml((string) ($oldValues['message'] ?? '')) ?></textarea></div>
        <p class="form-note full">Your enquiry will be stored securely for processing and emailed to our team. Required fields are marked *.</p>
        <button class="button dark" type="submit">Send enquiry</button>
      </form>
    </div></section>
  </main>
  <footer class="site-footer"><div class="wrap footer-inner"><span>© <?= date('Y') ?> Woodhask Certified Public Accountants</span><span>Member, Institute of Certified Public Accountants of Uganda</span><div class="footer-links"><a href="index.html">Home</a><a href="about.html">About</a><a href="services.html">Services</a></div></div></footer>
</body>
</html>
