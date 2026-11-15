<?php header("Content-Type: text/html; charset=utf-8"); ?>
<script>
/** 可用字段字典: 当前模板文书中的字段代码 (slot/dyn/ref) + 显示名 (取前导固定标签) */
function emrCalcVars() {
    const out = [];
    const seen = {};
    if (!_emrEditor) return out;
    EmrOps.paragraphs(_emrEditor.getDoc().body).forEach(function (x) {
        let label = '';
        (x.p.runs || []).forEach(function (it) {
            if (it.t === 'fx') { label = String(it.text || '').replace(/[：:]\s*$/, ''); return; }
            if (it.t === 'slot' && it.key && !seen[it.key]) {
                seen[it.key] = 1;
                out.push({ key: it.key, label: label || it.name || it.ph || it.key });
            } else if (it.t === 'dyn' && it.key && !it.calc && !seen[it.key]) {
                seen[it.key] = 1;
                out.push({ key: it.key, label: it.name || emr_dynHint(it.key) || it.key });
            } else if (it.t === 'ref' && it.key && !seen[it.key]) {
                seen[it.key] = 1;
                out.push({ key: it.key, label: it.fb || it.key });
            }
        });
    });
    return out;
}

/** 自动计算项配置块 (插入对话框/属性对话框共用); withName=false 省略名称输入 (属性框已有名称行)
 *  支持 数值计算 (加减乘除幂 + 数值函数) 与 时间计算 (minutes/hours 取字段原始时间值);
 *  输出格式: 数值(小数位) | 时长(分钟智能分段: <60 → X分钟, ≥60 → X小时X分钟) */
