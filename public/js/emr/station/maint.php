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
</script>
