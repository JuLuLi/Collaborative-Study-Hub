<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

require_vark_selected($conn);

$uid = (int)$_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_token"];

$flashOk = "";
$flashBad = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $csrfPost = $_POST["csrf_token"] ?? "";
  if (!hash_equals($csrf, $csrfPost)) {
    $flashBad = "Security check failed. Please refresh and try again.";
  } else {
    $action = $_POST["action"] ?? "";
    $code = trim($_POST["code"] ?? "");
    $groupId = (int)($_POST["group_id"] ?? 0);

    if ($action === "leave_group" && ($code !== "" || $groupId > 0)) {
      if ($groupId > 0) {
        $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
        $stmt->bind_param("ii", $groupId, $uid);
        $stmt->execute();
        $flashOk = "You left the group successfully.";
      } else if ($code !== "") {
        $stmt = $conn->prepare("SELECT id, created_by FROM `groups` WHERE group_code=? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc();
        if ($g && (int)$g["created_by"] !== $uid) {
          $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
          $stmt->bind_param("ii", $g["id"], $uid);
          $stmt->execute();
          $flashOk = "You left the group successfully.";
        } else if ($g && (int)$g["created_by"] === $uid) {
          $flashBad = "You are the creator of this group. You cannot leave it.";
        }
      }
    }
    
    if ($action === "delete_group" && $groupId > 0) {
      $stmt = $conn->prepare("SELECT created_by FROM `groups` WHERE id=? LIMIT 1");
      $stmt->bind_param("i", $groupId);
      $stmt->execute();
      $g = $stmt->get_result()->fetch_assoc();
      if ($g && (int)$g["created_by"] === $uid) {
        $stmt = $conn->prepare("DELETE FROM `groups` WHERE id=?");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $flashOk = "Group deleted successfully.";
      } else {
        $flashBad = "Only the group creator can delete this group.";
      }
    }
  }
}

