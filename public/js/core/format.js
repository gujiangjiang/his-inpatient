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

    /* 兼容旧入口：统一委托临床年龄口径。 */
    age(birth, at) {
        return this.ageClinical(birth, at);
    },

    /* 病案首页年龄口径：满1周岁只显示整岁；不足1周岁显示月；新生儿≤28天显示天。 */
    ageClinical(birth, at) {
        if (!birth) return '';
        const parseDate = value => {
            const m = String(value || '').match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);
            if (m) return new Date(+m[1], +m[2] - 1, +m[3]);
            const date = new Date(value);
            return isNaN(date.getTime()) ? null : date;
        };
        const b = parseDate(birth);
        const n = at ? parseDate(at) : new Date();
        if (!b || !n) return '';
        const start = new Date(b.getFullYear(), b.getMonth(), b.getDate());
        const end = new Date(n.getFullYear(), n.getMonth(), n.getDate());
        const days = Math.floor((end.getTime() - start.getTime()) / 86400000);
        if (days < 0) return '';
        if (days >= 365) return Math.max(1, Math.floor(days / 365.25)) + '岁';
        if (days > 28) return Math.max(1, Math.floor(days / 30.44)) + '月';
        return days + '天';
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

    /** 兼容旧入口：统一委托临床年龄口径。 */
    ageSimple(birth, at) {
        return this.ageClinical(birth, at);
    }
};
