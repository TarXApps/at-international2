<?php
// Contact form handler for A.T International. Sends the message to the address below.
header('Content-Type: application/json; charset=utf-8');
$to = 'info@atinternational.online';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }
if (!empty($_POST['website'])) { echo json_encode(['ok'=>true]); exit; } // honeypot: bots fill this in

$clean = function ($v) { return trim(str_replace(["\r", "\n"], ' ', strip_tags((string)$v))); };
$name  = $clean($_POST['name']  ?? '');
$email = $clean($_POST['email'] ?? '');
$phone = $clean($_POST['phone'] ?? '');
$msg   = trim(strip_tags((string)($_POST['message'] ?? '')));

if ($name === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422); echo json_encode(['ok'=>false]); exit;
}

$subject = 'Website enquiry from ' . $name;
$body = "Name: $name\nEmail: $email\nPhone: " . ($phone ?: '-') . "\n\n$msg\n";
$headers = "From: A.T International <no-reply@atinternational.online>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=utf-8\r\n";

$sent = mail($to, $subject, $body, $headers);
if (!$sent) http_response_code(500);
echo json_encode(['ok'=>$sent]);
