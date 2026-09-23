<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>检验/检查管理</h2>
    <button class="btn" onclick="lab_lab_exam_refresh()">刷新</button>
</div>

<div class="card">
    <div class="card-header">检验单</div>
    <div style="overflow-x:auto">
    <table class="table">
        <thead><tr><th>患者</th><th>床号</th><th>项目</th><th>标本</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="lab-table-body"></tbody>
    </table>
    </div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header">检查单</div>
    <div style="overflow-x:auto">
    <table class="table">
        <thead><tr><th>患者</th><th>床号</th><th>项目</th><th>部位</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="exam-table-body"></tbody>
    </table>
    </div>
</div>

<script>
async function lab_lab_exam_init() {
    document.getElementById('main-app').style.display = 'block';
    const sidebar = await fetch('/js/shared/sidebar_lab.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    await loadLabs();
    await loadExams();
}

async function loadLabs() {
    const result = await AjaxLoader.api('lab/list', {silent:true});
    const tbody = document.getElementById('lab-table-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(l => {
        let badge = '';
        if (l.status === 'pending') badge = 'badge-pending';
        else if (l.status === 'collected') badge = 'badge-verified';
        else if (l.status === 'reported') badge = 'badge-completed';
        
        const isCritical = l.is_critical ? ' <span class="badge badge-critical">危急</span>' : '';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(l.patient_name||'')}</td>
            <td>${Format.escape(l.bed_no||'')}</td>
            <td>${Format.escape(l.exam_name)}</td>
            <td>${Format.escape(l.specimen_type||'')}</td>
            <td><span class="badge ${badge}">${Format.escape(l.status)}</span>${isCritical}</td>
            <td>
                ${l.status === 'pending' ? `<button class="btn btn-small" onclick="lab_collect(${l.id})">采集</button>` : ''}
                ${l.status === 'collected' ? `<button class="btn btn-small btn-success" onclick="lab_result(${l.id})">录结果</button>` : ''}
                ${l.status === 'reported' ? `<button class="btn btn-small" onclick="lab_detail(${l.id})">详情</button>` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function loadExams() {
    const result = await AjaxLoader.api('exam/list', {silent:true});
    const tbody = document.getElementById('exam-table-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(e => {
        let badge = '';
        if (e.status === 'pending') badge = 'badge-pending';
        else if (e.status === 'reported') badge = 'badge-completed';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(e.patient_name||'')}</td>
            <td>${Format.escape(e.bed_no||'')}</td>
            <td>${Format.escape(e.exam_type)}</td>
            <td>${Format.escape(e.body_part||'')}</td>
            <td><span class="badge ${badge}">${Format.escape(e.status)}</span></td>
            <td>
                ${e.status === 'pending' ? `<button class="btn btn-small btn-success" onclick="exam_report(${e.id})">写报告</button>` : ''}
                ${e.status === 'reported' ? `<button class="btn btn-small" onclick="exam_detail(${e.id})">详情</button>` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function lab_collect(id) {
    await AjaxLoader.api('lab/collect', {method:'POST',data:{id}});
    loadLabs();
}

async function lab_result(id) {
    const detail = await AjaxLoader.api('lab/detail?id=' + id, {silent:true});
    const content = `
        <form id="lab-result-form">
            <input type="hidden" name="id" value="${id}">
            <div class="form-group"><label>患者：${Format.escape(detail.data.patient_name||'')}</label></div>
            <div class="form-group"><label>标本类型：<input type="text" name="specimen_type" class="form-control" value="${Format.escape(detail.data.specimen_type||'')}"></label></div>
            <table class="table">
                <thead><tr><th>项目</th><th>结果</th><th>单位</th><th>参考范围</th><th>标记</th></tr></thead>
                <tbody id="lab-result-items">
        ${detail.data.items.map(item => `
            <tr data-id="${item.id}">
                <td>${Format.escape(item.item_name)}</td>
                <td><input type="text" class="result-input" data-item="${item.id}" value="${Format.escape(item.result||'')}"></td>
                <td><input type="text" class="unit-input" data-item="${item.id}" value="${Format.escape(item.unit||'')}"></td>
                <td><input type="text" class="ref-input" data-item="${item.id}" value="${Format.escape(item.reference_range||'')}"></td>
                <td>
                    <select class="flag-input" data-item="${item.id}">
                        <option value="">正常</option>
                        <option value="H" ${item.flag==='H'?'selected':''}>高</option>
                        <option value="L" ${item.flag==='L'?'selected':''}>低</option>
                        <option value="critical" ${item.flag==='critical'?'selected':''}>危急</option>
                    </select>
                </td>
            </tr>
        `).join('')}
            </tbody>
            </table>
            <div class="form-group">
                <label><input type="checkbox" name="is_critical" value="1" ${detail.data.is_critical ? 'checked' : ''}> 危急值</label>
            </div>
        </form>
    `;
    Dom.modal('录入检验结果', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('lab-result-form');
            const items = [];
            detail.data.items.forEach(item => {
                items.push({
                    id: item.id,
                    result: form.querySelector('.result-input[data-item="' + item.id + '"]').value,
                    unit: form.querySelector('.unit-input[data-item="' + item.id + '"]').value,
                    reference_range: form.querySelector('.ref-input[data-item="' + item.id + '"]').value,
                    flag: form.querySelector('.flag-input[data-item="' + item.id + '"]').value
                });
            });
            const isCritical = form.querySelector('[name="is_critical"]').checked ? 1 : 0;
            await AjaxLoader.api('lab/result', {method:'POST',data:{id:id, is_critical: isCritical, items: items}});
            loadLabs();
        }},
        {text:'取消',type:'default'}
    ]);
}

async function lab_detail(id) {
    const detail = await AjaxLoader.api('lab/detail?id=' + id, {silent:true});
    let html = '<h3>' + Format.escape(detail.data.exam_name) + ' - ' + Format.escape(detail.data.patient_name||'') + '</h3>';
    html += '<table class="table"><thead><tr><th>项目</th><th>结果</th><th>单位</th><th>参考范围</th><th>标记</th></tr></thead><tbody>';
    detail.data.items.forEach(item => {
        html += '<tr><td>' + Format.escape(item.item_name) + '</td>';
        html += '<td>' + Format.escape(item.result||'') + '</td>';
        html += '<td>' + Format.escape(item.unit||'') + '</td>';
        html += '<td>' + Format.escape(item.reference_range||'') + '</td>';
        html += '<td><span class="' + Format.labFlagClass(item.flag) + '">' + Format.labFlag(item.flag) + '</span></td></tr>';
    });
    html += '</tbody></table>';
    Dom.modal('检验详情', html);
}

async function exam_report(id) {
    const detail = await AjaxLoader.api('exam/detail?id=' + id, {silent:true});
    const content = `
        <form id="exam-report-form">
            <input type="hidden" name="id" value="${id}">
            <div class="form-group"><label>患者：${Format.escape(detail.data.patient_name||'')}</label></div>
            ${Dom.formField({name:'findings',label:'所见',type:'textarea',rows:5}, detail.data.findings||'')}
            ${Dom.formField({name:'conclusion',label:'结论',type:'textarea',rows:3}, detail.data.conclusion||'')}
            ${Dom.formField({name:'impression',label:'印象',type:'textarea',rows:3}, detail.data.impression||'')}
        </form>
    `;
    Dom.modal('检查报告', content, [
        {text:'发布',type:'primary',onclick:async function(){
            const form = document.getElementById('exam-report-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            await AjaxLoader.api('exam/report', {method:'POST',data});
            loadExams();
        }},
        {text:'取消',type:'default'}
    ]);
}

async function exam_detail(id) {
    const detail = await AjaxLoader.api('exam/detail?id=' + id, {silent:true});
    const content = `
        <h3>${Format.escape(detail.data.exam_type)} - ${Format.escape(detail.data.patient_name||'')}</h3>
        <p><strong>所见：</strong>${Format.escape(detail.data.findings||'')}</p>
        <p><strong>结论：</strong>${Format.escape(detail.data.conclusion||'')}</p>
        <p><strong>印象：</strong>${Format.escape(detail.data.impression||'')}</p>
    `;
    Dom.modal('检查详情', content);
}

function lab_lab_exam_refresh() { loadLabs(); loadExams(); }
function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
