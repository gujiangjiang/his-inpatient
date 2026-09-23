// public/js/core/rich_editor.js - Quill.js 唯一入口
const RichEditor = {
    editors: {},

    create(elementId, content = '') {
        const el = document.getElementById(elementId);
        if (!el) {
            console.error('RichEditor: element not found:', elementId);
            return null;
        }

        // 检测 CDN 是否可用
        if (typeof Quill === 'undefined') {
            // 降级为 textarea
            const ta = document.createElement('textarea');
            ta.id = elementId;
            ta.name = el.getAttribute('name') || elementId;
            ta.value = content;
            ta.rows = 8;
            ta.className = 'form-control';
            el.parentNode.replaceChild(ta, el);
            return {
                getDelta: () => ({ ops: ta.value ? [{ insert: ta.value }] : [] }),
                getHTML: () => ta.value,
                setHTML: (html) => { ta.value = html; },
                setText: (text) => { ta.value = text; },
                getContent: () => ta.value
            };
        }

        const quill = new Quill('#' + elementId, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                    [{ 'align': [] }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        if (content) {
            try {
                quill.setContents(JSON.parse(content));
            } catch (e) {
                quill.clipboard.dangerouslyPasteHTML(content);
            }
        }

        this.editors[elementId] = quill;
        return {
            getDelta: () => JSON.stringify(quill.getContents()),
            getHTML: () => quill.root.innerHTML,
            setHTML: (html) => quill.clipboard.dangerouslyPasteHTML(html),
            setText: (text) => quill.setText(text),
            getContent: () => quill.root.innerHTML
        };
    },

    getDelta(elementId) {
        const editor = this.editors[elementId];
        return editor ? editor.getDelta() : null;
    },

    getHTML(elementId) {
        const editor = this.editors[elementId];
        return editor ? editor.getHTML() : null;
    }
};
