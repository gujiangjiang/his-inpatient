<?php header("Content-Type: text/html; charset=utf-8"); ?>
<?php require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="menu-group">药房</div>
<div class="menu-item" onclick="navigateTo('pharmacy','inventory')"><span class="menu-ico"><?= icon('common/nav', 'inventory', 16) ?></span><span class="menu-text">药品库存</span></div>
<div class="menu-item" onclick="navigateTo('pharmacy','dispensing')"><span class="menu-ico"><?= icon('common/nav', 'dispensing', 16) ?></span><span class="menu-text">发药管理</span></div>
