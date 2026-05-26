<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

header("Content-Type: application/json; charset=utf-8");

require_login();

$uid = (int)$_SESSION["user_id"];
$code = trim($_GET["code"] ?? "");
$afterId = (int)($_GET["after_id"] ?? 0);

if ($code === "") {
  echo json_encode(["ok" => false, "error" => "Missing group code."]);
  exit;
}

$stmt = $conn->prepare("SELECT id FROM `groups` WHERE group_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$g = $stmt->get_result()->fetch_assoc();

if (!$g) {
  echo json_encode(["ok" => false, "error" => "Group not found."]);
  exit;
}
$gid = (int)$g["id"];

$stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $gid, $uid);
$stmt->execute();
$isMember = (bool)$stmt->get_result()->fetch_assoc();

if (!$isMember) {
  echo json_encode(["ok" => false, "error" => "You are not a member of this group."]);
  exit;
}

// Get group creator info for delete permission
$stmt = $conn->prepare("SELECT created_by FROM `groups` WHERE id = ?");
$stmt->bind_param("i", $gid);
$stmt->execute();
$groupCreator = (int)$stmt->get_result()->fetch_assoc()["created_by"];

$stmt = $conn->prepare("
  SELECT m.id, m.user_id, u.name AS user_name, m.message_text,
         DATE_FORMAT(m.created_at, '%H:%i') AS created_at
  FROM messages m
  JOIN users u ON u.id = m.user_id
  WHERE m.group_id = ? AND m.id > ?
  ORDER BY m.id ASC
  LIMIT 100
");
$stmt->bind_param("ii", $gid, $afterId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$row) {
  $clean_text = $row['message_text'];
  $image_data = null;
  $audio_data = null;
  $whiteboard_data = null;
  
  $pattern = '/\[Media:(\{.*?\})\]/s';
  if (preg_match($pattern, $row['message_text'], $matches)) {
    $media = json_decode($matches[1], true);
    if ($media && isset($media['type']) && isset($media['data'])) {
      if ($media['type'] === 'image') $image_data = $media['data'];
      elseif ($media['type'] === 'audio') $audio_data = $media['data'];
      elseif ($media['type'] === 'whiteboard') $whiteboard_data = $media['data'];
      $clean_text = trim(str_replace($matches[0], '', $row['message_text']));
    }
  }
  
  $row['message_text'] = htmlspecialchars($clean_text, ENT_QUOTES, 'UTF-8');
  $row['user_name'] = htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8');
  $row['image_data'] = $image_data;
  $row['audio_data'] = $audio_data;
  $row['whiteboard_data'] = $whiteboard_data;
  $row['can_delete'] = ((int)$row['user_id'] === $uid || (int)$groupCreator === $uid);
}

echo json_encode(["ok" => true, "messages" => $rows]);
?>