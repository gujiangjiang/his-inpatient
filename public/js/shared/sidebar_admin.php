<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="menu-group">管理</div>
<div class="menu-item" onclick="navigateTo('admin','departments')">科室管理</div>
<div class="menu-item" onclick="navigateTo('admin','wards')">病区管理</div>
<div class="menu-item" onclick="navigateTo('admin','users')">人员管理</div>
<div class="menu-item" onclick="navigateTo('admin','api_configs')">接口配置</div>
<div class="menu-item" onclick="navigateTo('admin','sys_configs')">系统设置</div>
<div class="menu-group">患者</div>
<div class="menu-item" onclick="navigateTo('patients','patient_list')">患者管理</div>
<div class="menu-item" onclick="navigateTo('patients','beds')">床位管理</div>
