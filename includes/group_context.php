<?php

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == 'group_context.php') {
  header("Location: home.php");
  exit;
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/helpers.php";

// Check if user is logged in and has VARK selected
if (empty($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$uid = (int)$_SESSION["user_id"];

// Verify user has VARK style
$stmt = $conn->prepare("SELECT vark_style FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$userCheck = $stmt->get_result()->fetch_assoc();

if (!$userCheck || $userCheck['vark_style'] === null) {
  header("Location: vark_select.php");
  exit;
}

// Get group code from URL
$code = trim($_GET["code"] ?? $_POST["code"] ?? "");

if ($code === "") {
  header("Location: group_list.php");
  exit;
}

// Fetch group with creator name
$stmt = $conn->prepare("
  SELECT g.id, g.group_code, g.name, g.subject, g.description, g.vark_type,
         g.created_by, g.created_at,
         u.name AS creator_name
  FROM `groups` g
  JOIN users u ON u.id = g.created_by
  WHERE g.group_code = ?
  LIMIT 1
");
$stmt->bind_param("s", $code);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
  // Group not found
  header("Location: group_list.php?error=not_found");
  exit;
}

$gid = (int)$group["id"];
$isCreator = ((int)$group["created_by"] === $uid);

// Check if user is a member
$stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $gid, $uid);
$stmt->execute();
$isMember = (bool)$stmt->get_result()->fetch_assoc();

// If not a member, redirect to group info page (not chat)
$current_page = basename($_SERVER['PHP_SELF']);
if (!$isMember && in_array($current_page, ['chat.php', 'group_members.php', 'group_info.php'])) {
  header("Location: group.php?code=" . urlencode($group["group_code"]) . "&error=not_member");
  exit;
}

// Get user's name for chat
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$myName = $me["name"] ?? "You";

// Helper functions for VARK styling in group context
function get_group_vark_icon($type) {
  return match($type) {
    'V' => '📊',
    'A' => '🎧',
    'R' => '📚',
    'K' => '🔬',
    default => '🧠'
  };
}

function get_group_vark_tip($type) {
  return match($type) {
    'V' => 'Share diagrams, images, mind maps, and visual summaries.',
    'A' => 'Have discussions, Q&A sessions, and verbal explanations.',
    'R' => 'Share notes, reading lists, and written summaries.',
    'K' => 'Do hands-on activities, experiments, and practice tasks.',
    default => 'Collaborate effectively with your study partners.'
  };
}
?>