<script>
/* ================= 引用面板 (状态栏 ↑): 病历/检验/检查/医嘱/既往/内容 → 写回 ================= */
var _emrRefCaret = null;   // 打开面板时的光标位置 (写回定位用)
var _emrRefTab = 'doc';
var _emrRefCache = {};     // 文书 id → 纯文本缓存
var _emrRefSeq = 0;       // 引用面板 Tab 竞态令牌

function emr_refToggle() {
    const panel = document.getElementById('emr-ref-panel');
    if (panel.style.display !== 'none') { panel.style.display = 'none'; return; }
    if (_emrDesign) { Dom.toast('模板设计中不可使用引用写回', 'info'); return; }
    if (!_emrEditor || _emrEditor.readonly) { Dom.toast('请先打开可编辑的文书', 'info'); return; }
    _emrRefCaret = _emrEditor.getCaretPoint(); /* 记录光标模型位置 (含跟踪兜底); 写回时精确恢复 */
    panel.style.display = 'flex';
    emr_refSwitch(_emrRefTab);
}

function emr_refSwitch(tab) {
    _emrRefTab = tab;
    _emrRefSeq++;
    const mySeq = _emrRefSeq;
    document.querySelectorAll('.emr-refp-tab').forEach(function (t) { t.classList.toggle('active', t.dataset.tab === tab); });
    document.getElementById('emr-refp-left').innerHTML = '<div class="emr-refp-empty">加载中…</div>';
    document.getElementById('emr-refp-right').innerHTML = '<div class="emr-refp-empty">从左侧选择一条记录，右侧显示内容；可全选或选中部分文字后点击「写回病历」</div>';
    const loader = { doc: emr_refLoadDocs, past: emr_refLoadDocs, lab: emr_refLoadLab, exam: emr_refLoadExam, order: emr_refLoadOrders, content: emr_refLoadContent }[tab];
    if (loader) loader(mySeq);
}

/** 引用源通用加载: 拉取列表 → mapper 映射为 {name, time, load} → 渲染左栏 */
async function emr_refLoadSource(url, mapper, seq) {
    const r = await AjaxLoader.api(url, { silent: true });
    if (seq != null && seq !== _emrRefSeq) return; /* 竞态: 已切换 Tab, 丢弃旧响应 */
    emr_refListRender((r.data || []).map(mapper));
}

/** 左侧列表渲染: items = [{name, time, load()}] */
function emr_refListRender(items) {
    const left = document.getElementById('emr-refp-left');
    if (!items.length) {
        left.innerHTML = '<div class="emr-refp-empty">' + (_emrRefTab === 'past' ? '暂无既往住院病历' : '暂无可引用记录') + '</div>';
        return;
    }
    left.innerHTML = items.map(function (x, i) {
        return '<div class="emr-refp-item" data-i="' + i + '"><div class="emr-refp-name">' + Format.escape(x.name) + '</div><div class="emr-refp-time">' + Format.escape(x.time || '') + '</div></div>';
    }).join('');
    left.querySelectorAll('.emr-refp-item').forEach(function (el) {
        el.addEventListener('click', async function () {
            left.querySelectorAll('.emr-refp-item').forEach(function (x) { x.classList.remove('active'); });
            el.classList.add('active');
            const x = items[+el.dataset.i];
            const right = document.getElementById('emr-refp-right');
            right.innerHTML = '<div class="emr-refp-empty">加载中…</div>';
            try { right.textContent = await x.load(); } catch (e) { right.innerHTML = '<div class="emr-refp-empty">加载失败</div>'; }
        });
    });
}

/** 文书 doc_json → 纯文本 (含表格行) */
function emr_refDocText(doc) {
    const blockLines = function (b) {
        if (b.t === 'p') {
            let s = '';
            for (const it of (b.runs || [])) {
                if (it.t === 'text') s += it.text;
                else if (it.t === 'fx') s += it.text;
                else if (it.t === 'dyn' || it.t === 'ref') s += (it.text || '');
                else if (it.t === 'tab') s += '\t';
            }
            return [s];
        }
        if (b.t === 'tbl') {
            const out = [];
            for (const r of (b.rows || [])) {
                const cells = (r.cells || []).map(function (c) {
                    let t = '';
                    (c.blocks || []).forEach(function (cb) { t += blockLines(cb).join(' '); });
                    return t.trim();
                });
                out.push(cells.join(' | '));
            }
            return out;
        }
        return [];
    };
    const lines = [];
    (doc.body || []).forEach(function (b) { lines.push.apply(lines, blockLines(b)); });
    return lines.join('\n').replace(/\n{3,}/g, '\n\n').trim();
}

