// public/js/admin/drugs/drugform.js - 药品表单规格编辑器
// 依赖：Dom.modal / Format.escape；页面片段 admin_drugs_form 绑定 openSpecEditor()

/** 打开规格结构化编辑器（二级模态框） */
function openSpecEditor() {
    var dose = document.getElementById('dform-dosed').value || '';
    var dunit = document.getElementById('dform-doseunit').value || '';
    var pkt = document.getElementById('dform-packqty').value || '1';
    var punit = document.getElementById('dform-packunit').value || '';
    Dom.modal('规格编辑', '' +
        '<div class="form-grid spec-editor-grid">' +
        '<div class="form-group"><label class="form-label">单剂量值</label>' +
        '<input class="form-control" type="number" step="any" min="0" id="se_dose" value="' + Format.escape(dose) + '"></div>' +
        '<div class="form-group"><label class="form-label">单位</label>' +
        '<input class="form-control" id="se_dose_unit" value="' + Format.escape(dunit) + '" placeholder="如 g"></div>' +
        '<div class="form-group"><label class="form-label">包装数量</label>' +
        '<input class="form-control" type="number" min="1" id="se_pack_qty" value="' + Format.escape(pkt) + '"></div>' +
        '<div class="form-group"><label class="form-label">包装单位</label>' +
        '<input class="form-control" id="se_pack_unit" value="' + Format.escape(punit) + '" placeholder="如 粒"></div>' +
        '</div>' +
        '<div class="fs-12 text-muted">示例：0.5g×24粒 → 单剂量 0.5、单位 g、包装数量 24、单位 粒。库存/警戒统一以最小单位（粒）存储。</div>', [
        { text: '保存规格', type: 'primary', onclick: function () { seSaveSpec(); } },
        { text: '取消', type: 'default' }
    ]);
}

/** 保存规格：回写主表单隐藏字段与展示串 */
function seSaveSpec() {
    var dose = parseFloat(document.getElementById('se_dose').value);
    if (!(dose > 0)) { Dom.toast('请填写单剂量值', 'error'); return; }
    var dunit = document.getElementById('se_dose_unit').value.trim();
    var pkt = Math.max(1, parseInt(document.getElementById('se_pack_qty').value, 10) || 1);
    var punit = document.getElementById('se_pack_unit').value.trim();
    document.getElementById('dform-dosed').value = dose;
    document.getElementById('dform-doseunit').value = dunit;
    document.getElementById('dform-packqty').value = pkt;
    document.getElementById('dform-packunit').value = punit;
    document.getElementById('dform-spec').value = dose + dunit + (punit !== '' ? '×' + pkt + punit : '');
    syncSplitBox();
    syncWarn();
}

/** 拆零面板联动：每包装=1 禁拆零；拆零单价=包装价÷包装数 */
function syncSplitBox() {
    var chk = document.getElementById('dform-split');
    var box = document.getElementById('dform-split-box');
    if (!chk || !box) return;
    var pkt = parseInt((document.getElementById('dform-packqty') || {}).value, 10) || 1;
    var grp = chk.closest ? chk.closest('.form-group') : null;
    if (grp) grp.style.display = pkt <= 1 ? 'none' : '';
    chk.disabled = pkt <= 1;
    if (pkt <= 1) chk.checked = false;
    var on = !!chk.checked;
    box.style.display = (on && pkt > 1) ? 'block' : 'none';
    if (!on) return;
    var packUnit = (document.getElementById('dform-unit') || {}).value || '';
    var minUnit = (document.getElementById('dform-packunit') || {}).value || '';
    var price = parseFloat((document.getElementById('dform-price') || {}).value) || 0;
    var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
    set('dform-sp-packunit', packUnit || '盒');
    set('dform-sp-minunit', minUnit || '支');
    set('dform-sp-packqty', pkt);
    set('dform-sp-packprice', '¥' + price.toFixed(2));
    set('dform-sp-minprice', '¥' + (pkt > 1 ? (price / pkt).toFixed(4) : '0').replace(/\.?0+$/, ''));
}

/** 警戒库存换算：包装数量×包装单位数 → 最小单位绝对阈值 */
function syncWarn() {
    var warn = document.getElementById('dform-warn');
    var packqty = document.getElementById('dform-packqty');
    var hint = document.getElementById('dform-warn-hint');
    if (!warn || !packqty) return;
    var v = Math.max(0, parseInt(warn.value, 10) || 0);
    var ps = Math.max(1, parseInt(packqty.value, 10) || 1);
    var min = v * ps;
    warn.dataset.min = min;
    if (hint) {
        hint.textContent = ps > 1
            ? '库存 ≤ 警戒线时低库存报警。当前换算：' + v + ' ' + (document.getElementById('dform-unit').value || '盒') + ' = ' + min + ' ' + (document.getElementById('dform-packunit').value || '支') + '（最小单位绝对阈值）。'
            : '库存 ≤ 警戒线时低库存报警。该药品每包装数量为 1，直接按包装单位录入。';
    }
}

/** 表单绑定（admin_drugs_form 内 modal:loaded 后调用） */
function bindDrugSpecEditor() {
    ['dform-dosed', 'dform-doseunit', 'dform-packqty', 'dform-packunit', 'dform-price', 'dform-unit', 'dform-split'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', syncSplitBox);
            el.addEventListener('input', syncSplitBox);
        }
    });
    var warn = document.getElementById('dform-warn');
    var packqty = document.getElementById('dform-packqty');
    if (warn && packqty) {
        warn.addEventListener('input', syncWarn);
        warn.addEventListener('change', syncWarn);
        packqty.addEventListener('change', syncWarn);
    }
    syncSplitBox();
    syncWarn();
}