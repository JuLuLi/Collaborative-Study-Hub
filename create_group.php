<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

require_vark_selected($conn);

$uid = (int)$_SESSION["user_id"];

// CSRF token
if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_token"];

$err = "";
$success = false;

// Count created groups
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM `groups` WHERE created_by=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$createdCount = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
$maxGroups = 3;
$canCreate = $createdCount < $maxGroups;

function make_group_code(mysqli $conn): string {
  for ($i = 0; $i < 10; $i++) {
    $code = "G" . random_int(1000, 9999);
    $stmt = $conn->prepare("SELECT id FROM `groups` WHERE group_code=? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) return $code;
  }
  return "G" . time();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $canCreate) {
  $csrfPost = $_POST["csrf_token"] ?? "";
  if (!hash_equals($csrf, $csrfPost)) {
    $err = "Security check failed. Please refresh and try again.";
  } else {
    $name = trim($_POST["name"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $vark_type = $_POST["vark_type"] ?? "";
    $is_private = isset($_POST["is_private"]) ? 1 : 0;

    if ($name === "" || $subject === "" || $vark_type === "") {
      $err = "Please fill in Group Name, Subject, and VARK Type.";
    } elseif (!in_array($vark_type, ["V","A","R","K"], true)) {
      $err = "Invalid VARK type selected.";
    } elseif (strlen($name) > 100) {
      $err = "Group name is too long (max 100 characters).";
    } else {
      $code = make_group_code($conn);

      try {
        $stmt = $conn->prepare("
          INSERT INTO `groups`(group_code, name, subject, description, vark_type, created_by) 
          VALUES (?,?,?,?,?,?)
        ");
        $stmt->bind_param("sssssi", $code, $name, $subject, $description, $vark_type, $uid);
        $stmt->execute();

        $newGroupId = $conn->insert_id;

        // Creator automatically joins
        $stmt = $conn->prepare("INSERT INTO group_members(group_id, user_id) VALUES (?,?)");
        $stmt->bind_param("ii", $newGroupId, $uid);
        $stmt->execute();

        $success = true;
        $newGroupCode = $code;
      } catch (mysqli_sql_exception $e) {
        $err = "Could not create group. Please try again.";
      }
    }
  }
}

// Get user's VARK for recommendation
$stmt = $conn->prepare("SELECT vark_style FROM users WHERE id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$userVark = $stmt->get_result()->fetch_assoc()["vark_style"] ?? "V";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - Create Study Group</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .vark-option {
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .vark-option input {
      accent-color: var(--brand);
    }
    .vark-option.selected {
      background: rgba(110,231,255,0.1);
      border-color: rgba(110,231,255,0.4);
    }
    .success-card {
      text-align: center;
      padding: 30px;
      background: linear-gradient(135deg, rgba(52,211,153,0.1), rgba(52,211,153,0.05));
      border-radius: 24px;
      border: 1px solid rgba(52,211,153,0.3);
    }
    .group-code-display {
      font-size: 32px;
      font-weight: 800;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      letter-spacing: 4px;
      margin: 16px 0;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . "/includes/navbar.php"; ?>

  <div class="container">
    <?php if ($success): ?>
      <!-- Success View -->
      <div class="card">
        <div class="success-card">
          <div style="font-size: 64px; margin-bottom: 16px;">🎉</div>
          <h1 class="h1" style="margin-bottom: 8px;">Group Created Successfully!</h1>
          <p class="p">Your study group is ready to go.</p>
          
          <div class="group-code-display">
            <?= htmlspecialchars($newGroupCode) ?>
          </div>
          
          <p class="p" style="margin-bottom: 24px;">
            Share this code with friends so they can join your group.
          </p>
          
          <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
            <a href="chat.php?code=<?= urlencode($newGroupCode) ?>" class="btn btn-primary btn-large">💬 Enter Chat Now</a>
            <a href="group.php?code=<?= urlencode($newGroupCode) ?>" class="btn btn-large">📋 Group Settings</a>
            <a href="create_group.php" class="btn btn-large">➕ Create Another Group</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <!-- Create Group Form -->
      <div class="card">
        <div class="hd">
          <div>
            <h1 class="h1">🚀 Create a New Study Group</h1>
            <p class="p">Build your own learning community and invite peers to collaborate.</p>
          </div>
          <a class="btn" href="group_list.php">← Back to Groups</a>
        </div>

        <div class="bd">
          <?php if ($err): ?>
            <div class="notice bad" style="margin-bottom: 20px;"><?= htmlspecialchars($err) ?></div>
          <?php endif; ?>

          <?php if (!$canCreate): ?>
            <div class="notice bad" style="margin-bottom: 20px;">
              ⚠️ You have reached the maximum limit of <?= $maxGroups ?> groups.
              <a href="group_list.php">View your existing groups</a>
            </div>
          <?php endif; ?>

          <div class="grid grid-2" style="gap: 28px;">
            <div>
              <form method="POST" class="grid" style="gap: 18px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div>
                  <label class="p" style="margin-bottom: 6px; display: block;">Group Name *</label>
                  <input class="input" name="name" placeholder="e.g., CS Study Squad, Math Masters"
                         value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required <?= !$canCreate ? "disabled" : "" ?>>
                </div>

                <div>
                  <label class="p" style="margin-bottom: 6px; display: block;">Subject *</label>
                  <input class="input" name="subject" placeholder="e.g., Computer Science, Mathematics, Physics"
                         value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required <?= !$canCreate ? "disabled" : "" ?>>
                </div>

                <div>
                  <label class="p" style="margin-bottom: 6px; display: block;">Group Type (VARK) *</label>
                  <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <label class="vark-option card" style="padding: 12px; cursor: pointer; text-align: center;">
                      <input type="radio" name="vark_type" value="V" style="margin-right: 8px;" required <?= !$canCreate ? "disabled" : "" ?>>
                      <span>📊 Visual (V)</span>
                    </label>
                    <label class="vark-option card" style="padding: 12px; cursor: pointer; text-align: center;">
                      <input type="radio" name="vark_type" value="A" style="margin-right: 8px;" <?= !$canCreate ? "disabled" : "" ?>>
                      <span>🎧 Auditory (A)</span>
                    </label>
                    <label class="vark-option card" style="padding: 12px; cursor: pointer; text-align: center;">
                      <input type="radio" name="vark_type" value="R" style="margin-right: 8px;" <?= !$canCreate ? "disabled" : "" ?>>
                      <span>📚 Reading/Writing (R)</span>
                    </label>
                    <label class="vark-option card" style="padding: 12px; cursor: pointer; text-align: center;">
                      <input type="radio" name="vark_type" value="K" style="margin-right: 8px;" <?= !$canCreate ? "disabled" : "" ?>>
                      <span>🔬 Kinesthetic (K)</span>
                    </label>
                  </div>
                </div>

                <div>
                  <label class="p" style="margin-bottom: 6px; display: block;">Description (Optional)</label>
                  <textarea class="textarea" name="description" placeholder="What will this group study? Any goals or rules?"
                            rows="4" <?= !$canCreate ? "disabled" : "" ?>><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <button class="btn btn-primary" type="submit" style="margin-top: 8px;" <?= !$canCreate ? "disabled" : "" ?>>
                  ✨ Create Group
                </button>
              </form>
            </div>

            <!-- Info -->
            <div>
              <div class="card" style="box-shadow: none; background: rgba(17,31,56,0.4);">
                <div class="hd">
                  <h2 class="h2">💡 Group Creation Tips</h2>
                </div>
                <div class="bd">
                  <div class="notice" style="margin-bottom: 16px;">
                    <b>Your VARK Style:</b> 
                    <span class="badge <?= vark_badge_class($userVark) ?>">
                      <?= htmlspecialchars($userVark) ?> • <?= htmlspecialchars(vark_label($userVark)) ?>
                    </span>
                  </div>
                  
                  <div style="margin-top: 16px;">
                    <b>📌 Best Practices:</b>
                    <ul class="p" style="margin-top: 8px; padding-left: 20px;">
                      <li>Choose a clear, memorable group name</li>
                      <li>Select VARK type that matches your study style</li>
                      <li>Write a helpful description for new members</li>
                      <li>Share your group code with classmates</li>
                    </ul>
                  </div>

                  <div style="margin-top: 16px;">
                    <b>🔑 What happens next?</b>
                    <ul class="p" style="margin-top: 8px; padding-left: 20px;">
                      <li>You'll automatically join your group</li>
                      <li>Receive a unique 5-character group code</li>
                      <li>Start chatting and sharing resources immediately</li>
                    </ul>
                  </div>

                  <div class="notice" style="margin-top: 16px;">
                    You can create up to <strong><?= $maxGroups ?></strong> groups. 
                    You have created <strong><?= $createdCount ?></strong> so far.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Highlight selected VARK option
    document.querySelectorAll('.vark-option').forEach(opt => {
      const radio = opt.querySelector('input');
      radio.addEventListener('change', function() {
        document.querySelectorAll('.vark-option').forEach(o => o.classList.remove('selected'));
        if (this.checked) opt.classList.add('selected');
      });
      if (radio.checked) opt.classList.add('selected');
    });
  </script>
</body>
</html>