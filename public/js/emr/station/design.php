<?php /* 模板设计器 (聚合入口): 实现按职责拆分至 design/ 子目录, 按序加载共享全局作用域
 *   design/entry.php    进入/退出/保存模板设计 + 可插入字段目录
 *   design/panel.php    左栏插入面板(字段/固定元素/日期/表格选择器)
 *   design/context.php  设计模式右击菜单(表格属性/行列操作/固定元素删除)
 *   design/props.php    元素属性对话框(名称/占位/选项/日期/公式)
 *   design/insert.php   插入工具(字段/标题/引用) + 科室显示名 */ ?>
<?php include __DIR__ . '/design/entry.php'; ?>
<?php include __DIR__ . '/design/panel.php'; ?>
<?php include __DIR__ . '/design/context.php'; ?>
<?php include __DIR__ . '/design/props.php'; ?>
<?php include __DIR__ . '/design/insert.php'; ?>
