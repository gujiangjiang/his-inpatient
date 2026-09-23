<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>门诊病历查询</h2>
</div>

<div class="card">
    <div class="form-group">
        <input type="text" id="outpatient-keyword" class="form-control" placeholder="请输入姓名/就诊号">
        <button class="btn btn-primary" onclick="outpatient_search()">搜索</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        搜索结果
        <span id="outpatient-source" style="float:right"></span>
    </div>
    <table class="table">
        <thead><tr><th>就诊号</th><th>患者</th><th>日期</th><th>医生</th><th>操作</th></tr></thead>
        <tbody id="outpatient-results-body"></tbody>
    </table>
</div>

<script>
async function outpatient_search_init() {
    document.getElementById('main-app').style.display = 'block';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
}

async function outpatient_search() {
    const keyword = document.getElementById('outpatient-keyword').value;
    if (!keyword) { Dom.toast('请输入搜索关键词','error'); return; }
    
    const result = await AjaxLoader.api('outpatient/search?keyword=' + encodeURIComponent(keyword), {silent:true});
    
    const sourceEl = document.getElementById('outpatient-source');
    sourceEl.innerHTML = Components.dataBadge(result.source || 'mock');
    
    const tbody = document.getElementById('outpatient-results-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(r.visit_id)}</td>
            <td>${Format.escape(r.name)}</td>
            <td>${Format.escape(r.date)}</td>
            <td>${Format.escape(r.doctor)}</td>
            <td><button class="btn btn-small" onclick="outpatient_detail('${Format.escape(r.visit_id)}')">详情</button></td>
        `;
        tbody.appendChild(tr);
    });
}

async function outpatient_detail(visitId) {
    // 调用 mock data 获取详情
    const result = await AjaxLoader.api('outpatient/mock-data', {silent:true});
    const record = result.data.find(r => r.visit_id === visitId) || result.data[0];
    
    if (!record) { Dom.toast('未找到病历','error'); return; }
    
    const content = `
        <h3>${Format.escape(record.name)} (就诊号: ${Format.escape(record.visit_id)})</h3>
        <div class="card"><div class="card-header">主诉</div><div style="padding:10px">${Format.escape(record.chief_complaint||'')}</div></div>
        <div class="card"><div class="card-header">现病史</div><div style="padding:10px">${Format.escape(record.present_illness||'')}</div></div>
        <div class="card"><div class="card-header">查体</div><div style="padding:10px">${Format.escape(record.physical_exam||'')}</div></div>
        <div class="card"><div class="card-header">处方</div>
            <table class="table">
                ${record.prescriptions.map(p => `<tr><td>${Format.escape(p.name)}</td><td>${Format.escape(p.dosage)}</td><td>${Format.escape(p.frequency)}</td></tr>`).join('')}
            </table>
        </div>
        <div class="card"><div class="card-header">检验</div>
            <table class="table">
                ${record.lab_results.map(l => `<tr><td>${Format.escape(l.name)}</td><td>${Format.escape(l.result)}</td><td>${Format.escape(l.unit)}</td><td>${Format.escape(l.reference)}</td></tr>`).join('')}
            </table>
        </div>
    `;
    Dom.modal('门诊病历详情', content);
}

function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
