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
$resId = (int)($_POST["resource_id"] ?? 0);

if ($code === "" || $resId <= 0) {
  echo json_encode(["ok" => false, "error" => "Missing parameters."]);
  exit;
}

// Find group
$stmt = $conn->prepare("SELECT id, created_by FROM `groups` WHERE group_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$g = $stmt->get_result()->fetch_assoc();

if (!$g) {
  echo json_encode(["ok" => false, "error" => "Group not found."]);
  exit;
}
$gid = (int)$g["id"];
$groupCreator = (int)$g["created_by"];

// Must be member
$stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $gid, $uid);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
  echo json_encode(["ok" => false, "error" => "You are not a member of this group."]);
  exit;
}

// Check resource ownership
$stmt = $conn->prepare("SELECT id, created_by FROM group_resources WHERE id = ? AND group_id = ? LIMIT 1");
$stmt->bind_param("ii", $resId, $gid);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();

if (!$r) {
  echo json_encode(["ok" => false, "error" => "Resource not found."]);
  exit;
}

$resOwner = (int)$r["created_by"];
$canDelete = ($uid === $resOwner) || ($uid === $groupCreator);

if (!$canDelete) {
  echo json_encode(["ok" => false, "error" => "You don't have permission to delete this resource."]);
  exit;
}

// Delete
$stmt = $conn->prepare("DELETE FROM group_resources WHERE id = ? AND group_id = ?");
$stmt->bind_param("ii", $resId, $gid);
$stmt->execute();

echo json_encode(["ok" => true]);
?>