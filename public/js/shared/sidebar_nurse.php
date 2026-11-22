<?php header("Content-Type: text/html; charset=utf-8"); ?>
<?php require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="menu-item" onclick="navigateTo('nurse','station')"><span class="menu-ico"><?= icon('common/nav', 'dashboard', 16) ?></span><span class="menu-text">床位一览表</span></div>
<div class="menu-group">待办工作</div>
<div class="menu-item" onclick="navigateTo('nurse','prepare')"><span class="menu-ico"><?= icon('common/nav', 'dispensing', 16) ?></span><span class="menu-text">预摆药</span></div>
<div class="menu-item" onclick="navigateTo('nurse','checklist')"><span class="menu-ico"><?= icon('common/nav', 'checklist', 16) ?></span><span class="menu-text">核查清单</span></div>
