// public/js/emr_editor/emr_nursing_link.js - 电子病历生命体征自动关联 (护理记录单数据源)
// slot 元素带 link: {source:'nursing_notes', field:'temperature'} 时,
// 渲染/保存时以「当前文书内容之前最新」的护理记录取数 (预填护士可能提前录入)
// 缓存按患者: {patientId: rows}  rows 按 record_time 升序
(function () {
  'use strict';
  const NL = (window.EmrNursingLink = {});
  NL._cache = {};

  /* 拉取患者护理记录 (缓存) */
  NL.load = function (patientId) {
    const pid = String(patientId || '');
    if (!pid) return Promise.resolve([]);
    if (NL._cache[pid]) return Promise.resolve(NL._cache[pid]);
    return AjaxLoader.api('nurse/nursing-link?patient_id=' + pid, { silent: true }).then(function (r) {
      const rows = r.data || [];
      NL._cache[pid] = rows;
      return rows;
    }).catch(function () { NL._cache[pid] = []; return NL._cache[pid]; });
  };

  /* 取「截止时间之前」的最新一条值 (timeCutoff 省略取全部; 返回格式化字符串或空串) */
  NL.valueAt = function (rows, field, timeCutoff) {
    if (!rows || !rows.length) return '';
    let best = null;
    for (const r of rows) {
      if (!r || r[field] === null || r[field] === undefined || r[field] === '') continue;
      if (timeCutoff && r.record_time > timeCutoff) continue; /* 当前文书内容之前 */
      best = r;
    }
    if (!best) return '';
    return String(best[field]);
  };

  /* 根据 slot key 推断关联字段 */
  NL.fieldOf = function (slotKey) {
    const map = {
      vital_temp_val: 'temperature', vital_pulse_val: 'pulse', vital_resp_val: 'respiratory_rate',
      vital_bp_val: 'bp', vital_oxygen_val: 'oxygen_saturation', vital_spo2_val: 'oxygen_saturation',
      vital_height_val: 'height', vital_weight_val: 'weight',
      vital_sugar_val: 'blood_glucose', vital_glucose_val: 'blood_glucose'
    };
    return map[slotKey] || '';
  };

  /* 血压字段特殊: bp_systolic/bp_diastolic 组合 */
  NL.bpValue = function (rows, timeCutoff) {
    const s = NL.valueAt(rows, 'bp_systolic', timeCutoff);
    const d = NL.valueAt(rows, 'bp_diastolic', timeCutoff);
    return (s || d) ? ((s || '') + '/' + (d || '')) : '';
  };

  /* 取 slot 的自动关联值: rows + slotKey + timeCutoff → 字符串 */
  NL.slotValue = function (rows, slotKey, timeCutoff) {
    const f = NL.fieldOf(slotKey);
    if (!f) return '';
    if (f === 'bp') return NL.bpValue(rows, timeCutoff);
    return NL.valueAt(rows, f, timeCutoff);
  };

  /* 从 doc_json 提取该文书最早时间槽 (作为关联截止) — 简化: 取文书 updated_at */
  NL.cutoffOf = function (doc) {
    return (doc && doc.updated_at) ? String(doc.updated_at) : '';
  };

  /* 由患者ID异步取最终值 (供编辑器填充) */
  NL.valueFor = function (patientId, slotKey, doc) {
    return NL.load(patientId).then(function (rows) {
      return NL.slotValue(rows, slotKey, NL.cutoffOf(doc));
    });
  };
})();
