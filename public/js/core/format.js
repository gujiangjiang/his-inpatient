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
        const map = { 'male': '男', 'female': '女', '1': '男', '2': '女', '男': '男', '女': '女' };
        return map[g] || g || '未知';
    },

    /* 按出生日期计算年龄（YYYY-MM-DD → X岁 / X月 / X天） */
    age(birth, at) {
        if (!birth) return '';
        const b = new Date(birth);
        if (isNaN(b.getTime())) return '';
        const n = at ? new Date(at) : new Date();
        let y = n.getFullYear() - b.getFullYear();
        let m = n.getMonth() - b.getMonth();
        let d = n.getDate() - b.getDate();
        if (d < 0) { m--; d = new Date(n.getFullYear(), n.getMonth(), 0).getDate() + d; }
        if (m < 0) { y--; m += 12; }
        if (y >= 1) return y + '岁' + (m > 0 ? m + '月' : '');
        if (m >= 1) return m + '月' + d + '天';
        return Math.max(0, d) + '天';
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
    },

    /* ==================== 本地墙钟时间工具 (原病历站 emrNowStamp/emrYMD/emrStamp 下沉) ==================== */

    /** 当前本地墙钟 'YYYY-MM-DD HH:mm' (业务日期时间默认值) */
    nowStamp() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    },

    /** 取日期部分 'YYYY-MM-DD' (容错 / 分隔与缺时间部分) */
    ymd(v) {
        if (!v) return '';
        const m = String(v).match(/(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);
        return m ? m[1] + '-' + m[2].padStart(2, '0') + '-' + m[3].padStart(2, '0') : String(v).slice(0, 10);
    },

    /** 日期+时间 'YYYY-MM-DD HH:mm' (无时间部分时仅日期; 非日期串原样返回) */
    stamp(v) {
        if (!v) return '';
        const m = String(v).match(/(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2}))?/);
        if (!m) return String(v);
        const d = m[1] + '-' + m[2].padStart(2, '0') + '-' + m[3].padStart(2, '0');
        return m[4] != null ? d + ' ' + m[4].padStart(2, '0') + ':' + m[5].padStart(2, '0') : d;
    },

    /** 简化年龄 (年差法, 各页内联实现的统一入口; 与 age() 精确到天的语义区分) */
    ageSimple(birth) {
        if (!birth) return '';
        const b = new Date(birth);
        if (isNaN(b.getTime())) return '';
        return Math.max(0, new Date().getFullYear() - b.getFullYear());
    }
};
