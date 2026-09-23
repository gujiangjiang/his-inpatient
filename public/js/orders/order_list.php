<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>医嘱管理</h2>
    <div>
        <button class="btn btn-success" onclick="orders_submit_all()">提交全部草稿</button>
        <button class="btn btn-danger" id="batch-delete-btn" style="display:none" onclick="orders_batch_delete()">批量删除</button>
        <button class="btn" onclick="orders_refresh()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">选择患者</div>
    <div style="padding:15px">
        <select id="orders-patient" class="form-control" style="width:250px" onchange="loadOrders(this.value)">
            <option value="">请选择患者</option>
        </select>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span id="draft-tab" class="active-tab" onclick="showTab('draft')">草稿 (0)</span>
        <span id="pending-tab" onclick="showTab('pending')">待核对 (0)</span>
        <span id="other-tab" onclick="showTab('other')">已核对/执行 (0)</span>
    </div>
    <table class="table">
        <thead>
            <tr><th></th><th>患者</th><th>类型</th><th>内容</th><th>分类</th><th>状态</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody id="orders-table-body"></tbody>
    </table>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header">
        <button class="btn btn-primary" onclick="orders_add_medication()">药品+</button>
        <button class="btn btn-primary" onclick="orders_add_nonmedication()">非药品+</button>
    </div>
    <div id="order-form-container"></div>
</div>

<script>
var _currentPatientId = null;
var _currentPatient = null;
var _allOrders = { draft: [], pending: [], other: [] };
var _selectedOrders = new Set();
var _currentTab = 'draft';

async function orders_order_list_init() {
    document.getElementById('main-app').style.display = 'block';
    document.getElementById('login-container').style.display = 'none';
    const user = Auth.user;
    const sidebar = await fetch('/js/shared/sidebar_' + user.role + '.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    const result = await AjaxLoader.api('patients/list', {silent:true});
    const sel = document.getElementById('orders-patient');
    sel.innerHTML = '<option value="">请选择患者</option>';
    (result.data || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name + ' (' + p.admission_no + ')';
        sel.appendChild(opt);
    });
}

async function loadOrders(patientId) {
    if (!patientId) {
        _currentPatientId = null;
        return;
    }
    _currentPatientId = patientId;
    const result = await AjaxLoader.api('patients/get?id=' + patientId, {silent:true});
    _currentPatient = result.data;
    
    // 加载所有状态的医嘱
    const [draftRes, pendingRes, otherRes] = await Promise.all([
        AjaxLoader.api('orders/list?patient_id=' + patientId + '&status=draft', {silent:true}),
        AjaxLoader.api('orders/list?patient_id=' + patientId + '&status=pending', {silent:true}),
        AjaxLoader.api('orders/list?patient_id=' + patientId + '&status=verified&status=executing', {silent:true})
    ]);
    
    _allOrders.draft = draftRes.data?.pagination?.items || draftRes.data || [];
    _allOrders.pending = pendingRes.data?.pagination?.items || pendingRes.data || [];
    _allOrders.other = otherRes.data?.pagination?.items || otherRes.data || [];
    
    // 合并 verified 和 executing
    const verifiedRes = await AjaxLoader.api('orders/list?patient_id=' + patientId + '&status=verified', {silent:true});
    const executingRes = await AjaxLoader.api('orders/list?patient_id=' + patientId + '&status=executing', {silent:true});
    _allOrders.other = [...(verifiedRes.data || []), ...(executingRes.data || [])];
    
    _selectedOrders.clear();
    renderOrders();
}

