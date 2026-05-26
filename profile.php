<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

require_login();

$uid = (int)$_SESSION["user_id"];

$ok = "";
$err = "";

// Fetch current data
$stmt = $conn->prepare("SELECT name, email, vark_style, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  session_unset();
  session_destroy();
  header("Location: index.php");
  exit;
}

if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_token"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $csrfPost = $_POST["csrf_token"] ?? "";
  if (!hash_equals($csrf, $csrfPost)) {
    $err = "Security check failed. Please refresh and try again.";
  } else {
    $action = $_POST["action"] ?? "";

    if ($action === "update_profile") {
      $name = trim($_POST["name"] ?? "");
      $email = trim($_POST["email"] ?? "");

      if ($name === "" || $email === "") {
        $err = "Name and email are required.";
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = "Please enter a valid email address.";
      } else {
        try {
          $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
          $stmt->bind_param("ssi", $name, $email, $uid);
          $stmt->execute();
          $ok = "✅ Profile updated successfully.";
          $user["name"] = $name;
          $user["email"] = $email;
        } catch (mysqli_sql_exception $e) {
          if (str_contains($e->getMessage(), "Duplicate")) {
            $err = "This email is already used by another account.";
          } else {
            $err = "Could not update profile. Please try again.";
          }
        }
      }
    }

    if ($action === "change_password") {
      $current = $_POST["current_password"] ?? "";
      $new1 = $_POST["new_password"] ?? "";
      $new2 = $_POST["new_password2"] ?? "";

      if ($current === "" || $new1 === "" || $new2 === "") {
        $err = "Please fill all password fields.";
      } elseif (strlen($new1) < 6) {
        $err = "New password must be at least 6 characters.";
      } elseif ($new1 !== $new2) {
        $err = "New passwords do not match.";
      } else {
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current, $row["password_hash"])) {
          $err = "Current password is incorrect.";
        } else {
          $newHash = password_hash($new1, PASSWORD_DEFAULT);
          $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
          $stmt->bind_param("si", $newHash, $uid);
          $stmt->execute();
          $ok = "✅ Password changed successfully.";
        }
      }
    }
  }
}

function vark_profile_description($v) {
  return match($v) {
    'V' => 'You learn best with diagrams, charts, images, and visual explanations.',
    'A' => 'You learn best through discussions, lectures, and verbal explanations.',
    'R' => 'You learn best by reading textbooks, taking notes, and writing summaries.',
    'K' => 'You learn best with hands-on activities, experiments, and physical tasks.',
    default => 'Complete the VARK assessment to discover your learning style.'
  };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - My Profile</title>
  <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
  <?php include __DIR__ . "/includes/navbar.php"; ?>

  <div class="container">
    <div class="card">
      <div class="hd">
        <div>
          <h1 class="h1">👤 My Profile</h1>
          <p class="p">Manage your account information and security settings.</p>
        </div>
        <span class="badge">Member since <?= isset($user["created_at"]) && $user["created_at"] ? date("M Y", strtotime($user["created_at"])) : "Recently" ?></span>
      </div>

      <div class="bd">
        <?php if ($ok): ?><div class="notice good"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <div class="grid grid-2" style="gap: 24px;">
          <div class="card" style="box-shadow: none; background: rgba(17,31,56,0.3);">
            <div class="hd"><h2 class="h2">📝 Basic Information</h2></div>
            <div class="bd">
              <form method="POST" class="grid" style="gap: 14px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div>
                  <label class="p" style="margin-bottom: 5px; display: block;">Full Name</label>
                  <input class="input" name="name" value="<?= htmlspecialchars($user["name"] ?? "") ?>" required>
                </div>

                <div>
                  <label class="p" style="margin-bottom: 5px; display: block;">Email Address</label>
                  <input class="input" name="email" type="email" value="<?= htmlspecialchars($user["email"] ?? "") ?>" required>
                </div>

                <div>
                  <label class="p" style="margin-bottom: 5px; display: block;">VARK Learning Style</label>
                  <div class="badge <?= vark_badge_class($user["vark_style"] ?? "V") ?>" style="display: inline-block; padding: 8px 16px; font-size: 14px;">
                    <?= vark_icon($user["vark_style"] ?? "V") ?> <?= vark_full_name($user["vark_style"] ?? "V") ?>
                  </div>
                  <div class="p" style="margin-top: 8px; font-size: 13px;">
                    <?= vark_profile_description($user["vark_style"] ?? "V") ?>
                  </div>
                </div>

                <button class="btn btn-primary" type="submit">💾 Save Profile</button>
              </form>
            </div>
          </div>

          <div class="card" style="box-shadow: none; background: rgba(17,31,56,0.3);">
            <div class="hd"><h2 class="h2">🔒 Security</h2></div>
            <div class="bd">
              <form method="POST" class="grid" style="gap: 14px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="change_password">

                <div>
                  <label class="p" style="margin-bottom: 5px; display: block;">Current Password</label>
                  <input class="input" type="password" name="current_password" placeholder="Enter current password" required>
                </div>

                <div>
                  <label class="p" style="margin-bottom: 5px; display: block;">New Password</label>
                  <input class="input" type="password" name="new_password" placeholder="Min 6 characters" required>
                </div>

                <div>
                  <label class="p" style="margin-bottom: 5px; display: block;">Confirm New Password</label>
                  <input class="input" type="password" name="new_password2" placeholder="Re-enter new password" required>
                </div>

                <button class="btn btn-primary" type="submit">🔑 Change Password</button>
              </form>

              <div class="notice" style="margin-top: 16px;">
                <strong>🔐 Security Tip:</strong> Use a strong, unique password and never share it with anyone.
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="box-shadow: none; margin-top: 20px; background: rgba(17,31,56,0.2);">
          <div class="hd"><h2 class="h2">📊 Account Statistics</h2></div>
          <div class="bd">
            <div class="grid grid-2" style="gap: 16px; text-align: center;">
              <div>
                <div style="font-size: 36px; font-weight: bold; background: linear-gradient(135deg, var(--brand-cyan), var(--brand-purple)); -webkit-background-clip: text; background-clip: text; color: transparent;">
                  <?php
                    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM group_members WHERE user_id = ?");
                    $stmt->bind_param("i", $uid);
                    $stmt->execute();
                    $groupsJoined = $stmt->get_result()->fetch_assoc()['c'];
                    echo $groupsJoined;
                  ?>
                </div>
                <div class="p">Groups Joined</div>
              </div>
              <div>
                <div style="font-size: 36px; font-weight: bold; background: linear-gradient(135deg, var(--brand-cyan), var(--brand-purple)); -webkit-background-clip: text; background-clip: text; color: transparent;">
                  <?php
                    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM `groups` WHERE created_by = ?");
                    $stmt->bind_param("i", $uid);
                    $stmt->execute();
                    $groupsCreated = $stmt->get_result()->fetch_assoc()['c'];
                    echo $groupsCreated;
                  ?>
                </div>
                <div class="p">Groups Created</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>