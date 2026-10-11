<?php header("Content-Type: text/html; charset=utf-8"); ?>
<?php require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="menu-item" onclick="navigateTo('nurse','station')"><span class="menu-ico"><?= icon('common/nav', 'dashboard', 16) ?></span><span class="menu-text">护士站总览</span></div>
<div class="menu-item" onclick="navigateTo('nurse','checklist')"><span class="menu-ico"><?= icon('common/nav', 'checklist', 16) ?></span><span class="menu-text">核查清单</span></div>
<div class="menu-item" onclick="navigateTo('nurse','vitals')"><span class="menu-ico"><?= icon('common/nav', 'vitals', 16) ?></span><span class="menu-text">生命体征</span></div>
