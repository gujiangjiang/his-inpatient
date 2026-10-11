<?php
header("Content-Type: text/html; charset=utf-8");
require_once __DIR__ . '/../../icons/icon.php';
?>
<div class="module-placeholder">
    <div class="placeholder-icon"><?= icon('medical/objects', 'tool', 56) ?></div>
    <h3 id="placeholder-title">功能模块</h3>
    <p>该模块尚未对接，敬请期待</p>
</div>

<script>
/**
 * 占位页初始化: 读取侧边栏传入的模块名称
 */
function doctor_placeholder_init() {
    const title = document.getElementById('placeholder-title');
    if (title) {
        // 优先取点击时记录的模块名, 回退活动菜单项
        const activeItem = document.querySelector('.sidebar .menu-item.active');
        title.textContent = window.__placeholderTitle || (activeItem && activeItem.dataset.placeholder) || '功能模块';
    }
}
</script>