<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
  if (empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
  }
}

function require_vark_selected(mysqli $conn) {
  require_login();
  $uid = (int)$_SESSION['user_id'];

  $stmt = $conn->prepare("SELECT vark_style FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();

  if (!$res || $res['vark_style'] === null) {
    header("Location: vark_select.php");
    exit;
  }
}
?>