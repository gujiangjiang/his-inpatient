<?php header("Content-Type: text/html; charset=utf-8"); ?>
<?php require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="menu-group">临床工作</div>
<div class="menu-item" onclick="navigateTo('orders','order_station')"><span class="menu-ico"><?= icon('clinic/nav', 'order', 16) ?></span><span class="menu-text">医嘱</span></div>
<div class="menu-item" onclick="navigateTo('emr','diagnosis')"><span class="menu-ico"><?= icon('clinic/nav', 'diagnosis', 16) ?></span><span class="menu-text">诊断</span></div>
<div class="menu-item" onclick="navigateTo('emr','station')"><span class="menu-ico"><?= icon('clinic/nav', 'record', 16) ?></span><span class="menu-text">病历</span></div>
<div class="menu-item" onclick="navigateTo('outpatient','records')"><span class="menu-ico"><?= icon('clinic/nav', 'outpatient', 16) ?></span><span class="menu-text">门诊记录</span></div>
<div class="menu-item" onclick="navigateTo('outpatient','labs')"><span class="menu-ico"><?= icon('clinic/nav', 'lab', 16) ?></span><span class="menu-text">检验</span></div>
<div class="menu-item" onclick="navigateTo('outpatient','exams')"><span class="menu-ico"><?= icon('clinic/nav', 'exam', 16) ?></span><span class="menu-text">检查</span></div>
<div class="menu-item" onclick="openPlaceholder('处方')" data-placeholder="处方"><span class="menu-ico"><?= icon('clinic/nav', 'prescription', 16) ?></span><span class="menu-text">处方</span></div>
<div class="menu-item" onclick="openPlaceholder('手术')" data-placeholder="手术"><span class="menu-ico"><?= icon('clinic/nav', 'surgery', 16) ?></span><span class="menu-text">手术</span></div>
<div class="menu-item" onclick="openPlaceholder('护理')" data-placeholder="护理"><span class="menu-ico"><?= icon('clinic/nav', 'nursing', 16) ?></span><span class="menu-text">护理</span></div>
<div class="menu-item" onclick="navigateTo('clinic','consult')"><span class="menu-ico"><?= icon('clinic/nav', 'consult', 16) ?></span><span class="menu-text">会诊</span></div>
<div class="menu-item" onclick="navigateTo('case_front_page','main')"><span class="menu-ico"><?= icon('clinic/nav', 'home', 16) ?></span><span class="menu-text">首页</span></div>
<div class="menu-item" onclick="navigateTo('clinic','team')"><span class="menu-ico"><?= icon('common/nav', 'users', 16) ?></span><span class="menu-text">三级医师</span></div>
<div class="menu-item" onclick="openPlaceholder('提交')" data-placeholder="提交"><span class="menu-ico"><?= icon('clinic/nav', 'submit', 16) ?></span><span class="menu-text">提交</span></div>
<div class="menu-item" onclick="openPlaceholder('质控')" data-placeholder="质控"><span class="menu-ico"><?= icon('clinic/nav', 'quality', 16) ?></span><span class="menu-text">质控</span></div>
