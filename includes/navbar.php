<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = basename($_SERVER['PHP_SELF']);

$user_name = "";
$user_vark = "";
if (!empty($_SESSION['user_id']) && isset($conn)) {
  $uid = (int)$_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT name, vark_style FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
  if ($user) {
    $user_name = $user['name'];
    $user_vark = $user['vark_style'];
  }
}
?>
<div class="topbar">
  <div class="nav">
    <div class="brand">
      <img src="assets/images/csh-logo.png" alt="CSH" 
           style="width: 36px; height: 36px; border-radius: 12px; object-fit: cover;">
      <div>
        CSH <span style="color: var(--text-muted); font-weight: 600;">Collaborative Study Hub</span>
      </div>
    </div>

    <div class="navlinks">
      <a href="home.php" class="<?= $current_page == 'home.php' ? 'active' : '' ?>">🏠 Home</a>
      <a href="group_list.php" class="<?= $current_page == 'group_list.php' ? 'active' : '' ?>">📋 My Groups</a>
      <a href="create_group.php" class="<?= $current_page == 'create_group.php' ? 'active' : '' ?>">➕ Create</a>
      <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>">👤 Profile</a>
      
      <?php if (!empty($user_vark)): ?>
        <span class="badge <?= vark_badge_class($user_vark) ?>" style="margin-left: 8px;">
          <?= vark_icon($user_vark) ?> <?= $user_vark ?>
        </span>
      <?php endif; ?>
      
      <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>
  </div>
</div>

<style>
.navlinks a.active {
  border-color: var(--brand-cyan);
  background: linear-gradient(135deg, rgba(110,231,255,0.12), rgba(167,139,250,0.08));
  color: var(--brand-cyan);
}
.navlinks a.logout-btn {
  border-color: rgba(251,113,133,0.3);
  color: #fb7185;
}
.navlinks a.logout-btn:hover {
  background: rgba(251,113,133,0.1);
}
@media (max-width: 700px) {
  .nav { flex-direction: column; text-align: center; gap: 12px; }
  .navlinks { justify-content: center; }
  .brand { justify-content: center; }
}
</style>