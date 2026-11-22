<script>
/* 医嘱站·临床辅助决策卡 (挂载到 OrderStation 单例) */
Object.assign(OrderStation, {
    fillAssist(p) {
        const nameEl = document.getElementById('os-assist-name');
        const body = document.getElementById('os-assist-body');
        if (!nameEl || !body) return;
        nameEl.textContent = p.name || '';
        let html = '';
        if (p.allergy_history) {
            html += '<div class="os-assist-warn">' + svgIcon('common/status/warning', 14) + ' 过敏史：' + Format.escape(p.allergy_history) + '</div>';
        }
        if (p.is_critical == 1) {
            html += '<div class="os-assist-warn">' + svgIcon('common/status/warning', 14) + ' 危重患者，开嘱请谨慎</div>';
        }
        html += '<div class="os-assist-line"><b>诊断</b>' + Format.escape(p.admission_diagnosis || '-') + '</div>';
        html += '<div class="os-assist-line"><b>VTE</b>Caprini ' + Format.escape(p.vte_caprini || '未评') + ' · 出血 ' + Format.escape(p.vte_bleeding || '未评') + '</div>';
        html += '<div class="os-assist-line"><b>费用</b>¥ ' + Number(p.total_fee || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' · ' + Format.escape(p.fee_type || '') + '</div>';
        html += '<div class="os-assist-line os-assist-tip">开嘱时请注意药物相互作用与医保限制</div>';
        body.innerHTML = html;
        if (sessionStorage.getItem('os_assist_hidden_' + p.id) !== '1' && (p.allergy_history || p.is_critical == 1)) {
            document.getElementById('os-assist').style.display = 'block';
        }
    },

    toggleAssist() {
        const el = document.getElementById('os-assist');
        if (!el) return;
        const show = el.style.display === 'none';
        el.style.display = show ? 'block' : 'none';
        if (!show && this.patientId) sessionStorage.setItem('os_assist_hidden_' + this.patientId, '1');
        else if (this.patientId) sessionStorage.removeItem('os_assist_hidden_' + this.patientId);
    },
});
</script>
