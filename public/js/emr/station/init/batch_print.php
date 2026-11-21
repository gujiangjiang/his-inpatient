<script>
/* ================= 病历文书批量打印 ================= */
/**
 * 批量打印选择器: 仿模板管理左右两栏 (左=文书类别, 右=该类文书清单+复选框)。
 * 无 本科室/全院 区分; 切换左侧分类后右侧默认全不勾选, 支持一键全选。
 * 打印: 病程类重排打印(buildBatch), 其余文书原样打印(content_html 快照, 含抬头)。
 */
async function emr_batchPrintDialog() {
    const patientId = _emrPatient ? _emrPatient.id : null;
    if (!patientId) { Dom.toast('请先选择患者', 'error'); return; }
    let cats = _emrCategories.slice();
    let docs = _emrDocs.slice();
    let activeCat = 'all';
    /* 加载数据失败时回退到当前内存数据 (保证离线/缓存下仍可用) */
    try {
        const [cr, dr] = await Promise.all([
            AjaxLoader.api('emr/category-list', { silent: true }),
            AjaxLoader.api('emr/document-list?patient_id=' + patientId, { silent: true })
        ]);
        if (cr.data) cats = cr.data;
        if (dr.data) docs = dr.data;
    } catch (e) { /* 静默回退 */ }

    const checked = new Set();
    const catDocs = (key) => key === 'all' ? docs : docs.filter(d => String(d.category_id) === String(key));

    const content = '<div class="emr-tpm">'
        + '<div class="emr-tpm-body">'
        + '<div class="emr-tpm-cats" id="emr-bp-cats"></div>'
        + '<div class="emr-tpm-main" id="emr-bp-main"></div>'
        + '</div>'
        + '<div class="emr-tpl-foot"><button type="button" class="btn btn-small" id="emr-bp-checkall">一键全选</button>'
        + '<span class="emr-tb-spacer"></span>'
        + '<span class="fs-12 text-muted" id="emr-bp-count">已选 0 份</span>'
        + '<button type="button" class="btn btn-small btn-primary" id="emr-bp-print">打印</button></div>'
        + '</div>';
    const overlay = Dom.modal('病历文书打印', content, []);
    overlay.querySelector('.modal').classList.add('modal-lg');
    const catsEl = overlay.querySelector('#emr-bp-cats');
    const mainEl = overlay.querySelector('#emr-bp-main');

    function renderCats() {
        const list = [{ key: 'all', name: '全部' }].concat(cats.map(c => ({ key: String(c.id), name: c.name })));
        if (docs.some(d => !d.category_id)) list.push({ key: 'none', name: '未分类' });
        if (!list.some(x => x.key === activeCat)) activeCat = 'all';
        catsEl.innerHTML = list.map(x => {
            const n = x.key === 'none' ? docs.filter(d => !d.category_id).length : catDocs(x.key).length;
            return '<div class="emr-tpm-cat' + (x.key === activeCat ? ' active' : '') + '" data-cat="' + x.key + '">'
                + '<span class="emr-tpm-cat-name">' + Format.escape(x.name) + '</span>'
                + '<span class="emr-tpm-cat-count">' + n + '</span></div>';
        }).join('');
        catsEl.querySelectorAll('.emr-tpm-cat').forEach(el => {
            el.addEventListener('click', () => { activeCat = el.dataset.cat; renderCats(); renderMain(); });
        });
    }

    function renderMain() {
        const list = catDocs(activeCat);
        const catName = activeCat === 'all' ? '全部' : activeCat === 'none' ? '未分类'
            : (((cats.find(c => String(c.id) === activeCat)) || {}).name || '');
        /* 切换分类后右侧默认全不勾选 */
        checked.clear();
        const head = '<div class="emr-tpm-mhead"><span class="emr-tpm-mtitle">' + Format.escape(catName) + '</span>'
            + '<span class="emr-tpm-mcount">' + list.length + ' 份文书</span>'
            + '<span class="emr-tpm-batch" style="margin-left:auto;font-size:12px;color:#909399">勾选后点击「打印」</span></div>';
        let body;
        if (!list.length) {
            body = '<div class="emr-tpl-empty">该分类暂无文书</div>';
        } else {
            body = list.map(d => {
                const checkedAttr = checked.has(d.id) ? ' checked' : '';
                const signedTag = d.status === 'signed'
                    ? '<span class="emr-pub-tag dept">已签名</span>'
                    : '<span class="emr-pub-tag pend">草稿</span>';
                const cnt = (d.print_count == null ? 0 : d.print_count);
                return '<label class="emr-tpm-row emr-bp-row" data-id="' + d.id + '">'
                    + '<input type="checkbox" class="emr-bp-chk" value="' + d.id + '"' + checkedAttr + '>'
                    + '<span class="emr-tpm-rname" title="' + Format.escape(d.title || d.template_name || '未命名文书') + '">' + Format.escape(d.title || d.template_name || '未命名文书') + '</span>'
                    + signedTag
                    + '<span class="emr-bp-meta">' + Format.escape(Format.stamp(d.updated_at || d.created_at || '')) + '</span>'
                    + '<span class="emr-bp-count" title="该文书累计打印次数">打印 ' + cnt + ' 次</span>'
                    + '</label>';
            }).join('');
        }
        mainEl.innerHTML = head + body;
        mainEl.querySelectorAll('.emr-bp-chk').forEach(chk => {
            chk.addEventListener('change', function () {
                const id = Number(this.value);
                if (this.checked) checked.add(id); else checked.delete(id);
                updateCount();
            });
        });
    }

    function updateCount() {
        const el = overlay.querySelector('#emr-bp-count');
        if (el) el.textContent = '已选 ' + checked.size + ' 份';
    }

    overlay.querySelector('#emr-bp-checkall').addEventListener('click', function () {
        const list = catDocs(activeCat);
        /* 一键全选: 当前分类全部选中; 再次点击若全选则全取消 */
        const allChecked = list.length > 0 && list.every(d => checked.has(d.id));
        mainEl.querySelectorAll('.emr-bp-chk').forEach(chk => {
            chk.checked = !allChecked;
            if (!allChecked) checked.add(Number(chk.value)); else checked.delete(Number(chk.value));
        });
        updateCount();
    });

    overlay.querySelector('#emr-bp-print').addEventListener('click', async function () {
        const ids = Array.from(checked);
        if (!ids.length) { Dom.toast('请勾选要打印的文书', 'error'); return; }
        overlay.querySelector('#emr-bp-print').disabled = true;
        try {
            const r = await AjaxLoader.api('emr/document-batch-print', { method: 'POST', data: { patient_id: patientId, ids: ids } });
            const items = r.data || [];
            if (!items.length) { Dom.toast('未取到可打印文书', 'error'); return; }
            const info = buildPatientInfo();
            /* 合并组装：病程类重排 + 非病程类原样快照，统一进一个打印文档（一次弹窗） */
            const batch = items.map(x => {
                if (x.print_mode === 'repaginate') {
                    return { doc: x.doc_json || {}, stamp: x.updated_at || x.created_at || '', signHtml: emr_batch_sign(x), repaginate: true };
                }
                /* 其余文书: 优先原样打印签名快照 content_html (含抬头, 不改内容);
                   草稿/无快照时回退 buildHTML 重新生成 (内容一致, 不重排) */
                let raw = (x.content_html && x.content_html.indexOf('<!DOCTYPE') !== -1) ? x.content_html : '';
                if (!raw && x.doc_json) {
                    try { raw = EmrPrint.buildHTML(x.doc_json, info); } catch (e) { /* 回退 */ }
                }
                return { doc: x.doc_json || {}, stamp: x.updated_at || x.created_at || '', signHtml: emr_batch_sign(x), repaginate: false, rawHtml: raw };
            }).filter(x => x.doc && (x.repaginate || x.rawHtml));
            if (!batch.length) { Dom.toast('未取到可打印文书', 'error'); return; }
            EmrPrint.printCombined(batch, info);   /* 一次弹窗，一个打印文档，内容分页排列 */
            /* 打印次数已由后端累加: 刷新列表保持显示 */
            await refreshDocs();
        } catch (e) {
            Dom.toast('批量打印失败: ' + (e.message || '未知错误'), 'error');
        } finally {
            overlay.querySelector('#emr-bp-print').disabled = false;
        }
    });

    renderCats();
    renderMain();
}

/** 文书签名串（右下角）：签名医生 + 时间 */
function emr_batch_sign(d) {
    const signer = d.signer_name || '';
    const at = d.signed_at || '';
    if (!signer && !at) return '';
    return Format.escape((signer || '') + (at ? '　' + at : ''));
}
</script>
