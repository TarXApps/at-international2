<?php
// Private review page for client feedback. Access needs the secret link emailed to A.T International.
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/feedback-lib.php';

function rv_page($title, $inner) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . at_h($title) . ' | A.T International</title>'
       . '<meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<link href="css/bootstrap.min.css" rel="stylesheet"><link href="css/style.css" rel="stylesheet">'
       . '<link href="css/style-app4.css" rel="stylesheet"><link href="css/responsive.css" rel="stylesheet"><link href="css/brand.css" rel="stylesheet">'
       . '<link rel="icon" href="images/favicon.png"></head><body class="legal-page" style="background:#0A0330">'
       . '<div class="page-wrapper bg_linear_full"><header class="legal-header"><div class="auto-container legal-header-in">'
       . '<a class="legal-logo" href="index.php"><img src="images/at-logo-white.png" alt="A.T International"></a>'
       . '<span class="rv-badge">Feedback review (private)</span></div></header>'
       . '<main class="legal-main"><div class="auto-container"><article class="legal-card">' . $inner
       . '</article></div></main></div></body></html>';
    exit;
}
function rv_denied() { http_response_code(403); rv_page('Link not valid', '<h1>Link not valid</h1><p>This review link is not valid. Please use the link from the email we sent you.</p>'); }

$status_label = ['pending' => 'Waiting for review', 'approved' => 'Published', 'rejected' => 'Rejected'];

// ---- List of everything ----
if (isset($_GET['list'])) {
    $t = (string)($_GET['t'] ?? '');
    if (!hash_equals(at_list_token(), $t)) rv_denied();
    $all = array_reverse(at_load());
    $rows = '';
    foreach ($all as $s) {
        $rows .= '<tr><td>' . at_h(substr($s['created'], 0, 10)) . '</td><td>' . at_h($s['name']) . '</td><td>'
              . '<span class="rv-pill rv-' . at_h($s['status']) . '">' . at_h($status_label[$s['status']] ?? $s['status']) . '</span></td>'
              . '<td><a href="' . at_h(at_review_url($s['id'])) . '">Open</a></td></tr>';
    }
    if ($rows === '') $rows = '<tr><td colspan="4">No feedback yet.</td></tr>';
    rv_page('All feedback', '<div class="eyebrow">Private</div><h1>All feedback</h1>'
        . '<div class="rv-table-wrap"><table class="rv-table"><thead><tr><th>Date</th><th>Name</th><th>Status</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>');
}

// ---- One item ----
$id = (string)($_REQUEST['id'] ?? '');
$t  = (string)($_REQUEST['t'] ?? '');
if ($id === '' || !preg_match('/^[a-f0-9]{16}$/', $id) || !hash_equals(at_review_token($id), $t)) rv_denied();

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $clean = function ($v) { return trim(preg_replace('/\s+/', ' ', strip_tags((string)$v))); };
    $name = $clean($_POST['name'] ?? ''); $role = $clean($_POST['role'] ?? '');
    $msg  = trim(strip_tags((string)($_POST['message'] ?? '')));
    $msg  = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\r\n?/", "\n", $msg)));
    $mb   = function ($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); };
    if (!in_array($action, ['save', 'approve', 'reject', 'unpublish'], true)) {
        $notice = 'Unknown action.';
    } elseif ($name === '' || $msg === '' || $mb($msg) > 600 || $mb($name) > 80 || $mb($role) > 100) {
        $notice = 'Please check the name and text (feedback up to 600 characters).';
    } else {
        at_mutate(function ($all) use ($id, $action, $name, $role, $msg, &$notice) {
            foreach ($all as &$s) {
                if ($s['id'] !== $id) continue;
                $s['name'] = $name; $s['role'] = $role; $s['message'] = $msg;
                if ($action === 'approve')   { $s['status'] = 'approved'; $s['approved_at'] = date('c'); $notice = 'Approved. It is now showing in the Clients section of the website.'; }
                if ($action === 'reject')    { $s['status'] = 'rejected'; $s['approved_at'] = ''; $notice = 'Rejected. It will not appear on the website.'; }
                if ($action === 'unpublish') { $s['status'] = 'pending';  $s['approved_at'] = ''; $notice = 'Removed from the website. It is back to waiting for review.'; }
                if ($action === 'save')      { $notice = 'Edits saved.'; }
            }
            unset($s);
            return $all;
        });
    }
}

$item = null;
foreach (at_load() as $s) { if ($s['id'] === $id) $item = $s; }
if (!$item) rv_denied();

$q = '?id=' . rawurlencode($id) . '&t=' . rawurlencode($t);
$btns = '<button class="theme-btn btn-style-one" name="action" value="approve" type="submit"><span class="btn-title">' . ($item['status'] === 'approved' ? 'Save and keep published' : 'Approve and publish') . '</span></button> '
      . '<button class="rv-btn rv-ghost" name="action" value="save" type="submit">Save edits</button> ';
if ($item['status'] === 'approved') $btns .= '<button class="rv-btn rv-warn" name="action" value="unpublish" type="submit">Unpublish</button> ';
if ($item['status'] !== 'rejected') $btns .= '<button class="rv-btn rv-warn" name="action" value="reject" type="submit">Reject</button>';

$inner = '<div class="eyebrow">Private</div><h1>Review feedback</h1>'
    . '<p class="legal-meta">Received ' . at_h(substr($item['created'], 0, 10)) . ' &nbsp;|&nbsp; Status: <span class="rv-pill rv-' . at_h($item['status']) . '">' . at_h($status_label[$item['status']] ?? $item['status']) . '</span></p>'
    . ($notice !== '' ? '<div class="rv-notice">' . at_h($notice) . '</div>' : '')
    . '<p>Email (private, never shown on the website): <a href="mailto:' . at_h($item['email']) . '">' . at_h($item['email']) . '</a></p>'
    . '<form method="post" action="feedback-review.php" class="enq-form rv-form">'
    . '<input type="hidden" name="id" value="' . at_h($id) . '"><input type="hidden" name="t" value="' . at_h($t) . '">'
    . '<label for="rv-name">Name (shown on the website)</label><input id="rv-name" name="name" type="text" maxlength="80" value="' . at_h($item['name']) . '">'
    . '<label for="rv-role">Role and company (shown on the website, optional)</label><input id="rv-role" name="role" type="text" maxlength="100" value="' . at_h($item['role'] ?? '') . '">'
    . '<label for="rv-msg">Feedback (you can fix typos before publishing)</label><textarea id="rv-msg" name="message" rows="6" maxlength="600">' . at_h($item['message']) . '</textarea>'
    . '<div class="rv-actions">' . $btns . '</div></form>'
    . '<p style="margin-top:26px"><a href="' . at_h(at_list_url()) . '">See all feedback</a></p>';
rv_page('Review feedback', $inner);
