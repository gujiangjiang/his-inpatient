<script>
/* ================= 打开文书 (统一字处理编辑器) ================= */
var _emrOpenSeq = 0;       // 打开文书竞态令牌 (连点/并发响应只认最新)
async function emr_openDocument(docId) {
    clearTimeout(_emrAutoSaveTimer); /* 切换文书: 清理上一文书挂起的自动保存 */
    const mySeq = ++_emrOpenSeq;
    let r;
    try {
        r = await AjaxLoader.api('emr/document-get?id=' + docId, { silent: true });
    } catch (e) {
        Dom.toast('打不开文书: ' + (e && e.message ? e.message : '未知错误'), 'error');
        return;
    }
    if (mySeq !== _emrOpenSeq) return; /* 已被更新的打开请求取代 */
    const doc = r.data;
    _emrCurrentDoc = doc;
    document.getElementById('emr-ref-panel').style.display = 'none'; /* 切换文书: 收起引用面板 */
    _emrRefCache = {};
    if (_emrEditor) { _emrEditor.destroy(); _emrEditor = null; }

    document.getElementById('emr-editor-empty').style.display = 'none';
    document.getElementById('emr-editor').style.display = 'flex';

    // 兜底: 旧数据未转换时 (迁移 038 正常已处理), 客户端转换后按字处理打开, 保存时落库
    if (!doc.doc_json) {
        doc.doc_json = EmrDoc.fromSections(doc.sections || [], doc.title);
        doc.doc_meta = doc.doc_meta || {};
        if (!doc.doc_meta.guard) doc.doc_meta.guard = EmrDoc.collectGuard(doc.doc_json.body);
    }
    // 旧模型文档 (v<2): 槽位后文本视为值 (val 标记), 保证已填值可改选/参与计算, 保存时自动升级
    if (doc.doc_json && Number(doc.doc_json.v || 0) < EmrDoc.MODEL_V) {
        EmrDoc.legacyValMigrate(doc.doc_json.body || []);
        doc.doc_json.v = EmrDoc.MODEL_V;
    }
    // 已知日期字段 (入院日期/记录日期等) 补齐 dt → 作为日期元素展示与配置 (右击属性可见)
    if (doc.doc_json) EmrDoc.upgradeDateSlots(doc.doc_json.body || []);
    await openWordDoc(doc, doc.status === 'signed');
}
</script>
