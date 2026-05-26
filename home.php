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

// Get current user info
$stmt = $conn->prepare("SELECT name, email, vark_style, created_at FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$userName = $user["name"] ?? "User";
$userEmail = $user["email"] ?? "";
$userVark = $user["vark_style"] ?? "V";
$userSince = date("M Y", strtotime($user["created_at"] ?? "now"));

// Handle JOIN action
$join_success = false;
$join_error = false;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "join_from_home") {
  $csrfPost = $_POST["csrf_token"] ?? "";
  if (!hash_equals($csrf, $csrfPost)) {
    $join_error = "Security check failed.";
  } else {
    $code = trim($_POST["code"] ?? "");
    if ($code !== "") {
      $stmt = $conn->prepare("SELECT id FROM `groups` WHERE group_code=? LIMIT 1");
      $stmt->bind_param("s", $code);
      $stmt->execute();
      $g = $stmt->get_result()->fetch_assoc();
      
      if ($g) {
        $gid = (int)$g["id"];
        try {
          $stmt = $conn->prepare("INSERT INTO group_members(group_id, user_id) VALUES (?, ?)");
          $stmt->bind_param("ii", $gid, $uid);
          $stmt->execute();
          $join_success = true;
        } catch (mysqli_sql_exception $e) {
          $join_error = "You are already a member of this group.";
        }
      } else {
        $join_error = "Group not found.";
      }
    }
  }
}

