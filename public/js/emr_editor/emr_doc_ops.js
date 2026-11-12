// public/js/emr_editor/emr_doc_ops.js - 病历编辑操作 (固定元素保护核心)
// 所有函数在 EmrHist.tx 内运行, 直接修改 doc.body; 位置 = {p:段落对象, o:字符偏移}
// 保护规则: fx/slot 原子项永不删除/篡改; 整段删除时若段内含固定元素则拒绝; 事务后守卫校验
(function () {
  'use strict';
  const M = window.EmrDoc;
  const O = (window.EmrOps = {});

  /** 建立上下文: 全部段落索引 {p, cont, i} */
  O.ctx = (blocks) => {
    const paras = [];
    M.walk(blocks, (b, cont, i) => {
      if (b.t === 'p') paras.push({ p: b, cont, i });
    });
    return paras;
  };
  const infoOf = (blocks, p) => O.ctx(blocks).find((x) => x.p === p) || null;

  /* ---------- 插入 ---------- */
  /* 注意: 不能在插入时把「位于固定项起点的光标」强行推到其后 —— 该位置同时也是
     前一槽位的结束边界(如体征行: 槽位后紧邻下一个固定标签), 推移会导致输入错位;
     固定项的删除保护由 deleteRange/守卫校验负责, 插入边界无需额外钳制 */
  /** 在 pos 插入文本; 返回新光标位置 */
  O.insertTextAt = (pos, text, rPr) => {
    if (!text) return pos;
    const p = pos.p;
    const o = Math.max(0, Math.min(pos.o, M.plen(p)));
    const i = M.splitAt(p, o);
    const it = M.text(text, M.cloneR(rPr || p.rPr || {}));
    p.runs.splice(i, 0, it);
    M.normalize(p);
    return { p, o: o + text.length };
  };

  /** 在 pos 插入 tab (制表); 返回新位置 */
  O.insertTabAt = (pos) => {
    const p = pos.p;
    const o = Math.max(0, Math.min(pos.o, M.plen(p)));
    const i = M.splitAt(p, o);
    p.runs.splice(i, 0, M.item('tab'));
    return { p, o: o + 1 };
  };

  /** 在 pos 处换行(回车): 后半段进新段落; 返回新段落起始 */
  O.insertParaAt = (pos) => {
    const o = pos.o;
    const p = pos.p;
    const info = infoOf(pos.cont, p);
    if (!info) return pos;
    const runs = p.runs;
    if (o === 0 && runs.length === 0) {
      const np = M.para();
      info.cont.splice(info.i, 0, np);
      return { p: np, o: 0, cont: info.cont };
    }
    const i = M.splitAt(p, o);
    const tail = runs.splice(i);
    /* 新段继承首段段落/字符默认格式, 但保护项留在首段 */
    const np = M.para(tail, Object.assign({}, p.pPr), M.cloneR(p.rPr));
    info.cont.splice(info.i + 1, 0, np);
    return { p: np, o: 0, cont: info.cont };
  };

  /* ---------- 删除 ---------- */
  /** 擦除单段 [from,to) 内文本; 保护项(含完全在范围内)一律保留 */
  const eraseInPara = (p, from, to) => {
    if (from === to) return;
    const out = [];
    let pos = 0;
    for (const it of p.runs) {
      const n = M.ilen(it);
      const s = pos, e = pos + n;
      pos = e;
      if (M.isProtected(it)) { out.push(it); continue; }
      if (it.t !== 'text') {
        if (s < from || e > to) out.push(it);
        continue;
      }
      if (e <= from || s >= to) { out.push(it); continue; }
      const keepL = Math.max(0, from - s);
      const keepR = Math.max(0, e - to);
      const t = it.text.slice(0, keepL) + it.text.slice(n - keepR);
      if (t) out.push(Object.assign({}, it, { text: t }));
    }
    p.runs = out;
  };

  /** 段内是否含保护项 */
  const hasProtected = (p) => p.runs.some(M.isProtected);

  /** 删除 [a,b) (同段或跨段); 返回折叠后光标
   *  跨段合并规则: 空段不吞并内容段 —— 保留有内容段落的段落格式(对齐等), 只删除空段 */
  O.deleteRange = (a, b) => {
    if (a.p === b.p) {
      const lo = Math.min(a.o, b.o), hi = Math.max(a.o, b.o);
      eraseInPara(a.p, lo, hi);
      M.normalize(a.p);
      return { p: a.p, o: lo, cont: a.cont };
    }
    /* 跨段: 中间整段不可含固定元素或内容 (设计模式同样保护, 删除固定元素用右击菜单) */
    const paras = O.ctx(a.cont);
    const ia = paras.findIndex((x) => x.p === a.p);
    const ib = paras.findIndex((x) => x.p === b.p);
    if (ia < 0 || ib < 0) {
      /* 跨容器选区 (如表格单元格 ↔ 正文): 不做段落合并, 仅擦除两端选区内容 */
      eraseInPara(a.p, a.o, M.plen(a.p));
      eraseInPara(b.p, 0, b.o);
      M.normalize(a.p);
      M.normalize(b.p);
      return { p: a.p, o: Math.min(a.o, M.plen(a.p)), cont: a.cont };
    }
    for (let k = ia + 1; k < ib; k++) {
      if (hasProtected(paras[k].p) || paras[k].p.runs.length) {
        const e = new Error('该区域包含固定元素, 不可整段删除');
        e.code = 'protected'; throw e;
      }
    }
    eraseInPara(a.p, a.o, M.plen(a.p));
    eraseInPara(b.p, 0, b.o);
    M.normalize(a.p);
    M.normalize(b.p);
    const contA = paras[ia], contB = paras[ib];
    /* b 已空: 只删 b, 保留 a (a 的格式不变) */
    if (!b.p.runs.length) {
      contB.cont.splice(contB.i, 1);
      return { p: a.p, o: a.o, cont: a.cont };
    }
    /* a 已空: 只删 a, 保留 b (保留 b 的段落格式: 右对齐等) */
    if (!a.p.runs.length) {
      contA.cont.splice(contA.i, 1);
      return { p: b.p, o: 0, cont: b.cont };
    }
    /* 双方均有内容: 合并进左侧段落 (保护项随 run 迁移, 顺序不变) */
    const tail = b.p.runs.slice();
    a.p.runs = a.p.runs.concat(tail);
    contB.cont.splice(contB.i, 1);
    M.normalize(a.p);
    return { p: a.p, o: a.o, cont: a.cont };
  };

  /** Backspace: 删光标前一个字符 */
  O.deleteBackward = (pos) => {
    if (pos.o > 0) return O.deleteRange({ p: pos.p, o: pos.o - 1, cont: pos.cont }, pos);
    const paras = O.ctx(pos.cont);
    const info = paras.find((x) => x.p === pos.p);
    if (!info || info.i === 0) return pos;
    const prev = paras[info.i - 1].p;
    if (hasProtected(prev)) {
      /* 当前段为空 (如槽位行后回车产生的空行): 只删空段, 不影响固定段 */
      if (!pos.p.runs.length) {
        const cur = paras[info.i];
        cur.cont.splice(cur.i, 1);
        return { p: prev, o: M.plen(prev), cont: pos.cont };
      }
      const e = new Error('上一段含固定元素, 不可整段删除');
      e.code = 'protected'; throw e;
    }
    const end = { p: prev, o: M.plen(prev), cont: pos.cont };
    return O.deleteRange(end, pos);
  };

  /** Delete: 删光标后一个字符 */
  O.deleteForward = (pos) => {
    if (pos.o < M.plen(pos.p)) return O.deleteRange(pos, { p: pos.p, o: pos.o + 1, cont: pos.cont });
    const paras = O.ctx(pos.cont);
    const info = paras.find((x) => x.p === pos.p);
    if (!info || info.i >= paras.length - 1) return pos;
    const next = paras[info.i + 1].p;
    if (hasProtected(next)) {
      /* 当前段为空: 只删空段, 不动固定段 */
      if (!pos.p.runs.length) {
        const cur = paras[info.i];
        cur.cont.splice(cur.i, 1);
        const prevInfo = info.i > 0 ? paras[info.i - 1] : null;
        if (prevInfo) return { p: prevInfo.p, o: M.plen(prevInfo.p), cont: pos.cont };
        return { p: next, o: 0, cont: pos.cont };
      }
      const e = new Error('下一段含固定元素, 不可整段删除');
      e.code = 'protected'; throw e;
    }
    return O.deleteRange(pos, { p: next, o: 0, cont: pos.cont });
  };

  /* ---------- 格式 ---------- */
  /** 对 [a,b) 文本 run 应用 rPr 片段 (toggle: 有则移除, 无则设置)
   *  注意: 模板 JSON 的文本 run 可能缺 rPr 字段 (仅在有格式时序列化), 必须先补齐 */
  const applyPatch = (it, patch) => {
    if (it.t !== 'text') return;
    it.rPr = it.rPr || {};
    for (const key in patch) {
      if (it.rPr[key] === patch[key]) delete it.rPr[key];
      else it.rPr[key] = patch[key];
    }
    if (!Object.keys(it.rPr).length) delete it.rPr;
  };
  O.toggleRange = (a, b, patch) => {
    if (a.p === b.p) {
      const lo = Math.min(a.o, b.o), hi = Math.max(a.o, b.o);
      const i0 = M.splitAt(a.p, lo), i1 = M.splitAt(a.p, hi);
      for (let k = i0; k < i1; k++) applyPatch(a.p.runs[k], patch);
      M.normalize(a.p);
      return;
    }
    const paras = O.ctx(a.cont);
    let inRange = false;
    for (const x of paras) {
      if (x.p === a.p) inRange = true;
      if (!inRange) continue;
      const s = x.p === a.p ? a.o : 0;
      const e = x.p === b.p ? b.o : M.plen(x.p);
      const i0 = M.splitAt(x.p, s), i1 = M.splitAt(x.p, e);
      for (let k = i0; k < i1; k++) applyPatch(x.p.runs[k], patch);
      M.normalize(x.p);
      if (x.p === b.p) break;
    }
  };

  /** 设置段落格式 (对齐等) */
  O.setParaProp = (p, key, val) => {
    if (val === undefined || val === null || val === '') delete p.pPr[key];
    else p.pPr[key] = val;
  };

  /* ---------- 动态只读项 dyn (BMI 自动计算 / 签名 / 诊断同步) ---------- */
  /** 取段内某 slot 之后紧邻的文本值 (到下一个保护项或段尾) */
  O.valueAfterSlot = (p, key) => {
    let on = false, s = '';
    for (const it of p.runs) {
      if (on) {
        if (M.isProtected(it)) break;
        if (it.t === 'text') s += it.text;
      } else if (it.t === 'slot' && it.key === key) on = true;
    }
    return s;
  };
  /** 设置槽位值: 清除槽位后到下一个保护项之间的旧值, 写入新文本 (下拉选项/外部回填用) */
  O.setSlotValue = (p, key, text) => {
    const runs = p.runs;
    const idx = runs.findIndex((it) => it.t === 'slot' && it.key === key);
    if (idx < 0) return false;
    let end = idx + 1;
    while (end < runs.length && !M.isProtected(runs[end])) end++;
    const repl = text ? [M.text(String(text))] : [];
    runs.splice(idx + 1, end - (idx + 1), ...repl);
    return true;
  };

  /** 标记光标位置前方最近槽位为「已手动修改」(manual:true) —— 首页同步规则: 手动改过并保存的字段停止自动更新 */
  O.markManualAround = (p, o) => {
    let last = null, pos = 0;
    for (const it of p.runs) {
      if (pos >= o) break;
      if (it.t === 'slot') last = it;
      pos += M.ilen(it);
    }
    if (last) last.manual = true;
  };
  /** 按 key 在全文设置槽位值 (模型级, 不触发撤销; 首页自动同步用) */
  O.setSlotValueByKey = (body, key, text) => {
    const p = O.findParaWithItem(body, 'slot', key);
    if (!p) return false;
    return O.setSlotValue(p, key, text);
  };

  /** 按 key 在全文中设置动态只读项 dyn 的文本 (首页同步用) */
  O.setDynTextByKey = (body, key, text) => {
    const p = O.findParaWithItem(body, 'dyn', key);
    if (!p) return false;
    return O.setDynTextIn(p, key, text);
  };
  /** 设置包含指定项的段落内 ref 的文本 (字段引用解析用); 返回是否命中 */
  O.setRefTextIn = (p, key, text) => {
    for (const it of p.runs) {
      if (it.t === 'ref' && it.key === key) { it.text = String(text || ''); return true; }
    }
    return false;
  };
  /** 按 key 在全文设置字段引用 ref 的文本 */
  O.setRefTextByKey = (body, key, text) => {
    const p = O.findParaWithItem(body, 'ref', key);
    if (!p) return false;
    return O.setRefTextIn(p, key, text);
  };
  /* ---------- 模板设计模式 ---------- */
  /** 设计模式标志 (供编辑器/宿主查询; 固定元素删除保护与设计模式无关: 一律只能右击菜单删除) */
  O.design = false;
  O.setDesignMode = (v) => { O.design = !!v; };

  /** 设计模式: 在 pos 插入字段 (固定标签 + 填写槽; opts.select 为下拉选项) */
  O.insertFieldAt = (pos, label, opts) => {
    const p = pos.p;
    const o = Math.max(0, Math.min(pos.o, M.plen(p)));
    const i = M.splitAt(p, o);
    const key = 'f' + Date.now().toString(36) + Math.floor(Math.random() * 90 + 10);
    const items = [M.fx(key, String(label) + '：')];
    const slot = M.slot(key + '_val', '请输入' + label);
    if (opts && Array.isArray(opts.select)) slot.opts = opts.select.slice(); /* 空数组也保留: 标记为下拉槽待配置 */
    if (opts && opts.multi) slot.multi = true;
    items.push(slot);
    p.runs.splice(i, 0, ...items);
    M.normalize(p);
    return { p, o: o + 2, cont: pos.cont };
  };
  /** 设计模式: 插入字段引用 */
  O.insertRefAt = (pos, key, fb) => {
    const p = pos.p;
    const o = Math.max(0, Math.min(pos.o, M.plen(p)));
    const i = M.splitAt(p, o);
    p.runs.splice(i, 0, M.item('ref', { key: String(key), fb: String(fb || '＿＿') }));
    return { p, o: o + 1, cont: pos.cont };
  };
  /* ---------- 表格 (设计模式) ---------- */
  /** 按块 id 在全文(含单元格)中查找块 */
  O.findBlockById = (body, id) => {
    let hit = null;
    const walk = (bl) => {
      for (const b of bl) {
        if (hit) return;
        if (b.id === id) { hit = b; return; }
        if (b.t === 'tbl') for (const r of b.rows) for (const c of r.cells) walk(c.blocks || []);
      }
    };
    walk(body);
    return hit;
  };

  /** 在 pos 段落后插入 n×n 表格 (列宽均分正文宽; 表格后补空段便于继续书写) */
  O.insertTableAt = (pos, nRows, nCols, contentW) => {
    const p = pos.p;
    const info = infoOf(pos.cont, p);
    if (!info) return pos;
    const total = contentW || 523; /* A4 内容宽 (pt) ≈ 595 - 36×2 */
    const w = +(total / nCols).toFixed(1);
    const rows = [];
    for (let ri = 0; ri < nRows; ri++) {
      const cells = [];
      for (let ci = 0; ci < nCols; ci++) cells.push(M.cell([M.para()], { w }));
      rows.push(M.row(cells));
    }
    const tbl = M.table(rows, rows[0].cells.map(() => w));
    info.cont.splice(info.i + 1, 0, tbl);
    const np = M.para();
    info.cont.splice(info.i + 2, 0, np);
    return { p: np, o: 0, cont: info.cont };
  };
  /** 按块 id 删除块 (含单元格内; 设计模式表格整体删除) */
  O.removeBlockById = (body, id) => {
    const walk = (bl) => {
      for (let i = 0; i < bl.length; i++) {
        const b = bl[i];
        if (b.id === id) { bl.splice(i, 1); return true; }
        if (b.t === 'tbl') for (const r of b.rows) for (const c of r.cells) if (walk(c.blocks || [])) return true;
      }
      return false;
    };
    return walk(body);
  };

  O.tableAddRow = (tbl) => {
    const last = tbl.rows[tbl.rows.length - 1];
    tbl.rows.push(M.row(last.cells.map((c) => M.cell([M.para()], Object.assign({}, c.tcPr || {})))));
  };
  O.tableDelRow = (tbl) => { if (tbl.rows.length > 1) tbl.rows.pop(); };
  O.tableAddCol = (tbl) => {
    const w = (tbl.grid && tbl.grid.length) ? tbl.grid[tbl.grid.length - 1] : 60;
    tbl.grid = (tbl.grid || []).concat([w]);
    tbl.rows.forEach((r) => r.cells.push(M.cell([M.para()], { w })));
  };
  O.tableDelCol = (tbl) => {
    if ((tbl.grid || []).length > 1) {
      tbl.grid.pop();
      tbl.rows.forEach((r) => r.cells.pop());
    }
  };

  /** 设计模式: 删除单个原子项 (仅右击菜单调用) */
  O.removeItemAt = (p, item) => {
    const i = p.runs.indexOf(item);
    if (i >= 0) { p.runs.splice(i, 1); M.normalize(p); return true; }
    return false;
  };

  /** 日期时间格式化: val='yyyy-mm-dd[ hh:mm]' → fmt 模板 (yyyy年=四位年, mm=月, dd=日, hh=时, nn=分) */
  O.fmtDateTime = (val, fmt) => {
    const s = String(val || '').trim();
    if (!s) return '';
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!m) return s; /* 非标准值(如手输文本)原样返回 */
    const [, Y, Mo, D, H, Mi] = m;
    const tpl = fmt || (m[4] ? 'yyyy-mm-dd hh:nn' : 'yyyy-mm-dd');
    return tpl
      .replace(/yyyy/g, Y).replace(/mm/g, Mo).replace(/dd/g, D)
      .replace(/hh/g, H || '00').replace(/nn/g, Mi || '00');
  };

  /** 设计模式: 在 pos 插入任意原子项 (dyn/ref/date 等), item 为 {t, key, ...} */
  O.insertRunAt = (pos, item) => {
    const p = pos.p;
    const o = Math.max(0, Math.min(pos.o, M.plen(p)));
    const i = M.splitAt(p, o);
    p.runs.splice(i, 0, Object.assign({ rPr: {} }, item));
    M.normalize(p);
    return { p, o: o + 1, cont: pos.cont };
  };

  /** 设置包含指定项的段落内 dyn 的文本; 返回是否命中 */
  O.setDynTextIn = (p, key, text) => {
    for (const it of p.runs) {
      if (it.t === 'dyn' && it.key === key) { it.text = String(text || ''); return true; }
    }
    return false;
  };
  /** 遍历全部槽位: fn(段落, 槽位项) (含表格单元格内) */
  O.walkSlots = (body, fn) => {
    M.walk(body, (b) => {
      if (b.t === 'p') for (const it of (b.runs || [])) if (it.t === 'slot') fn(b, it);
    });
  };

  /** 在全文中查找包含某项 (t+key) 的段落 */
  O.findParaWithItem = (body, t, key) => {
    let hit = null;
    M.walk(body, (b) => {
      if (hit) return false;
      if (b.t === 'p' && b.runs.some((it) => it.t === t && it.key === key)) hit = b;
      return true;
    });
    return hit;
  };

  /** BMI 自动计算: 由身高(cm)/体重(kg)槽位值重算 → dyn(vital_bmi_value); 任一缺失则清空 */
  O.recalcBmi = (body) => {
    const bPara = O.findParaWithItem(body, 'dyn', 'vital_bmi_value');
    if (!bPara) return;
    const hPara = O.findParaWithItem(body, 'slot', 'vital_height_val');
    const wPara = O.findParaWithItem(body, 'slot', 'vital_weight_val');
    const num = (s) => {
      const m = String(s || '').replace(/[^\d.]/g, '');
      const v = parseFloat(m);
      return isFinite(v) && v > 0 ? v : 0;
    };
    const h = hPara ? num(O.valueAfterSlot(hPara, 'vital_height_val')) : 0;
    const w = wPara ? num(O.valueAfterSlot(wPara, 'vital_weight_val')) : 0;
    const val = (h > 0 && w > 0) ? (w / Math.pow(h / 100, 2)).toFixed(1) : '';
    O.setDynTextIn(bPara, 'vital_bmi_value', val);
  };

  /* ---------- 守卫校验 (事务后) ---------- */
  /** 事务后校验: 固定元素键集合必须与模板完全一致; 不一致抛错触发回滚 */
  O.assertGuard = (body, guard) => {
    if (!guard) return;
    const now = M.collectGuard(body);
    if (!M.guardEqual(now, guard)) {
      const e = new Error('固定元素被改动, 已撤销本次操作');
      e.code = 'protected'; throw e;
    }
  };
})();