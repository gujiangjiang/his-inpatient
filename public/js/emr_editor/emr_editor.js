// public/js/emr_editor/emr_editor.js - 病历字处理编辑器控制器
// 内容画布: 固定抬头(不可编辑) + contenteditable 正文区(块/固定标签/填写槽)
// 输入处理: beforeinput 全量接管 → 模型 ops → 事务历史 → 重渲染 → 恢复光标
// IME: 组合期不干预 DOM, 组合结束按 compositionstart 捕获的光标位置整体入模型
(function () {
  'use strict';
  const M = window.EmrDoc, O = window.EmrOps, C = window.EmrCaret;
  const R = window.EmrRender, H = window.EmrHist, P = window.EmrPrint;

  const create = (opts) => {
    const inst = {
      container: opts.container, doc: opts.doc, guard: opts.guard || null,
      patientInfo: opts.patientInfo || {}, readonly: !!opts.readonly, design: !!opts.design,
      trackChanges: !!opts.trackChanges, /* 修订模式: 删除留痕 (签名后维护解锁的文书) */
      onChange: opts.onChange || null, onStatus: opts.onStatus || null,
      bodyEl: null, index: new Map(), composing: false, composeStart: null,
      dirty: false, _handlers: [], _lastPos: null, /* _lastPos: 最近一次可靠的模型光标位置 */
    };

    /* ---------- 归一化: 补齐块 id (模板/迁移数据可能缺失, 渲染与光标映射必需) ---------- */
    M.ensureIds(inst.doc.body || []);
    O.setTrackChanges(!inst.readonly && !inst.design && inst.trackChanges); /* 修订模式: 删除留痕 */
    if (!inst.readonly && !inst.design) O.recalcBmi(inst.doc.body); /* 打开即校正 BMI (设计模板不引用患者数据) */

    /* ---------- DOM 搭建 ---------- */
    inst.container.innerHTML = '<div class="emr-head-wrap"></div>'
      + '<div class="emr-body" contenteditable="' + (inst.readonly ? 'false' : 'true') + '"></div>';
    inst.headEl = inst.container.querySelector('.emr-head-wrap');
    inst.bodyEl = inst.container.querySelector('.emr-body');
    inst.headEl.innerHTML = R.headerToHTML(inst.doc.header, inst.patientInfo, inst.design);

    /* ---------- 历史绑定 ---------- */
    H.bind(() => inst.doc, (d) => { inst.doc = d; });
    H.clear();
    if (!inst.readonly) O.recalcCalcs(inst.doc.body); /* 打开即刷新自动计算项 (设计模式同样预览) */

    /* ---------- 渲染 ---------- */
    /* 滚动容器: 整页画布滚动区 (重渲染时保持滚动位置, 避免"闪跳") */
    const scroller = () => inst.container.closest ? inst.container.closest('.emr-editor-scroll') : null;
    /* 光标不可见时最小化滚动使其可见 (保持在视口边缘内) */
    const ensureCaretVisible = () => {
      const sc = scroller();
      const sel = window.getSelection();
      if (!sc || !sel || !sel.rangeCount) return;
      const rc = sel.getRangeAt(0).getBoundingClientRect();
      if (!rc || (!rc.top && !rc.bottom)) return;
      const sr = sc.getBoundingClientRect();
      const pad = 24;
      if (rc.top < sr.top + pad) sc.scrollTop -= (sr.top + pad - rc.top);
      else if (rc.bottom > sr.bottom - pad) sc.scrollTop += (rc.bottom - (sr.bottom - pad));
    };
    const renderBody = (pos, end) => {
      const sc = scroller();
      const keepTop = sc ? sc.scrollTop : 0;
      const keepLeft = sc ? sc.scrollLeft : 0;
      inst.bodyEl.innerHTML = R.blocksToHTML(inst.doc.body, inst.design);
      inst.index = new Map();
      O.paragraphs(inst.doc.body).forEach((x) => inst.index.set(x.p.id, x));
      if (sc) { sc.scrollTop = keepTop; sc.scrollLeft = keepLeft; }
      if (pos) {
        inst.bodyEl.focus({ preventScroll: true });
        C.restore(inst.bodyEl, pos, end || pos);
        inst._lastPos = pos;
        ensureCaretVisible();
      }
      inst.updateStatus();
    };

    /* ---------- 状态 ---------- */
    inst.updateStatus = () => {
      let chars = 0;
      M.walk(inst.doc.body, (b) => {
        if (b.t === 'p') for (const it of b.runs) if (it.t === 'text') chars += it.text.length;
      });
      inst.statusText = chars;
      if (inst.onStatus) inst.onStatus(chars, inst.dirty);
    };
    inst.markSaved = () => { inst.dirty = false; inst.updateStatus(); };
    inst.isDirty = () => inst.dirty;

    /* ---------- 输入当前格式 ---------- */
    const rPrOf = (pos) => {
      const loc = M.locate(pos.p, pos.o);
      const prev = pos.p.runs[loc.i - 1];
      return (prev && prev.rPr) || {};
    };

    /* ---------- 事务封装 ---------- */
    const apply = (label, fn, end) => {
      const selNow = C.rangeToModel(inst.bodyEl, inst.index);
      const beforePos = selNow && selNow.a;
      let pos = null;
      try {
        H.tx(label, () => {
          pos = fn();
          O.recalcCalcs(inst.doc.body);          /* 自动计算项: 任一模式跟随字段值刷新 */
          if (!inst.design) {
            O.recalcBmi(inst.doc.body);          /* 派生项自动重算 (BMI legacy) */
            /* 用户编辑 ⇒ 光标前方最近填写槽记为手动修改 (诊断同步除外; 自动填充不经此处) */
            if (label !== '同步' && pos) O.markManualAround(pos.p, pos.o);
            O.assertGuard(inst.doc.body, inst.guard);
          }
        }, beforePos);
      } catch (e) {
        console.error('编辑事务失败', e);
        if (e && e.message) Dom.toast(e.message, 'error');
        renderBody(); /* 历史已回滚, 重建 DOM */
        return;
      }
      inst.dirty = true; /* 先置脏: renderBody → updateStatus 才能立即显示"未保存" */
      renderBody(pos, end);
      if (inst.onChange) inst.onChange(inst);
    };

    /* ---------- 书写模式可编辑域 (Word 式: 仅填写槽值区可编辑) ----------
       模板固定区 (标签/标题/正文模板文字) 一律不可输入; 点击/输入落在固定区时
       自动重定向到同段最近的填写槽值位置; 段落内无填写槽 → 完全锁定 */
    const valueCtx = (p, o) => {
      const loc = M.locate(p, o);
      const at = p.runs[loc.i];
      if (at && at.t === 'text') return !!at.val;
      let k = loc.i - 1;
      while (k >= 0 && p.runs[k].t === 'br') k--;
      const prev = p.runs[k];
      return !!(prev && (prev.t === 'slot' || (prev.t === 'text' && prev.val)));
    };
    /** 值区原样返回; 固定区重定向到同段最近填写槽的值起点 */
    const writePos = (pos) => {
      const p = pos.p;
      const o = Math.max(0, Math.min(pos.o, M.paraLen(p)));
      if (valueCtx(p, o)) return { p, o, cont: pos.cont, redir: false };
      let best = -1, bestD = Infinity, so = 0;
      for (const it of p.runs) {
        if (it.t === 'slot') { const d = Math.abs(so - o); if (d < bestD) { bestD = d; best = so; } }
        so += M.itemLen(it);
      }
      return best < 0 ? null : { p, o: best + 1, cont: pos.cont, redir: true };
    };
    const firstSlotPos = (info) => {
      let so = 0;
      for (const it of info.p.runs) {
        if (it.t === 'slot') return { p: info.p, o: so + 1, cont: info.cont, redir: true };
        so += M.itemLen(it);
      }
      return null;
    };
    /** 表格模板可将标签与输入拆到相邻单元格/段落; 点击标签时定位对应输入槽 */
    const writePosAt = (pos, pElHint) => {
      const wp = writePos(pos);
      if (wp) return wp;
      try {
        const pEl = (pElHint && pElHint.closest && pElHint.closest('.p'))
          || inst.bodyEl.querySelector('.p[data-pid="' + pos.p.id + '"]');
        const td = pEl && pEl.closest ? pEl.closest('td') : null;
        if (!td) return null;
        const cells = [td, td.nextElementSibling, td.previousElementSibling].filter(Boolean);
        for (const cell of cells) {
          const paras = Array.from(cell.querySelectorAll('.p'));
          const at = paras.indexOf(pEl);
          const order = at >= 0 ? paras.slice(at + 1).concat(paras.slice(0, at).reverse()) : paras;
          for (const pel of order) {
            const info = inst.index.get(+pel.dataset.pid);
            const hit = info && firstSlotPos(info);
            if (hit) return hit;
          }
        }
      } catch (e) { /* 忽略 */ }
      return null;
    };

    /* ---------- 输入操作 ---------- */
    /** 当前模型选区; 浏览器在不可编辑元素内输入会把选区"回收"到编辑宿主 →
     *  此时退回最近一次可靠光标 (_lastPos), 保证输入不丢 */
    const modelSel = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (sel) return sel;
      if (!inst._lastPos) return null;
      const s = window.getSelection();
      const r0 = (s && s.rangeCount) ? s.getRangeAt(0) : null;
      const inside = (r0 && inst.bodyEl.contains(r0.startContainer)) || document.activeElement === inst.bodyEl;
      if (!inside) return null;
      /* 位置重解析: 撤销/重渲染会替换文档对象 */
      const p = O.findBlockById(inst.doc.body, inst._lastPos.p.id);
      if (!p || p.t !== 'p') return null;
      const info = inst.index.get(p.id);
      const pos = { p, o: Math.min(inst._lastPos.o, M.paraLen(p)), cont: info ? info.cont : inst.doc.body };
      return { a: pos, b: pos, collapsed: true };
    };
    inst.insertText = (text) => {
      if (!text) return;
      const sel = modelSel();
      if (!sel) return;
      let target = sel.a;
      if (!inst.design) {
        const wp = writePosAt(sel.a);
        if (!wp) return; /* 固定区: 不可输入 */
        target = wp;
      }
      apply('typing', () => {
        if (!sel.collapsed) target = O.deleteRange(target, sel.b);
        const np = O.insertTextAt(target, text, rPrOf(target));
        O.assertGuard(inst.doc.body, inst.guard);
        return np;
      });
    };

    inst.enter = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      let target = sel.a;
      if (!inst.design) {
        const wp = writePosAt(sel.a);
        if (!wp) return;
        target = wp;
      }
      /* 值上下文内回车 = 值内换行 (br), 归属该槽位值 (Word: 控件内新行);
         普通段落回车 = 新段落 */
      const inValue = !inst.design && valueCtx(target.p, target.o);
      apply('换行', () => {
        if (!sel.collapsed) target = O.deleteRange(target, sel.b);
        if (inValue) {
          const i = M.splitAt(target.p, target.o);
          target.p.runs.splice(i, 0, M.item('br'));
          O.assertGuard(inst.doc.body, inst.guard);
          return { p: target.p, o: target.o + 1, cont: target.cont };
        }
        const np = O.insertParaAt(target);
        O.assertGuard(inst.doc.body, inst.guard);
        return np;
      });
    };

    inst.lineBreak = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      let target = sel.a;
      if (!inst.design) {
        const wp = writePosAt(sel.a);
        if (!wp) return;
        target = wp;
      }
      apply('换行', () => {
        if (!sel.collapsed) target = O.deleteRange(target, sel.b);
        const i = M.splitAt(target.p, target.o);
        target.p.runs.splice(i, 0, M.item('br'));
        O.assertGuard(inst.doc.body, inst.guard);
        return { p: target.p, o: target.o + 1, cont: target.cont };
      });
    };

    inst.insertTab = () => {
      const sel = modelSel();
      if (!sel) return;
      let start = sel.a;
      if (!inst.design) {
        const wp = writePosAt(sel.a);
        if (!wp) return;
        start = wp;
      }
      apply('制表', () => {
        const pos = sel.collapsed ? start : O.deleteRange(start, sel.b);
        return O.insertTabAt(pos);
      });
    };

    inst.backspace = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      apply('删除', () => {
        const pos = sel.collapsed ? O.deleteBackward(sel.a) : O.deleteRange(sel.a, sel.b);
        O.assertGuard(inst.doc.body, inst.guard);
        return pos;
      });
    };

    inst.del = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      apply('删除', () => {
        const pos = sel.collapsed ? O.deleteForward(sel.a) : O.deleteRange(sel.a, sel.b);
        O.assertGuard(inst.doc.body, inst.guard);
        return pos;
      });
    };

    inst.toggle = (prop, val) => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel || sel.collapsed) return;
      const end = sel.b;
      apply('格式', () => {
        const patch = {}; patch[prop] = val;
        O.toggleRange(sel.a, sel.b, patch);
        O.assertGuard(inst.doc.body, inst.guard);
        return sel.a;
      }, end);
    };

    inst.undo = () => {
      if (inst.readonly) return;
      const pos = H.undoOne();
      if (pos) { renderBody(pos); inst.dirty = true; if (inst.onChange) inst.onChange(inst); }
    };
    inst.redo = () => {
      if (inst.readonly) return;
      const pos = H.redoOne();
      if (pos) { renderBody(pos); inst.dirty = true; if (inst.onChange) inst.onChange(inst); }
    };

    inst.setAlign = (align) => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      apply('对齐', () => {
        O.setParaProp(sel.a.p, 'align', align);
        return sel.a;
      });
    };

    /* ---------- 事件 ---------- */
    const onBeforeInput = (e) => {
      const t = e.inputType;
      if (inst.readonly) { e.preventDefault(); return; }
      if (inst.composing) {
        if (t === 'insertCompositionText') return; /* 组合输入中: 交给浏览器 */
        /* 组合已结束但状态残留(部分浏览器/输入法失焦不触发 compositionend):
           按正常输入处理, 避免后续所有输入被吞 */
        inst.composing = false;
      }
      if (t === 'insertText' || t === 'insertTranspose' || t === 'insertReplacementText') { e.preventDefault(); inst.insertText(e.data || ''); return; }
      if (t === 'insertParagraph') { e.preventDefault(); inst.enter(); return; }
      if (t === 'insertLineBreak') { e.preventDefault(); inst.lineBreak(); return; }
      if (t === 'deleteContentBackward' || t === 'deleteWordBackward' || t === 'deleteHardLineBackward' || t === 'deleteSoftLineBackward') { e.preventDefault(); inst.backspace(); return; }
      if (t === 'deleteContentForward' || t === 'deleteWordForward' || t === 'deleteHardLineForward' || t === 'deleteSoftLineForward') { e.preventDefault(); inst.del(); return; }
      if (t === 'formatBold') { e.preventDefault(); inst.toggle('b', true); return; }
      if (t === 'formatItalic') { e.preventDefault(); inst.toggle('i', true); return; }
      if (t === 'formatUnderline') { e.preventDefault(); inst.toggle('u', true); return; }
      if (t === 'formatStrikeThrough') { e.preventDefault(); inst.toggle('strike', true); return; }
      if (t === 'historyUndo' || t === 'historyRedo' || t === 'insertFromPaste') { e.preventDefault(); return; }
      e.preventDefault();
    };

    const onKeyDown = (e) => {
      if (inst.readonly) return;
      if (e.key === 'Tab') { e.preventDefault(); inst.insertTab(); return; }
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? inst.redo() : inst.undo(); return; }
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); inst.redo(); return; }
    };

    const onCompositionStart = (e) => {
      inst.composing = true;
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      inst.composeStart = sel && sel.a;
    };
    const onCompositionEnd = (e) => {
      inst.composing = false;
      const text = e.data || '';
      const pos = inst.composeStart;
      inst.composeStart = null;
      if (!pos) return;
      renderBody(pos); /* 清掉浏览器组合期间产生的原始文本, 回到模型一致 */
      if (text) inst.insertText(text);
    };

    const onPaste = (e) => {
      if (inst.readonly) { e.preventDefault(); return; }
      e.preventDefault();
      const text = (e.clipboardData && e.clipboardData.getData('text/plain')) || '';
      if (!text) return;
      const sel = modelSel();
      if (!sel) return;
      let target = sel.a;
      if (!inst.design) {
        const wp = writePosAt(sel.a);
        if (!wp) return;
        target = wp;
      }
      apply('typing', () => {
        if (!sel.collapsed) target = O.deleteRange(target, sel.b);
        let p = target;
        for (const ch of text) p = O.insertTextAt(p, ch, rPrOf(p));
        O.assertGuard(inst.doc.body, inst.guard);
        return p;
      });
    };

    /* 占位符激活: 点击后隐藏占位文字(背景提示), 光标落在字段内; 点别处恢复
     *  设计模式下激活槽位 = 后续输入填入该字段 (O.valTyping); 未激活输入为普通正文 */
    let activeSlot = null;
    const deactivateSlot = () => {
      if (activeSlot) { activeSlot.classList.remove('emr-slot-active'); activeSlot = null; }
      O.setValTyping(false);
    };
    const activateSlot = (el) => {
      deactivateSlot();
      activeSlot = el;
      O.setValTyping(true);
      el.classList.add('emr-slot-active');
    };

    const ui = window.EmrEditorUI; /* 弹层 UI: 下拉/复选/日期 (emr_editor_ui.js) */

    /** 槽位可选项: opts 自定义 或 dict 全局字典 (全院统一数据源) */
    const optsOf = (it) => {
      if (Array.isArray(it.opts) && it.opts.length) return it.opts;
      if (it.dict && window.EmrDictsOf) return window.EmrDictsOf(it.dict);
      return [];
    };

    /** 弹层选择/日期: 把选项写入槽位 (供空槽点击与已填值改选共用) */
    const applySlotChoice = (info, key, o, val) => {
      apply('选择', () => {
        O.setSlotValue(info.p, key, val);
        O.assertGuard(inst.doc.body, inst.guard);
        return { p: info.p, o: o + 1 + String(val).length, cont: info.cont };
      });
    };
    const applySlotDate = (info, key, o, val) => {
      apply('日期', () => {
        O.setSlotValue(info.p, key, val);
        O.assertGuard(inst.doc.body, inst.guard);
        return { p: info.p, o: o + 1 + String(val).length, cont: info.cont };
      });
    };

    /* 光标统一定位: 鼠标在正文按下时, 一律由我们按点击坐标放置光标与焦点
       (caretRangeFromPoint 与浏览器命中测试同源) —— 不再依赖各浏览器的原生行为,
       彻底规避「个别浏览器在 contenteditable=false 邻位不放置光标」导致的点击无效 */
    /* ---------- 设计模式: 表格框线拖拽 (列宽/行高) ---------- */
    const PX2PT = 72 / 96;
    const ptPx = (v) => (Math.round(v * 96 / 72 * 100) / 100) + 'px';
    let resizeState = null;
    const startTableResize = (e, td, colMode) => {
      e.preventDefault();
      const tblEl = td.closest('table[data-tblid]');
      if (!tblEl) return;
      const model = O.findBlockById(inst.doc.body, +tblEl.dataset.tblid);
      if (!model || model.t !== 'tbl') return;
      const trEl = td.parentElement;
      const ri = Array.prototype.indexOf.call(trEl.parentElement.children, trEl);
      const ci = td.cellIndex;
      const hasNext = ci < (model.rows[0] ? model.rows[0].cells.length - 1 : 0);
      const cellW = (r, i) => (r.cells[i] && r.cells[i].tcPr && r.cells[i].tcPr.w) || (model.grid && model.grid[i]) || 60;
      const state = {
        model, tblEl, trEl, ri, ci, hasNext,
        mode: colMode ? 'col' : 'row',
        startX: e.clientX, startY: e.clientY,
        w: cellW(model.rows[ri], ci),
        wNext: hasNext ? cellW(model.rows[ri], ci + 1) : 0,
        h: (model.rows[ri].trPr && model.rows[ri].trPr.h) || Math.round(trEl.getBoundingClientRect().height * PX2PT),
        curW: null, curWNext: null, curH: null,
        $col: [], $colNext: [],
      };
      Array.prototype.forEach.call(tblEl.rows, (tr) => {
        state.$col.push(tr.cells[ci]);
        if (hasNext && tr.cells[ci + 1]) state.$colNext.push(tr.cells[ci + 1]);
      });
      resizeState = state;
      inst.bodyEl.style.cursor = colMode ? 'col-resize' : 'row-resize';
      const onMove = (ev) => {
        if (!resizeState) return;
        ev.preventDefault();
        if (resizeState.mode === 'col') {
          const dpt = (ev.clientX - resizeState.startX) * PX2PT;
          const nw = Math.max(20, resizeState.w + dpt);
          const nwn = resizeState.hasNext ? Math.max(20, resizeState.wNext - dpt) : null;
          resizeState.curW = nw; resizeState.curWNext = nwn;
          resizeState.$col.forEach((td2) => { td2.style.width = ptPx(nw); });
          if (nwn != null) resizeState.$colNext.forEach((td2) => { td2.style.width = ptPx(nwn); });
          const m = resizeState.model;
          let sum = 0;
          (m.grid || []).forEach((g, i) => { sum += i === resizeState.ci ? nw : (i === resizeState.ci + 1 && nwn != null ? nwn : g); });
          if (!(m.grid || []).length) sum = nw + (nwn || 0);
          resizeState.tblEl.style.width = ptPx(sum);
        } else {
          const dpt = (ev.clientY - resizeState.startY) * PX2PT;
          const nh = Math.max(20, resizeState.h + dpt);
          resizeState.curH = nh;
          resizeState.trEl.style.height = ptPx(nh);
        }
      };
      const onUp = () => {
        document.removeEventListener('mousemove', onMove, true);
        document.removeEventListener('mouseup', onUp, true);
        inst.bodyEl.style.cursor = '';
        if (!resizeState) return;
        const m = resizeState.model;
        if (resizeState.mode === 'col' && resizeState.curW != null) {
          m.rows.forEach((row) => {
            const c1 = row.cells[resizeState.ci];
            if (c1) { c1.tcPr = c1.tcPr || {}; c1.tcPr.w = +resizeState.curW.toFixed(1); }
            if (resizeState.curWNext != null) {
              const c2 = row.cells[resizeState.ci + 1];
              if (c2) { c2.tcPr = c2.tcPr || {}; c2.tcPr.w = +resizeState.curWNext.toFixed(1); }
            }
          });
          m.grid = (m.rows[0] ? m.rows[0].cells.map((c) => (c.tcPr && c.tcPr.w) || 60) : []);
        } else if (resizeState.mode === 'row' && resizeState.curH != null) {
          const row = m.rows[resizeState.ri];
          if (row) { row.trPr = row.trPr || {}; row.trPr.h = +resizeState.curH.toFixed(1); }
        }
        inst.dirty = true;
        if (inst.onChange) inst.onChange(inst);
        resizeState = null;
      };
      document.addEventListener('mousemove', onMove, true);
      document.addEventListener('mouseup', onUp, true);
      if (resizeState) resizeState._rm = [onMove, onUp];
    };

    const onMouseDown = (e) => {
      if (inst.readonly || e.button !== 0) return;
      const t = e.target;
      /* 设计模式: 表格框线附近按下 → 列宽/行高拖拽 */
      if (inst.design && t.closest && !t.closest('[data-item]')) {
        const td = t.closest('td');
        if (td && td.closest('table[data-tblid]')) {
          const r = td.getBoundingClientRect();
          if ((r.right - e.clientX) <= 5) { startTableResize(e, td, true); return; }
          if ((r.bottom - e.clientY) <= 5) { startTableResize(e, td, false); return; }
        }
      }
      if (t.closest && (t.closest('.emr-slot') || t.closest('.emr-ph') || t.closest('.emr-dyn'))) return; /* 槽位/占位/动态项走 click 逻辑 */
      const x = e.clientX, y = e.clientY;
      setTimeout(() => {
        if (!inst.bodyEl.isConnected) return;
        if (document.querySelector('.emr-choice') || document.querySelector('.dtp')) return; /* 弹层打开中 */
        inst.bodyEl.focus();
        let range = null;
        try {
          if (document.caretRangeFromPoint) range = document.caretRangeFromPoint(x, y);
          else if (document.caretPositionFromPoint) {
            const p = document.caretPositionFromPoint(x, y);
            if (p) { range = document.createRange(); range.setStart(p.offsetNode, p.offset); range.collapse(true); }
          }
        } catch (err) { range = null; }
        if (range && inst.bodyEl.contains(range.startContainer)) {
          const sClick = window.getSelection();
          sClick.removeAllRanges();
          sClick.addRange(range);
          let m = C.rangeToModel(inst.bodyEl, inst.index);
          let geom = false; /* 几何回退解析: 原生光标不可解析 (点击固定标签等) */
          if (!m) {
            const pEl = (t.closest && t.closest('.p'))
              || (function () { const e2 = document.elementFromPoint(x, y); return e2 && e2.closest ? e2.closest('.p') : null; })();
            const infoG = pEl ? inst.index.get(+pEl.dataset.pid) : null;
            if (!infoG) {
              window.getSelection().removeAllRanges();
              inst._lastPos = null;
              deactivateSlot();
              return;
            }
            let bestEl = null, bestD = Infinity;
            const kids = pEl.querySelectorAll('.pc > *');
            for (const el2 of kids) {
              if (isNaN(+el2.dataset.o)) continue;
              const r2 = el2.getBoundingClientRect();
              const d2 = x < r2.left ? r2.left - x : (x > r2.right ? x - r2.right : 0);
              if (d2 < bestD) { bestD = d2; bestEl = el2; }
            }
            let geO = bestEl ? +bestEl.dataset.o : 0;
            if (bestEl && bestEl.classList.contains('emr-fx') && !bestEl.classList.contains('emr-vital-unit')) {
              /* 点击标签: 定位到其后最近填写槽 (该标签对应的控件) */
              let seen = false, so = 0, hitO = -1;
              for (const it2 of infoG.p.runs) {
                if (it2.t === 'fx' && it2.key === bestEl.dataset.key) { seen = true; so += M.itemLen(it2); continue; }
                if (seen && it2.t === 'slot') { hitO = so + 1; break; }
                so += M.itemLen(it2);
              }
              if (hitO >= 0) geO = hitO;
            }
            m = { a: { p: infoG.p, o: Math.min(Math.max(geO, 0), M.paraLen(infoG.p)), cont: infoG.cont }, collapsed: true };
            geom = true;
          }
          /* 书写模式: 位置整形 (固定区点击 → 重定向到最近槽位值; 锁定区 → 保持原光标) */
          let wp = null;
          if (!inst.design) {
            const pElHint = (t.closest && t.closest('.p')) || null;
            wp = writePosAt(m.a, pElHint);
            if (!wp) {
              window.getSelection().removeAllRanges();
              inst._lastPos = null;
              deactivateSlot();
              return;
            }
          }
          if ((wp && wp.redir) || geom) {
            const t2 = (wp && wp.redir) ? { p: wp.p, o: wp.o, cont: wp.cont } : m.a;
            C.restore(inst.bodyEl, t2);
            inst._lastPos = t2;
            /* 落在空槽占位处 → 激活 (占位透明, 立即可输入) */
            const pEl = inst.bodyEl.querySelector('.p[data-pid="' + t2.p.id + '"]');
            const phEl = pEl ? pEl.querySelector('.emr-ph[data-o="' + t2.o + '"]') : null;
            if (phEl) activateSlot(phEl); else deactivateSlot();
            return;
          }
          const s = window.getSelection();
          s.removeAllRanges();
          s.addRange(range);
          inst._lastPos = m.a;
          /* 点击落在原子项边界(如体征行槽位)时, 浏览器不渲染光标:
             按模型位置重放一次 → 注入零宽锚点, 光标可见 */
          if (m.collapsed) C.restore(inst.bodyEl, m.a);
          deactivateSlot();
        } else {
          window.getSelection().removeAllRanges();
          inst._lastPos = null;
          deactivateSlot();
        }
      }, 0);
    };

    const onClick = (e) => {
      /* 点击选项/日期槽的已填值文本: 重新弹层改选 (枚举字段以选择为准) */
      const span = e.target.closest && e.target.closest('.pc > span');
      if (span && !span.dataset.item && !inst.readonly) {
        const ppEl = span.closest('.p');
        const info2 = ppEl ? inst.index.get(+ppEl.dataset.pid) : null;
        if (info2) {
          const before = +span.dataset.o - 1;
          if (before >= 0) {
            const loc2 = M.locate(info2.p, before);
            const prev = info2.p.runs[loc2.i];
            if (prev && prev.t === 'slot') {
              const prevOpts = optsOf(prev);
              if (prevOpts.length) {
                e.preventDefault();
                deactivateSlot();
                if (prev.multi) {
                  const cur = O.valueAfterSlot(info2.p, prev.key).trim();
                  ui.openMultiChoice(span, prevOpts, cur, (val) => applySlotChoice(info2, prev.key, +span.dataset.o - 1, val));
                } else {
                  ui.openChoice(span, prevOpts, (val) => applySlotChoice(info2, prev.key, +span.dataset.o - 1, val));
                }
                return;
              }
              /* 日期槽 (设计期 dt 属性或内置 datePickers): 已填值点击重新弹层改选 (含格式) */
              const dtCfg2 = prev.dt
                ? { mode: prev.dt.mode || 'datetime', fmt: prev.dt.fmt || '' }
                : (opts.datePickers && opts.datePickers[prev.key] ? { mode: opts.datePickers[prev.key], fmt: '' } : null);
              if (dtCfg2) {
                e.preventDefault();
                deactivateSlot();
                const cur = O.valueAfterSlot(info2.p, prev.key).trim();
                const opened = ui.openDatePicker(span, dtCfg2.mode, cur, (val) => applySlotDate(info2, prev.key, +span.dataset.o - 1, O.fmtDateTime(val, dtCfg2.fmt)));
                if (opened) return;
              }
            }
          }
        }
      }
      /* 诊断类动态项: 点击一键同步诊断 (来源: 左侧「诊断」模块, 不允许手改) */
      const pEl = e.target.closest && e.target.closest('.p');
      const syncEl = pEl && pEl.querySelector('.emr-dyn-sync');
      if (pEl && syncEl) {
        e.preventDefault();
        deactivateSlot();
        if (inst.readonly || !opts.diagSync) return;
        const key = syncEl.dataset.key || 'initial_diagnosis_value';
        Promise.resolve(opts.diagSync(key)).then((text) => {
          if (text == null) return;
          inst.setDyn(key, String(text));
        }).catch(() => {});
        return;
      }
      /* 填写槽 (已填写点击值文本走上一分支): 空槽点击其占位/槽位元素 */
      const slotEl = e.target.closest && (e.target.closest('.emr-slot') || e.target.closest('.emr-ph'));
      if (!slotEl) {
        /* 点击标签/行内空白: mousedown 已重定向到占位框内 → 保持激活; 否则解除激活 */
        const sNow = window.getSelection();
        const c0 = sNow && sNow.rangeCount ? sNow.getRangeAt(0).startContainer : null;
        const elNow = c0 ? (c0.nodeType === 3 ? c0.parentElement : c0) : null;
        const phNow = elNow && elNow.closest ? elNow.closest('.emr-ph') : null;
        if (phNow) { activateSlot(phNow); return; }
        deactivateSlot();
        return;
      }
      e.preventDefault();
      const isPh = slotEl.classList.contains('emr-ph');
      const sp = slotEl.closest('.p');
      const info = inst.index.get(+sp.dataset.pid);
      if (!info) return;
      if (inst.readonly) return;
      const o = isPh ? (+slotEl.dataset.o - 1) : +slotEl.dataset.o; /* 占位 span 紧跟槽位 (占位 o = 槽位 o + 1) */
      const loc = M.locate(info.p, o);
      const item = info.p.runs[loc.i];
      if (!item || item.t !== 'slot') { deactivateSlot(); return; }
      /* 带选项的填写槽: 单选弹下拉选项即选即填; 复选弹勾选层, 确定后顿号拼接 */
      const itemOpts = optsOf(item);
      if (itemOpts.length) {
        deactivateSlot();
        if (item.multi) {
          const cur = O.valueAfterSlot(info.p, item.key).trim();
          ui.openMultiChoice(slotEl, itemOpts, cur, (val) => {
            apply('选择', () => {
              O.setSlotValue(info.p, item.key, val);
              O.assertGuard(inst.doc.body, inst.guard);
              return { p: info.p, o: o + 1 + String(val).length, cont: info.cont };
            });
          });
        } else {
          ui.openChoice(slotEl, itemOpts, (val) => {
            apply('选择', () => {
              O.setSlotValue(info.p, item.key, val);
              O.assertGuard(inst.doc.body, inst.guard);
              return { p: info.p, o: o + 1 + String(val).length, cont: info.cont };
            });
          });
        }
        return;
      }
      /* 日期类填写槽: dt 属性(设计期配置) 或 opts.datePickers(内置键) 弹出日期时间选择框 */
      const dtCfg = (item.dt || (opts.datePickers && opts.datePickers[item.key] ? { mode: opts.datePickers[item.key] } : null));
      if (dtCfg) {
        deactivateSlot();
        const cur = O.valueAfterSlot(info.p, item.key).trim();
        const opened = ui.openDatePicker(slotEl, dtCfg.mode || 'datetime', cur, (val) => {
          const shown = O.fmtDateTime(val, dtCfg.fmt);
          apply('日期', () => {
            O.setSlotValue(info.p, item.key, shown);
            O.assertGuard(inst.doc.body, inst.guard);
            return { p: info.p, o: o + 1 + String(shown).length, cont: info.cont };
          });
        });
        if (opened) return;
        /* 无选择器组件时退化为普通输入 */
      }
      /* 普通填写槽: 光标落在槽内左侧 (占位框内), 激活后占位透明 (Word 式: 立即可输入) */
      inst.bodyEl.focus();
      C.restore(inst.bodyEl, { p: info.p, o: o + 1, cont: info.cont });
      inst._lastPos = { p: info.p, o: o + 1, cont: info.cont };
      if (isPh) activateSlot(slotEl);
    };

    /** 写入动态只读项 (如 初步诊断同步) */
    inst.setDyn = (key, text) => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      const pos = sel ? sel.a : null;
      apply('同步', () => {
        const p = O.findParaWithItem(inst.doc.body, 'dyn', key);
        if (!p) { const err = new Error('缺少字段: ' + key); err.code = 'nodyn'; throw err; }
        O.setDynTextIn(p, key, text);
        O.assertGuard(inst.doc.body, inst.guard);
        return pos;
      });
    };

    const on = (el, type, fn) => {
      el.addEventListener(type, fn);
      inst._handlers.push([el, type, fn]);
    };
    on(inst.bodyEl, 'beforeinput', onBeforeInput);
    on(inst.bodyEl, 'keydown', onKeyDown);
    on(inst.bodyEl, 'compositionstart', onCompositionStart);
    on(inst.bodyEl, 'compositionend', onCompositionEnd);
    on(inst.bodyEl, 'paste', onPaste);
    on(inst.bodyEl, 'click', onClick);
    on(inst.bodyEl, 'mousedown', onMouseDown);
    on(inst.bodyEl, 'blur', () => { deactivateSlot(); inst.composing = false; });
    /* 选区变化跟踪: 记住正文内最近一次可靠光标位置 (引用写回等外部插入用) */
    const onSelChange = () => {
      const sel = window.getSelection();
      if (!sel || !sel.rangeCount) return;
      const r0 = sel.getRangeAt(0);
      if (!inst.bodyEl.contains(r0.startContainer)) return;
      const m = C.rangeToModel(inst.bodyEl, inst.index);
      if (m) inst._lastPos = m.a;
    };
    document.addEventListener('selectionchange', onSelChange);
    inst._handlers.push([document, 'selectionchange', onSelChange]);
    /* 右键: 正文内禁用原生菜单; 设计模式下定位元素/表格并回调宿主 (属性/删除) */
    on(inst.container, 'contextmenu', (e) => {
      e.preventDefault();
      if (!inst.design) return;
      const el0 = e.target.closest && e.target.closest('[data-item]');
      /* 右击「槽位值/引用值」等元素后随文本时, 归属到其前面的槽位元素 (便于改属性) */
      const valSpan = !el0 && e.target.closest ? e.target.closest('.pc > span') : null;
      const prevSlot = valSpan && valSpan.previousElementSibling && valSpan.previousElementSibling.classList
        && valSpan.previousElementSibling.classList.contains('emr-slot') ? valSpan.previousElementSibling : null;
      const el = el0 || prevSlot;
      if (el && opts.onItemContext) {
        const pEl = el.closest('.p');
        const info = pEl ? inst.index.get(+pEl.dataset.pid) : null;
        if (!info) return;
        const o = +el.dataset.o;
        const loc = M.locate(info.p, o);
        const item = info.p.runs[loc.i];
        if (!item) return;
        try { opts.onItemContext(item, info.p, o, e.clientX, e.clientY); } catch (err) { console.error('元素菜单失败', err); }
        return;
      }
      const tblEl = e.target.closest && e.target.closest('table[data-tblid]');
      if (tblEl && opts.onTableContext) {
        const td = e.target.closest && e.target.closest('td');
        const ri = td ? Array.prototype.indexOf.call(td.parentElement.parentElement.rows, td.parentElement) : 0;
        const ci = td ? td.cellIndex : 0;
        try { opts.onTableContext(+tblEl.dataset.tblid, ri, ci, e.clientX, e.clientY); } catch (err) { console.error('表格菜单失败', err); }
      }
    });
    /* 设计模式: 悬停表格框线时提示可拖拽 (列/行) */
    on(inst.bodyEl, 'mousemove', (e) => {
      if (!inst.design || resizeState) return;
      const t = e.target;
      if (!t.closest || t.closest('[data-item]')) return;
      const td = t.closest('td');
      let cur = '';
      if (td && td.closest('table[data-tblid]')) {
        const r = td.getBoundingClientRect();
        if ((r.right - e.clientX) <= 5) cur = 'col-resize';
        else if ((r.bottom - e.clientY) <= 5) cur = 'row-resize';
      }
      if (inst.bodyEl.style.cursor !== cur) inst.bodyEl.style.cursor = cur;
    });

    /* ---------- 设计模式插入 (模板制作) ---------- */
    /** 插入点解析: 实时选区优先 → 最近跟踪光标; 按块 id 重新解析为当前文档对象
     *  (撤销/重做/重渲染会替换文档对象); 无有效插入点返回 null (调用方提示, 不再兜底到文末) */
    const posNow = () => {
      let pos = null;
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (sel) pos = sel.a;
      else if (inst._lastPos) pos = inst._lastPos;
      if (!pos) return null;
      const p = O.findBlockById(inst.doc.body, pos.p.id);
      if (!p || p.t !== 'p') return null;
      const info = O.paragraphs(inst.doc.body).find((x) => x.p === p);
      if (!info) return null;
      return { p, o: Math.max(0, Math.min(pos.o, M.paraLen(p))), cont: info.cont };
    };
    /** 取插入点, 无有效位置时提示 (模板元素必须插入到点击的位置) */
    const requirePos = () => {
      const pos = posNow();
      if (!pos) Dom.toast('请先在页面中点击要插入的位置', 'error');
      return pos;
    };
    inst.insertField = (label, opts) => {
      const pos = requirePos();
      if (!pos) return;
      apply('设计', () => O.insertFieldAt(pos, label, opts));
    };
    inst.insertRef = (key, fb) => {
      const pos = requirePos();
      if (!pos) return;
      apply('设计', () => O.insertRefAt(pos, key, fb));
    };
    inst.insertItem = (item) => {
      const pos = requirePos();
      if (!pos) return;
      apply('设计', () => O.insertRunAt(pos, item));
    };
    /** 设计模式: 在当前段后插入 n×n 表格 (按页面边距计算正文可用宽) */
    inst.insertTable = (nRows, nCols) => {
      const pos = requirePos();
      if (!pos) return;
      const page = inst.doc.page || {};
      const contentW = 595 - (page.ml != null ? page.ml : 36) - (page.mr != null ? page.mr : 36);
      apply('设计', () => O.insertTableAt(pos, nRows, nCols, contentW));
    };
    /** 在当前段之后插入右对齐签名行 (医师签名：+ 签名动态项; 签名时服务端自动填入) */
    inst.insertSignLine = () => {
      const pos = requirePos();
      if (!pos) return;
      apply('设计', () => {
        const info = O.paragraphs(inst.doc.body).find((x) => x.p === pos.p);
        const np = M.para([M.fx('sign_line', '医师签名：'), M.item('dyn', { key: 'sign_names', text: '' })], { align: 'right' });
        if (info) info.cont.splice(info.i + 1, 0, np);
        else inst.doc.body.push(np);
        return { p: np, o: 0, cont: info ? info.cont : inst.doc.body };
      });
    };
    inst.insertHeading = (text) => {
      const pos = requirePos();
      if (!pos) return;
      apply('设计', () => {
        const np = O.insertParaAt(pos);
        np.p.pPr = Object.assign({}, np.p.pPr, { align: 'center', borders: { top: true } });
        np.p.rPr = Object.assign({}, np.p.rPr, { b: true, sz: 13 });
        np.p.runs = text ? [M.text(String(text))] : [];
        return { p: np.p, o: (text ? String(text).length : 0), cont: np.cont };
      });
    };
    /** 在当前段落后插入横向分隔线 (空段落 + 上框线; 同「体格检查」标题上方的分割线) */
    inst.insertDivider = () => {
      const pos = requirePos();
      if (!pos) return;
      apply('设计', () => {
        const info = O.paragraphs(inst.doc.body).find((x) => x.p === pos.p);
        const np = M.para([], { borders: { top: true } });
        if (info) info.cont.splice(info.i + 1, 0, np);
        else inst.doc.body.push(np);
        return { p: np, o: 0, cont: info ? info.cont : inst.doc.body };
      });
    };

    /* ---------- 对外 ---------- */
    inst.refresh = () => { renderBody(); inst.dirty = true; if (inst.onChange) inst.onChange(inst); };
    /** 光标模型位置: 实时选区优先, 否则返回最近跟踪位置 (外部控件点击导致选区丢失时仍可靠) */
    inst.getCaretPoint = () => {
      const s = C.rangeToModel(inst.bodyEl, inst.index);
      if (s) { inst._lastPos = s.a; return s.a; }
      return inst._lastPos;
    };
    /** 恢复光标到模型位置; 返回是否成功 (位置失效/段落已被删除时 false, 调用方应放弃插入) */
    inst.focusCaret = (pos) => {
      if (!pos || !inst.bodyEl.isConnected) return false;
      if (!C.modelToRange(inst.bodyEl, pos, pos)) return false; /* 位置失效校验 */
      inst.bodyEl.focus({ preventScroll: true }); /* 不引发画布滚动跳动 */
      C.restore(inst.bodyEl, pos); /* 元素边界自动注入零宽锚点, 保证光标可见 */
      return true;
    };
    /** 多行文本整块插入: 值内以 br 分行 (与填写槽值模型一致, 可继续编辑删除);
     *  单事务一次重渲染 (引用写回用; 不逐行闪跳) */
    inst.insertBlockText = (text) => {
      const raw = String(text || '').replace(/\r/g, '');
      if (!raw) return false;
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return false;
      let target = sel.a;
      if (!inst.design) {
        const wp = writePosAt(sel.a);
        if (!wp) return false;
        target = wp;
      }
      apply('引用', () => {
        if (!sel.collapsed) target = O.deleteRange(target, sel.b);
        let pos = target;
        const lines = raw.split('\n');
        lines.forEach((ln, i) => {
          if (i > 0) {
            const li = M.splitAt(pos.p, pos.o);
            pos.p.runs.splice(li, 0, M.item('br'));
            pos = { p: pos.p, o: pos.o + 1, cont: pos.cont };
          }
          if (ln) for (const ch of ln) pos = O.insertTextAt(pos, ch, rPrOf(pos));
        });
        O.assertGuard(inst.doc.body, inst.guard);
        return pos;
      });
      return true;
    };
    inst.removeItem = (p, item) => {
      const info = O.paragraphs(inst.doc.body).find((x) => x.p === p);
      if (!info) return;
      apply('设计', () => { O.removeItemAt(p, item); return { p, o: 0, cont: info.cont }; });
    };
    inst.getDoc = () => inst.doc;
    inst.buildPrintHTML = () => P.buildHTML(inst.doc, inst.patientInfo);
    inst.print = () => P.print(inst.doc, inst.patientInfo);
    inst.setReadonly = (ro) => {
      inst.readonly = !!ro;
      inst.bodyEl.contentEditable = ro ? 'false' : 'true';
    };
    inst.setPatientInfo = (pi) => {
      inst.patientInfo = pi || {};
      inst.headEl.innerHTML = R.headerToHTML(inst.doc.header, inst.patientInfo, inst.design);
    };
    inst.destroy = () => {
      if (resizeState) {
        if (resizeState._rm) {
          document.removeEventListener('mousemove', resizeState._rm[0], true);
          document.removeEventListener('mouseup', resizeState._rm[1], true);
        }
        resizeState = null;
      }
      if (window.EmrEditorUI && window.EmrEditorUI.dispose) window.EmrEditorUI.dispose(); /* destroy: 关闭遗留弹层(含日期) */
      O.setValTyping(false);
      O.setTrackChanges(false);
      inst._lastPos = null;
      inst._handlers.forEach(([el, type, fn]) => el.removeEventListener(type, fn));
      inst._handlers = [];
      inst.container.innerHTML = '';
    };

    renderBody(null);
    return inst;
  };

  window.EmrEditor = { create };
})();