function emrCalcBlock(item, withName) {
    const calc = item.calc || {};
    const isDur = calc.fmt === 'dur';
    const vars = emrCalcVars();
    const html = (withName === false ? '' :
            '<div class="form-group"><label class="form-label">显示名称（自由命名，如 BMI / 手术时长）</label>'
            + '<input id="calc-name" class="form-control" value="' + Format.escape(item.name || '') + '" placeholder="如：BMI"></div>')
        + '<div class="form-group"><label class="form-label">计算公式（点击下方字段代码/函数插入）</label>'
        + '<input id="calc-expr" class="form-control" value="' + Format.escape(calc.expr || '') + '" placeholder="数值如 vital_weight_val / (vital_height_val / 100) ^ 2；时间如 minutes(surgery_start_val, surgery_end_val)"></div>'
        + '<div class="form-group"><label class="form-label">可用函数（点击插入）</label><div class="emr-calc-vars">'
        + ['round(', 'abs(', 'sqrt(', 'min(', 'max('].map(function (f) {
            return '<span class="emr-calc-var emr-calc-fn" data-f="' + f + '" title="数值函数">' + f.replace('(', '') + '</span>';
        }).join('')
        + '<span class="emr-calc-var emr-calc-fn" data-f="minutes(" title="两字段时间差(分钟): minutes(开始字段, 结束字段)">时间差·分钟</span>'
        + '<span class="emr-calc-var emr-calc-fn" data-f="hours(" title="两字段时间差(小时): hours(开始字段, 结束字段)">时间差·小时</span>'
        + '</div></div>'
        + '<div class="form-group"><label class="form-label">输出格式</label><div class="emr-prop-radios">'
        + '<label class="emr-prop-radio"><input type="radio" name="calc-fmt" value="num"' + (!isDur ? ' checked' : '') + '> 数值</label>'
        + '<label class="emr-prop-radio"><input type="radio" name="calc-fmt" value="dur"' + (isDur ? ' checked' : '') + '> 时长（60分钟内显示「X分钟」，超过显示「X小时X分钟」）</label>'
        + '</div></div>'
        + '<div class="form-group" id="calc-dec-row"' + (isDur ? ' style="display:none"' : '') + '><label class="form-label">小数位（数值格式）</label>'
        + '<input id="calc-dec" type="number" min="0" max="4" class="form-control emr-calc-dec" value="' + (calc.dec != null ? calc.dec : 1) + '"></div>'
        + '<div class="form-group"><label class="form-label">可用字段（点击插入代码）</label>'
        + '<div class="emr-calc-vars" id="calc-vars">'
        + (vars.length ? vars.map(function (v) {
            return '<span class="emr-calc-var" data-k="' + Format.escape(v.key) + '" title="' + Format.escape(v.key) + '">'
                + Format.escape(v.label) + ' <i>' + Format.escape(v.key) + '</i></span>';
        }).join('') : '<span class="emr-prop-testempty">当前模板暂无可用字段（请先在模板中插入字段）</span>')
        + '</div><p class="emr-tpl-hint">计算仅依据当前病历内字段值（含混排下拉/日期）；时间计算兼容多种格式：<b>2026-10-11 23:50</b> 至 <b>2026-10-12 01:50</b>（跨天/跨月/跨年正确换算）、2026年10月11日 23时50分、10月11日 23:50、23:50；保存时若公式涉及字段在文书中不存在将报错。</p></div>';
    const insertAt = function (sub, text) {
        const inp = sub.querySelector('#calc-expr');
        const s = inp.selectionStart != null ? inp.selectionStart : inp.value.length;
        const e = inp.selectionEnd != null ? inp.selectionEnd : s;
        inp.value = inp.value.slice(0, s) + text + inp.value.slice(e);
        inp.focus();
    };
    return {
        html: html,
        bind: function (sub) {
            sub.querySelectorAll('.emr-calc-var[data-k]').forEach(function (chip) {
                chip.addEventListener('click', function () { insertAt(sub, chip.dataset.k); });
            });
            sub.querySelectorAll('.emr-calc-fn').forEach(function (chip) {
                chip.addEventListener('click', function () { insertAt(sub, chip.dataset.f); });
            });
            sub.querySelectorAll('input[name="calc-fmt"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    const row = sub.querySelector('#calc-dec-row');
                    if (row) row.style.display = (sub.querySelector('input[name="calc-fmt"]:checked') || {}).value === 'dur' ? 'none' : '';
                });
            });
        },
        /** 读取并校验; 校验失败返回 null (调用方保留弹窗) */
        read: function (sub) {
            const expr = (sub.querySelector('#calc-expr').value || '').trim();
            const fmt = (sub.querySelector('input[name="calc-fmt"]:checked') || {}).value === 'dur' ? 'dur' : 'num';
            let dec = parseInt(sub.querySelector('#calc-dec').value, 10);
            if (isNaN(dec) || dec < 0) dec = 0;
            if (dec > 4) dec = 4;
            if (!expr) { Dom.toast('请输入计算公式', 'error'); return null; }
            const keys = EmrCalc.exprKeys(expr);
            if (!keys) { Dom.toast('公式包含不支持的字符（仅限数字/字段代码/+-*/^/括号/内置函数）', 'error'); return null; }
            if (!keys.length) { Dom.toast('公式需引用至少一个字段代码', 'error'); return null; }
            const known = emrCalcVars().map(function (v) { return v.key; });
            const unknown = keys.filter(function (k) { return known.indexOf(k) < 0; });
            if (unknown.length) { Dom.toast('公式引用了当前模板中不存在的字段: ' + unknown.join('、'), 'error'); return null; }
            /* 时间函数参数需为两个字段代码: minutes(开始, 结束) */
            const timeFnRe = /\b(?:minutes|hours)\s*\(\s*([^,()]+?)\s*,\s*([^,()]+?)\s*\)/g;
            let tm;
            while ((tm = timeFnRe.exec(expr))) {
                const a = tm[1].trim(), b = tm[2].trim();
                if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(a) || !/^[A-Za-z_][A-Za-z0-9_]*$/.test(b)) {
                    Dom.toast('时间差函数的两个参数需为字段代码，如 minutes(开始字段, 结束字段)', 'error');
                    return null;
                }
            }
            const nameEl = sub.querySelector('#calc-name');
            return { name: nameEl ? ((nameEl.value || '').trim() || '自动项') : null, expr: expr, dec: dec, fmt: fmt };
        }
    };
}

/** 插入自动计算项: 显示名称(如 BMI) + 公式(字段代码) + 小数位; 书写时按当前病历自动计算 */
function emr_dsnInsertCalc() {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const blk = emrCalcBlock({ calc: { expr: '', dec: 1 }, name: '' });
    const sub = Dom.modal('插入自动计算项', blk.html, [
        { text: '插入', type: 'primary', onclick: function () {
            const r = blk.read(sub);
            if (!r) return false;
            _emrEditor.insertItem({
                t: 'dyn',
                key: 'calc_' + Date.now().toString(36) + Math.floor(Math.random() * 90 + 10),
                name: r.name, text: '',
                calc: { expr: r.expr, dec: r.dec, fmt: r.fmt }
            });
        } },
        { text: '取消', type: 'default' }
    ]);
    blk.bind(sub);
}
</script>
