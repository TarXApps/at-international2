<?php
// Receives a client's feedback, stores it as "pending" and emails A.T International a review link.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/feedback-lib.php';

function fb_fail($code, $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fb_fail(405, 'Method not allowed');
if (!empty($_POST['website'])) { echo json_encode(['ok' => true]); exit; } // honeypot: bots fill this in

$clean = function ($v) { return trim(preg_replace('/\s+/', ' ', strip_tags((string)$v))); };
$name  = $clean($_POST['name'] ?? '');
$role  = $clean($_POST['role'] ?? '');
$email = $clean($_POST['email'] ?? '');
$linkedin = at_linkedin($clean($_POST['linkedin'] ?? ''));
$msg   = trim(strip_tags((string)($_POST['message'] ?? '')));
$msg   = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\r\n?/", "\n", $msg)));
$len   = function ($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); };

$loaded = (int)($_POST['ts'] ?? 0);
if ($loaded > 0 && (time() * 1000 - $loaded) < 3000) fb_fail(429, 'Please take a moment to review your feedback before sending.');

if ($len($name) < 2 || $len($name) > 80)                     fb_fail(422, 'Please enter your name.');
if ($len($role) > 100)                                       fb_fail(422, 'Your role and company is too long.');
if ($linkedin === false)                                     fb_fail(422, 'Please enter a valid LinkedIn profile link (linkedin.com/in/your-name), or leave it empty.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))              fb_fail(422, 'Please enter a valid email address.');
if ($len($msg) < 20)                                         fb_fail(422, 'Please write at least a couple of sentences.');
if ($len($msg) > 600)                                        fb_fail(422, 'Please keep your feedback under 600 characters.');
if (empty($_POST['consent']))                                fb_fail(422, 'Please tick the box to confirm we may publish your feedback.');

try {
    $ipHash = substr(at_sign('ip:' . ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16);
    $id = bin2hex(random_bytes(8));
    $tooMany = false;
    at_mutate(function ($all) use (&$tooMany, $ipHash, $id, $name, $role, $email, $msg, $linkedin) {
        $recent = 0; $day = 0;
        foreach ($all as $s) {
            $age = time() - strtotime($s['created'] ?? '1970-01-01');
            if (($s['ip'] ?? '') === $ipHash && $age < 3600) $recent++;
            if ($age < 86400) $day++;
        }
        if ($recent >= 3 || $day >= 40) { $tooMany = true; return $all; }
        $all[] = ['id' => $id, 'name' => $name, 'role' => $role, 'linkedin' => $linkedin, 'email' => $email, 'message' => $msg,
                  'status' => 'pending', 'created' => date('c'), 'approved_at' => '', 'ip' => $ipHash];
        return $all;
    });
    if ($tooMany) fb_fail(429, 'We have received several submissions already. Please try again later.');

    $body = "A client has sent feedback for the website.\n\n"
          . "Name: $name\nRole / company: " . ($role !== '' ? $role : '-') . "\nLinkedIn: " . ($linkedin !== '' ? $linkedin : '-') . "\nEmail: $email\n\n"
          . "Feedback:\n$msg\n\n"
          . "Review, edit, approve or reject it here:\n" . at_review_url($id) . "\n\n"
          . "All feedback:\n" . at_list_url() . "\n\n"
          . "It will only appear on the website after you approve it.\n";
    at_mail_owner('New client feedback to review: ' . $name, $body, $email);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    fb_fail(500, 'Sorry, we could not save your feedback. Please email info@atinternational.online.');
}