async function emr_refLoadDocs(seq) {
    const all = (await AjaxLoader.api('emr/document-list?patient_id=' + _emrPatient.id, { silent: true })).data || [];
    if (seq != null && seq !== _emrRefSeq) return;
    const docs = _emrRefTab === 'past'
        ? all.filter(function (d) { return d.admission_no && _emrPatient.admission_no && d.admission_no !== _emrPatient.admission_no; })
        : all;
    emr_refListRender(docs.map(function (d) {
        return {
            name: (d.title || d.template_name || '未命名文书') + (d.status === 'signed' ? '（已签名）' : ''),
            time: emrStamp(d.updated_at || d.created_at || ''),
            load: async function () {
                if (_emrRefCache[d.id] != null) return _emrRefCache[d.id];
                const dr = await AjaxLoader.api('emr/document-get?id=' + d.id, { silent: true });
                const txt = emr_refDocText((dr.data && dr.data.doc_json) || {});
                _emrRefCache[d.id] = txt;
                return txt || '（空文书）';
            }
        };
    }));
}

async function emr_refLoadLab(seq) {
    await emr_refLoadSource('lab/list?patient_id=' + _emrPatient.id, function (x) {
        return {
            name: x.exam_name || '检验报告',
            time: emrStamp(x.reported_at || x.created_at || ''),
            load: async function () {
                const dr = await AjaxLoader.api('lab/detail?id=' + x.id, { silent: true });
                const d = dr.data || {};
                const lines = (d.items || []).map(function (it) {
                    return it.item_name + '：' + (it.result || '') + (it.unit ? ' ' + it.unit : '') + (it.reference_range ? '（参考 ' + it.reference_range + '）' : '') + (it.flag ? ' ' + it.flag : '');
                });
                return '【' + (d.exam_name || '') + '】' + (d.reported_at ? ' 报告时间 ' + d.reported_at : '') + '\n' + (lines.join('\n') || '（暂无结果明细）');
            }
        };
    }, seq);
}

async function emr_refLoadExam(seq) {
    await emr_refLoadSource('exam/list?patient_id=' + _emrPatient.id, function (x) {
        return {
            name: (x.exam_type || '检查') + (x.body_part ? '·' + x.body_part : ''),
            time: emrStamp(x.reported_at || x.created_at || ''),
            load: async function () {
                return '【' + (x.exam_type || '') + (x.body_part ? '（' + x.body_part + '）' : '') + '】' + (x.reported_at ? ' 报告时间 ' + x.reported_at : '')
                    + (x.findings ? '\n检查所见：' + x.findings : '')
                    + (x.conclusion ? '\n检查结论：' + x.conclusion : '')
                    + (x.impression ? '\n印象：' + x.impression : '')
                    + (!x.findings && !x.conclusion && !x.impression ? '\n（暂无报告内容）' : '');
            }
        };
    }, seq);
}

async function emr_refLoadOrders(seq) {
    await emr_refLoadSource('orders/list?patient_id=' + _emrPatient.id, function (x) {
        return {
            name: (x.content || '医嘱') + (x.dosage ? ' ' + x.dosage : '') + (x.frequency ? ' ' + x.frequency : ''),
            time: emrStamp(x.start_date || x.created_at || '') + (x.status ? ' · ' + x.status : ''),
            load: async function () {
                return (x.order_period === 'temp' ? '临时医嘱' : '长期医嘱') + '：' + (x.content || '')
                    + (x.dosage ? '\n剂量：' + x.dosage + (x.dosage_unit || '') : '')
                    + (x.frequency ? '\n频次：' + x.frequency : '')
                    + (x.usage_name ? '\n用法：' + x.usage_name : '')
                    + (x.notes ? '\n备注：' + x.notes : '')
                    + (x.start_date ? '\n开始：' + x.start_date : '');
            }
        };
    }, seq);
}

async function emr_refLoadContent(seq) {
    await emr_refLoadSource('emr/content-list', function (x) {
        return {
            name: (x.category_name ? '[' + x.category_name + '] ' : '') + x.name,
            time: emrStamp(x.updated_at || ''),
            load: async function () { return x.content || '（空内容）'; }
        };
    }, seq);
}

/** 写回病历: 右侧选中部分文字则写所选, 否则写全部; 按行分段写入光标处 */
function emr_refWriteBack() {
    const right = document.getElementById('emr-refp-right');
    let text = '';
    const sel = window.getSelection();
    if (sel && sel.rangeCount && right.contains(sel.anchorNode)) {
        const t = String(sel.toString() || '').trim();
        if (t) text = t;
    }
    if (!text) text = String(right.innerText || '').trim();
    if (!text) { Dom.toast('右侧暂无内容可写回', 'error'); return; }
    if (!_emrEditor) { Dom.toast('编辑器未就绪', 'error'); return; }
    /* 必须定位到文书内有效光标处才能写回 (禁止写入无设计区域/文末兜底) */
    if (!_emrEditor.focusCaret(_emrRefCaret)) {
        Dom.toast('请先在文书中点击要插入的位置，再使用引用写回', 'info');
        return;
    }
    const lines = text.replace(/\r/g, '').split('\n');
    lines.forEach(function (ln, i) {
        if (i > 0) _emrEditor.enter();
        if (ln) _emrEditor.insertText(ln);
    });
    Dom.toast('已写回病历', 'success');
}

</script>
