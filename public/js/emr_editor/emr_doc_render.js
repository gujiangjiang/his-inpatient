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
  const runsToHTML = (p) => {
    const out = [];
    let pos = 0;
    for (const it of p.runs) {
      const n = M.ilen(it);
      const o = pos; pos += n;
      if (it.t === 'text') {
        if (it.text) out.push('<span data-o="' + o + '" data-n="' + n + '"' + (it.rPr && Object.keys(it.rPr).length ? ' style="' + R.rPrStyle(it.rPr) + '"' : '') + '>' + esc(it.text) + '</span>');
        continue;
      }
      if (it.t === 'fx') {
        out.push('<span class="emr-fx" data-item="fx" data-key="' + esc(it.key) + '" data-o="' + o + '" data-n="1" contenteditable="false">' + esc(it.text) + '</span>');
        continue;
      }
      if (it.t === 'slot') {
        const filled = paraHasTextAfter(p, p.runs.indexOf(it));
        out.push(filled
          ? '<span class="emr-slot" data-item="slot" data-key="' + esc(it.key) + '" data-o="' + o + '" data-n="1" contenteditable="false"></span>'
          : '<span class="emr-slot" data-item="slot" data-key="' + esc(it.key) + '" data-o="' + o + '" data-n="1" contenteditable="false">' + esc(it.ph || '填写') + '</span>');
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
  R.paraToHTML = (p) => {
    const inner = runsToHTML(p);
    const ps = R.pPrStyle(p.pPr);
    return '<div class="p" data-pid="' + p.id + '"><div class="pc"' + (ps ? ' style="' + ps + '"' : '') + '>'
      + (inner || '<br>') + '</div></div>';
  };

  /** 块列表 → HTML (表格递归) */
  R.blocksToHTML = (blocks) => {
    const out = [];
    for (const b of blocks) {
      if (b.t === 'p') out.push(R.paraToHTML(b));
      else if (b.t === 'tbl') {
        const grid = b.grid || [];
        const cells = b.rows.map((r) =>
          '<tr>' + r.cells.map((c) => {
            const st = [];
            if (c.tcPr) {
              if (c.tcPr.w) st.push('width:' + pt(c.tcPr.w));
              if (c.tcPr.shd) st.push('background:' + c.tcPr.shd);
            }
            return '<td' + (st.length ? ' style="' + st.join(';') + '"' : '') + '>' + R.blocksToHTML(c.blocks) + '</td>';
          }).join('') + '</tr>').join('');
        out.push('<table class="emr-tbl" style="width:' + (grid.length ? pt(grid.reduce((a, x) => a + x, 0)) : '100%') + '">' + cells + '</table>');
      }
    }
    return out.join('');
  };

  /* ---------- 抬头 ---------- */
  /** 固定抬头 HTML: 医院名/文书名/患者信息栏; patientInfo 键: name/gender/age/admission_no/ward_name/bed_no */
  R.headerToHTML = (header, patientInfo) => {
    const info = patientInfo || {};
    const fields = (header.info_fields || []).map((f) => {
      const [label, key] = f;
      const val = info[key] || '';
      return '<span class="emr-hf"><b>' + esc(label) + '</b><span class="emr-hf-val">' + esc(val) + '</span></span>';
    }).join('');
    return '<div class="emr-page-head">'
      + (header.hospital ? '<div class="emr-hos">' + esc(header.hospital) + '</div>' : '')
      + (header.doc_name ? '<div class="emr-docname">' + esc(header.doc_name) + '</div>' : '')
      + (fields ? '<div class="emr-infobar">' + fields + '</div>' : '')
      + '</div>';
  };
})();