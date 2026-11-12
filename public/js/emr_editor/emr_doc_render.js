// public/js/emr_editor/emr_doc_render.js - 病历渲染: 模型→DOM (编辑画布与打印共用)
// 段落: <div class="p" data-pid><div class="pc">…</div></div>
// 文本 run: <span data-o data-n>…</span>; 保护项: <span class="emr-fx|emr-slot" data-item data-o data-n contenteditable=false>
// tab/br: <span class="emr-tab">\t</span> / <br data-o data-n>
// 抬头顶部固定: 医院名 + 文书名 + 患者信息栏 (info_fields 与 patientInfo 绑定)
(function () {
  'use strict';
  const M = window.EmrDoc;
  const R = (window.EmrRender = {});
  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  const PX = 96 / 72;
  const pt = (v) => v ? (Math.round(v * PX * 100) / 100) + 'px' : '';

  /* ---------- run 样式 ---------- */
  R.rPrStyle = (rPr) => {
    const r = rPr || {};
    const s = [];
    if (r.b) s.push('font-weight:bold');
    if (r.i) s.push('font-style:italic');
    if (r.u) s.push('text-decoration:underline');
    if (r.strike) s.push('text-decoration:line-through');
    if (r.font) s.push('font-family:' + r.font);
    if (r.sz) s.push('font-size:' + pt(r.sz));
    if (r.color) s.push('color:' + r.color);
    return s.join(';');
  };
  R.pPrStyle = (pPr) => {
    const p = pPr || {};
    const s = [];
    if (p.align) s.push('text-align:' + p.align);
    if (p.ind) s.push('text-indent:' + pt(p.ind));
    if (p.sp) s.push('line-height:' + p.sp);
    /* flex: 'between' = 弹性行: 字段居中且两侧对齐 (体征行等) */
    if (p.flex === 'between') s.push('display:flex', 'justify-content:space-between', 'align-items:baseline', 'gap:10px', 'padding:0 2px');
    if (p.borders) {
      if (p.borders.top) s.push('border-top:1px solid #000');
      if (p.borders.bottom) s.push('border-bottom:1px solid #000');
      if (p.borders.top || p.borders.bottom) s.push('padding:4px 0', 'margin:10px 0 6px');
    }
    return s.join(';');
  };

  /* ---------- 段内 run → HTML ---------- */
  const paraHasTextAfter = (p, idx) => {
    for (let k = idx + 1; k < p.runs.length; k++) {
      const it = p.runs[k];
      if (M.isProtected(it)) return false;
      if (it.t === 'text' && it.text.trim()) return true;
    }
    return false;
  };
  /** dyn 元素的占位文案 (设计模式显示用途, 不引用实际数据) */
  const DYN_PH = {
    vital_bmi_value: 'BMI自动计算', sign_names: '医师签名', initial_diagnosis_value: '初步诊断(点此同步)',
    diag_admission: '入院诊断', diag_discharge: '出院诊断',
    p_name: '姓名', p_gender: '性别', p_age: '年龄',
  };
  const runsToHTML = (p, design) => {
    const out = [];
    let pos = 0;
    let afterSlot = false; /* 槽位后的文本 = 该字段的值: 编辑状态带下划线提示可编辑 */
    for (const it of p.runs) {
      const n = M.ilen(it);
      const o = pos; pos += n;
      if (it.t === 'text') {
        if (it.text) {
          const cls = afterSlot ? ' class="emr-val"' : '';
          const st = it.rPr && Object.keys(it.rPr).length ? ' style="' + R.rPrStyle(it.rPr) + '"' : '';
          out.push('<span' + cls + ' data-o="' + o + '" data-n="' + n + '"' + st + '>' + esc(it.text) + '</span>');
        }
        continue;
      }
      if (M.isProtected(it) && it.t !== 'slot') afterSlot = false; /* fx/dyn/ref 结束值的范围 */
      if (it.t === 'fx') {
        out.push('<span class="emr-fx" data-item="fx" data-key="' + esc(it.key) + '" data-o="' + o + '" data-n="1" contenteditable="false">' + esc(it.text) + '</span>');
        continue;
      }
      if (it.t === 'slot') {
        afterSlot = true;
        const filled = paraHasTextAfter(p, p.runs.indexOf(it));
        const cls = 'emr-slot' + ((Array.isArray(it.opts) && it.opts.length) || it.dt ? ' emr-slot-sel' : '') + (it.dt ? ' emr-slot-dt' : '') + (it.multi ? ' emr-slot-multi' : '') + (it.req ? ' emr-req' : '');
        out.push(filled
          ? '<span class="' + cls + '" data-item="slot" data-key="' + esc(it.key) + '" data-o="' + o + '" data-n="1" contenteditable="false"></span>'
          : '<span class="' + cls + '" data-item="slot" data-key="' + esc(it.key) + '" data-o="' + o + '" data-n="1" contenteditable="false">' + esc(it.ph || '填写') + '</span>');
        continue;
      }
      if (it.t === 'ref') {
        /* 字段引用 (自动调用其他文书的字段值); 设计模式仅显示默认占位符, 不引用 */
        const txt = design ? (it.fb || '＿＿') : (it.text || it.fb || '');
        out.push('<span class="emr-ref' + (design ? ' emr-ref-des' : '') + '" data-item="ref" data-key="' + esc(it.key) + '"'
          + ' data-o="' + o + '" data-n="1" contenteditable="false">' + esc(txt) + '</span>');
        continue;
      }
      if (it.t === 'dyn') {
        const sync = it.key === 'initial_diagnosis_value' || it.key === 'diag_admission' || it.key === 'diag_discharge';
        const syncTitle = it.key === 'diag_discharge' ? '点击同步出院诊断（来源：左侧诊断 → 出院诊断）'
          : it.key === 'diag_admission' ? '点击同步入院诊断（来源：左侧诊断 → 入院诊断）'
          : '点击同步诊断（来源：左侧诊断 → 入院诊断）';
        const txt = design ? ('（' + (DYN_PH[it.key] || it.key) + '）') : it.text;
        out.push('<span class="emr-dyn' + (sync ? ' emr-dyn-sync' : '') + (design ? ' emr-dyn-des' : '') + '" data-item="dyn" data-key="' + esc(it.key) + '"'
          + ' data-o="' + o + '" data-n="1" contenteditable="false"'
          + (sync && !design ? ' title="' + syncTitle + '"' : '') + '>' + esc(txt) + '</span>');
        continue;
      }
      if (it.t === 'tab') {
        out.push('<span class="emr-tab" data-item="tab" data-o="' + o + '" data-n="1">\t</span>');
        continue;
      }
      if (it.t === 'br') {
        out.push('<br data-item="br" data-o="' + o + '" data-n="1">');
        continue;
      }
      /* 未知项: 空占位 */
      out.push('<span class="emr-unknown" data-item="unk" data-o="' + o + '" data-n="1">\uFFFC</span>');
    }
    return out.join('');
  };

  /** 段落 → HTML */
  R.paraToHTML = (p, design) => {
    const inner = runsToHTML(p, design);
    const ps = R.pPrStyle(p.pPr);
    return '<div class="p" data-pid="' + p.id + '"><div class="pc"' + (ps ? ' style="' + ps + '"' : '') + '>'
      + (inner || '<br>') + '</div></div>';
  };

  /** 块列表 → HTML (表格递归) */
  R.blocksToHTML = (blocks, design) => {
    const out = [];
    for (const b of blocks) {
      if (b.t === 'p') out.push(R.paraToHTML(b, design));
      else if (b.t === 'tbl') {
        const grid = b.grid || [];
        const cells = b.rows.map((r) => {
          const trh = r.trPr && r.trPr.h ? ' style="height:' + pt(r.trPr.h) + '"' : '';
          return '<tr' + trh + '>' + r.cells.map((c) => {
            const st = [];
            if (c.tcPr) {
              if (c.tcPr.w) st.push('width:' + pt(c.tcPr.w));
              if (c.tcPr.shd) st.push('background:' + c.tcPr.shd);
            }
            return '<td' + (st.length ? ' style="' + st.join(';') + '"' : '') + '>' + R.blocksToHTML(c.blocks, design) + '</td>';
          }).join('') + '</tr>';
        }).join('');
        const cls = 'emr-tbl' + (b.tblPr && b.tblPr.plain ? ' emr-tbl-plain' : '') + (b.tblPr && b.tblPr.bd === 0 ? ' emr-tbl-none' : b.tblPr && b.tblPr.bd === 2 ? ' emr-tbl-bold' : '');
        const st = [];
        st.push('width:' + (grid.length ? pt(grid.reduce((a, x) => a + x, 0)) : '100%'));
        if (b.tblPr && b.tblPr.center) st.push('margin-left:auto', 'margin-right:auto'); /* 整表居中, 内部单元格左对齐 */
        out.push('<table class="' + cls + '" data-tblid="' + b.id + '" style="' + st.join(';') + '">' + cells + '</table>');
      }
    }
    return out.join('');
  };

  /* ---------- 抬头 ---------- */
  /** 固定抬头 HTML: 医院名/文书名/患者信息栏; patientInfo 键: name/gender/age/admission_no/ward_name/bed_no
   *  design=true (设计模板) 时信息栏以占位符呈现, 不显示真实患者 */
  R.headerToHTML = (header, patientInfo, design) => {
    const info = patientInfo || {};
    const fields = (header.info_fields || []).map((f) => {
      const [label, key] = f;
      const val = design ? '＿＿＿＿' : (info[key] || '');
      return '<span class="emr-hf"><b>' + esc(label) + '</b><span class="emr-hf-val' + (design ? ' emr-ph-des' : '') + '">' + esc(val) + '</span></span>';
    }).join('');
    return '<div class="emr-page-head">'
      + (header.hospital ? '<div class="emr-hos">' + esc(header.hospital) + '</div>' : '')
      + (header.doc_name ? '<div class="emr-docname">' + esc(header.doc_name) + '</div>' : '')
      + (fields ? '<div class="emr-infobar">' + fields + '</div>' : '')
      + '</div>';
  };
})();