<?php header("Content-Type: text/html; charset=utf-8"); ?>
<?php require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="menu-item" onclick="navigateTo('nurse','station')"><span class="menu-ico"><?= icon('common/nav', 'dashboard', 16) ?></span><span class="menu-text">床位一览表</span></div>
<div class="menu-group">护理文书</div>
<div class="menu-item" onclick="navigateTo('nurse','temperature')"><span class="menu-ico"><?= icon('common/nav', 'vitals', 16) ?></span><span class="menu-text">体温单</span></div>
<div class="menu-item" onclick="navigateTo('nurse','nursing_notes')"><span class="menu-ico"><?= icon('common/nav', 'checklist', 16) ?></span><span class="menu-text">护理记录单</span></div>
<div class="menu-item" onclick="navigateTo('nurse','nursing_docs')"><span class="menu-ico"><?= icon('medical/objects', 'patient', 16) ?></span><span class="menu-text">常规护理文书</span></div>
<div class="menu-group">待办工作</div>
<div class="menu-item" onclick="navigateTo('nurse','prepare')"><span class="menu-ico"><?= icon('common/nav', 'dispensing', 16) ?></span><span class="menu-text">预摆药</span></div>
<div class="menu-item" onclick="navigateTo('nurse','checklist')"><span class="menu-ico"><?= icon('common/nav', 'checklist', 16) ?></span><span class="menu-text">核查清单</span></div>