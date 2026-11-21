<script>
/** 动态项用途说明 */
function emr_dynHint(key) {
    const D = {
        vital_bmi_value: 'BMI 自动计算', sign_names: '医师签名（签名时自动填入）',
        initial_diagnosis_value: '初步诊断（点击同步诊断）', diag_admission: '入院诊断（打开时自动填入）',
        diag_discharge: '出院诊断（打开时自动填入）', p_name: '患者姓名', p_gender: '患者性别', p_age: '患者年龄'
    };
    return D[key] || key;
}

/** 元素名称推断 (未设置自定义名时) */
function emr_dsnItemName(item) {
    if (item.name) return item.name;
    if (item.t === 'fx') return item.text || '';
    return item.key || item.t;
}

/** 属性对话框·日期块 (slot.dt): 返回 {html, bind(sub), read(sub)} */
function emrPropDtBlock(item) {
    const mode = item.dt.mode || 'datetime';
    const fmt = item.dt.fmt || '';
    const presets = ['', 'yyyy-mm-dd', 'yyyy年mm月dd日', 'yyyy-mm-dd hh:nn', 'yyyy年mm月dd日 hh时nn分', 'hh:nn'];
    const html = '<div class="form-group"><label class="form-label">日期类型</label><div class="emr-prop-radios">'
        + [['datetime', '日期+时间'], ['date', '仅日期'], ['time', '仅时间']].map(function (m) {
            return '<label class="emr-prop-radio"><input type="radio" name="dt-mode" value="' + m[0] + '"' + (mode === m[0] ? ' checked' : '') + '> ' + m[1] + '</label>';
        }).join('') + '</div></div>'
        + '<div class="form-group"><label class="form-label">日期格式</label>'
        + '<select id="prop-dt-fmt" class="form-control">' + presets.map(function (x) {
            return '<option value="' + x + '"' + (fmt === x ? ' selected' : '') + '>' + (x || '默认(按类型)') + '</option>';
        }).join('') + '</select>'
        + '<input id="prop-dt-fmtc" class="form-control" style="margin-top:6px" placeholder="自定义格式（可留空）: yyyy/mm/dd/hh/nn" value="' + (presets.indexOf(fmt) < 0 ? Format.escape(fmt) : '') + '"></div>';
    return {
        html: html,
        bind: function () {},
        read: function (sub) {
            const modeSel = sub.querySelector('input[name="dt-mode"]:checked');
            const fmtSel = (sub.querySelector('#prop-dt-fmt').value || '').trim();
            const fmtCustom = (sub.querySelector('#prop-dt-fmtc').value || '').trim();
            return { mode: modeSel ? modeSel.value : 'datetime', fmt: fmtCustom || fmtSel };
        }
    };
}

/** 属性对话框·下拉选项块 (slot.opts / slot.dict): 选项来源(自定义/全局字典) + 单选复选 + 默认选中勾选
 *  全局字典 = 全院统一选项 (民族/婚姻/职业/病史陈述者/有无等): 模板仅存字典键, 选项不随模板导出;
 *  默认选中 = 模板勾选 (新建文书时自动填入; 单选/复选均可再取消) */
