<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="module-placeholder">
    <div class="placeholder-icon">🛠</div>
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
        // 尝试读取侧边栏 item 传入的模块名
        const activeItem = document.querySelector('.sidebar .menu-item.active');
        title.textContent = (activeItem && activeItem.dataset.placeholder) ? activeItem.dataset.placeholder : '功能模块';
    }
}
</script>