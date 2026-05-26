<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

header("Content-Type: application/json; charset=utf-8");

require_login();

$uid = (int)$_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  echo json_encode(["ok" => false, "error" => "Invalid request method."]);
  exit;
}

// CSRF Protection
$csrf = $_POST["csrf_token"] ?? "";
if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $csrf)) {
  echo json_encode(["ok" => false, "error" => "Security check failed. Please refresh and try again."]);
  exit;
}

$code = trim($_POST["code"] ?? "");
$msg = trim($_POST["message"] ?? "");

if ($code === "") {
  echo json_encode(["ok" => false, "error" => "Group code is required."]);
  exit;
}

if ($msg === "") {
  echo json_encode(["ok" => false, "error" => "Message cannot be empty."]);
  exit;
}

// Length limits
if (mb_strlen($msg) > 2000) {
  echo json_encode(["ok" => false, "error" => "Message too long (max 2000 characters)."]);
  exit;
}

// Find group
$stmt = $conn->prepare("SELECT id FROM `groups` WHERE group_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$g = $stmt->get_result()->fetch_assoc();

if (!$g) {
  echo json_encode(["ok" => false, "error" => "Group not found."]);
  exit;
}
$gid = (int)$g["id"];

// Must be a member
$stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $gid, $uid);
$stmt->execute();
$isMember = (bool)$stmt->get_result()->fetch_assoc();

if (!$isMember) {
  echo json_encode(["ok" => false, "error" => "You are not a member of this group."]);
  exit;
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (group_id, user_id, message_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $gid, $uid, $msg);
$stmt->execute();

echo json_encode(["ok" => true]);
?>