function emrPropOptsBlock(item) {
    const isMulti = !!item.multi;
    let picked = Array.isArray(item.defPick) ? item.defPick.slice() : [];
    const dictNames = { nation: '民族', marriage: '婚姻状况', occupation: '职业', teller: '病史陈述者', yesno: '无/有', yesno_unknown: '无/有/不详' };
    const curDict = item.dict || '';
    const dictOpts = Object.keys(dictNames).map(function (k) {
        return '<option value="' + k + '"' + (curDict === k ? ' selected' : '') + '>' + dictNames[k] + '（全局字典）</option>';
    }).join('');
    const html = '<div class="form-group"><label class="form-label">选项来源</label><div class="emr-prop-radios">'
        + '<label class="emr-prop-radio"><input type="radio" name="opt-src" value="custom"' + (!curDict ? ' checked' : '') + '> 自定义选项</label>'
        + '<label class="emr-prop-radio"><input type="radio" name="opt-src" value="dict"' + (curDict ? ' checked' : '') + '> 全局字典（全院统一）</label>'
        + '</div></div>'
        + '<div class="form-group" id="prop-dict-row"' + (curDict ? '' : ' style="display:none"') + '><label class="form-label">选择字典（全院统一数据源，模板不内嵌选项）</label>'
        + '<select id="prop-dict" class="form-control">' + dictOpts + '</select></div>'
        + '<div class="form-group" id="prop-opts-row"' + (curDict ? ' style="display:none"' : '') + '><label class="form-label">选项内容（每行一个，也可用顿号/逗号分隔）</label>'
        + '<textarea id="prop-opts" class="form-control" rows="5" placeholder="承认&#10;否认&#10;不详">' + Format.escape((item.opts || []).join('\n')) + '</textarea></div>'
        + '<div class="form-group"><label class="form-label">选择类型</label><div class="emr-prop-radios">'
        + [['single', '单选（下拉选择）'], ['multi', '复选（多选、顿号拼接）']].map(function (m) {
            const on = (m[0] === 'multi') === isMulti;
            return '<label class="emr-prop-radio"><input type="radio" name="sel-type" value="' + m[0] + '"' + (on ? ' checked' : '') + '> ' + m[1] + '</label>';
        }).join('') + '</div></div>'
        + '<div class="form-group"><label class="form-label">默认选中（新建文书时自动填入，可取消；不勾选则不预填）</label>'
        + '<div id="prop-defpick" class="emr-prop-test"></div></div>';
    const isMultiNow = (sub) => {
        const sel = sub.querySelector('input[name="sel-type"]:checked');
        return !!(sel && sel.value === 'multi');
    };
    const srcNow = function (sub) {
        const r = sub.querySelector('input[name="opt-src"]:checked');
        return r ? r.value : 'custom';
    };
    const linesNow = function (sub) {
        if (srcNow(sub) === 'dict') {
            const k = sub.querySelector('#prop-dict').value;
            return (window.EmrDicts && window.EmrDicts[k]) ? window.EmrDicts[k].slice() : [];
        }
        return emrParseOpts(sub.querySelector('#prop-opts').value);
    };
    return {
        html: html,
        bind: function (sub) {
            const box = sub.querySelector('#prop-defpick');
            const renderPick = function () {
                const lines = linesNow(sub);
                if (!lines.length) { box.innerHTML = '<span class="emr-prop-testempty">暂无选项</span>'; return; }
                box.innerHTML = lines.map(function (o2) {
                    return '<label class="emr-prop-radio"><input type="checkbox" value="' + Format.escape(o2) + '"' + (picked.indexOf(o2) >= 0 ? ' checked' : '') + '> ' + Format.escape(o2) + '</label>';
                }).join('');
                box.querySelectorAll('input').forEach(function (inp) {
                    inp.addEventListener('change', function () {
                        if (inp.checked) {
                            if (isMultiNow(sub)) {
                                if (picked.indexOf(inp.value) < 0) picked.push(inp.value);
                            } else {
                                picked = [inp.value]; /* 单选: 仅保留一项 */
                                box.querySelectorAll('input').forEach(function (x) { x.checked = x === inp; });
                            }
                        } else {
                            picked = picked.filter(function (x) { return x !== inp.value; });
                        }
                    });
                });
            };
            sub.querySelector('#prop-opts').addEventListener('input', renderPick);
            sub.querySelector('#prop-dict').addEventListener('change', renderPick);
            sub.querySelectorAll('input[name="opt-src"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    const dict = srcNow(sub) === 'dict';
                    sub.querySelector('#prop-dict-row').style.display = dict ? '' : 'none';
                    sub.querySelector('#prop-opts-row').style.display = dict ? 'none' : '';
                    renderPick();
                });
            });
            sub.querySelectorAll('input[name="sel-type"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    if (!isMultiNow(sub) && picked.length > 1) picked = picked.slice(0, 1);
                    box.querySelectorAll('input').forEach(function (x) { x.checked = picked.indexOf(x.value) >= 0; });
                });
            });
            renderPick();
        },
        read: function (sub) {
            const dict = srcNow(sub) === 'dict';
            const lines = linesNow(sub);
            const multi = isMultiNow(sub);
            let dp = picked.filter(function (x) { return lines.indexOf(x) >= 0; });
            if (!multi) dp = dp.slice(0, 1);
            return { dict: dict ? sub.querySelector('#prop-dict').value : '', opts: dict ? [] : lines, multi: multi, defPick: dp };
        }
    };
}

/** 元素属性对话框 (通用): 名称/内容/默认内容/默认占位符/是否必选 + 类型专属(日期/下拉选项)
 *  元素名称 = 系统识别用, 只读展示 (由元素内容/标签/键自动推导, 保证与模板结构一致);
 *  仅「自动计算项」的显示名称可改 (如 BMI) */
