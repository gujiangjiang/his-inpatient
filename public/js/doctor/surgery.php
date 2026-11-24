<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">手术</h3>
        <p class="page-desc">手术申请与已申请手术管理：提交后生成临时医嘱（在…麻醉下行…（明晨8:00/立即））；<b>择期手术自动将长期医嘱停至明晨8:00</b>；急诊/日间不停医嘱仅通知</p>
    </div>
    <div>
        <button type="button" class="btn" onclick="surgery_showList()">已申请手术</button>
        <button type="button" class="btn" onclick="surgery_refresh()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">手术申请<span class="fs-12 text-muted" id="surgery-patient-info"></span></div>
    <div class="form-grid" style="padding:0 12px 12px">
        <div class="form-group"><label class="form-label">麻醉方式</label>
            <select class="form-control" id="surg-anesthesia" data-spicker="off">
                <option value="">请选择</option>
                <option>全身麻醉</option><option>椎管内麻醉</option><option>硬膜外麻醉</option><option>腰麻</option>
                <option>臂丛麻醉</option><option>神经阻滞麻醉</option><option>局部麻醉</option><option>表面麻醉</option><option>其他</option>
            </select></div>
        <div class="form-group"><label class="form-label">体位</label>
            <select class="form-control" id="surg-position" data-spicker="off">
                <option value="">请选择</option>
                <option>仰卧位</option><option>侧卧位</option><option>俯卧位</option><option>截石位</option><option>坐位</option><option>其他</option>
            </select></div>
        <div class="form-group"><label class="form-label">术前评估地点</label>
            <select class="form-control" id="surg-place" data-spicker="off">
                <option value="">请选择</option>
                <option>手术室</option><option>病房</option><option>门诊手术室</option>
            </select></div>
        <div class="form-group"><label class="form-label">手术位置</label>
            <select class="form-control" id="surg-side" data-spicker="off">
                <option value="">无</option>
                <option>左侧</option><option>右侧</option><option>双侧</option>
            </select></div>
        <div class="form-group"><label class="form-label">手术医生</label>
            <select class="form-control" id="surg-surgeon" data-spicker="off"><option value="">请选择</option></select></div>
        <div class="form-group"><label class="form-label">一助</label>
            <select class="form-control" id="surg-assist1" data-spicker="off"><option value="">无</option></select></div>
        <div class="form-group"><label class="form-label">二助</label>
            <select class="form-control" id="surg-assist2" data-spicker="off"><option value="">无</option></select></div>
        <div class="form-group"><label class="form-label">手术类别</label>
            <select class="form-control" id="surg-category" data-spicker="off">
                <option value="elective" selected>择期（次日8:00，自动停长期医嘱）</option>
                <option value="emergency">急诊（立即）</option>
                <option value="day">日间（次日8:00）</option>
            </select></div>
        <div class="form-group cf-span-2" style="grid-column:span 2"><label class="form-label">手术名称</label>
            <input type="text" class="form-control" id="surg-name" placeholder="如：锁骨骨折切开复位内固定术"></div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">备注</label>
            <input type="text" class="form-control" id="surg-notes" placeholder="如：术前禁食禁饮8小时"></div>
    </div>
    <div style="padding:0 12px 14px"><button type="button" class="btn btn-primary" onclick="surgery_submit()">提交手术申请</button></div>
</div>

<script>
async function doctor_surgery_init() {
    await App.mountShell('doctor');
    surgery_refresh();
}

/** 加载患者信息与科室医生 */
async function surgery_refresh() {
    const p = PatientContext.current();
    const info = document.getElementById('surgery-patient-info');
    if (!p) {
        if (info) info.textContent = ' · 请先选择患者';
        return;
    }
    const age = Format.ageClinical(p.birth_date);
    if (info) info.textContent = ' · ' + p.name + ' ' + Format.gender(p.gender) + ' ' + age + ' · 诊断：' + (p.admission_diagnosis || '-');
    try {
        const r = await AjaxLoader.api('emr/dept-doctors', { silent: true });
        const docs = r.data || [];
        ['surg-surgeon', 'surg-assist1', 'surg-assist2'].forEach(function (id) {
            const sel = document.getElementById(id);
            if (!sel) return;
            const keep = sel.value;
            sel.innerHTML = '<option value="">' + (id === 'surg-surgeon' ? '请选择' : '无') + '</option>'
                + docs.map(d => '<option value="' + d.id + '">' + Format.escape(d.name) + '</option>').join('');
            if (keep) sel.value = keep;
        });
        /* 默认手术医生为本人 */
        const me = Auth.user && Auth.user.id;
        const sSel = document.getElementById('surg-surgeon');
        if (sSel && !sSel.value && me && docs.some(d => String(d.id) === String(me))) sSel.value = String(me);
    } catch (e) { /* ignore */ }
}

