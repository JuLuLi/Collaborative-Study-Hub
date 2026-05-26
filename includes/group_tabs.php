<?php

if (!isset($group) || !isset($activeTab)) {
  // If called without context, try to find group
  if (isset($gid) && isset($conn)) {
    $stmt = $conn->prepare("SELECT group_code, name FROM `groups` WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $gid);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();
  }
  if (!isset($group)) {
    echo '<div class="notice bad">Error: Group context missing.</div>';
    return;
  }
}
?>
<div class="group-tabs">
  <div class="tabs">
    <a class="tab <?= ($activeTab === 'chat') ? 'active' : '' ?>"
       href="chat.php?code=<?= urlencode($group['group_code']) ?>">
      💬 Chat
    </a>

    <a class="tab <?= ($activeTab === 'members') ? 'active' : '' ?>"
       href="group_members.php?code=<?= urlencode($group['group_code']) ?>">
      👥 Members
    </a>

    <a class="tab <?= ($activeTab === 'info') ? 'active' : '' ?>"
       href="group_info.php?code=<?= urlencode($group['group_code']) ?>">
      📚 Resources
    </a>
    
    <a class="tab <?= ($activeTab === 'group') ? 'active' : '' ?>"
       href="group.php?code=<?= urlencode($group['group_code']) ?>">
      📋 About
    </a>
  </div>
  
  <?php if ($activeTab !== 'chat' && isset($group['vark_type'])): ?>
    <div class="vark-tip" style="margin-top: 12px; padding: 8px 12px; background: rgba(110,231,255,0.05); border-radius: 12px; border-left: 3px solid; <?= 
      match($group['vark_type']) {
        'V' => 'border-left-color: #6ee7ff;',
        'A' => 'border-left-color: #34d399;',
        'R' => 'border-left-color: #fbbf24;',
        'K' => 'border-left-color: #a78bfa;',
        default => 'border-left-color: var(--brand-cyan);'
      }
    ?>">
      <span class="p" style="margin: 0; font-size: 13px;">
        <strong>💡 VARK Tip:</strong> 
        <?= get_group_vark_tip($group['vark_type'] ?? 'V') ?>
      </span>
    </div>
  <?php endif; ?>
</div>

<style>
.group-tabs {
  margin-top: 8px;
}

.tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.tab {
  padding: 8px 18px;
  border-radius: 40px;
  border: 1px solid var(--border);
  background: rgba(255, 255, 255, 0.04);
  color: var(--text-muted);
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s ease;
}

.tab:hover {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text);
  transform: translateY(-1px);
}

.tab.active {
  border-color: rgba(110, 231, 255, 0.5);
  background: linear-gradient(135deg, rgba(110, 231, 255, 0.15), rgba(167, 139, 250, 0.1));
  color: var(--brand-cyan);
  box-shadow: 0 0 10px rgba(110, 231, 255, 0.15);
}

@media (max-width: 500px) {
  .tab {
    padding: 6px 12px;
    font-size: 12px;
  }
}
</style>