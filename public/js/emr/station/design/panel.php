<script>
/** 插入字段展开列表: 分组字段目录 + 搜索 + 自定义字段入口; 点击即插入光标处 */
function emr_dsnToggleFields() {
    const head = document.getElementById('dsn-sec-field');
    const box = document.getElementById('dsn-fields');
    const open = head.classList.toggle('open');
    box.style.display = open ? '' : 'none';
    if (!open) return;
    if (!box.dataset.built) { box.dataset.built = '1'; emr_dsnRenderFieldList(''); }
    const inp = box.querySelector('.emr-dsn-search');
    if (inp) inp.focus();
}
function emr_dsnRenderFieldList(q) {
    const box = document.getElementById('dsn-fields');
    const kw = (q || '').trim();
    let html = '<input class="emr-dsn-search form-control" placeholder="搜索字段…" value="' + Format.escape(kw) + '">';
    EMR_FIELD_CATALOG.forEach(function (grp) {
        const items = grp.items.filter(function (x) { return !kw || x[2].indexOf(kw) >= 0 || x[1].indexOf(kw) >= 0; });
        if (!items.length) return;
        html += '<div class="emr-dsn-group">' + Format.escape(grp.g) + '</div>';
        html += items.map(function (x) {
            return '<div class="emr-dsn-field" data-t="' + x[0] + '" data-k="' + x[1] + '">' + Format.escape(x[2]) + '</div>';
        }).join('');
    });
    html += '<div class="emr-dsn-field emr-dsn-custom" id="dsn-field-custom">＋ 自定义字段（标签＋输入槽）</div>';
    box.innerHTML = html;
    const inp = box.querySelector('.emr-dsn-search');
    inp.addEventListener('input', function () { emr_dsnRenderFieldList(inp.value); });
    box.querySelectorAll('.emr-dsn-field[data-k]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (!_emrEditor) return;
            const t = el.dataset.t, k = el.dataset.k;
            if (t === 'dyn') _emrEditor.insertItem({ t: 'dyn', key: k, text: '' });
            else _emrEditor.insertRef(k, '＿＿');
        });
    });
    const cu = box.querySelector('#dsn-field-custom');
    if (cu) cu.addEventListener('click', function () { emr_dsnInsertField(false); });
}

/* 固定元素目录: 设计期插入的原子元素 (不可日常删除, 右击可改属性/删除) */
var EMR_FIXED_ELEMENTS = [
    { id: 'date', label: '日期组件', hint: '默认日期+时间, 右击可改类型与格式' },
    { id: 'text', label: '固定文字', hint: '不可删除的描述文字（如 主诉：现病史：等标签）' },
    { id: 'sign', label: '签名元素', hint: '医师签名行（签名时自动填入姓名）' },
    { id: 'hr', label: '分隔线', hint: '在当前段落后插入一条横向分隔线（区分上下内容，如体格检查上方横线）' }
];

/** 固定元素展开列表 */
function emr_dsnToggleFixed() {
    const head = document.getElementById('dsn-sec-fixed');
    const box = document.getElementById('dsn-fixed');
    const open = head.classList.toggle('open');
    box.style.display = open ? '' : 'none';
    if (!open || box.dataset.built) return;
    box.dataset.built = '1';
    box.innerHTML = EMR_FIXED_ELEMENTS.map(function (x) {
        return '<div class="emr-dsn-field" data-fixed="' + x.id + '" title="' + Format.escape(x.hint) + '">' + Format.escape(x.label) + '</div>';
    }).join('');
    box.querySelectorAll('.emr-dsn-field').forEach(function (el) {
        el.addEventListener('click', function () {
            if (!_emrEditor) return;
            if (el.dataset.fixed === 'date') {
                emr_dsnInsertDate();
            } else if (el.dataset.fixed === 'text') {
                if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
                const sub = Dom.modal('插入固定文字',
                    '<div class="form-group"><label class="form-label">文字内容（书写时不可删除，如 主诉：）</label>'
                    + '<input id="dsn-fixedtext" class="form-control" placeholder="如：主诉："></div>',
                    [
                        { text: '插入', type: 'primary', onclick: function () {
                            if (!_emrEditor) return;
                            const t = (document.getElementById('dsn-fixedtext').value || '').trim();
                            if (!t) { Dom.toast('请输入文字内容', 'error'); return; }
                            _emrEditor.insertItem({ t: 'fx', key: 't' + Date.now().toString(36) + Math.floor(Math.random() * 90 + 10), text: t });
                        } },
                        { text: '取消', type: 'default' }
                    ]);
                const inp = sub.querySelector('#dsn-fixedtext');
                inp.focus();
            } else if (el.dataset.fixed === 'sign') {
                _emrEditor.insertSignLine();
            } else if (el.dataset.fixed === 'hr') {
                _emrEditor.insertDivider();
            }
        });
    });
}

/** 选项文本解析: 换行/顿号/逗号/分号分隔 (创建与属性配置一致) */
function emrParseOpts(v) {
    return String(v || '').split(/[\n、，,;；]/).map(function (x) { return x.trim(); }).filter(Boolean);
}

/** 插入日期组件 (类型/格式/占位描述; 占位描述即未选择时的显示文字) */
function emr_dsnInsertDate() {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const blk = emrPropDtBlock({ t: 'slot', dt: { mode: 'datetime', fmt: '' } });
    const content = blk.html
        + '<div class="form-group"><label class="form-label">占位描述（未选择时显示，打印不可见）</label>'
        + '<input id="dsn-dt-ph" class="form-control" value="请选择日期和时间" placeholder="如：请选择日期和时间（可留空）"></div>';
    const sub = Dom.modal('插入日期组件', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const dt = blk.read(sub);
            const ph = (sub.querySelector('#dsn-dt-ph').value || '').trim();
            _emrEditor.insertItem({
                t: 'slot',
                key: 'd' + Date.now().toString(36) + Math.floor(Math.random() * 90 + 10),
                ph: ph,
                dt: dt
            });
        } },
        { text: '取消', type: 'default' }
    ]);
    blk.bind(sub);
}

/** 表格快速选择器: 6×6 移入高亮 n×m, 点击插入 */
function emr_dsnBuildTablePicker() {
    const box = document.getElementById('emr-tblpick');
    if (box.dataset.built) return;
    box.dataset.built = '1';
    let cells = '';
    for (let r = 1; r <= 6; r++) for (let c = 1; c <= 6; c++) {
        cells += '<span data-r="' + r + '" data-c="' + c + '"></span>';
    }
    box.innerHTML = cells;
    const label = document.getElementById('emr-tblpick-label');
    const all = Array.from(box.querySelectorAll('span'));
    const clear = function () { all.forEach(function (x) { x.classList.remove('on'); }); };
    all.forEach(function (x) {
        x.addEventListener('mouseover', function () {
            const R = +x.dataset.r, C = +x.dataset.c;
            all.forEach(function (y) { y.classList.toggle('on', +y.dataset.r <= R && +y.dataset.c <= C); });
            label.textContent = R + ' 行 × ' + C + ' 列';
        });
        x.addEventListener('click', function () {
            if (!_emrEditor) return;
            _emrEditor.insertTable(+x.dataset.r, +x.dataset.c);
            label.textContent = '已插入 ' + x.dataset.r + ' 行 × ' + x.dataset.c + ' 列（拖动框线可调列宽/行高）';
        });
    });
    box.addEventListener('mouseleave', function () { clear(); if (label.textContent.indexOf('已插入') < 0) label.textContent = '移入选择行列（拖动框线可调列宽/行高）'; });
}
</script>
