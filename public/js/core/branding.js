// public/js/core/branding.js - 医院双名称的统一状态、对齐 DOM 与打印抬头
const HospitalBranding = (() => {
    const fallback = '开源通用医院信息系统';
    const clean = v => String(v == null ? '' : v).trim();
    const normalize = source => {
        source = source || {};
        return {
            hospital_name: clean(source.hospital_name != null ? source.hospital_name : source.hospital) || fallback,
            hospital_name2: clean(source.hospital_name2 != null ? source.hospital_name2 : source.hospital2)
        };
    };
    let current = Object.assign({}, window.HIS_BRAND || {}, normalize(window.HIS_BRAND));

    function setCurrent(source) {
        current = Object.assign({}, current, source || {}, normalize(source || current));
        window.HIS_BRAND = Object.assign({}, current);
        return Object.assign({}, current);
    }
    function get() { return Object.assign({}, current); }
    function applyPair(primary, secondary, source) {
        const names = source ? setCurrent(source) : current;
        if (primary) { primary.textContent = names.hospital_name; primary.title = names.hospital_name; }
        if (secondary) {
            secondary.textContent = names.hospital_name2;
            secondary.title = names.hospital_name2;
            secondary.hidden = names.hospital_name2 === '';
            secondary.style.display = '';
        }
        return names;
    }
    function stampHeader(header) {
        header = header || {};
        header.hospital = current.hospital_name;
        header.hospital2 = current.hospital_name2;
        return header;
    }
    function displayName(parenthesized) {
        return current.hospital_name2
            ? current.hospital_name + (parenthesized ? '（' + current.hospital_name2 + '）' : ' · ' + current.hospital_name2)
            : current.hospital_name;
    }
    function headerHtml(extraClass) {
        const second = current.hospital_name2
            ? '<div class="brand-name-line print-hospital2">' + Format.escape(current.hospital_name2) + '</div>' : '';
        return '<div class="brand-name-lockup print-brand-lockup ' + Format.escape(extraClass || '') + '">'
            + '<div class="brand-name-line print-hospital">' + Format.escape(current.hospital_name) + '</div>' + second + '</div>';
    }
    return { normalize, setCurrent, get, applyPair, stampHeader, displayName, headerHtml };
})();
