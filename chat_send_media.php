<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

header("Content-Type: application/json; charset=utf-8");

// Check if user is logged in
require_login();

$uid = (int)$_SESSION["user_id"];

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  echo json_encode([
    "ok" => false, 
    "error" => "Invalid request method. Please use POST."
  ]);
  exit;
}

$csrf = $_POST["csrf_token"] ?? "";
if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $csrf)) {
  echo json_encode([
    "ok" => false, 
    "error" => "Security check failed. Please refresh the page and try again."
  ]);
  exit;
}

$code = trim($_POST["code"] ?? "");
$msg = trim($_POST["message"] ?? "");
$media_type = trim($_POST["media_type"] ?? "");
$media_data = trim($_POST["media_data"] ?? "");

// Validate group code
if ($code === "") {
  echo json_encode([
    "ok" => false, 
    "error" => "Group code is required."
  ]);
  exit;
}

// Validate media type (image, audio, whiteboard)
$allowed_media_types = ['image', 'audio', 'whiteboard'];
if ($media_type !== "" && !in_array($media_type, $allowed_media_types)) {
  echo json_encode([
    "ok" => false, 
    "error" => "Invalid media type. Allowed: image, audio, whiteboard."
  ]);
  exit;
}

$max_media_size = 5000000; // 5MB

if ($media_type !== "" && strlen($media_data) > $max_media_size) {
  echo json_encode([
    "ok" => false, 
    "error" => "Media file is too large (max " . ($max_media_size / 1000000) . "MB). Please use a smaller file."
  ]);
  exit;
}

// Validate message length (max 2000 chars)
if (strlen($msg) > 2000) {
  echo json_encode([
    "ok" => false, 
    "error" => "Message is too long (max 2000 characters)."
  ]);
  exit;
}

$stmt = $conn->prepare("SELECT id FROM `groups` WHERE group_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
  echo json_encode([
    "ok" => false, 
    "error" => "Group not found. Please check the group code."
  ]);
  exit;
}

$gid = (int)$group["id"];

$stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $gid, $uid);
$stmt->execute();
$isMember = (bool)$stmt->get_result()->fetch_assoc();

if (!$isMember) {
  echo json_encode([
    "ok" => false, 
    "error" => "You are not a member of this group. Please join the group first."
  ]);
  exit;
}

$final_message = $msg;

// If media is present, embed it as JSON in the message
if ($media_type !== "" && $media_data !== "") {
  if ($media_type === 'image' && !preg_match('/^data:image\/[a-zA-Z]+;base64,/', $media_data)) {
    echo json_encode([
      "ok" => false, 
      "error" => "Invalid image data format."
    ]);
    exit;
  }
  
  if ($media_type === 'audio' && !preg_match('/^data:audio\/[a-zA-Z]+;base64,/', $media_data)) {
    echo json_encode([
      "ok" => false, 
      "error" => "Invalid audio data format."
    ]);
    exit;
  }
  
  if ($media_type === 'whiteboard' && !preg_match('/^data:image\/png;base64,/', $media_data)) {
    echo json_encode([
      "ok" => false, 
      "error" => "Invalid whiteboard data format."
    ]);
    exit;
  }
  
  // Create JSON object for media
  $media_json = json_encode([
    'type' => $media_type,
    'data' => $media_data
  ]);
  
  // Append media marker to message
  $final_message = $msg . "\n[Media:" . $media_json . "]";
  
  $max_total_size = 8000000; // 8MB
  
  if (strlen($final_message) > $max_total_size) {
    echo json_encode([
      "ok" => false, 
      "error" => "Total message size is too large (max 8MB). Please use a smaller image or shorter audio."
    ]);
    exit;
  }
}

$stmt = $conn->prepare("INSERT INTO messages (group_id, user_id, message_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $gid, $uid, $final_message);

if ($stmt->execute()) {
  echo json_encode([
    "ok" => true,
    "message" => "Message sent successfully!"
  ]);
} else {
  echo json_encode([
    "ok" => false, 
    "error" => "Database error: Could not save message. Please try again."
  ]);
}

$stmt->close();
$conn->close();
?>