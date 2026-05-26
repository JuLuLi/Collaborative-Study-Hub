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

$csrf = $_POST["csrf_token"] ?? "";
if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $csrf)) {
  echo json_encode(["ok" => false, "error" => "Security check failed."]);
  exit;
}

$code = trim($_POST["code"] ?? "");
$type = trim($_POST["resource_type"] ?? "NOTE");
$title = trim($_POST["title"] ?? "");
$content = trim($_POST["content"] ?? "");

if ($code === "" || $title === "") {
  echo json_encode(["ok" => false, "error" => "Title is required."]);
  exit;
}

if (!in_array($type, ["LINK", "NOTE", "TASK"], true)) {
  echo json_encode(["ok" => false, "error" => "Invalid resource type."]);
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
if (!$stmt->get_result()->fetch_assoc()) {
  echo json_encode(["ok" => false, "error" => "You are not a member of this group."]);
  exit;
}

// Insert resource
$stmt = $conn->prepare("
  INSERT INTO group_resources (group_id, created_by, resource_type, title, content)
  VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("iisss", $gid, $uid, $type, $title, $content);
$stmt->execute();

echo json_encode(["ok" => true]);
?>