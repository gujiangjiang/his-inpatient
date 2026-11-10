// public/js/core/discharge_modal.js - 出院办理 (医嘱系统组成部分: 左侧「临床工作」栏入口)
// 今日出院: 默认 30 分钟后, 不自动停医嘱
// 明日出院: 默认明早 8 点, 自动将所有长期医嘱停止到所选时间 (待护士确认)
// 前后端双重校验: 未核对医嘱 / 未发放处方 拦截; 未出结果的检验检查仅提示不拦截 (开单即已扣费)
const DischargeModal = {
    fmt(d) {
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    },
    parse(v) {
        if (!v) return null;
        const m = String(v).match(/(\d{4})-(\d{1,2})-(\d{1,2})[ T](\d{1,2}):(\d{1,2})/);
        if (!m) return null;
        const d = new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], 0);
        return isNaN(d.getTime()) ? null : d;
    },

    open() {
        const patient = PatientContext.current();
        if (!patient) { Dom.toast('请先选择患者', 'error'); return; }
        const now = new Date();
        const defToday = new Date(now.getTime() + 30 * 60000);
        const defTomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 8, 0, 0);
        const fmt = this.fmt;

        const content = '<div class="emr-dc">'
            + '<div class="emr-dc-modes">'
            + '<label class="emr-dc-mode"><input type="radio" name="emr-dc-mode" value="today" checked> 今日出院 <span class="emr-dc-mode-sub">（默认 30 分钟后；不自动停止医嘱）</span></label>'
            + '<label class="emr-dc-mode"><input type="radio" name="emr-dc-mode" value="tomorrow"> 明日出院 <span class="emr-dc-mode-sub">（自动将长期医嘱停止到所选时间）</span></label>'
            + '</div>'
            + '<div class="emr-dc-dt"><label class="form-label">出院日期时间</label>'
            + '<input id="emr-dc-at" class="form-control" data-dtpicker="datetime" value="' + fmt(defToday) + '"></div>'
            + '<div id="emr-dc-check" class="emr-dc-check">校验中…</div>'
            + '<div class="emr-dc-foot"><button type="button" class="btn btn-primary" id="emr-dc-ok" disabled>确认出院</button>'
            + '<button type="button" class="btn" id="emr-dc-cancel">取消</button></div>'
            + '</div>';
        const overlay = Dom.modal('出院办理 - ' + (patient.name || ''), content, []);
        const dtEl = overlay.querySelector('#emr-dc-at');
        const okBtn = overlay.querySelector('#emr-dc-ok');
        const checkEl = overlay.querySelector('#emr-dc-check');
        if (window.DateTimePicker) DateTimePicker.initInputs(overlay);
        const esc = (s) => (typeof Format !== 'undefined' && Format.escape ? Format.escape(s) : String(s == null ? '' : s));
        let checkOk = false;

        function renderCheck(data) {
            data = data || {};
            const blockers = [];
            const orders = data.orders || [];
            const meds = data.meds || [];
            const infos = data.info || [];
            if (orders.length) blockers.push('未核对/待处理医嘱 ' + orders.length + ' 条：' + orders.slice(0, 3).map(o => esc(o.content)).join('、') + (orders.length > 3 ? ' 等' : ''));
            if (meds.length) blockers.push('未发放（未扣费）处方 ' + meds.length + ' 条：' + meds.slice(0, 3).map(x => esc(x.content)).join('、') + (meds.length > 3 ? ' 等' : ''));
            checkOk = data.ok && !blockers.length;
            let html = '';
            if (checkOk) html += '<div class="emr-dc-ok">校验通过，可以办理出院</div>';
            else html += '<div class="emr-dc-block">暂不可出院：</div><ul class="emr-dc-list">' + blockers.map(g => '<li>' + g + '</li>').join('') + '</ul>';
            if (infos.length) html += '<div class="emr-dc-info">提示（不影响出院）：' + infos.map(esc).join('；') + '</div>';
            checkEl.innerHTML = html;
            updateOk();
        }
        function updateOk() {
            const d = DischargeModal.parse(dtEl.value);
            okBtn.disabled = !(checkOk && d && d.getTime() >= Date.now());
        }
        AjaxLoader.api('emr/discharge-check?patient_id=' + patient.id, { silent: true }).then((resp) => renderCheck(resp.data || {})).catch((err) => {
            console.error('出院校验失败', err);
            checkEl.innerHTML = '<div class="emr-dc-block">校验失败：' + esc(err && err.message || '请稍后重试') + '</div>';
        });
        dtEl.addEventListener('input', updateOk);
        dtEl.addEventListener('change', updateOk);
        overlay.querySelectorAll('input[name="emr-dc-mode"]').forEach(r => {
            r.addEventListener('change', () => {
                dtEl.value = fmt(r.value === 'tomorrow' ? defTomorrow : defToday);
                updateOk();
            });
        });
        overlay.querySelector('#emr-dc-cancel').addEventListener('click', () => overlay.remove());
        okBtn.addEventListener('click', async () => {
            const mode = (overlay.querySelector('input[name="emr-dc-mode"]:checked') || {}).value || 'today';
            const at = (dtEl.value || '').trim();
            const d = DischargeModal.parse(at);
            if (!d || d.getTime() < Date.now()) { Dom.toast('请选择不早于当前时间的出院日期时间', 'error'); return; }
            okBtn.disabled = true;
            try {
                const r = await AjaxLoader.api('emr/discharge', { method: 'POST', data: { patient_id: patient.id, mode: mode, discharge_at: at } });
                const stopped = r.data && r.data.stopped ? ('，已停止 ' + r.data.stopped + ' 条长期医嘱（待护士确认）') : '';
                Dom.toast('已开具「' + (mode === 'today' ? '今日出院' : '明日出院') + '」医嘱' + stopped + '，请护士审核', 'success');
                overlay.remove();
                if (typeof window.refreshDocs === 'function') { try { refreshDocs(); } catch (e) { /* 非病历页忽略 */ } }
            } catch (e) {
                okBtn.disabled = false; /* AjaxLoader 已提示原因 */
            }
        });
    }
};