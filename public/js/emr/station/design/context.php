<script>
/** 设计模式右击表格: 浮动菜单 (添加行/列二级菜单、删除行/列二级菜单、表格属性、删除表格) */
function emr_dsnTableContext(tblId, ri, ci, x, y) {
    const items = [
        { a: 'add', label: '添加', sub: [
            { a: 'add-row-up', label: '向上添加行' }, { a: 'add-row-down', label: '向下添加行' },
            { a: 'add-col-left', label: '向左添加列' }, { a: 'add-col-right', label: '向右添加列' }
        ] },
        { a: 'delmenu', label: '删除', sub: [
            { a: 'del-row', label: '删除当前行', danger: true }, { a: 'del-col', label: '删除当前列', danger: true }
        ] },
        { a: 'props', label: '表格属性…' },
        { a: 'tbl-del', label: '删除表格', danger: true }
    ];
    EmrUI.floatMenu(items, x, y, function (a) {
        const withTbl = function (fn) {
            if (!_emrEditor) return;
            const tbl = EmrOps.findBlockById(_emrEditor.getDoc().body, tblId);
            if (!tbl || tbl.t !== 'tbl') { Dom.toast('表格不存在', 'error'); return; }
            fn(tbl);
            _emrEditor.refresh();
        };
        if (a === 'add-row-up') return withTbl(function (t) { EmrOps.tableInsertRow(t, ri, true); });
        if (a === 'add-row-down') return withTbl(function (t) { EmrOps.tableInsertRow(t, ri, false); });
        if (a === 'add-col-left') return withTbl(function (t) { EmrOps.tableInsertCol(t, ci, true); });
        if (a === 'add-col-right') return withTbl(function (t) { EmrOps.tableInsertCol(t, ci, false); });
        if (a === 'del-row') return withTbl(function (t) { EmrOps.tableDelRowAt(t, ri); });
        if (a === 'del-col') return withTbl(function (t) { EmrOps.tableDelColAt(t, ci); });
        if (a === 'props') { emr_dsnTableProps(tblId); return; }
        if (a === 'tbl-del') {
            Dom.confirm('删除表格', '确认从模板中删除整个表格？', function () {
                    if (!_emrEditor) return;
                    EmrOps.removeBlockById(_emrEditor.getDoc().body, tblId);
                    _emrEditor.refresh();
                }, { danger: true, okText: '删除' });
        }
    });
}

/** 表格属性: 框线样式(默认/加粗/无) + 行数列数增减 + 删除表格 */
function emr_dsnTableProps(tblId) {
    const tbl = EmrOps.findBlockById(_emrEditor.getDoc().body, tblId);
    if (!tbl || tbl.t !== 'tbl') { Dom.toast('表格不存在', 'error'); return; }
    const curBd = (tbl.tblPr && tbl.tblPr.bd === 0) ? 'none' : (tbl.tblPr && tbl.tblPr.bd === 2) ? 'bold' : 'default';
    const bdOpts = [['default', '默认细线'], ['bold', '加粗框线'], ['none', '无框线']].map(function (m) {
        return '<label class="emr-prop-radio"><input type="radio" name="tbl-bd" value="' + m[0] + '"' + (curBd === m[0] ? ' checked' : '') + '> ' + m[1] + '</label>';
    }).join('');
    const sub = Dom.modal('表格属性',
        '<div class="form-group"><label class="form-label">框线样式</label><div class="emr-prop-radios">' + bdOpts + '</div></div>'
        + '<div class="form-group emr-tblp-rows"><label class="form-label">行数：<b id="tblp-rows">' + tbl.rows.length + '</b></label>'
        + '<button type="button" class="btn btn-small" id="tblp-addrow">添加行</button>'
        + '<button type="button" class="btn btn-small" id="tblp-delrow">删除行</button></div>'
        + '<div class="form-group emr-tblp-rows"><label class="form-label">列数：<b id="tblp-cols">' + (tbl.grid || []).length + '</b></label>'
        + '<button type="button" class="btn btn-small" id="tblp-addcol">添加列</button>'
        + '<button type="button" class="btn btn-small" id="tblp-delcol">删除列</button></div>',
        [
            { text: '确定', type: 'primary', onclick: function () {
                const sel = sub.querySelector('input[name="tbl-bd"]:checked');
                const v = sel ? sel.value : 'default';
                tbl.tblPr = tbl.tblPr || {};
                if (v === 'none') tbl.tblPr.bd = 0;
                else if (v === 'bold') tbl.tblPr.bd = 2;
                else delete tbl.tblPr.bd;
                if (!Object.keys(tbl.tblPr).length) delete tbl.tblPr;
                _emrEditor.refresh();
                Dom.toast('表格属性已更新', 'success');
            } },
            { text: '删除表格', type: 'danger', onclick: function () {
                EmrOps.removeBlockById(_emrEditor.getDoc().body, tblId);
                _emrEditor.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    const upd = function () {
        sub.querySelector('#tblp-rows').textContent = tbl.rows.length;
        sub.querySelector('#tblp-cols').textContent = (tbl.grid || []).length;
        _emrEditor.refresh();
    };
    sub.querySelector('#tblp-addrow').addEventListener('click', function () { EmrOps.tableAddRow(tbl); upd(); });
    sub.querySelector('#tblp-delrow').addEventListener('click', function () { EmrOps.tableDelRow(tbl); upd(); });
    sub.querySelector('#tblp-addcol').addEventListener('click', function () { EmrOps.tableAddCol(tbl); upd(); });
    sub.querySelector('#tblp-delcol').addEventListener('click', function () { EmrOps.tableDelCol(tbl); upd(); });
}

/** 设计模式右击元素: 浮动菜单 (属性 / 删除固定元素) */
function emr_dsnItemContext(item, p, o, x, y) {
    const items = [{ a: 'props', label: '元素属性…' }];
    if (EmrDoc.isProtected(item)) items.push({ a: 'del', label: '删除该固定元素', danger: true });
    EmrUI.floatMenu(items, x, y, function (a) {
        if (a === 'props') { emr_dsnShowProps(item, p, o); return; }
        if (a === 'del') {
            Dom.confirm('删除固定元素', '确认从模板中删除该元素？删除后文书结构中不再保留。', function () { if (_emrEditor) _emrEditor.removeItem(p, item); }, { danger: true, okText: '删除' });
        }
    });
}
</script>
