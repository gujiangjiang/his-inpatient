<?php header("Content-Type: text/html; charset=utf-8"); ?>
<script>
/** 病历维护申请: 已签名病历发起 编辑/删除 申请, 管理员审核通过后解锁 (批准编辑→退回草稿; 批准删除→删除文书) */
function emr_maintDialog(doc) {
    if (doc.maint_pending_id) {
        Dom.toast('该病历已有待审核的维护申请，请等待管理员处理', 'info');
        return;
    }
    const content =
        '<p class="emr-tpl-hint">已签名病历受法律锁定保护：需提交维护申请并经管理员审核通过后方可处理。</p>'
        + '<div class="form-group"><label class="form-label">维护类型</label><div class="emr-prop-radios">'
        + '<label class="emr-prop-radio"><input type="radio" name="maint-act" value="edit" checked> 编辑（批准后退回草稿，修改后需重新签名）</label>'
        + '<label class="emr-prop-radio"><input type="radio" name="maint-act" value="delete"> 删除（批准后连同历史版本一并删除）</label>'
        + '</div></div>'
        + '<div class="form-group"><label class="form-label">维护原因（必填，管理员审核参考）</label>'
        + '<textarea id="maint-reason" class="form-control" rows="3" placeholder="如：血压数值录入错误，需更正后重新签名"></textarea></div>';
    const sub = Dom.modal('病历维护申请 · ' + Format.escape(doc.title || doc.template_name || '未命名文书'), content, [
        { text: '发送申请', type: 'primary', onclick: async function () {
            const act = (sub.querySelector('input[name="maint-act"]:checked') || {}).value || 'edit';
            const reason = (sub.querySelector('#maint-reason').value || '').trim();
            if (!reason) { Dom.toast('请填写维护原因', 'error'); return false; }
            await AjaxLoader.api('emr/maintenance-request', { method: 'POST', data: { doc_id: doc.id, action: act, reason: reason } });
            Dom.toast('维护申请已发送，等待管理员审核', 'success');
            await refreshDocs();
        } },
        { text: '取消', type: 'default' }
    ]);
    sub.querySelector('#maint-reason').focus();
}

/* ================= 维护申请列表 (左下角「维护」入口: 本人申请 + 状态 + 一键打开编辑) ================= */
var _EMR_MAINT_TAG = {
    pending: ['待审核', 'pend'],
    approved: ['已批准', 'dept'],
    rejected: ['已驳回', 'rej']
};

/** 打开本人病历维护申请列表 */
async function emr_maintListDialog() {
    const r = await AjaxLoader.api('emr/maintenance-list', { silent: true });
    const items = r.data || [];
    let body;
    if (!items.length) {
        body = '<div class="emr-tpl-empty">暂无维护申请：已签名病历可在左侧右击「病历维护…」发起申请</div>';
    } else {
        body = items.map(function (it) {
            const st = _EMR_MAINT_TAG[it.status] || [it.status, ''];
            const actTxt = it.action === 'delete' ? '删除' : '编辑';
            const meta = [
                it.patient_name ? it.patient_name + (it.admission_no ? '（' + it.admission_no + '）' : '') : '（患者不明）',
                (it.requested_at || '').slice(0, 16),
                it.reviewer_name ? '审核：' + it.reviewer_name : ''
            ].filter(Boolean).join(' · ');
            let op = '';
            if (it.status === 'approved' && it.action === 'edit') {
                if (it.doc_status === 'draft' && it.patient_id) {
                    op = '<button type="button" class="btn btn-small btn-primary" data-open="' + it.doc_id + '" data-patient="' + it.patient_id + '">打开编辑</button>';
                } else if (it.doc_status === 'signed') {
                    op = '<span class="rv-meta">已重新签名</span>';
                } else {
                    op = '<span class="rv-meta">文书已删除</span>';
                }
            } else if (it.status === 'approved' && it.action === 'delete') {
                op = '<span class="rv-meta">已删除</span>';
            } else if (it.status === 'rejected' && it.reject_reason) {
                op = '<span class="rv-meta" title="' + Format.escape(it.reject_reason) + '">驳回原因已注明</span>';
            }
            return '<div class="rv-row">'
                + '<div class="rv-name"><b>' + Format.escape(it.doc_title || '未命名文书') + '</b>'
                + '<span class="rv-prev">' + Format.escape(meta) + ' · 原因：' + Format.escape(it.reason || '') + '</span></div>'
                + '<span class="emr-pub-tag ' + st[1] + '">' + st[0] + '·' + actTxt + '</span>'
                + '<span class="rv-act">' + op + '</span></div>';
        }).join('');
    }
    const sub = Dom.modal('病历维护申请', '<div class="emr-maint-list">' + body + '</div>', [{ text: '关闭', type: 'default' }]);
    sub.querySelectorAll('button[data-open]').forEach(function (b) {
        b.addEventListener('click', function () { emr_maintOpen(b.dataset.patient, b.dataset.open, sub); });
    });
}

/** 一键打开已批准的病历: 切换患者上下文 → 刷新病历站 → 打开文书编辑 (修订留痕) */
async function emr_maintOpen(patientId, docId, overlay) {
    const r = await AjaxLoader.api('patients/get?id=' + patientId, { silent: true });
    if (!r.data) { Dom.toast('患者不存在或无权访问', 'error'); return; }
    PatientContext.set(r.data);
    _emrPatient = PatientContext.current();
    if (overlay) overlay.remove();
    await emr_station_init();
    await emr_openDocument(docId);
}
</script>