function renderOrders() {
    const orders = _allOrders[_currentTab] || [];
    const tbody = document.getElementById('orders-table-body');
    tbody.innerHTML = '';
    
    const showCheckbox = _selectedOrders.size > 0;
    document.getElementById('batch-delete-btn').style.display = showCheckbox ? 'inline-block' : 'none';
    
    // 更新 tab 计数
    document.getElementById('draft-tab').textContent = '草稿 (' + _allOrders.draft.length + ')';
    document.getElementById('pending-tab').textContent = '待核对 (' + _allOrders.pending.length + ')';
    document.getElementById('other-tab').textContent = '已核对/执行 (' + _allOrders.other.length + ')';
    
    if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">暂无医嘱</td></tr>';
        return;
    }
    
    orders.forEach(o => {
        const tr = document.createElement('tr');
        const isDraft = o.status === 'draft';
        const isChecked = _selectedOrders.has(o.id);
        
        // 添加选中样式
        if (isChecked) tr.style.background = '#f0f7ff';
        
        tr.innerHTML = `
            <td>${isDraft ? '<input type="checkbox" onchange="toggleSelect(this,' + o.id + ',"' + o.status + '")' + (isChecked ? 'checked' : '') + '>' : ''}</td>
            <td>
                ${Format.escape(o.content)}
                ${o.category === 'medication' ? '<span class="badge badge-warning">药品</span>' : '<span class="badge badge-default">非药品</span>'}
                ${o.is_auto_generated == 1 ? '<span class="badge badge-info">自动生成</span>' : ''}
            </td>
            <td>
                ${o.sub_orders ? formatSubOrders(o.sub_orders) : ''}
            </td>
            <td>${Format.escape(o.category || '')}</td>
            <td><span class="badge ${Format.orderStatusClass(o.status)}">${Format.orderStatus(o.status)}</span></td>
            <td>${Format.datetime(o.start_date)}</td>
            <td>
                ${isDraft ? `
                    <button class="btn btn-small btn-danger" onclick="deleteDraft(${o.id})">删除</button>
                    <button class="btn btn-small btn-success" onclick="submitOrder(${o.id})">提交</button>
                ` : ''}
                ${!isDraft ? `
                    <button class="btn btn-small" onclick="showOrderActions(${o.id})">操作</button>
                ` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function formatSubOrders(subs) {
    if (!subs || subs.length === 0) return '';
    return subs.map(s => '<div style="padding-left:20px">→ ' + Format.escape(s.content) + ' <span class="badge ' + Format.orderStatusClass(s.status) + '">' + Format.orderStatus(s.status) + '</span></div>').join('');
}

function showTab(tab) {
    _currentTab = tab;
    _selectedOrders.clear();
    renderOrders();
}

function toggleSelect(checkbox, orderId, status) {
    if (status !== 'draft') return;
    if (checkbox.checked) {
        _selectedOrders.add(orderId);
    } else {
        _selectedOrders.delete(orderId);
    }
    renderOrders();
}

async function submitOrder(id) {
    await AjaxLoader.api('orders/submit', {method:'POST',data:{patient_id:_currentPatientId, ids:[id]}});
    loadOrders(_currentPatientId);
}

async function orders_submit_all() {
    const draftIds = (_allOrders.draft || []).map(o => o.id);
    if (!draftIds.length) { Dom.toast('没有草稿医嘱可提交','error'); return; }
    await AjaxLoader.api('orders/submit', {method:'POST',data:{patient_id:_currentPatientId, ids:draftIds}});
    Dom.toast('已提交全部草稿', 'success');
    loadOrders(_currentPatientId);
}

async function deleteDraft(id) {
    await AjaxLoader.api('orders/batch-delete', {method:'POST',data:{ids:[id]}});
    loadOrders(_currentPatientId);
}

async function orders_batch_delete() {
    if (_selectedOrders.size === 0) return;
    await AjaxLoader.api('orders/batch-delete', {method:'POST',data:{ids:Array.from(_selectedOrders)}});
    _selectedOrders.clear();
    loadOrders(_currentPatientId);
}

function showOrderActions(id) {
    const order = (_allOrders.draft || _allOrders.pending || _allOrders.other || []).find(o => o.id == id);
    if (!order) return;
    
    let actions = '';
    actions += '<div style="padding:5px 0"><button class="btn btn-small" onclick="copyOrder(' + id + ')">复制医嘱</button></div>';
    
    // 作废: 仅限已核对(pending/verified/executing)的医嘱
    if (['pending', 'verified', 'executing'].includes(order.status)) {
        actions += '<div style="padding:5px 0"><button class="btn btn-small btn-danger" onclick="invalidateOrder(' + id + ')">作废</button></div>';
    }
    
    Dom.modal('医嘱操作', actions, [
        {text:'关闭',type:'default'}
    ]);
}

async function copyOrder(id) {
    try {
        const result = await AjaxLoader.api('orders/copy', {method:'POST',data:{id}});
        if (result.status === 'success' && result.data.copied) {
            Dom.toast('复制成功 (新的草稿医嘱)', 'success');
            loadOrders(_currentPatientId);
        }
    } catch(e) {
        // 错误已经在 AjaxLoader 中处理
    }
}

async function invalidateOrder(id) {
    Dom.modal('确认作废', '<p>确认作废此医嘱吗？作废后不可恢复。</p>', [
        {text:'确认作废',type:'danger',onclick:async function(){
            await AjaxLoader.api('orders/invalidate', {method:'POST',data:{id}});
            loadOrders(_currentPatientId);
        }},
        {text:'取消',type:'default'}
    ]);
}

function orders_add_medication() {
    showOrderForm('medication');
}

function orders_add_nonmedication() {
    showOrderForm('nonmedication');
}

function showOrderForm(type) {
    let content = '';
    
    if (type === 'medication') {
        content = `
            <div class="form-group">
                <label class="form-label">药品</label>
                <select id="order-med-select" class="form-control">
                    <option value="">请选择药品</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">用量</label>
                <input type="text" id="order-med-dosage" class="form-control" placeholder="如: 1 袋">
            </div>
            <div class="form-group">
                <label class="form-label">频次</label>
                <input type="text" id="order-med-freq" class="form-control" placeholder="如: qd">
            </div>
            <div class="form-group">
                <label class="form-label">医嘱内容</label>
                <input type="text" id="order-med-content" class="form-control" placeholder="如: 5%葡萄糖 250ml">
            </div>
            <input type="hidden" id="order-category" value="medication">
        `;
        // 加载药品列表
        AjaxLoader.api('pharmacy/medication-list', {silent:true}).then(r => {
            const sel = document.getElementById('order-med-select');
            r.data.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.name + ' ' + (m.specification || '');
                opt.textContent = m.name + ' (' + (m.specification || '') + ') 库存:' + m.stock_quantity;
                sel.appendChild(opt);
            });
        });
    } else {
        content = `
            <div class="form-group">
                <label class="form-label">医嘱内容</label>
                <input type="text" id="order-med-content" class="form-control" placeholder="输入医嘱内容">
            </div>
            <input type="hidden" id="order-category" value="">
        `;
    }
    
    Dom.modal('添加医嘱', content, [
        {text:'保存为草稿',type:'primary',onclick:async function(){
            const content = document.getElementById('order-med-content').value;
            const category = document.getElementById('order-category').value;
            if (!content) { Dom.toast('请输入医嘱内容','error'); return; }
            const dosage = document.getElementById('order-med-dosage')?.value || '';
            const frequency = document.getElementById('order-med-freq')?.value || '';
            
            await AjaxLoader.api('orders/save', {method:'POST',data:{
                patient_id: _currentPatientId,
                content: content,
                category: category || null,
                order_type: category === 'medication' ? 'medication' : 'general',
                dosage: dosage,
                frequency: frequency
            }});
            loadOrders(_currentPatientId);
        }},
        {text:'取消',type:'default'}
    ]);
}

function orders_refresh() {
    if (_currentPatientId) loadOrders(_currentPatientId);
}

function navigateTo(module, page) {
    AjaxLoader.loadPage(module, page);
}
</script>
