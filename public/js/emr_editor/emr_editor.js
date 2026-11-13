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
      onChange: opts.onChange || null, onStatus: opts.onStatus || null,
      bodyEl: null, index: new Map(), composing: false, composeStart: null,
      dirty: false, _handlers: [], _lastPos: null, /* _lastPos: 最近一次可靠的模型光标位置 */
    };

    /* ---------- 归一化: 补齐块 id (模板/迁移数据可能缺失, 渲染与光标映射必需) ---------- */
    M.ensureIds(inst.doc.body || []);
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

    /* ---------- 渲染 ---------- */
    const renderBody = (pos, end) => {
      inst.bodyEl.innerHTML = R.blocksToHTML(inst.doc.body, inst.design);
      inst.index = new Map();
      O.ctx(inst.doc.body).forEach((x) => inst.index.set(x.p.id, x));
      if (pos) {
        inst.bodyEl.focus();
        C.restore(inst.bodyEl, pos, end || pos);
        inst._lastPos = pos;
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
          if (!inst.design) {
            O.recalcBmi(inst.doc.body);          /* 派生项自动重算 (BMI) */
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

    /* ---------- 输入操作 ---------- */
    inst.insertText = (text) => {
      if (!text) return;
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      let target = sel.a;
      apply('typing', () => {
        if (!sel.collapsed) target = O.deleteRange(sel.a, sel.b);
        const np = O.insertTextAt(target, text, rPrOf(target));
        O.assertGuard(inst.doc.body, inst.guard);
        return np;
      });
    };

    inst.enter = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      let target = sel.a;
      apply('换行', () => {
        if (!sel.collapsed) target = O.deleteRange(sel.a, sel.b);
        const np = O.insertParaAt(target);
        O.assertGuard(inst.doc.body, inst.guard);
        return np;
      });
    };

    inst.lineBreak = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      let target = sel.a;
      apply('换行', () => {
        if (!sel.collapsed) target = O.deleteRange(sel.a, sel.b);
        const i = M.splitAt(target.p, target.o);
        target.p.runs.splice(i, 0, M.item('br'));
        O.assertGuard(inst.doc.body, inst.guard);
        return { p: target.p, o: target.o + 1, cont: target.cont };
      });
    };

    inst.insertTab = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      apply('制表', () => {
        let pos = sel.collapsed ? sel.a : O.deleteRange(sel.a, sel.b);
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
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (!sel) return;
      let target = sel.a;
      apply('typing', () => {
        if (!sel.collapsed) target = O.deleteRange(sel.a, sel.b);
        let p = target;
        for (const ch of text) p = O.insertTextAt(p, ch, rPrOf(p));
        O.assertGuard(inst.doc.body, inst.guard);
        return p;
      });
    };

    /* 占位符激活: 点击后隐藏占位文字(背景提示), 光标落在字段内; 点别处恢复 */
    let activeSlot = null;
    const deactivateSlot = () => {
      if (activeSlot) { activeSlot.classList.remove('emr-slot-active'); activeSlot = null; }
    };
    const activateSlot = (el) => {
      deactivateSlot();
      activeSlot = el;
      el.classList.add('emr-slot-active');
    };

    /* 下拉选项弹层 (填写槽 opts) */
    let choiceBox = null;
    const closeChoice = () => {
      if (choiceBox) { choiceBox.remove(); choiceBox = null; }
      document.removeEventListener('mousedown', onDocDown, true);
      document.removeEventListener('keydown', onChoiceKey, true);
    };
    const onDocDown = (ev) => { if (choiceBox && !choiceBox.contains(ev.target)) closeChoice(); };
    const onChoiceKey = (ev) => { if (ev.key === 'Escape') { ev.preventDefault(); closeChoice(); } };

    /* 日期类填写槽: 借全局 DateTimePicker (core/datetime_picker.js) 弹出选择框 */
    let dtpInput = null;
    const openDatePicker = (slotEl, type, current, onPick) => {
      if (typeof window.DateTimePicker === 'undefined') return false;
      if (dtpInput) { dtpInput.remove(); dtpInput = null; }
      const inp = document.createElement('input');
      inp.type = 'text';
      inp.dataset.dtpicker = type; /* 'date' | 'datetime' */
      inp.value = current || '';
      inp.style.cssText = 'position:fixed;opacity:0;width:1px;height:1px;pointer-events:none;border:0;padding:0;margin:0;';
      const r = slotEl.getBoundingClientRect();
      inp.style.left = Math.round(r.left) + 'px';
      inp.style.top = Math.round(r.bottom) + 'px';
      document.body.appendChild(inp);
      dtpInput = inp;
      let done = false;
      const finish = () => { if (!done) { done = true; if (dtpInput === inp) dtpInput = null; inp.remove(); } };
      inp.addEventListener('change', () => { const v = inp.value; finish(); if (v) onPick(v); });
      try { window.DateTimePicker.open(inp); } catch (e) { finish(); return false; }
      setTimeout(finish, 300000); /* 兜底清理 */
      return true;
    };
    /* 复选弹层: options 多选, 确定后以顿号拼接 (当前值按分隔符回填勾选) */
    const openMultiChoice = (slotEl, options, current, onOk) => {
      closeChoice();
      const box = document.createElement('div');
      box.className = 'emr-choice emr-choice-multi';
      const r = slotEl.getBoundingClientRect();
      box.style.left = Math.round(r.left) + 'px';
      box.style.top = Math.round(r.bottom + 2) + 'px';
      const cur = String(current || '').split(/[、,，;；]\s*/).filter(Boolean);
      options.forEach((opt) => {
        const lab = document.createElement('label');
        lab.className = 'emr-choice-ck';
        const ck = document.createElement('input');
        ck.type = 'checkbox';
        ck.value = opt;
        ck.checked = cur.indexOf(opt) >= 0;
        lab.appendChild(ck);
        lab.appendChild(document.createTextNode(' ' + opt));
        box.appendChild(lab);
      });
      const ft = document.createElement('div');
      ft.className = 'emr-choice-ft';
      const ok = document.createElement('button');
      ok.type = 'button'; ok.className = 'mini-btn mini-ok'; ok.textContent = '确定';
      const clr = document.createElement('button');
      clr.type = 'button'; clr.className = 'mini-btn'; clr.textContent = '清空';
      ft.appendChild(ok); ft.appendChild(clr);
      box.appendChild(ft);
      document.body.appendChild(box);
      const rr = box.getBoundingClientRect();
      if (rr.right > window.innerWidth - 8) box.style.left = Math.max(8, rr.left - (rr.right - window.innerWidth + 8)) + 'px';
      choiceBox = box;
      const mousedownGuard = (ev) => { ev.preventDefault(); };
      box.addEventListener('mousedown', mousedownGuard);
      ok.addEventListener('click', () => {
        const vals = Array.from(box.querySelectorAll('input[type=checkbox]:checked')).map((x) => x.value);
        closeChoice();
        onOk(vals.join('、'));
      });
      clr.addEventListener('click', () => { closeChoice(); onOk(''); });
      setTimeout(() => {
        document.addEventListener('mousedown', onDocDown, true);
        document.addEventListener('keydown', onChoiceKey, true);
      }, 0);
    };
    const openChoice = (slotEl, options, onPick) => {
      closeChoice();
      const box = document.createElement('div');
      box.className = 'emr-choice';
      const r = slotEl.getBoundingClientRect();
      box.style.left = Math.round(r.left) + 'px';
      box.style.top = Math.round(r.bottom + 2) + 'px';
      options.forEach((opt) => {
        const item = document.createElement('div');
        item.className = 'emr-choice-item';
        item.textContent = opt;
        item.addEventListener('mousedown', (ev) => {
          ev.preventDefault(); ev.stopPropagation();
          closeChoice();
          onPick(opt);
        });
        box.appendChild(item);
      });
      document.body.appendChild(box);
      choiceBox = box;
      setTimeout(() => {
        document.addEventListener('mousedown', onDocDown, true);
        document.addEventListener('keydown', onChoiceKey, true);
      }, 0);
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
    let rs = null;
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
      rs = state;
      inst.bodyEl.style.cursor = colMode ? 'col-resize' : 'row-resize';
      const onMove = (ev) => {
        if (!rs) return;
        ev.preventDefault();
        if (rs.mode === 'col') {
          const dpt = (ev.clientX - rs.startX) * PX2PT;
          const nw = Math.max(20, rs.w + dpt);
          const nwn = rs.hasNext ? Math.max(20, rs.wNext - dpt) : null;
          rs.curW = nw; rs.curWNext = nwn;
          rs.$col.forEach((td2) => { td2.style.width = ptPx(nw); });
          if (nwn != null) rs.$colNext.forEach((td2) => { td2.style.width = ptPx(nwn); });
          const m = rs.model;
          let sum = 0;
          (m.grid || []).forEach((g, i) => { sum += i === rs.ci ? nw : (i === rs.ci + 1 && nwn != null ? nwn : g); });
          if (!(m.grid || []).length) sum = nw + (nwn || 0);
          rs.tblEl.style.width = ptPx(sum);
        } else {
          const dpt = (ev.clientY - rs.startY) * PX2PT;
          const nh = Math.max(20, rs.h + dpt);
          rs.curH = nh;
          rs.trEl.style.height = ptPx(nh);
        }
      };
      const onUp = () => {
        document.removeEventListener('mousemove', onMove, true);
        document.removeEventListener('mouseup', onUp, true);
        inst.bodyEl.style.cursor = '';
        if (!rs) return;
        const m = rs.model;
        if (rs.mode === 'col' && rs.curW != null) {
          m.rows.forEach((row) => {
            const c1 = row.cells[rs.ci];
            if (c1) { c1.tcPr = c1.tcPr || {}; c1.tcPr.w = +rs.curW.toFixed(1); }
            if (rs.curWNext != null) {
              const c2 = row.cells[rs.ci + 1];
              if (c2) { c2.tcPr = c2.tcPr || {}; c2.tcPr.w = +rs.curWNext.toFixed(1); }
            }
          });
          m.grid = (m.rows[0] ? m.rows[0].cells.map((c) => (c.tcPr && c.tcPr.w) || 60) : []);
        } else if (rs.mode === 'row' && rs.curH != null) {
          const row = m.rows[rs.ri];
          if (row) { row.trPr = row.trPr || {}; row.trPr.h = +rs.curH.toFixed(1); }
        }
        inst.dirty = true;
        if (inst.onChange) inst.onChange(inst);
        rs = null;
      };
      document.addEventListener('mousemove', onMove, true);
      document.addEventListener('mouseup', onUp, true);
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
      if (t.closest && (t.closest('.emr-slot') || t.closest('.emr-dyn'))) return; /* 槽位/动态项走 click 逻辑 */
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
          const s = window.getSelection();
          s.removeAllRanges();
          s.addRange(range);
          const m = C.rangeToModel(inst.bodyEl, inst.index);
          if (m) inst._lastPos = m.a;
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
              if (Array.isArray(prev.opts) && prev.opts.length) {
                e.preventDefault();
                deactivateSlot();
                if (prev.multi) {
                  const cur = O.valueAfterSlot(info2.p, prev.key).trim();
                  openMultiChoice(span, prev.opts, cur, (val) => applySlotChoice(info2, prev.key, +span.dataset.o - 1, val));
                } else {
                  openChoice(span, prev.opts, (val) => applySlotChoice(info2, prev.key, +span.dataset.o - 1, val));
                }
                return;
              }
              if (opts.datePickers && opts.datePickers[prev.key]) {
                e.preventDefault();
                deactivateSlot();
                const cur = O.valueAfterSlot(info2.p, prev.key).trim();
                const opened = openDatePicker(span, opts.datePickers[prev.key], cur, (val) => applySlotDate(info2, prev.key, +span.dataset.o - 1, val));
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
      const slot = e.target.closest && e.target.closest('.emr-slot');
      if (!slot) { deactivateSlot(); return; }
      e.preventDefault();
      const sp = slot.closest('.p');
      const info = inst.index.get(+sp.dataset.pid);
      if (!info) return;
      if (inst.readonly) return;
      const o = +slot.dataset.o;
      const loc = M.locate(info.p, o);
      const item = info.p.runs[loc.i];
      /* 带选项的填写槽: 单选弹下拉选项即选即填; 复选弹勾选层, 确定后顿号拼接 */
      if (item && item.t === 'slot' && Array.isArray(item.opts) && item.opts.length) {
        deactivateSlot();
        if (item.multi) {
          const cur = O.valueAfterSlot(info.p, item.key).trim();
          openMultiChoice(slot, item.opts, cur, (val) => {
            apply('选择', () => {
              O.setSlotValue(info.p, item.key, val);
              O.assertGuard(inst.doc.body, inst.guard);
              return { p: info.p, o: o + 1 + String(val).length, cont: info.cont };
            });
          });
        } else {
          openChoice(slot, item.opts, (val) => {
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
      const dtCfg = item && item.t === 'slot'
        ? (item.dt || (opts.datePickers && opts.datePickers[item.key] ? { mode: opts.datePickers[item.key] } : null))
        : null;
      if (dtCfg) {
        deactivateSlot();
        const cur = O.valueAfterSlot(info.p, item.key).trim();
        const opened = openDatePicker(slot, dtCfg.mode || 'datetime', cur, (val) => {
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
      inst.bodyEl.focus();
      C.restore(inst.bodyEl, { p: info.p, o: o + 1, cont: info.cont });
      inst._lastPos = { p: info.p, o: o + 1, cont: info.cont };
      activateSlot(slot);
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
      if (!inst.design || rs) return;
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
    const posNow = () => {
      const sel = C.rangeToModel(inst.bodyEl, inst.index);
      if (sel) return sel.a;
      const paras = O.ctx(inst.doc.body);
      const p = paras.length ? paras[paras.length - 1].p : inst.doc.body[inst.doc.body.length - 1];
      return { p, o: M.plen(p), cont: inst.doc.body };
    };
    inst.insertField = (label, opts) => {
      apply('设计', () => O.insertFieldAt(posNow(), label, opts));
    };
    inst.insertRef = (key, fb) => {
      apply('设计', () => O.insertRefAt(posNow(), key, fb));
    };
    inst.insertItem = (item) => {
      apply('设计', () => O.insertRunAt(posNow(), item));
    };
    /** 设计模式: 在当前段后插入 n×n 表格 (按页面边距计算正文可用宽) */
    inst.insertTable = (nRows, nCols) => {
      const page = inst.doc.page || {};
      const contentW = 595 - (page.ml != null ? page.ml : 36) - (page.mr != null ? page.mr : 36);
      apply('设计', () => O.insertTableAt(posNow(), nRows, nCols, contentW));
    };
    /** 在当前段之后插入右对齐签名行 (医师签名：+ 签名动态项; 签名时服务端自动填入) */
    inst.insertSignLine = () => {
      apply('设计', () => {
        const pos = posNow();
        const info = O.ctx(inst.doc.body).find((x) => x.p === pos.p);
        const np = M.para([M.fx('sign_line', '医师签名：'), M.item('dyn', { key: 'sign_names', text: '' })], { align: 'right' });
        if (info) info.cont.splice(info.i + 1, 0, np);
        else inst.doc.body.push(np);
        return { p: np, o: 0, cont: info ? info.cont : inst.doc.body };
      });
    };
    inst.insertHeading = (text) => {
      apply('设计', () => {
        const pos = posNow();
        const np = O.insertParaAt(pos);
        np.p.pPr = Object.assign({}, np.p.pPr, { align: 'center', borders: { top: true } });
        np.p.rPr = Object.assign({}, np.p.rPr, { b: true, sz: 13 });
        np.p.runs = text ? [M.text(String(text))] : [];
        return { p: np.p, o: (text ? String(text).length : 0), cont: np.cont };
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
      const r = C.modelToRange(inst.bodyEl, pos, pos);
      if (!r) return false;
      inst.bodyEl.focus();
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(r);
      return true;
    };
    inst.removeItem = (p, item) => {
      const info = O.ctx(inst.doc.body).find((x) => x.p === p);
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
      inst._handlers.forEach(([el, type, fn]) => el.removeEventListener(type, fn));
      inst._handlers = [];
      inst.container.innerHTML = '';
    };

    renderBody(null);
    return inst;
  };

  window.EmrEditor = { create };
})();