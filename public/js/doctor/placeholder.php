<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="module-placeholder">
    <div class="placeholder-icon">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#c0c4cc" stroke-width="1.4"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14.6 6.2a4.4 4.4 0 0 0-5.8 5.7L3 17.7 6.3 21l5.8-5.8a4.4 4.4 0 0 0 5.7-5.8l-2.9 2.9-2.7-2.7 2.4-3.4z"/>
        </svg>
    </div>
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