// Get recommended groups (by user's VARK)
$stmt = $conn->prepare("
  SELECT g.id, g.group_code, g.name, g.subject, g.description, g.vark_type, g.created_at,
         (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS members,
         CASE WHEN gm.id IS NULL THEN 0 ELSE 1 END AS is_member
  FROM `groups` g
  LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
  WHERE g.vark_type = ? AND g.created_by != ?
  ORDER BY g.created_at DESC
  LIMIT 6
");
$stmt->bind_param("isi", $uid, $userVark, $uid);
$stmt->execute();
$recommended = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's groups (joined + created)
$stmt = $conn->prepare("
  SELECT DISTINCT g.id, g.group_code, g.name, g.subject, g.vark_type, g.created_by,
         (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS members,
         (g.created_by = ?) AS is_creator,
         CASE WHEN gm.id IS NULL THEN 0 ELSE 1 END AS is_member
  FROM `groups` g
  LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
  WHERE g.created_by = ? OR gm.user_id = ?
  ORDER BY g.created_at DESC
  LIMIT 10
");
$stmt->bind_param("iiii", $uid, $uid, $uid, $uid);
$stmt->execute();
$myGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle leave group
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "leave_group") {
  $csrfPost = $_POST["csrf_token"] ?? "";
  if (hash_equals($csrf, $csrfPost)) {
    $groupId = (int)($_POST["group_id"] ?? 0);
    if ($groupId > 0) {
      $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
      $stmt->bind_param("ii", $groupId, $uid);
      $stmt->execute();
      header("Location: home.php?left=1");
      exit;
    }
  }
}

// Search query
$q = trim($_GET["q"] ?? "");
$searchResults = [];
if ($q !== "") {
  $like = "%" . $q . "%";
  $stmt = $conn->prepare("
    SELECT g.id, g.group_code, g.name, g.subject, g.description, g.vark_type,
           (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS members,
           CASE WHEN gm.id IS NULL THEN 0 ELSE 1 END AS is_member
    FROM `groups` g
    LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
    WHERE g.group_code LIKE ? OR g.subject LIKE ? OR g.name LIKE ?
    ORDER BY (g.group_code = ?) DESC, g.created_at DESC
    LIMIT 20
  ");
  $stmt->bind_param("issss", $uid, $like, $like, $like, $q);
  $stmt->execute();
  $searchResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$flashMessage = "";
if (isset($_GET['vark_set'])) {
  $flashMessage = "🎉 Your VARK style has been set to " . vark_full_name($userVark) . "!";
}
if (isset($_GET['left'])) {
  $flashMessage = "You left the group successfully.";
}
if ($join_success) {
  $flashMessage = "✅ Successfully joined the group!";
}
if ($join_error) {
  $flashMessage = $join_error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - Dashboard</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .welcome-card {
      background: linear-gradient(135deg, rgba(110,231,255,0.1), rgba(167,139,250,0.08));
      border-radius: 28px;
      padding: 28px 32px;
      margin-bottom: 28px;
      border: 1px solid rgba(110,231,255,0.2);
    }
    
    .vark-profile-card {
      background: rgba(17,31,56,0.6);
      border-radius: 24px;
      padding: 24px;
      text-align: center;
      border: 1px solid var(--border);
    }
    
    .vark-badge-large {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      padding: 12px 24px;
      border-radius: 60px;
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 16px;
    }
    
    .vark-badge-large.V { background: rgba(110,231,255,0.15); border: 1px solid rgba(110,231,255,0.4); color: #6ee7ff; }
    .vark-badge-large.A { background: rgba(52,211,153,0.15); border: 1px solid rgba(52,211,153,0.4); color: #34d399; }
    .vark-badge-large.R { background: rgba(251,191,36,0.15); border: 1px solid rgba(251,191,36,0.4); color: #fbbf24; }
    .vark-badge-large.K { background: rgba(167,139,250,0.15); border: 1px solid rgba(167,139,250,0.4); color: #a78bfa; }
    
    .search-bar {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 24px;
    }
    .search-bar .input {
      flex: 1;
      min-width: 200px;
    }
    
    .group-card {
      background: rgba(17,31,56,0.5);
      border-radius: 20px;
      padding: 18px 20px;
      margin-bottom: 12px;
      border: 1px solid rgba(255,255,255,0.08);
      transition: all 0.2s ease;
    }
    .group-card:hover {
      border-color: rgba(110,231,255,0.3);
      background: rgba(17,31,56,0.7);
    }
    
    .empty-state {
      text-align: center;
      padding: 48px 24px;
      color: var(--muted);
    }
    
    .quick-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 16px;
    }
    
    .flash-message {
      padding: 14px 20px;
      border-radius: 16px;
      margin-bottom: 20px;
      animation: fadeIn 0.3s ease;
    }
    .flash-success {
      background: rgba(52,211,153,0.15);
      border: 1px solid rgba(52,211,153,0.3);
      color: #34d399;
    }
    .flash-error {
      background: rgba(251,113,133,0.15);
      border: 1px solid rgba(251,113,133,0.3);
      color: #fb7185;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .stat-mini {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--muted);
    }
  </style>
</head>
<body>
  <?php include __DIR__ . "/includes/navbar.php"; ?>

  <div class="container">
    <?php if ($flashMessage): ?>
      <div class="flash-message <?= strpos($flashMessage, '✅') !== false || strpos($flashMessage, '🎉') !== false ? 'flash-success' : (strpos($flashMessage, 'error') !== false || strpos($flashMessage, 'already') !== false ? 'flash-error' : '') ?>">
        <?= htmlspecialchars($flashMessage) ?>
      </div>
    <?php endif; ?>

    <!-- Welcome Section -->
    <div class="welcome-card">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
          <h1 style="margin: 0 0 8px 0;">Welcome back, <?= htmlspecialchars($userName) ?>! 👋</h1>
          <p class="p" style="margin: 0;">Ready to study smarter together?</p>
        </div>
        <div class="quick-actions">
          <a href="create_group.php" class="btn btn-primary">➕ Create New Group</a>
          <a href="group_list.php" class="btn">📋 My Groups</a>
        </div>
      </div>
    </div>

    <div class="grid grid-2" style="gap: 24px;">
      <div>
        <div class="vark-profile-card">
          <div class="vark-badge-large <?= $userVark ?>">
            <span style="font-size: 28px;"><?= vark_icon($userVark) ?></span>
            <span><?= vark_full_name($userVark) ?></span>
          </div>
          <p class="p"><?= vark_description($userVark) ?></p>
          <div class="stat-mini" style="justify-content: center; margin-top: 12px;">
            <span>📅 Member since <?= $userSince ?></span>
          </div>
          <div style="margin-top: 16px;">
            <a href="profile.php" class="btn" style="width: 100%;">✏️ Edit Profile</a>
          </div>
        </div>
        
        <div class="card" style="margin-top: 24px;">
          <div class="hd">
            <h2 class="h2">🔍 Search Groups</h2>
            <span class="badge">By code, name, or subject</span>
          </div>
          <div class="bd">
            <form method="GET" class="search-bar">
              <input class="input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Enter group code, name, or subject...">
              <button class="btn btn-primary" type="submit">Search</button>
              <?php if ($q !== ""): ?>
                <a class="btn" href="home.php">Clear</a>
              <?php endif; ?>
            </form>
            
            <?php if ($q !== ""): ?>
              <?php if (count($searchResults) === 0): ?>
                <div class="empty-state">
                  <p>No groups found for "<?= htmlspecialchars($q) ?>"</p>
                  <a href="create_group.php" class="btn btn-primary" style="margin-top: 12px;">Create a new group</a>
                </div>
              <?php else: ?>
                <?php foreach ($searchResults as $g): ?>
                  <div class="group-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                      <div>
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                          <h3 style="margin: 0;"><?= htmlspecialchars($g["name"]) ?></h3>
                          <span class="badge <?= vark_badge_class($g["vark_type"]) ?>"><?= htmlspecialchars($g["vark_type"]) ?></span>
                          <span class="badge">👥 <?= (int)$g["members"] ?> members</span>
                        </div>
                        <div class="p" style="margin: 6px 0 0;">
                          <span>📖 <?= htmlspecialchars($g["subject"]) ?></span>
                          <span>🔑 Code: <?= htmlspecialchars($g["group_code"]) ?></span>
                        </div>
                        <?php if (!empty($g["description"])): ?>
                          <div class="p" style="margin: 8px 0 0; font-size: 13px;"><?= htmlspecialchars(mb_strimwidth($g["description"], 0, 100, "…")) ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="stat-mini">
                        <?php if ((int)$g["is_member"] === 1): ?>
                          <span class="badge" style="background: rgba(52,211,153,0.15);">✓ Member</span>
                          <a href="chat.php?code=<?= urlencode($g["group_code"]) ?>" class="btn btn-primary" style="padding: 8px 16px;">Enter Chat</a>
                        <?php else: ?>
                          <form method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="join_from_home">
                            <input type="hidden" name="code" value="<?= htmlspecialchars($g["group_code"]) ?>">
                            <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Join Group</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div>
        <div class="card">
          <div class="hd">
            <h2 class="h2">✨ Recommended for You</h2>
            <span class="badge <?= vark_badge_class($userVark) ?>"><?= vark_icon($userVark) ?> <?= vark_full_name($userVark) ?></span>
          </div>
          <div class="bd">
            <?php if (count($recommended) === 0): ?>
              <div class="empty-state">
                <p>No recommended groups yet.</p>
                <a href="create_group.php" class="btn btn-primary">Create your first group</a>
              </div>
            <?php else: ?>
              <?php foreach ($recommended as $g): ?>
                <div class="group-card">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                    <div>
                      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h3 style="margin: 0;"><?= htmlspecialchars($g["name"]) ?></h3>
                        <span class="badge <?= vark_badge_class($g["vark_type"]) ?>"><?= htmlspecialchars($g["vark_type"]) ?></span>
                      </div>
                      <div class="p" style="margin: 4px 0 0;">
                        <span>📖 <?= htmlspecialchars($g["subject"]) ?></span>
                        <span>👥 <?= (int)$g["members"] ?> members</span>
                      </div>
                      <?php if (!empty($g["description"])): ?>
                        <div class="p" style="margin: 6px 0 0; font-size: 13px;"><?= htmlspecialchars(mb_strimwidth($g["description"], 0, 80, "…")) ?></div>
                      <?php endif; ?>
                    </div>
                    <div>
                      <?php if ((int)$g["is_member"] === 1): ?>
                        <span class="badge" style="background: rgba(52,211,153,0.15);">✓ Joined</span>
                        <a href="chat.php?code=<?= urlencode($g["group_code"]) ?>" class="btn btn-primary" style="padding: 8px 16px;">Enter</a>
                      <?php else: ?>
                        <form method="POST" style="margin: 0;">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                          <input type="hidden" name="action" value="join_from_home">
                          <input type="hidden" name="code" value="<?= htmlspecialchars($g["group_code"]) ?>">
                          <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Join</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- My Groups -->
        <div class="card" style="margin-top: 24px;">
          <div class="hd">
            <h2 class="h2">📌 My Groups</h2>
            <a href="group_list.php" class="badge">View all →</a>
          </div>
          <div class="bd">
            <?php if (count($myGroups) === 0): ?>
              <div class="empty-state">
                <p>You haven't joined any groups yet.</p>
                <a href="#search" class="btn btn-primary" onclick="document.querySelector('.search-bar .input').focus();">Search for groups</a>
              </div>
            <?php else: ?>
              <?php foreach (array_slice($myGroups, 0, 5) as $g): ?>
                <div class="group-card">
                  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h3 style="margin: 0;"><?= htmlspecialchars($g["name"]) ?></h3>
                        <span class="badge <?= vark_badge_class($g["vark_type"]) ?>"><?= htmlspecialchars($g["vark_type"]) ?></span>
                        <?php if ((int)$g["is_creator"] === 1): ?>
                          <span class="badge" style="background: rgba(110,231,255,0.15);">👑 Creator</span>
                        <?php endif; ?>
                      </div>
                      <div class="p" style="margin: 4px 0 0;">
                        <span>📖 <?= htmlspecialchars($g["subject"]) ?></span>
                        <span>👥 <?= (int)$g["members"] ?> members</span>
                      </div>
                    </div>
                    <div class="stat-mini" style="gap: 8px;">
                      <a href="chat.php?code=<?= urlencode($g["group_code"]) ?>" class="btn btn-primary" style="padding: 8px 16px;">💬 Chat</a>
                      <a href="group.php?code=<?= urlencode($g["group_code"]) ?>" class="btn" style="padding: 8px 14px;">Info</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (count($myGroups) > 5): ?>
                <div style="text-align: center; margin-top: 12px;">
                  <a href="group_list.php" class="btn">View all <?= count($myGroups) ?> groups →</a>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>