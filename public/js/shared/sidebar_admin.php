<?php header("Content-Type: text/html; charset=utf-8"); ?>
<?php require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="menu-group">患者管理</div>
<div class="menu-item" onclick="navigateTo('patients','patient_list')"><span class="menu-ico"><?= icon('medical/objects', 'patient', 16) ?></span><span class="menu-text">患者管理</span></div>
<div class="menu-item" onclick="navigateTo('patients','beds')"><span class="menu-ico"><?= icon('medical/objects', 'bed', 16) ?></span><span class="menu-text">床位管理</span></div>
<div class="menu-group">业务管理</div>
<div class="menu-item" onclick="navigateTo('admin','departments')"><span class="menu-ico"><?= icon('medical/objects', 'hospital', 16) ?></span><span class="menu-text">科室管理</span></div>
<div class="menu-item" onclick="navigateTo('admin','wards')"><span class="menu-ico"><?= icon('common/nav', 'ward', 16) ?></span><span class="menu-text">病区管理</span></div>
<div class="menu-item" onclick="navigateTo('admin','users')"><span class="menu-ico"><?= icon('common/nav', 'users', 16) ?></span><span class="menu-text">人员管理</span></div>
<div class="menu-group">接口集成</div>
<div class="menu-item" onclick="navigateTo('admin','integrations')"><span class="menu-ico"><?= icon('common/nav', 'plug', 16) ?></span><span class="menu-text">接口管理</span></div>
<div class="menu-item" onclick="navigateTo('admin','api_configs')"><span class="menu-ico"><?= icon('common/nav', 'link', 16) ?></span><span class="menu-text">接口配置</span></div>
<div class="menu-group">系统设置</div>
<div class="menu-item" onclick="navigateTo('admin','sys_configs')"><span class="menu-ico"><?= icon('common/nav', 'sliders', 16) ?></span><span class="menu-text">系统设置</span></div>
