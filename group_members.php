<?php
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/group_context.php";

$activeTab = "members";

$remove_success = "";
$remove_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'remove_member') {
  $csrfPost = $_POST["csrf_token"] ?? "";
  if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $csrfPost)) {
    $remove_error = "Security check failed.";
  } else {
    // Only creator can remove members
    if (!$isCreator) {
      $remove_error = "Only the group creator can remove members.";
    } else {
      $member_id = (int)($_POST["member_id"] ?? 0);
      $member_name = trim($_POST["member_name"] ?? "");
      
      // Cannot remove yourself
      if ($member_id === $uid) {
        $remove_error = "You cannot remove yourself. Use 'Leave Group' instead.";
      } else {
        // Check if member exists in this group
        $stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $gid, $member_id);
        $stmt->execute();
        $isMember = $stmt->get_result()->fetch_assoc();
        
        if (!$isMember) {
          $remove_error = "This user is not a member of this group.";
        } else {
          // Remove member
          $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
          $stmt->bind_param("ii", $gid, $member_id);
          $stmt->execute();
          $remove_success = "✅ " . htmlspecialchars($member_name) . " has been removed from the group.";
        }
      }
    }
  }
}

$stmt = $conn->prepare("
  SELECT u.id, u.name, u.email, u.vark_style,
         DATE_FORMAT(gm.joined_at, '%Y-%m-%d %H:%i') AS joined_at
  FROM group_members gm
  JOIN users u ON u.id = gm.user_id
  WHERE gm.group_id = ?
  ORDER BY (u.id = ?) DESC, (u.id = ?) DESC, gm.joined_at ASC
");
$stmt->bind_param("iii", $gid, $group['created_by'], $uid);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$memberCount = count($members);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - <?= htmlspecialchars($group["name"]) ?> | Members</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .member-actions {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .remove-btn {
      background: rgba(251,113,133,0.1);
      border: 1px solid rgba(251,113,133,0.3);
      color: #fb7185;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .remove-btn:hover {
      background: rgba(251,113,133,0.2);
      border-color: #fb7185;
    }
    .creator-badge {
      background: rgba(110,231,255,0.15);
      color: #6ee7ff;
    }
    .member-row {
      transition: all 0.2s ease;
    }
    .member-row.removing {
      opacity: 0.5;
      background: rgba(251,113,133,0.1);
    }
  </style>
</head>
<body>
  <?php include __DIR__ . "/includes/navbar.php"; ?>

  <div class="container">
    <div class="card">
      <div class="hd">
        <div>
          <h1 class="h1"><?= htmlspecialchars($group["name"]) ?></h1>
          <p class="p">
            <span class="badge">Code: <?= htmlspecialchars($group["group_code"]) ?></span>
            <span class="badge">👥 <?= $memberCount ?> members</span>
            <?php if ($isCreator): ?>
              <span class="badge" style="background: rgba(110,231,255,0.15);">👑 You are the creator</span>
            <?php endif; ?>
          </p>
          <?php include __DIR__ . "/includes/group_tabs.php"; ?>
        </div>
        <a class="btn" href="chat.php?code=<?= urlencode($group["group_code"]) ?>">💬 Go to Chat</a>
      </div>

      <div class="bd">
        <?php if ($remove_success): ?>
          <div class="notice good" style="margin-bottom: 16px;"><?= htmlspecialchars($remove_success) ?></div>
        <?php endif; ?>
        <?php if ($remove_error): ?>
          <div class="notice bad" style="margin-bottom: 16px;"><?= htmlspecialchars($remove_error) ?></div>
        <?php endif; ?>

        <table class="table">
          <thead>
            <tr>
              <th>Member</th>
              <th>VARK Style</th>
              <th>Joined</th>
              <th>Role</th>
              <?php if ($isCreator): ?>
                <th>Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <tr class="member-row" data-user-id="<?= $m['id'] ?>">
                <td style="font-weight: 600;">
                  <?= htmlspecialchars($m["name"]) ?>
                  <?php if ((int)$m["id"] === (int)$uid): ?>
                    <span class="badge" style="margin-left: 8px;">You</span>
                  <?php endif; ?>
                 </td>
                <td>
                  <span class="badge <?= vark_badge_class($m['vark_style']) ?>">
                    <?= vark_icon($m['vark_style']) ?> <?= htmlspecialchars($m['vark_style'] ?? '?') ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($m["joined_at"]) ?></td>
                <td>
                  <?php if ((int)$m["id"] === (int)$group["created_by"]): ?>
                    <span class="badge creator-badge">👑 Creator</span>
                  <?php elseif ((int)$m["id"] === (int)$uid): ?>
                    <span class="badge">You</span>
                  <?php else: ?>
                    <span class="badge">Member</span>
                  <?php endif; ?>
                </td>
                <?php if ($isCreator): ?>
                  <td class="member-actions">
                    <?php if ((int)$m["id"] !== (int)$group["created_by"] && (int)$m["id"] !== (int)$uid): ?>
                      <form method="POST" class="remove-form" data-member-name="<?= htmlspecialchars($m['name']) ?>" 
                            onsubmit="return confirmRemove('<?= htmlspecialchars($m['name']) ?>')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="remove_member">
                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                        <input type="hidden" name="member_name" value="<?= htmlspecialchars($m['name']) ?>">
                        <button type="submit" class="remove-btn" title="Remove <?= htmlspecialchars($m['name']) ?> from group">
                          🚪 Remove
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="badge" style="opacity: 0.5;">—</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($isCreator): ?>
          <div class="notice" style="margin-top: 16px; text-align: center;">
            👑 <strong>Creator Controls:</strong> You can remove any member (except yourself) from this group.
          </div>
        <?php else: ?>
          <div class="notice" style="margin-top: 16px; text-align: center;">
            💡 Only the group creator can remove members from this group.
          </div>
        <?php endif; ?>
        
        <div class="notice" style="margin-top: 12px; text-align: center;">
          📊 <strong>Group Stats:</strong> <?= $memberCount ?> member<?= $memberCount > 1 ? 's' : '' ?> in this study group
        </div>
      </div>
    </div>
  </div>

  <script>
    function confirmRemove(memberName) {
      return confirm(`⚠️ Are you sure you want to remove "${memberName}" from this group?\n\nThey will lose access to all chats and resources.`);
    }
    
    document.querySelectorAll('.remove-form').forEach(form => {
      form.addEventListener('submit', function(e) {
        const memberName = this.getAttribute('data-member-name');
        if (!confirm(`⚠️ Remove "${memberName}" from this group?\n\nThey will lose access to all chats and resources.`)) {
          e.preventDefault();
          return false;
        }
        
        const row = this.closest('.member-row');
        if (row) {
          row.classList.add('removing');
        }
        const btn = this.querySelector('.remove-btn');
        if (btn) {
          btn.textContent = '⏳ Removing...';
          btn.disabled = true;
        }
      });
    });
  </script>
</body>
</html>