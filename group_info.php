<?php
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/group_context.php";

$activeTab = "info";

if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_token"];

$ok = "";
$err = "";

// Creator can edit basic info
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_info'])) {
  if (!$isCreator) {
    $err = "Only the group creator can edit group information.";
  } else {
    $csrfPost = $_POST["csrf_token"] ?? "";
    if (!hash_equals($csrf, $csrfPost)) {
      $err = "Security check failed. Please refresh and try again.";
    } else {
      $name = trim($_POST["name"] ?? "");
      $subject = trim($_POST["subject"] ?? "");
      $description = trim($_POST["description"] ?? "");

      if ($name === "" || $subject === "") {
        $err = "Group name and subject are required.";
      } else {
        $stmt = $conn->prepare("UPDATE `groups` SET name = ?, subject = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $subject, $description, $gid);
        $stmt->execute();
        $ok = "✅ Group info updated successfully.";

        $group["name"] = $name;
        $group["subject"] = $subject;
        $group["description"] = $description;
      }
    }
  }
}

// Fetch resources
$stmt = $conn->prepare("
  SELECT r.id, r.resource_type, r.title, r.content,
         DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i') AS created_at,
         u.name AS created_by_name
  FROM group_resources r
  JOIN users u ON u.id = r.created_by
  WHERE r.group_id = ?
  ORDER BY r.created_at DESC
  LIMIT 100
");
$stmt->bind_param("i", $gid);
$stmt->execute();
$resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Split resources by type
$links = array_values(array_filter($resources, fn($r) => $r["resource_type"] === "LINK"));
$notes = array_values(array_filter($resources, fn($r) => $r["resource_type"] === "NOTE"));
$tasks = array_values(array_filter($resources, fn($r) => $r["resource_type"] === "TASK"));

$varkHint = get_group_vark_tip($group["vark_type"] ?? 'V');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - <?= htmlspecialchars($group["name"]) ?> | Resources</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .resource-card {
      background: rgba(17,31,56,0.4);
      border-radius: 16px;
      padding: 14px 16px;
      margin-bottom: 10px;
      transition: all 0.2s;
    }
    .resource-card:hover {
      background: rgba(17,31,56,0.6);
      transform: translateX(4px);
    }
    .delete-resource {
      background: none;
      border: none;
      color: var(--brand-pink);
      cursor: pointer;
      font-size: 18px;
    }
    .delete-resource:hover {
      opacity: 0.7;
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
            <span class="badge">Created by: <?= htmlspecialchars($group["creator_name"]) ?></span>
            <span class="badge <?= vark_badge_class($group["vark_type"]) ?>">
              <?= vark_icon($group["vark_type"]) ?> <?= htmlspecialchars($group["vark_type"]) ?> • <?= htmlspecialchars(vark_label($group["vark_type"])) ?>
            </span>
          </p>
          <?php include __DIR__ . "/includes/group_tabs.php"; ?>
        </div>
        <a class="btn" href="chat.php?code=<?= urlencode($group["group_code"]) ?>">💬 Go to Chat</a>
      </div>

      <div class="bd">
        <?php if ($ok): ?><div class="notice good"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <div class="grid grid-2" style="gap: 24px;">
          <div class="card" style="box-shadow: none; background: rgba(17,31,56,0.3);">
            <div class="hd"><h2 class="h2">📌 About This Group</h2></div>
            <div class="bd">
              <?php if (!empty($group["description"])): ?>
                <div class="p"><?= nl2br(htmlspecialchars($group["description"])) ?></div>
              <?php else: ?>
                <div class="notice">No description added yet.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="card" style="box-shadow: none; background: rgba(17,31,56,0.3);">
            <div class="hd">
              <h2 class="h2">✏️ Edit Group Info</h2>
              <span class="badge"><?= $isCreator ? "Creator access" : "Read only" ?></span>
            </div>
            <div class="bd">
              <?php if (!$isCreator): ?>
                <div class="notice">Only the group creator can edit group information.</div>
              <?php endif; ?>

              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="update_info" value="1">
                <div class="grid" style="gap: 12px;">
                  <input class="input" name="name" value="<?= htmlspecialchars($group["name"]) ?>" <?= $isCreator ? "" : "disabled" ?>>
                  <input class="input" name="subject" value="<?= htmlspecialchars($group["subject"]) ?>" <?= $isCreator ? "" : "disabled" ?>>
                  <textarea class="textarea" name="description" rows="3" <?= $isCreator ? "" : "disabled" ?>><?= htmlspecialchars($group["description"] ?? "") ?></textarea>
                  <button class="btn btn-primary" type="submit" <?= $isCreator ? "" : "disabled" ?>>Save Changes</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Resources Section -->
        <div class="card" style="box-shadow: none; margin-top: 20px;">
          <div class="hd">
            <h2 class="h2">📚 Shared Resources</h2>
            <span class="badge">All members can add</span>
          </div>
          <div class="bd">
            <div class="grid grid-2" style="gap: 20px;">
              <div>
                <h3 class="h2" style="margin-bottom: 12px;">➕ Add Resource</h3>
                <div class="grid" style="gap: 10px;">
                  <select id="resType" class="select">
                    <option value="LINK">🔗 LINK (URL / Reference)</option>
                    <option value="NOTE" selected>📝 NOTE (Summary / Points)</option>
                  </select>
                  <input id="resTitle" class="input" placeholder="Title (required)">
                  <textarea id="resContent" class="textarea" rows="2" placeholder="Content (URL, description, steps...)"></textarea>
                  <button id="addBtn" class="btn btn-primary">+ Add Resource</button>
                  <div id="resMsg" class="p" style="margin: 0;"></div>
                </div>
              </div>

              <div>
                <h3 class="h2" style="margin-bottom: 12px;">📋 Resources List</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                  <?php if (count($resources) === 0): ?>
                    <div class="notice">No resources shared yet. Be the first!</div>
                  <?php else: ?>
                    <?php foreach ($resources as $r): ?>
                      <div class="resource-card" data-res-id="<?= $r['id'] ?>" data-res-type="<?= $r['resource_type'] ?>" data-res-content="<?= htmlspecialchars($r['content'] ?? '') ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                          <div style="flex: 1;">
                            <strong>
                              <?= $r['resource_type'] === 'LINK' ? '🔗' : ($r['resource_type'] === 'NOTE' ? '📝' : '✅') ?>
                              <?php if ($r['resource_type'] === 'LINK'): ?>
                                <!-- LINK: Clickable -->
                                <a href="<?= htmlspecialchars($r['content'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" 
                                  style="color: var(--brand-cyan); text-decoration: none; border-bottom: 1px solid rgba(110,231,255,0.3);"
                                  onmouseover="this.style.borderBottomColor='var(--brand-cyan)'" 
                                  onmouseout="this.style.borderBottomColor='rgba(110,231,255,0.3)'">
                                  <?= htmlspecialchars($r['title']) ?>
                                </a>
                              <?php else: ?>
                                <!-- NOTE/TASK: Copyable -->
                                <span class="copyable-text" data-copy="<?= htmlspecialchars($r['content'] ?? '') ?>" 
                                      style="cursor: pointer; border-bottom: 1px dashed rgba(255,255,255,0.3);"
                                      onclick="copyToClipboard(this)" 
                                      onmouseover="this.style.borderBottomColor='var(--brand-cyan)'" 
                                      onmouseout="this.style.borderBottomColor='rgba(255,255,255,0.3)'">
                                  <?= htmlspecialchars($r['title']) ?> 📋
                                </span>
                              <?php endif; ?>
                            </strong>
                            
                            <?php if ($r['resource_type'] !== 'LINK'): ?>
                              <div class="p" style="margin: 4px 0 0; font-size: 13px;"><?= nl2br(htmlspecialchars($r['content'] ?? '')) ?></div>
                            <?php else: ?>
                              <div class="p" style="margin: 4px 0 0; font-size: 12px; color: var(--text-muted);">
                                🔗 <?= htmlspecialchars(mb_strimwidth($r['content'] ?? '', 0, 50, '...')) ?>
                              </div>
                            <?php endif; ?>
                            
                            <div class="meta" style="margin-top: 5px;">Added by <?= htmlspecialchars($r['created_by_name']) ?> • <?= htmlspecialchars($r['created_at']) ?></div>
                          </div>
                          <button class="delete-resource" data-id="<?= $r['id'] ?>" title="Delete" style="background: none; border: none; color: #fb7185; cursor: pointer; font-size: 18px;">🗑️</button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
const addBtn = document.getElementById('addBtn');
const resType = document.getElementById('resType');
const resTitle = document.getElementById('resTitle');
const resContent = document.getElementById('resContent');
const resMsg = document.getElementById('resMsg');
const CSRF = <?= json_encode($csrf) ?>;
const GROUP_CODE = <?= json_encode($group["group_code"]) ?>;

addBtn.addEventListener('click', async () => {
  const type = resType.value;
  const title = resTitle.value.trim();
  const content = resContent.value.trim();

  if (!title) {
    resMsg.textContent = "❌ Title is required.";
    resMsg.style.color = "#fb7185";
    return;
  }

  addBtn.disabled = true;
  addBtn.textContent = 'Adding...';

  try {
    const form = new FormData();
    form.append("csrf_token", CSRF);
    form.append("code", GROUP_CODE);
    form.append("resource_type", type);
    form.append("title", title);
    form.append("content", content);

    const res = await fetch("group_resource_add.php", {
      method: "POST",
      body: form,
      credentials: "same-origin"
    });

    const data = await res.json();
    if (data && data.ok) {
      resMsg.textContent = "✅ Resource added! Refreshing...";
      resMsg.style.color = "#34d399";
      setTimeout(() => window.location.reload(), 800);
    } else {
      resMsg.textContent = data?.error || "Failed to add resource.";
      resMsg.style.color = "#fb7185";
    }
  } catch (err) {
    resMsg.textContent = "Network error. Please try again.";
    resMsg.style.color = "#fb7185";
  } finally {
    addBtn.disabled = false;
    addBtn.textContent = '+ Add Resource';
  }
});

// Delete resource
document.querySelectorAll('.delete-resource').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    const resId = btn.dataset.id;
    if (!confirm('Delete this resource?')) return;

    try {
      const form = new FormData();
      form.append("csrf_token", CSRF);
      form.append("code", GROUP_CODE);
      form.append("resource_id", resId);

      const res = await fetch("group_resource_delete.php", {
        method: "POST",
        body: form,
        credentials: "same-origin"
      });
      const data = await res.json();
      if (data && data.ok) {
        window.location.reload();
      } else {
        alert(data?.error || 'Delete failed.');
      }
    } catch (err) {
      alert('Network error.');
    }
  });
});
</script>