// Get created groups
$stmt = $conn->prepare("
  SELECT g.id, g.group_code, g.name, g.subject, g.vark_type, g.created_at, g.description,
         (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS members
  FROM `groups` g
  WHERE g.created_by = ?
  ORDER BY g.created_at DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$createdGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get joined groups
$stmt = $conn->prepare("
  SELECT g.id, g.group_code, g.name, g.subject, g.vark_type, g.created_at, g.created_by, g.description,
         (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS members,
         (SELECT name FROM users WHERE id = g.created_by) AS creator_name
  FROM group_members gm
  JOIN `groups` g ON g.id = gm.group_id
  WHERE gm.user_id = ? AND g.created_by != ?
  ORDER BY gm.joined_at DESC
");
$stmt->bind_param("ii", $uid, $uid);
$stmt->execute();
$joinedGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$createdCount = count($createdGroups);
$joinedCount = count($joinedGroups);
$maxGroups = 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - My Groups</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .group-header-stats {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      margin-bottom: 28px;
    }
    .stat-pill {
      background: rgba(17,31,56,0.6);
      border-radius: 40px;
      padding: 8px 18px;
      font-size: 14px;
    }
    .group-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .delete-btn {
      background: rgba(251,113,133,0.1);
      border-color: rgba(251,113,133,0.3);
      color: #fb7185;
    }
    .delete-btn:hover {
      background: rgba(251,113,133,0.2);
    }
    .empty-groups {
      text-align: center;
      padding: 48px 24px;
    }
    .empty-groups .btn {
      margin-top: 16px;
    }
    
    /* Section Divider Styles */
    .groups-section {
      margin-bottom: 40px;
    }
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 2px solid rgba(110,231,255,0.2);
    }
    .section-title {
      font-size: 22px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-badge {
      background: rgba(110,231,255,0.15);
      padding: 4px 12px;
      border-radius: 30px;
      font-size: 13px;
      font-weight: normal;
    }
    .groups-container {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .group-card {
      background: rgba(17,31,56,0.5);
      border-radius: 20px;
      padding: 18px 20px;
      border: 1px solid rgba(255,255,255,0.08);
      transition: all 0.2s ease;
    }
    .group-card:hover {
      border-color: rgba(110,231,255,0.3);
      background: rgba(17,31,56,0.7);
      transform: translateX(4px);
    }
    .group-info {
      flex: 1;
    }
    .group-title {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 6px;
    }
    .group-title h3 {
      margin: 0;
      font-size: 18px;
    }
    .group-meta {
      margin: 6px 0 0;
      font-size: 13px;
      color: rgba(255,255,255,0.6);
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
    }
    .group-description {
      margin: 8px 0 0;
      font-size: 13px;
      color: rgba(255,255,255,0.5);
    }
    
    @media (max-width: 700px) {
      .group-card {
        padding: 14px 16px;
      }
      .group-title h3 {
        font-size: 16px;
      }
      .group-actions {
        margin-top: 12px;
        width: 100%;
        justify-content: flex-start;
      }
      .group-card > div {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . "/includes/navbar.php"; ?>

  <div class="container">
    <div class="card">
      <div class="hd">
        <div>
          <h1 class="h1">📋 My Groups</h1>
          <p class="p">Manage all the study groups you've created or joined.</p>
        </div>

        <div class="group-actions">
          <a class="btn btn-primary" href="create_group.php"
             <?= ($createdCount >= $maxGroups) ? 'aria-disabled="true" onclick="return false;" style="opacity:.5; pointer-events:none;"' : '' ?>>
            ➕ Create Group (<?= $createdCount ?>/<?= $maxGroups ?>)
          </a>
          <a class="btn" href="home.php">🏠 Dashboard</a>
        </div>
      </div>

      <div class="bd">
        <?php if ($flashOk): ?>
          <div class="notice good" style="margin-bottom: 20px;"><?= htmlspecialchars($flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashBad): ?>
          <div class="notice bad" style="margin-bottom: 20px;"><?= htmlspecialchars($flashBad) ?></div>
        <?php endif; ?>

        <div class="group-header-stats">
          <div class="stat-pill">👑 Created: <strong><?= $createdCount ?></strong> / <?= $maxGroups ?></div>
          <div class="stat-pill">🤝 Joined: <strong><?= $joinedCount ?></strong></div>
          <div class="stat-pill">👥 Total Memberships: <strong><?= $createdCount + $joinedCount ?></strong></div>
        </div>

        <div class="groups-section">
          <div class="section-header">
            <div class="section-title">
              <span>👑</span> Groups You Created
              <span class="section-badge"><?= $createdCount ?> groups</span>
            </div>
            <?php if ($createdCount > 0): ?>
              <a href="create_group.php" class="badge" style="font-size: 12px;">+ Create more</a>
            <?php endif; ?>
          </div>
          
          <div class="groups-container">
            <?php if (count($createdGroups) === 0): ?>
              <div class="empty-groups card" style="background: rgba(17,31,56,0.3); margin: 0;">
                <div style="font-size: 48px; margin-bottom: 12px;">🚀</div>
                <p>You haven't created any groups yet.</p>
                <a href="create_group.php" class="btn btn-primary">Create Your First Group</a>
              </div>
            <?php else: ?>
              <?php foreach ($createdGroups as $g): ?>
                <div class="group-card">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                    <div class="group-info">
                      <div class="group-title">
                        <h3><?= htmlspecialchars($g["name"]) ?></h3>
                        <span class="badge <?= vark_badge_class($g["vark_type"]) ?>"><?= htmlspecialchars($g["vark_type"]) ?></span>
                        <span class="badge">👥 <?= (int)$g["members"] ?> members</span>
                        <span class="badge" style="background: rgba(110,231,255,0.15);">👑 Creator</span>
                      </div>
                      <div class="group-meta">
                        <span>📖 <?= htmlspecialchars($g["subject"]) ?></span>
                        <span>🔑 Code: <strong><?= htmlspecialchars($g["group_code"]) ?></strong></span>
                      </div>
                      <?php if (!empty($g["description"])): ?>
                        <div class="group-description"><?= htmlspecialchars(mb_strimwidth($g["description"], 0, 100, "…")) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="group-actions">
                      <a href="chat.php?code=<?= urlencode($g["group_code"]) ?>" class="btn btn-primary" style="padding: 8px 16px;">💬 Chat</a>
                      <a href="group.php?code=<?= urlencode($g["group_code"]) ?>" class="btn" style="padding: 8px 16px;">⚙️ Manage</a>
                      <form method="POST" style="margin: 0;" onsubmit="return confirm('⚠️ Delete this group permanently? This action cannot be undone. All chats and resources will be lost.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="delete_group">
                        <input type="hidden" name="group_id" value="<?= $g["id"] ?>">
                        <button type="submit" class="btn delete-btn" style="padding: 8px 14px;">🗑️ Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="groups-section">
          <div class="section-header">
            <div class="section-title">
              <span>🤝</span> Groups You Joined
              <span class="section-badge"><?= $joinedCount ?> groups</span>
            </div>
            <?php if ($joinedCount === 0): ?>
              <a href="home.php" class="badge" style="font-size: 12px;">Browse recommendations</a>
            <?php endif; ?>
          </div>
          
          <div class="groups-container">
            <?php if (count($joinedGroups) === 0): ?>
              <div class="empty-groups card" style="background: rgba(17,31,56,0.3); margin: 0;">
                <div style="font-size: 48px; margin-bottom: 12px;">🔍</div>
                <p>You haven't joined any groups yet.</p>
                <a href="home.php" class="btn btn-primary">Browse Recommended Groups</a>
              </div>
            <?php else: ?>
              <?php foreach ($joinedGroups as $g): ?>
                <div class="group-card">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                    <div class="group-info">
                      <div class="group-title">
                        <h3><?= htmlspecialchars($g["name"]) ?></h3>
                        <span class="badge <?= vark_badge_class($g["vark_type"]) ?>"><?= htmlspecialchars($g["vark_type"]) ?></span>
                        <span class="badge">👥 <?= (int)$g["members"] ?> members</span>
                      </div>
                      <div class="group-meta">
                        <span>📖 <?= htmlspecialchars($g["subject"]) ?></span>
                        <span>🔑 Code: <strong><?= htmlspecialchars($g["group_code"]) ?></strong></span>
                        <span>👤 Created by: <?= htmlspecialchars($g["creator_name"] ?? "Unknown") ?></span>
                      </div>
                      <?php if (!empty($g["description"])): ?>
                        <div class="group-description"><?= htmlspecialchars(mb_strimwidth($g["description"], 0, 100, "…")) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="group-actions">
                      <a href="chat.php?code=<?= urlencode($g["group_code"]) ?>" class="btn btn-primary" style="padding: 8px 16px;">💬 Chat</a>
                      <a href="group.php?code=<?= urlencode($g["group_code"]) ?>" class="btn" style="padding: 8px 16px;">📋 Info</a>
                      <form method="POST" style="margin: 0;" onsubmit="return confirm('Leave this group? You can join again later if you have the code.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="leave_group">
                        <input type="hidden" name="group_id" value="<?= $g["id"] ?>">
                        <button type="submit" class="btn delete-btn" style="padding: 8px 14px;">🚪 Leave</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="notice" style="margin-top: 20px; text-align: center;">
          💡 <strong>Tip:</strong> Share your group code with classmates to invite them. Only members can see group chats and resources.
        </div>
      </div>
    </div>
  </div>
</body>
</html>