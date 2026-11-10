// public/js/core/team_modal.js - 三级医师设置 (模态框, 侧边栏入口)
// 住院医师(主管) / 主治医师(上级) / 主任医师 / 诊疗组; 保存至患者指派 (patients/assign)
const TeamModal = {
    doctors: null,

    async open() {
        /* 注意: PatientContext 为全局词法绑定(const), 不是 window 属性, 必须用裸标识符 */
        const p = (typeof PatientContext !== 'undefined') ? PatientContext.current() : null;
        if (!p) { Dom.toast('请先选择患者', 'error'); return; }
        try {
            if (!this.doctors) {
                const r = await AjaxLoader.api('doctors/doctor-list', { silent: true });
                this.doctors = r.data || [];
            }
        } catch (e) { this.doctors = this.doctors || []; }
        const opts = '<option value="">请选择</option>' + this.doctors.map(d =>
            '<option value="' + d.id + '">' + Format.escape(d.name) + '</option>').join('');
        const content = '<div class="team-modal">'
            + '<div class="form-group"><label class="form-label">住院医师（主管医师）</label><select id="team-resident" class="form-control">' + opts + '</select></div>'
            + '<div class="form-group"><label class="form-label">主治医师（上级医师）</label><select id="team-attending" class="form-control">' + opts + '</select></div>'
            + '<div class="form-group"><label class="form-label">主任医师</label><select id="team-director" class="form-control">' + opts + '</select></div>'
            + '<div class="form-group"><label class="form-label">诊疗组</label><input id="team-group" class="form-control" placeholder="如：骨科一组"></div>'
            + '<div class="emr-tpl-foot"><button type="button" class="btn btn-primary" id="team-save">保存</button>'
            + '<button type="button" class="btn" id="team-cancel">取消</button></div>'
            + '</div>';
        const overlay = Dom.modal('三级医师 - ' + Format.escape(p.name || ''), content, []);

        /* 回填当前指派 */
        try {
            const a = await AjaxLoader.api('patients/assignment?patient_id=' + p.id, { silent: true });
            const t = a.data || {};
            overlay.querySelector('#team-resident').value = t.attending_doctor_id || '';
            overlay.querySelector('#team-attending').value = t.senior_doctor_id || '';
            overlay.querySelector('#team-director').value = t.director_doctor_id || '';
            overlay.querySelector('#team-group').value = t.treatment_group || '';
        } catch (e) { /* 无指派信息时留空 */ }
        ['team-resident', 'team-attending', 'team-director'].forEach(function (id) {
            const el = overlay.querySelector('#' + id);
            if (window.SelectPicker) SelectPicker.refresh(el);
        });

        overlay.querySelector('#team-cancel').addEventListener('click', function () { overlay.remove(); });
        overlay.querySelector('#team-save').addEventListener('click', async function () {
            const resident = overlay.querySelector('#team-resident').value;
            if (!resident) { Dom.toast('请选择住院医师（主管）', 'error'); return; }
            try {
                await AjaxLoader.api('patients/assign', { method: 'POST', data: {
                    patient_id: p.id,
                    attending_doctor_id: resident,
                    senior_doctor_id: overlay.querySelector('#team-attending').value,
                    director_doctor_id: overlay.querySelector('#team-director').value,
                    treatment_group: overlay.querySelector('#team-group').value.trim()
                } });
                Dom.toast('三级医师已保存', 'success');
                overlay.remove();
            } catch (e) { /* AjaxLoader 已提示 */ }
        });
    }
};