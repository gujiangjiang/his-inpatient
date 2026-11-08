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

  /** 光标贴靠保护项: 若 offset 落在保护项起点, 推进到其后 (新内容一律落到固定元素之后) */
  const clampAfterProtected = (p, o) => {
    const loc = M.locate(p, o);
    const it = p.runs[loc.i];
    if (it && M.isProtected(it) && loc.off === 0) return loc.start + M.ilen(it);
    return o;
  };

  /* ---------- 插入 ---------- */
  /** 在 pos 插入文本; 返回新光标位置 */
  O.insertTextAt = (pos, text, rPr) => {
    if (!text) return pos;
    const p = pos.p;
    let o = clampAfterProtected(p, pos.o);
    const i = M.splitAt(p, o);
    const it = M.text(text, M.cloneR(rPr || p.rPr || {}));
    p.runs.splice(i, 0, it);
    M.normalize(p);
    return { p, o: o + text.length };
  };

  /** 在 pos 插入 tab (制表); 返回新位置 */
  O.insertTabAt = (pos) => {
    const p = pos.p;
    let o = clampAfterProtected(p, pos.o);
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
    /* 跨段: 中间整段不可含固定元素或内容 */
    const paras = O.ctx(a.cont);
    const ia = paras.findIndex((x) => x.p === a.p);
    const ib = paras.findIndex((x) => x.p === b.p);
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
  /** 对 [a,b) 文本 run 应用 rPr 片段 (toggle: 有则移除, 无则设置) */
  O.toggleRange = (a, b, patch) => {
    if (a.p === b.p) {
      const lo = Math.min(a.o, b.o), hi = Math.max(a.o, b.o);
      const i0 = M.splitAt(a.p, lo), i1 = M.splitAt(a.p, hi);
      for (let k = i0; k < i1; k++) {
        const it = a.p.runs[k];
        if (it.t !== 'text') continue;
        for (const key in patch) {
          if (it.rPr[key] === patch[key]) delete it.rPr[key];
          else it.rPr[key] = patch[key];
        }
        if (!Object.keys(it.rPr).length) delete it.rPr;
      }
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
      for (let k = i0; k < i1; k++) {
        const it = x.p.runs[k];
        if (it.t !== 'text') continue;
        for (const key in patch) {
          if (it.rPr[key] === patch[key]) delete it.rPr[key];
          else it.rPr[key] = patch[key];
        }
        if (!Object.keys(it.rPr).length) delete it.rPr;
      }
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
  /** 设置包含指定项的段落内 dyn 的文本; 返回是否命中 */
  O.setDynTextIn = (p, key, text) => {
    for (const it of p.runs) {
      if (it.t === 'dyn' && it.key === key) { it.text = String(text || ''); return true; }
    }
    return false;
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