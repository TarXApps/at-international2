<?php
// Shared helpers for the client feedback flow (submit -> email review -> publish).
// This file is a library: it refuses to run when opened directly.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

const AT_OWNER_EMAIL = 'info@atinternational.online';
const AT_SITE_URL    = 'https://atinternational.online';

/** Folder for submissions and the signing key. Kept outside the web root when possible. */
function at_data_dir() {
    static $dir = null;
    if ($dir !== null) return $dir;
    $candidates = [dirname(__DIR__) . '/at-international-data', __DIR__ . '/.feedback-data'];
    foreach ($candidates as $i => $p) {
        if (!is_dir($p)) @mkdir($p, 0700, true);
        if (is_dir($p) && is_writable($p)) {
            if ($i === count($candidates) - 1) {
                @file_put_contents($p . '/.htaccess', "Require all denied\n");
            }
            return $dir = $p;
        }
    }
    throw new RuntimeException('No writable data folder');
}

function at_secret() {
    static $s = null;
    if ($s !== null) return $s;
    $f = at_data_dir() . '/secret.key';
    if (!is_file($f)) {
        file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($f, 0600);
    }
    return $s = trim((string)file_get_contents($f));
}

function at_sign($what) { return hash_hmac('sha256', $what, at_secret()); }
function at_review_token($id) { return at_sign('review:' . $id); }
function at_list_token() { return at_sign('list'); }
function at_review_url($id) { return AT_SITE_URL . '/feedback-review.php?id=' . rawurlencode($id) . '&t=' . at_review_token($id); }
function at_list_url() { return AT_SITE_URL . '/feedback-review.php?list=1&t=' . at_list_token(); }
/** Clean a LinkedIn profile link. Returns '' if empty, false if it is not a linkedin.com address. */
function at_linkedin($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (!preg_match('~^https?://~i', $v)) $v = 'https://' . $v;
    $p = parse_url($v);
    if (!$p || empty($p['host']) || !preg_match('~(^|\.)linkedin\.com$~i', $p['host'])) return false;
    $path = rtrim($p['path'] ?? '', '/');
    if ($path === '' || !preg_match('~^/[A-Za-z0-9_%./\-]+$~', $path) || strlen($path) > 150) return false;
    return 'https://' . strtolower($p['host']) . $path;
}
function at_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Read every submission (newest last). */
function at_load() {
    $f = at_data_dir() . '/submissions.json';
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

/** Change the stored list safely: $fn receives the array and returns the new array. */
function at_mutate(callable $fn) {
    $lock = fopen(at_data_dir() . '/submissions.lock', 'c');
    flock($lock, LOCK_EX);
    $f = at_data_dir() . '/submissions.json';
    $cur = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    if (!is_array($cur)) $cur = [];
    $new = $fn($cur);
    $tmp = $f . '.tmp';
    file_put_contents($tmp, json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    rename($tmp, $f);
    flock($lock, LOCK_UN);
    fclose($lock);
    return $new;
}

/** Email the owner. If the server cannot send mail, keep a copy in outbox.log. */
function at_mail_owner($subject, $body, $replyTo = '') {
    $headers = "From: A.T International <no-reply@atinternational.online>\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers .= "Reply-To: $replyTo\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    $ok = @mail(AT_OWNER_EMAIL, $subject, $body, $headers);
    if (!$ok) {
        @file_put_contents(at_data_dir() . '/outbox.log', '[' . date('c') . "] $subject\n$body\n\n", FILE_APPEND | LOCK_EX);
    }
    return $ok;
}

/** Approved testimonials, newest approval first. */
function at_approved($max = 12) {
    $out = [];
    foreach (at_load() as $s) {
        if (($s['status'] ?? '') === 'approved') $out[] = $s;
    }
    usort($out, function ($a, $b) { return strcmp($b['approved_at'] ?? '', $a['approved_at'] ?? ''); });
    return array_slice($out, 0, $max);
}

function at_card($name, $role, $text, $link = '') {
    echo '<div class="country-block-three col-lg-6 col-md-6 col-sm-12"><div class="inner-box"><div class="content">'
       . '<div class="quote-mark"><i class="fa fa-quote-left"></i></div>'
       . '<div class="text">&ldquo;' . at_h($text) . '&rdquo;</div>'
       . '<h4 class="title who">' . at_h($name) . '</h4>'
       . ($link !== '' ? '<div class="role"><a class="li-link" href="' . at_h($link) . '" target="_blank" rel="noopener nofollow"><i class="fab fa-linkedin"></i> LinkedIn profile</a></div>' : '')
       . ($role !== '' ? '<div class="role">' . at_h($role) . '</div>' : '')
       . '</div></div></div>';
}

/**
 * Print the testimonial cards: approved client feedback first, then the built-in ones,
 * up to $max cards in total. Any failure falls back to the built-in cards only.
 */
function at_render_testimonials(array $static, $max = 6) {
    $cards = [];
    try {
        foreach (at_approved($max) as $s) $cards[] = [$s['name'], $s['role'] ?? '', $s['message'], $s['linkedin'] ?? ''];
    } catch (Throwable $e) { $cards = []; }
    foreach ($static as $t) $cards[] = $t;
    foreach (array_slice($cards, 0, $max) as $c) at_card($c[0], $c[1], $c[2], $c[3] ?? '');
}