function surgery_collectForm() {
    return {
        patient_id: (PatientContext.current() || {}).id,
        category: document.getElementById('surg-category').value,
        anesthesia_type: document.getElementById('surg-anesthesia').value,
        patient_position: document.getElementById('surg-position').value,
        assess_place: document.getElementById('surg-place').value,
        side: document.getElementById('surg-side').value,
        surgeon_id: document.getElementById('surg-surgeon').value,
        assist1_id: document.getElementById('surg-assist1').value,
        assist2_id: document.getElementById('surg-assist2').value,
        surgery_name: (document.getElementById('surg-name').value || '').trim(),
        notes: (document.getElementById('surg-notes').value || '').trim(),
    };
}

async function surgery_submit() {
    const p = PatientContext.current();
    if (!p) { Dom.toast('请先选择患者', 'error'); return; }
    const data = surgery_collectForm();
    if (!data.surgery_name) { Dom.toast('请填写手术名称', 'error'); return; }
    const catLabel = { elective: '择期（次晨8:00）', emergency: '急诊（立即）', day: '日间（次晨8:00）' }[data.category];
    Dom.modal('确认提交手术申请',
        '<p>手术：<b>' + Format.escape(data.surgery_name) + '</b>（' + catLabel + '）</p>'
        + (data.category === 'elective'
            ? '<p class="emr-tpl-hint">择期手术提交后：生成临时医嘱（明晨8:00）并将该患者所有长期医嘱自动停至明晨8:00（待护士确认）。</p>'
            : '<p class="emr-tpl-hint">' + (data.category === 'emergency' ? '急诊' : '日间') + '手术不会自动停止长期医嘱，仅通知病区护士。</p>'),
        [
            { text: '提交', type: 'primary', onclick: async function () {
                const r = await AjaxLoader.api('surgery/apply', { method: 'POST', data: data });
                const extra = r.data.stopped_long ? ('；已自动停止 ' + r.data.stopped_long + ' 条长期医嘱（停至明晨8:00，待护士确认）') : '';
                Dom.toast('手术申请已提交：' + (r.data.content || '') + extra, 'success');
                document.getElementById('surg-name').value = '';
                document.getElementById('surg-notes').value = '';
            } },
            { text: '取消', type: 'default' }
        ]);
}

/** 已申请手术列表 (可取消) */
async function surgery_showList() {
    const p = PatientContext.current();
    if (!p) { Dom.toast('请先选择患者', 'error'); return; }
    const r = await AjaxLoader.api('surgery/list?patient_id=' + p.id + '&include=cancelled', { silent: true });
    const rows = r.data || [];
    const catLabel = { elective: '择期', emergency: '急诊', day: '日间' };
    const body = rows.length ? ('<table class="table fix-head"><thead><tr><th>类别</th><th>手术名称</th><th>手术时间</th><th>麻醉/体位</th><th>术者</th><th>状态</th><th>操作</th></tr></thead><tbody>'
        + rows.map(function (s) {
            const cancelled = s.status === 'cancelled';
            const st = cancelled
                ? '<span class="badge badge-default">已取消</span>'
                : '<span class="badge badge-warning">申请中</span>' + (s.order_status === 'invalid' ? ' <span class="badge badge-default">医嘱已作废</span>' : '');
            const op = cancelled ? '<span class="text-muted fs-12">—</span>'
                : '<button type="button" class="btn btn-small btn-danger" data-cancel="' + s.id + '">取消手术</button>';
            return '<tr><td>' + (catLabel[s.category] || '-') + '</td><td>' + Format.escape(s.surgery_name) + '</td>'
                + '<td>' + Format.escape(String(s.planned_at || s.surgery_date || '').slice(0, 16)) + '</td>'
                + '<td>' + Format.escape(s.anesthesia_type || '-') + (s.patient_position ? ' / ' + Format.escape(s.patient_position) : '') + '</td>'
                + '<td>' + Format.escape(s.surgeon_name || '-') + '</td><td>' + st + '</td><td>' + op + '</td></tr>';
        }).join('') + '</tbody></table>')
        : '<div class="empty-state">暂无手术申请</div>';
    const sub = Dom.modal('已申请手术', body, [{ text: '关闭', type: 'default' }]);
    sub.querySelectorAll('button[data-cancel]').forEach(function (b) {
        b.addEventListener('click', function () {
            const sid = b.dataset.cancel;
            const inp = '<div class="form-group"><label class="form-label">取消原因（可选）</label><input id="surg-cancel-reason" class="form-control" placeholder="如：患者要求暂缓手术"></div>';
            const s2 = Dom.modal('取消手术申请', '<p>取消后该手术关联的临时医嘱将自动作废，确定取消吗？</p>' + inp, [
                { text: '确认取消手术', type: 'danger', onclick: async function () {
                    await AjaxLoader.api('surgery/cancel', { method: 'POST', data: { id: sid, reason: (s2.querySelector('#surg-cancel-reason').value || '').trim() } });
                    Dom.toast('手术申请已取消，关联医嘱已作废', 'success');
                    s2.remove();
                    sub.remove();
                    surgery_showList();
                } },
                { text: '返回', type: 'default' }
            ]);
        });
    });
}
</script>
