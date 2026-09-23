// public/js/core/components.js - 共享组件
const Components = {
    breadcrumb(items) {
        let html = '<nav class="breadcrumb"><span class="breadcrumb-item">首页</span>';
        items.forEach(item => {
            html += '<span class="breadcrumb-item">' + Format.escape(item) + '</span>';
        });
        html += '</nav>';
        return html;
    },

    dataBadge(source) {
        if (source === 'live') {
            return '<span class="badge badge-success">实时接口</span>';
        } else if (source === 'mock') {
            return '<span class="badge badge-default">模拟数据</span>';
        }
        return '';
    }
};
