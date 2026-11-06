// public/js/core/rich_editor.js - 无第三方依赖的简易编辑器 (自研)
// 说明: 全站已改用自研「病历字处理编辑器」(public/js/emr_editor/);
// 本组件仅保留给旧版页面兜底使用, 提供与既有调用方一致的 API (基于原生 textarea)。
const RichEditor = {
    editors: {},

    create(elementId, content = '') {
        const el = document.getElementById(elementId);
        if (!el) {
            console.error('RichEditor: element not found:', elementId);
            return null;
        }
        let ta = this.editors[elementId];
        if (ta && document.body.contains(ta)) {
            if (content) ta.value = String(content);
            return this._wrap(ta);
        }
        ta = document.createElement('textarea');
        ta.id = elementId;
        ta.name = el.getAttribute('name') || elementId;
        ta.rows = 8;
        ta.className = 'form-control';
        el.parentNode.replaceChild(ta, el);
        if (content) ta.value = String(content);
        this.editors[elementId] = ta;
        return this._wrap(ta);
    },

    _wrap(ta) {
        return {
            getDelta: () => JSON.stringify({ ops: ta.value ? [{ insert: ta.value }] : [] }),
            getHTML: () => ta.value,
            setHTML: (html) => { ta.value = html; },
            setText: (text) => { ta.value = text; },
            getContent: () => ta.value,
            root: ta
        };
    },

    getHTML(elementId) {
        const ta = this.editors[elementId];
        return ta ? ta.value : null;
    }
};