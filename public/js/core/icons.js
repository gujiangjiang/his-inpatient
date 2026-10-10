// public/js/core/icons.js - 全站自绘 SVG 图标库 (禁止 emoji, 统一使用 24 视图线稿)
const Icons = {
    _wrap(inner, size, cls) {
        return `<svg class="svg-icon${cls ? ' ' + cls : ''}" width="${size}" height="${size}" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
            aria-hidden="true">${inner}</svg>`;
    },

    /** 医院建筑 + 十字 */
    hospital(size = 24) {
        return this._wrap(
            '<path d="M4 21V7.5L12 3l8 4.5V21"/><path d="M2.5 21h19"/>' +
            '<path d="M12 7.5v6"/><path d="M9 10.5h6"/>',
            size
        );
    },

    /** 扳手工具 */
    tool(size = 24) {
        return this._wrap(
            '<path d="M14.6 6.2a4.4 4.4 0 0 0-5.8 5.7L3 17.7 6.3 21l5.8-5.8a4.4 4.4 0 0 0 5.7-5.8l-2.9 2.9-2.7-2.7 2.4-3.4z"/>',
            size
        );
    },

    /** 警告三角 */
    warning(size = 14) {
        return this._wrap(
            '<path d="M12 3.2 2.6 20h18.8L12 3.2z"/><path d="M12 10v4.2"/><path d="M12 17.3h.01"/>',
            size
        );
    },

    /** 关闭 / 删除 */
    close(size = 12) {
        return this._wrap('<path d="M5.5 5.5l13 13"/><path d="M18.5 5.5l-13 13"/>', size);
    },

    /** 更多 (水平三点) */
    more(size = 16) {
        return this._wrap(
            '<circle cx="5" cy="12" r="1.3" fill="currentColor" stroke="none"/>' +
            '<circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"/>' +
            '<circle cx="19" cy="12" r="1.3" fill="currentColor" stroke="none"/>',
            size
        );
    },

    /** 下拉箭头 */
    caretDown(size = 12) {
        return this._wrap('<path d="M6.5 9.5 12 15l5.5-5.5"/>', size);
    },

    /** 右向箭头 (展开) */
    caretRight(size = 12) {
        return this._wrap('<path d="M9.5 6.5 15 12l-5.5 5.5"/>', size);
    },

    /** 患者 (人像) */
    patient(size = 24) {
        return this._wrap(
            '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-4 3.8-6 7.5-6s6.7 2 7.5 6"/>',
            size
        );
    }
};

window.Icons = Icons;
