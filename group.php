<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

require_vark_selected($conn);

$uid = (int)$_SESSION["user_id"];
$code = trim($_GET["code"] ?? "");

if ($code === "") {
  header("Location: home.php");
  exit;
}

// Fetch group by code
$stmt = $conn->prepare("
  SELECT g.id, g.group_code, g.name, g.subject, g.description, g.vark_type, g.created_by, g.created_at,
         u.name AS creator_name,
         (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS members
  FROM groups g
  JOIN users u ON u.id = g.created_by
  WHERE g.group_code = ?
  LIMIT 1
");
$stmt->bind_param("s", $code);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
  http_response_code(404);
  $notFound = true;
} else {
  $notFound = false;

  $gid = (int)$group["id"];

  // Membership check
  $stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id=? AND user_id=? LIMIT 1");
  $stmt->bind_param("ii", $gid, $uid);
  $stmt->execute();
  $isMember = (bool)$stmt->get_result()->fetch_assoc();

  $flash = "";
  $flashType = "";

  // Handle actions
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "join") {
      if (!$isMember) {
        try {
          $stmt = $conn->prepare("INSERT INTO group_members(group_id, user_id) VALUES (?,?)");
          $stmt->bind_param("ii", $gid, $uid);
          $stmt->execute();
          $isMember = true;

          header("Location: chat.php?code=" . urlencode($group["group_code"]) . "&joined=1");
          exit;
        } catch (mysqli_sql_exception $e) {
          $flash = "Could not join this group. Please try again.";
          $flashType = "bad";
        }
      }
    }

    if ($action === "leave") {
      if ($isMember) {
        $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
        $stmt->bind_param("ii", $gid, $uid);
        $stmt->execute();
        $isMember = false;

        header("Location: group_list.php?left=1");
        exit;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - Group</title>
  <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
  <?php include __DIR__ . "/includes/navbar.php"; ?>

  <div class="container">
    <?php if ($notFound): ?>
      <div class="card">
        <div class="hd">
          <h1 class="h1">Group not found</h1>
          <a class="btn" href="home.php">Back</a>
        </div>
        <div class="bd">
          <div class="notice bad">This group code does not exist.</div>
        </div>
      </div>
    <?php else: ?>

      <div class="card">
        <div class="hd">
          <div>
            <h1 class="h1"><?= htmlspecialchars($group["name"]) ?></h1>
            <p class="p">
              <span class="badge">Code: <?= htmlspecialchars($group["group_code"]) ?></span>
              <span class="badge">Subject: <?= htmlspecialchars($group["subject"]) ?></span>
              <span class="badge <?= vark_badge_class($group["vark_type"]) ?>">
                <?= htmlspecialchars($group["vark_type"]) ?> • <?= htmlspecialchars(vark_label($group["vark_type"])) ?>
              </span>
            </p>
          </div>

          <div class="row" style="flex-wrap:wrap; justify-content:flex-end;">
            <a class="btn" href="home.php">Home</a>
            <a class="btn" href="group_list.php">Group List</a>

            <?php if ($isMember): ?>
              <a class="btn btn-primary" href="chat.php?code=<?= urlencode($group["group_code"]) ?>">Enter Chat</a>

              <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="leave">
                <button class="btn btn-danger" type="submit"
                  onclick="return confirm('Leave this group? You will lose access to the chat unless you join again.')">
                  Leave Group
                </button>
              </form>
            <?php else: ?>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="join">
                <button class="btn btn-primary" type="submit">Join Group</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="bd">
          <?php if ($flash): ?>
            <div class="notice <?= $flashType ?>" style="margin-bottom:12px;">
              <?= htmlspecialchars($flash) ?>
            </div>
          <?php endif; ?>

          <div class="grid grid-2">
            <div class="card" style="box-shadow:none;">
              <div class="hd"><h2 class="h2">About this group</h2></div>
              <div class="bd">
                <?php if (!empty($group["description"])): ?>
                  <div class="p"><?= nl2br(htmlspecialchars($group["description"])) ?></div>
                <?php else: ?>
                  <div class="notice">No description provided.</div>
                <?php endif; ?>
              </div>
            </div>

            <div class="card" style="box-shadow:none;">
              <div class="hd"><h2 class="h2">Group info</h2></div>
              <div class="bd">
                <div class="notice">
                  <div><b>Members:</b> <?= (int)$group["members"] ?></div>
                  <div style="margin-top:6px;"><b>Created by:</b> <?= htmlspecialchars($group["creator_name"]) ?></div>
                  <div style="margin-top:6px;"><b>Created at:</b> <?= htmlspecialchars($group["created_at"]) ?></div>
                </div>

                <div class="notice" style="margin-top:12px;">
                  <b>CSH Structure hint:</b><br>
                  <?php
                    echo match($group["vark_type"]) {
                      'V' => "Use diagrams, images, mind-maps, and visual summaries in chat.",
                      'A' => "Use discussion, explanations, lecture-style summaries, and Q&A sessions.",
                      'R' => "Use text notes, lists, summaries, references, and structured reading plans.",
                      'K' => "Use practice tasks, hands-on exercises, experiments, and step-by-step doing.",
                      default => "Use the study style that works best for the group."
                    };
                  ?>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

    <?php endif; ?>
  </div>
</body>
</html>