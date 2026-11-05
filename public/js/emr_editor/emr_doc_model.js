// public/js/emr_editor/emr_doc_model.js - 病历文档模型
// 文档 = {v:1, title, page(排版), header(固定抬头), body(正文块)}
// 正文块: {t:'p',pPr,rPr,runs} 段落 | {t:'tbl',grid,rows} 表格
// run 项: text/tab/br(文本) + fx(固定标签,原子不可删) + slot(填写槽,原子不可删)
// 固定元素保护: 模型层 ops 禁止删除/越过 fx/slot; 每次事务后 validateGuard 校验键集合与模板一致
(function () {
  'use strict';
  const M = (window.EmrDoc = {});
  let NID = 1;
  M.nid = () => NID++;

  /* ---------- 构造器 ---------- */
  M.para = (runs, pPr, rPr) => ({ t: 'p', id: M.nid(), pPr: pPr || {}, rPr: rPr || {}, runs: runs || [] });
  M.text = (text, rPr) => ({ t: 'text', text: String(text), rPr: rPr || {} });
  M.fx = (key, text) => ({ t: 'fx', key, text: String(text || ''), rPr: {} });
  M.slot = (key, ph) => ({ t: 'slot', key, ph: String(ph || ''), rPr: {} });
  M.item = (t, extra) => Object.assign({ t, rPr: {} }, extra || {});
  M.cell = (blocks, tcPr) => ({ id: M.nid(), tcPr: tcPr || {}, blocks: blocks && blocks.length ? blocks : [M.para()] });
  M.row = (cells, trPr) => ({ id: M.nid(), trPr: trPr || {}, cells });
  M.table = (rows, grid, tblPr) => ({ t: 'tbl', id: M.nid(), tblPr: tblPr || {}, grid: grid || [], rows: rows || [] });

  /* ---------- 长度与定位 ---------- */
  /** 项占字符位: 文本=字符数; 固定标签/填写槽/制表符等=1 占位(光标可停在两侧, 不可删除) */
  M.ilen = (it) => {
    if (it.t === 'text') return it.text.length;
    return 1;
  };
  M.isProtected = (it) => it.t === 'fx' || it.t === 'slot';
  M.plen = (p) => { let n = 0; for (const it of p.runs) n += M.ilen(it); return n; };

  /** 定位 offset o 到项: {i(项下标), off(项内偏移), start(项起始)} */
  M.locate = (p, o) => {
    let pos = 0;
    const runs = p.runs;
    for (let i = 0; i < runs.length; i++) {
      const n = M.ilen(runs[i]);
      if (o < pos + n) return { i, off: o - pos, start: pos };
      pos += n;
    }
    return { i: runs.length, off: 0, start: pos };
  };

  /** 分裂 runs, 使 offset o 处成为项边界; 返回 o 后第一个项下标 */
  M.splitAt = (p, o) => {
    let pos = 0;
    for (let i = 0; i < p.runs.length; i++) {
      const it = p.runs[i];
      const n = M.ilen(it);
      if (o === pos) return i;
      if (o < pos + n) {
        if (it.t !== 'text') return i; /* 保护项/特殊项不可内部切开 */
        const k = o - pos;
        p.runs.splice(i, 1,
          Object.assign({}, it, { text: it.text.slice(0, k) }),
          Object.assign({}, it, { text: it.text.slice(k), rPr: M.cloneR(it.rPr) }));
        return i + 1;
      }
      pos += n;
    }
    return p.runs.length;
  };

  /** 插入点: 分裂到项边界, 返回首个可插入项下标 */
  M.insertIndex = (p, o) => M.splitAt(p, o);

  /* ---------- 规范化 ---------- */
  M.sameRPr = (a, b) => JSON.stringify(a || {}) === JSON.stringify(b || {});
  M.cloneR = (r) => r ? JSON.parse(JSON.stringify(r)) : {};

  /** 合并相邻同格式文本 run, 丢弃空文本 */
  M.normalize = (p) => {
    const out = [];
    for (const it of p.runs) {
      if (it.t === 'text' && !it.text) continue;
      const last = out[out.length - 1];
      if (last && it.t === 'text' && last.t === 'text' && M.sameRPr(last.rPr, it.rPr)) {
        out[out.length - 1] = Object.assign({}, last, { text: last.text + it.text });
        continue;
      }
      out.push(it);
    }
    p.runs = out;
    return p;
  };

  /* ---------- 固定元素守卫 ---------- */
  /** 递归收集正文中 fx/slot 键: {fx:[...], slot:[...]} (排序去重) */
  M.collectGuard = (blocks) => {
    const guard = { fx: [], slot: [] };
    const walk = (bl) => {
      for (const b of bl) {
        if (!b) continue;
        if (b.t === 'p') {
          for (const it of b.runs || []) {
            if (it.t === 'fx' && it.key) guard.fx.push(it.key);
            if (it.t === 'slot' && it.key) guard.slot.push(it.key);
          }
        } else if (b.t === 'tbl') {
          for (const r of b.rows || []) for (const c of r.cells || []) walk(c.blocks || []);
        }
      }
    };
    walk(blocks);
    guard.fx = Array.from(new Set(guard.fx)).sort();
    guard.slot = Array.from(new Set(guard.slot)).sort();
    return guard;
  };
  /** 两守卫集合是否完全一致 (不可增/删/改键) */
  M.guardEqual = (a, b) =>
    JSON.stringify(a && a.fx) === JSON.stringify(b && b.fx) &&
    JSON.stringify(a && a.slot) === JSON.stringify(b && b.slot);

  /* ---------- 页面与默认文档 ---------- */
  M.DEFAULT_PAGE = {
    paper: 'A4', mt: 62, mb: 56, ml: 68, mr: 68,
    header_h: 52, footer_h: 44,
    font: '仿宋_GB2312, FangSong, SimSun, serif', sz: 12, line: 1.5,
  };
  /** 由模板 schema (word 模式) 生成空白文档 */
  M.fromSchema = (schema) => ({
    v: 1,
    title: schema.title || '',
    page: Object.assign({}, M.DEFAULT_PAGE, schema.page || {}),
    header: Object.assign({ hospital: '', doc_name: '', info_fields: [] }, schema.header || {}),
    body: JSON.parse(JSON.stringify(schema.body && schema.body.length ? schema.body : [M.para()])),
  });

  /* ---------- 遍历 ---------- */
  /** 深度遍历正文块: fn(block, container, index) 返回 false 可中断 */
  M.walk = (blocks, fn) => {
    for (let i = 0; i < blocks.length; i++) {
      const b = blocks[i];
      if (fn(b, blocks, i) === false) return false;
      if (b.t === 'tbl') {
        for (const r of b.rows) for (const c of r.cells) {
          if (M.walk(c.blocks, fn) === false) return false;
        }
      }
    }
    return true;
  };
})();