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
  echo json_encode(["ok" => false, "error" => "Security check failed."]);
  exit;
}

$code = trim($_POST["code"] ?? "");
$message_id = (int)($_POST["message_id"] ?? 0);

if ($code === "" || $message_id <= 0) {
  echo json_encode(["ok" => false, "error" => "Invalid parameters."]);
  exit;
}

// Find group
$stmt = $conn->prepare("SELECT id, created_by FROM `groups` WHERE group_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
  echo json_encode(["ok" => false, "error" => "Group not found."]);
  exit;
}

$gid = (int)$group["id"];
$groupCreator = (int)$group["created_by"];

// Check if user is member of the group
$stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $gid, $uid);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
  echo json_encode(["ok" => false, "error" => "You are not a member of this group."]);
  exit;
}

// Get message owner
$stmt = $conn->prepare("SELECT user_id FROM messages WHERE id = ? AND group_id = ? LIMIT 1");
$stmt->bind_param("ii", $message_id, $gid);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();

if (!$msg) {
  echo json_encode(["ok" => false, "error" => "Message not found."]);
  exit;
}

$messageOwner = (int)$msg["user_id"];

// Check permission: owner or group creator can delete
if ($uid !== $messageOwner && $uid !== $groupCreator) {
  echo json_encode(["ok" => false, "error" => "You don't have permission to delete this message."]);
  exit;
}

// Delete the message
$stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND group_id = ?");
$stmt->bind_param("ii", $message_id, $gid);
$stmt->execute();

echo json_encode(["ok" => true, "message" => "Message deleted successfully."]);
?>