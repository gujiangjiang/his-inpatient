// public/js/core/format.js - 公共字典 + HTML 转义
const Format = {
    escape(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },

    gender(g) {
        const map = { 'male': '男', 'female': '女', 'male': '男', 'female': '女', '1': '男', '2': '女', '男': '男', '女': '女' };
        return map[g] || g || '未知';
    },

    role(role) {
        const map = {
            admin: '管理员',
            doctor: '医生',
            nurse: '护士',
            pharmacist: '药师',
            lab_tech: '检验技师'
        };
        return map[role] || role;
    },

    orderStatus(status) {
        const map = {
            draft: '草稿',
            pending: '待核对',
            invalid_pending: '待作废',
            stop_pending: '待停止',
            verified: '已核对',
            executing: '执行中',
            completed: '已完成',
            stopped: '停止',
            invalid: '作废'
        };
        return map[status] || status || '未知';
    },

    orderStatusClass(status) {
        return 'badge-' + (status || 'default');
    },

    labFlag(flag) {
        if (!flag) return '';
        const map = { H: '高', L: '低', critical: '危急' };
        return map[flag] || flag;
    },

    labFlagClass(flag) {
        if (flag === 'critical') return 'badge-critical';
        if (flag === 'H') return 'badge-warning';
        if (flag === 'L') return 'badge-info';
        return 'badge-default';
    },

    date(dt) {
        if (!dt) return '';
        return new Date(dt).toLocaleString('zh-CN', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
    },

    datetime(dt) {
        if (!dt) return '';
        return new Date(dt).toLocaleString('zh-CN', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    },

    // 医嘱列表风格短时间: 26-10-09 11:30
    shortTime(dt) {
        if (!dt) return '';
        const d = new Date(String(dt).replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dt;
        const p = n => String(n).padStart(2, '0');
        return String(d.getFullYear()).slice(2) + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }
};
