<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="menu-group">临床工作</div>
<div class="menu-item" onclick="navigateTo('orders','order_list')">医嘱</div>
<div class="menu-item" onclick="navigateTo('emr','diagnosis')">诊断</div>
<div class="menu-item" onclick="navigateTo('emr','station')">病历</div>
<div class="menu-item" onclick="navigateTo('doctor','placeholder')" data-placeholder="检验">检验</div>
<div class="menu-item" onclick="navigateTo('doctor','placeholder')" data-placeholder="检查">检查</div>
<div class="menu-item" onclick="navigateTo('doctor','placeholder')" data-placeholder="处方">处方</div>
<div class="menu-item" onclick="navigateTo('doctor','placeholder')" data-placeholder="手术">手术</div>
<div class="menu-item" onclick="navigateTo('doctor','placeholder')" data-placeholder="护理">护理</div>
<div class="menu-item" onclick="navigateTo('doctor','placeholder')" data-placeholder="会诊">会诊</div>
<div class="menu-item" onclick="navigateTo('case_front_page','main')">首页</div>
<div class="menu-item" onclick="navigateTo('doctor','placeholder')" data-placeholder="提交/质控">提交/质控</div>