function emr_dsnShowProps(item, p, o) {
    const TYPE_NAME = { fx: '固定文字', slot: '填写字段', ref: '字段引用', dyn: '动态项' };
    const parts = [];
    const dtBlk = (item.t === 'slot' && item.dt) ? emrPropDtBlock(item) : null;
    const optsBlk = (item.t === 'slot' && (Array.isArray(item.opts) || item.dict)) ? emrPropOptsBlock(item) : null;
    const calcBlk = (item.t === 'dyn' && item.calc) ? emrCalcBlock(item, false) : null;

    if (item.t === 'dyn' && item.calc) {
        /* 自动计算项: 显示名称可自由命名 (如 BMI), 不影响元素键/引用 */
        parts.push('<div class="form-group"><label class="form-label">显示名称（可自由命名，如 BMI）</label>'
            + '<input id="prop-name" class="form-control" value="' + Format.escape(item.name || '') + '" placeholder="如：BMI"></div>');
    } else {
        parts.push('<div class="form-group"><label class="form-label">元素名称（系统识别用，不可修改）</label>'
            + '<p class="emr-tpl-hint emr-prop-static">' + Format.escape(emr_dsnItemName(item)) + ' · ' + (TYPE_NAME[item.t] || '元素') + '</p></div>');
    }
    if (item.t === 'fx') {
        parts.push('<div class="form-group"><label class="form-label">元素内容（固定显示、日常不可删除）</label>'
            + '<input id="prop-fx-text" class="form-control" value="' + Format.escape(item.text || '') + '"></div>');
    }
    if (item.t === 'slot') {
        parts.push('<div class="form-group"><label class="form-label">占位描述（未填写时显示的灰色提示，打印不可见）</label>'
            + '<input id="prop-ph" class="form-control" value="' + Format.escape(item.ph || '') + '" placeholder="如：请输入 / 请选择日期和时间"></div>');
        /* 下拉/复选等可供选择的项目: 默认值改为在下方选项里勾选 (不再使用「默认内容」预填) */
        if (!Array.isArray(item.opts) && !item.dict) {
            parts.push('<div class="form-group"><label class="form-label">默认内容（新建文书时自动预填，可打印、可修改）</label>'
                + '<input id="prop-def" class="form-control" value="' + Format.escape(item.def || '') + '" placeholder="可留空"></div>');
        }
        parts.push('<div class="form-group"><label class="emr-prop-radio"><input type="checkbox" id="prop-req"' + (item.req ? ' checked' : '') + '> 是否必选（未完成时红色下划线标记，且无法签名）</label></div>');
        if (dtBlk) parts.push(dtBlk.html);
        if (optsBlk) parts.push(optsBlk.html);
    }
    if (item.t === 'ref') {
        parts.push('<div class="form-group"><label class="form-label">默认占位符（取不到值时显示，打印不可见）</label>'
            + '<input id="prop-fb" class="form-control" value="' + Format.escape(item.fb || '＿＿') + '"></div>');
    }
    if (item.t === 'dyn') {
        if (calcBlk) {
            parts.push(calcBlk.html); /* 自动计算项: 公式编辑 + 字段代码字典 */
        } else {
            parts.push('<div class="form-group"><label class="form-label">说明</label><p class="emr-tpl-hint">动态项内容由系统自动填充（' + Format.escape(emr_dynHint(item.key)) + '），书写时不可手动编辑。</p></div>');
        }
    }
    const sub = Dom.modal('元素属性 · ' + (TYPE_NAME[item.t] || '元素'), parts.join(''), [
        { text: '确定', type: 'primary', onclick: function () {
            const nameEl = sub.querySelector('#prop-name'); /* 仅自动计算项可改显示名 */
            if (nameEl) {
                const name = (nameEl.value || '').trim();
                if (name) item.name = name; else delete item.name;
            }
            if (item.t === 'fx') {
                const tv = (sub.querySelector('#prop-fx-text').value || '').trim();
                if (!tv) { Dom.toast('元素内容不能为空', 'error'); return false; }
                item.text = tv;
            }
            if (item.t === 'slot') {
                const ph = (sub.querySelector('#prop-ph').value || '').trim();
                if (ph) item.ph = ph; else delete item.ph;
                const defEl = sub.querySelector('#prop-def');
                if (defEl) {
                    const def = (defEl.value || '').trim();
                    if (def) item.def = def; else delete item.def;
                }
                if (sub.querySelector('#prop-req').checked) item.req = true; else delete item.req;
                if (dtBlk) item.dt = dtBlk.read(sub);
                if (optsBlk) {
                    const r0 = optsBlk.read(sub);
                    if (r0.dict) { item.dict = r0.dict; delete item.opts; }
                    else { delete item.dict; item.opts = r0.opts; }
                    if (r0.multi) item.multi = true; else delete item.multi;
                    if (r0.defPick.length) item.defPick = r0.defPick; else delete item.defPick;
                }
            }
            if (item.t === 'ref') item.fb = (sub.querySelector('#prop-fb').value || '').trim() || '＿＿';
            if (calcBlk) {
                const rc = calcBlk.read(sub);
                if (!rc) return false;
                item.calc = { expr: rc.expr, dec: rc.dec, fmt: rc.fmt };
            }
            if (_emrEditor) _emrEditor.refresh();
            Dom.toast('属性已更新', 'success');
        } },
        { text: '取消', type: 'default' }
    ]);
    if (dtBlk) dtBlk.bind(sub);
    if (optsBlk) optsBlk.bind(sub);
    if (calcBlk) calcBlk.bind(sub);
}
</script>
