<?php /* 病历站初始化 (聚合入口): 实现按职责拆分至 init/ 子目录, 按序加载共享全局作用域
 *   init/bootstrap.php   站点初始化/文书树渲染 + 全局状态变量
 *   init/batch_print.php 病历文书批量打印选择器
 *   init/creator.php     模板选择器/新建文书/患者信息与页面参数
 *   init/open_doc.php    打开文书 (统一字处理编辑器, 含竞态令牌)
 *   init/sync.php        病案首页/诊断/字段引用自动同步 + openWordDoc */ ?>
<?php include __DIR__ . '/init/bootstrap.php'; ?>
<?php include __DIR__ . '/init/batch_print.php'; ?>
<?php include __DIR__ . '/init/creator.php'; ?>
<?php include __DIR__ . '/init/open_doc.php'; ?>
<?php include __DIR__ . '/init/sync.php'; ?>
