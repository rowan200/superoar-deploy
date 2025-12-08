<?php
/**
 * SUPEROAR Contact Form Handler
 * Upload this file to your cPanel hosting in the public_html/api/ folder
 * 
 * CONFIGURATION: Update the settings below with your details
 */

// ============================================
// CONFIGURATION - UPDATE THESE VALUES
// ============================================
$config = [
    'recipient_email' => 'rowthepro1@superoar.com',     // Your email address to receive messages
    'recipient_name'  => 'SUPEROAR Sales',              // Display name
    'from_email'      => 'noreply@superoar.com',        // From email on your domain
    'site_name'       => 'SUPEROAR',                    // Your site name
    'allowed_origins' => [                               // Allowed domains (for CORS)
        'http://localhost:5173',                         // Vite dev server
        'http://localhost:3000',                         // Alternative dev server
        'https://superoar.com',                          // Your production domain
        'https://www.superoar.com',                      // www version
    ],
];

// ============================================
// CORS HEADERS
// ============================================
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $config['allowed_origins'])) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // For development, you can allow all origins (remove in production for security)
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// ONLY ACCEPT POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST request.'
    ]);
    exit();
}

// ============================================
// GET AND VALIDATE INPUT
// ============================================
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data received.'
    ]);
    exit();
}

// Required fields validation
$requiredFields = ['name', 'email', 'message'];
$errors = [];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $errors[] = ucfirst($field) . ' is required.';
    }
}

// Validate email format
if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $errors)
    ]);
    exit();
}

// ============================================
// SANITIZE INPUT DATA
// ============================================
$name = htmlspecialchars(strip_tags(trim($data['name'])));
$email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
$phone = isset($data['phone']) ? htmlspecialchars(strip_tags(trim($data['phone']))) : 'Not provided';
$orderType = isset($data['orderType']) ? htmlspecialchars(strip_tags(trim($data['orderType']))) : 'Not specified';
$message = htmlspecialchars(strip_tags(trim($data['message'])));

// ============================================
// PREPARE EMAIL
// ============================================
$subject = "[{$config['site_name']}] New Contact Form Submission from {$name}";

// HTML Email Body
$htmlBody = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #FFD700; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #111; }
        .content { background: #f9f9f9; padding: 20px; }
        .field { margin-bottom: 15px; }
        .field-label { font-weight: bold; color: #555; }
        .field-value { margin-top: 5px; padding: 10px; background: #fff; border-left: 3px solid #FFD700; }
        .footer { text-align: center; padding: 20px; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>{$config['site_name']} - New Inquiry</h1>
        </div>
        <div class='content'>
            <div class='field'>
                <div class='field-label'>Name:</div>
                <div class='field-value'>{$name}</div>
            </div>
            <div class='field'>
                <div class='field-label'>Email:</div>
                <div class='field-value'><a href='mailto:{$email}'>{$email}</a></div>
            </div>
            <div class='field'>
                <div class='field-label'>Phone:</div>
                <div class='field-value'>{$phone}</div>
            </div>
            <div class='field'>
                <div class='field-label'>Order Type:</div>
                <div class='field-value'>{$orderType}</div>
            </div>
            <div class='field'>
                <div class='field-label'>Message:</div>
                <div class='field-value'>" . nl2br($message) . "</div>
            </div>
        </div>
        <div class='footer'>
            This message was sent from the {$config['site_name']} website contact form.
        </div>
    </div>
</body>
</html>
";

// Plain text version
$textBody = "
NEW CONTACT FORM SUBMISSION
===========================

Name: {$name}
Email: {$email}
Phone: {$phone}
Order Type: {$orderType}

Message:
{$message}

---
This message was sent from the {$config['site_name']} website contact form.
";

// ============================================
// EMAIL HEADERS
// ============================================
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    "From: {$config['site_name']} <{$config['from_email']}>",
    "Reply-To: {$name} <{$email}>",
    'X-Mailer: PHP/' . phpversion()
];

// ============================================
// SEND EMAIL
// ============================================
$mailSent = mail(
    $config['recipient_email'],
    $subject,
    $htmlBody,
    implode("\r\n", $headers)
);

if ($mailSent) {
    // Optionally send auto-reply to the customer
    $autoReplySubject = "Thank you for contacting {$config['site_name']}";
    $autoReplyBody = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #FFD700; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #111; font-size: 24px; }
        .content { background: #f9f9f9; padding: 30px; }
        .footer { text-align: center; padding: 20px; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>{$config['site_name']}</h1>
        </div>
        <div class='content'>
            <p>Dear {$name},</p>
            <p>Thank you for reaching out to us! We have received your message and our team will get back to you within 24 hours.</p>
            <p>If you have any urgent questions, please don't hesitate to call us directly.</p>
            <p>Best regards,<br>The {$config['site_name']} Team</p>
        </div>
        <div class='footer'>
            &copy; " . date('Y') . " {$config['site_name']}. All rights reserved.
        </div>
    </div>
</body>
</html>
";

    $autoReplyHeaders = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: {$config['site_name']} <{$config['from_email']}>",
        'X-Mailer: PHP/' . phpversion()
    ];

    // Send auto-reply (uncomment the line below to enable)
    // mail($email, $autoReplySubject, $autoReplyBody, implode("\r\n", $autoReplyHeaders));

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully! We will get back to you within 24 hours.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message. Please try again later or contact us directly.'
    ]);
}
?>
