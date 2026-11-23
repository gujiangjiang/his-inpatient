// public/js/core/branding.js - 医院双名称的统一状态、对齐 DOM 与打印抬头
const HospitalBranding = (() => {
    const fallback = '开源通用医院信息系统';
    const clean = v => String(v == null ? '' : v).trim();
    const isNamed = v => { const s = clean(v); return !!s && s !== fallback; };
    let current = { hospital_name: '', hospital_name2: '' };
    const normalize = source => {
        source = source || {};
        const rawName = clean(source.hospital_name != null ? source.hospital_name : source.hospital);
        const rawName2 = clean(source.hospital_name2 != null ? source.hospital_name2 : source.hospital2);
        /* 来源带真实名称时原样使用 (保住快照固化语义); 历史单据可能无快照/快照未固化
           医院名甚至固化了占位名, 此时成对回退为「当前设置的医院名称」。 */
        if (isNamed(rawName)) return { hospital_name: rawName, hospital_name2: rawName2 };
        const currentName = clean(current.hospital_name);
        if (isNamed(currentName)) {
            return { hospital_name: currentName, hospital_name2: rawName2 || clean(current.hospital_name2) };
        }
        return { hospital_name: rawName || fallback, hospital_name2: rawName2 };
    };
    current = Object.assign({}, current, window.HIS_BRAND || {}, normalize(window.HIS_BRAND));

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