<script>
function copyToClipboard(element) {
  const textToCopy = element.getAttribute('data-copy');
  if (!textToCopy) return;
  
  // Create temporary textarea
  const textarea = document.createElement('textarea');
  textarea.value = textToCopy;
  document.body.appendChild(textarea);
  textarea.select();
  textarea.setSelectionRange(0, 99999); // For mobile devices
  
  try {
    const successful = document.execCommand('copy');
    if (successful) {
      // Show success notification
      const originalText = element.innerHTML;
      element.innerHTML = '✅ Copied!';
      element.style.color = '#34d399';
      setTimeout(() => {
        element.innerHTML = originalText;
        element.style.color = '';
      }, 1500);
    } else {
      alert('Failed to copy. Please manually copy the text.');
    }
  } catch (err) {
    alert('Copy failed. Please manually copy the text.');
  }
  
  document.body.removeChild(textarea);
}

async function copyWithModernAPI(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch (err) {
    return false;
  }
}

window.copyToClipboard = async function(element) {
  const textToCopy = element.getAttribute('data-copy');
  if (!textToCopy) return;
  
  if (navigator.clipboard && navigator.clipboard.writeText) {
    try {
      await navigator.clipboard.writeText(textToCopy);
      const originalText = element.innerHTML;
      element.innerHTML = '✅ Copied!';
      element.style.color = '#34d399';
      setTimeout(() => {
        element.innerHTML = originalText;
        element.style.color = '';
      }, 1500);
      return;
    } catch (err) {
    }
  }
  
  const textarea = document.createElement('textarea');
  textarea.value = textToCopy;
  textarea.style.position = 'fixed';
  textarea.style.top = '-9999px';
  textarea.style.left = '-9999px';
  document.body.appendChild(textarea);
  textarea.select();
  
  try {
    document.execCommand('copy');
    const originalText = element.innerHTML;
    element.innerHTML = '✅ Copied!';
    element.style.color = '#34d399';
    setTimeout(() => {
      element.innerHTML = originalText;
      element.style.color = '';
    }, 1500);
  } catch (err) {
    alert('Copy failed. Please manually copy the text.');
  }
  
  document.body.removeChild(textarea);
};

const style = document.createElement('style');
style.textContent = `
  .copyable-text {
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .copyable-text:hover {
    color: var(--brand-cyan);
  }
  .resource-card a {
    transition: all 0.2s ease;
  }
  .resource-card a:hover {
    color: var(--brand-cyan) !important;
  }
`;
document.head.appendChild(style);
</script>
</body>
</html>