// public/js/emr/emr_ui.js - EMR 通用 UI 小部件 (浮动菜单等)
// 供病历站页面片段与设计器共用; 统一处理定位回拢/外点关闭/Esc/二级菜单
(function () {
  'use strict';

  /**
   * 浮动右键菜单
   * @param items [{ a, label, danger, sub: [item...] }]  a=动作标识; sub=二级菜单
   * @param x, y  页面坐标 (clientX/clientY)
   * @param onPick(action, item)  点击项回调 (二级项同样回调其 a); 菜单随即关闭
   * @returns close() 手动关闭
   */
  const floatMenu = (items, x, y, onPick) => {
    document.querySelectorAll('.emr-ctx-float').forEach((m) => m.remove());
    const menu = document.createElement('div');
    menu.className = 'emr-context-menu emr-ctx-float';
    menu.style.cssText = 'position:fixed;z-index:5200;left:' + x + 'px;top:' + y + 'px;min-width:132px;';

    const itemHTML = (it) => {
      const cls = 'ctx-item' + (it.danger ? ' danger' : '') + (Array.isArray(it.sub) && it.sub.length ? ' has-sub' : '');
      const sub = (Array.isArray(it.sub) && it.sub.length)
        ? '<div class="ctx-sub">' + it.sub.map(itemHTML).join('') + '</div>'
        : '';
      return '<div class="' + cls + '" data-a="' + String(it.a || '') + '">'
        + '<span>' + String(it.label == null ? '' : it.label) + '</span>'
        + (sub ? '<span class="ctx-caret">›</span>' + sub : '')
        + '</div>';
    };
    menu.innerHTML = items.map(itemHTML).join('');
    document.body.appendChild(menu);

    const r = menu.getBoundingClientRect();
    if (r.right > window.innerWidth - 8) menu.style.left = Math.max(8, x - r.width) + 'px';
    if (r.bottom > window.innerHeight - 8) menu.style.top = Math.max(8, y - r.height) + 'px';

    const close = () => {
      menu.remove();
      document.removeEventListener('mousedown', onDoc, true);
      document.removeEventListener('keydown', onEsc, true);
    };
    const onDoc = (ev) => { if (!menu.contains(ev.target)) close(); };
    const onEsc = (ev) => { if (ev.key === 'Escape') close(); };
    setTimeout(() => {
      document.addEventListener('mousedown', onDoc, true);
      document.addEventListener('keydown', onEsc, true);
    }, 0);

    menu.querySelectorAll('.ctx-item').forEach((el) => {
      if (el.querySelector('.ctx-sub')) return; /* 含二级菜单的父项: 仅悬浮展开, 不响应点击动作 */
      el.addEventListener('click', () => {
        const a = el.dataset.a;
        close();
        if (typeof onPick === 'function') onPick(a, el);
      });
    });
    return close;
  };

  /** 转义文本 (标签等动态值写入 innerHTML 前必用) */
  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  window.EmrUI = { floatMenu, esc };
})();
