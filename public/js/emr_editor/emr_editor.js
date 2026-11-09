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
      patientInfo: opts.patientInfo || {}, readonly: !!opts.readonly,
      onChange: opts.onChange || null, onStatus: opts.onStatus || null,
      bodyEl: null, index: new Map(), composing: false, composeStart: null,
      dirty: false, _handlers: [],
    };

    /* ---------- 归一化: 补齐块 id (模板/迁移数据可能缺失, 渲染与光标映射必需) ---------- */
    M.ensureIds(inst.doc.body || []);
    if (!inst.readonly) O.recalcBmi(inst.doc.body); /* 打开即校正 BMI */

    /* ---------- DOM 搭建 ---------- */
    inst.container.innerHTML = '<div class="emr-head-wrap"></div>'
      + '<div class="emr-body" contenteditable="' + (inst.readonly ? 'false' : 'true') + '"></div>';
    inst.headEl = inst.container.querySelector('.emr-head-wrap');
    inst.bodyEl = inst.container.querySelector('.emr-body');
    inst.headEl.innerHTML = R.headerToHTML(inst.doc.header, inst.patientInfo);

    /* ---------- 历史绑定 ---------- */
    H.bind(() => inst.doc, (d) => { inst.doc = d; });
    H.clear();

    /* ---------- 渲染 ---------- */
    const renderBody = (pos, end) => {
      inst.bodyEl.innerHTML = R.blocksToHTML(inst.doc.body);
      inst.index = new Map();
      O.ctx(inst.doc.body).forEach((x) => inst.index.set(x.p.id, x));
      if (pos) {
        inst.bodyEl.focus();
        C.restore(inst.bodyEl, pos, end || pos);
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
          O.recalcBmi(inst.doc.body);          /* 派生项自动重算 (BMI) */
          /* 用户编辑 ⇒ 光标前方最近填写槽记为手动修改 (诊断同步除外; 自动填充不经此处) */
          if (label !== '同步' && pos) O.markManualAround(pos.p, pos.o);
          O.assertGuard(inst.doc.body, inst.guard);
        }, beforePos);
      } catch (e) {
        console.error('编辑事务失败', e);
        if (e && e.message) Dom.toast(e.message, 'error');
        renderBody(); /* 历史已回滚, 重建 DOM */
        return;
      }
      renderBody(pos, end);
      inst.dirty = true;
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
    const onMouseDown = (e) => {
      if (inst.readonly || e.button !== 0) return;
      const t = e.target;
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
                openChoice(span, prev.opts, (val) => applySlotChoice(info2, prev.key, +span.dataset.o - 1, val));
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
      /* 初步诊断行: 点击一键同步诊断 (来源: 左侧「诊断」的入院诊断, 不允许手改) */
      const pEl = e.target.closest && e.target.closest('.p');
      if (pEl && pEl.querySelector('.emr-dyn-sync')) {
        e.preventDefault();
        deactivateSlot();
        if (inst.readonly || !opts.diagSync) return;
        Promise.resolve(opts.diagSync()).then((text) => {
          if (text == null) return;
          inst.setDyn('initial_diagnosis_value', String(text));
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
      /* 带选项的填写槽: 弹出下拉选项, 选择即填入(替换旧值) */
      if (item && item.t === 'slot' && Array.isArray(item.opts) && item.opts.length) {
        deactivateSlot();
        openChoice(slot, item.opts, (val) => {
          apply('选择', () => {
            O.setSlotValue(info.p, item.key, val);
            O.assertGuard(inst.doc.body, inst.guard);
            return { p: info.p, o: o + 1 + String(val).length, cont: info.cont };
          });
        });
        return;
      }
      /* 日期类填写槽: 弹出日期时间选择框 */
      if (item && item.t === 'slot' && opts.datePickers && opts.datePickers[item.key]) {
        deactivateSlot();
        const cur = O.valueAfterSlot(info.p, item.key).trim();
        const opened = openDatePicker(slot, opts.datePickers[item.key], cur, (val) => {
          apply('日期', () => {
            O.setSlotValue(info.p, item.key, val);
            O.assertGuard(inst.doc.body, inst.guard);
            return { p: info.p, o: o + 1 + String(val).length, cont: info.cont };
          });
        });
        if (opened) return;
        /* 无选择器组件时退化为普通输入 */
      }
      inst.bodyEl.focus();
      C.restore(inst.bodyEl, { p: info.p, o: o + 1, cont: info.cont });
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
    /* 病历 Word 样式区域内禁用鼠标右键菜单 (避免原生菜单干扰编辑与粘贴) */
    on(inst.container, 'contextmenu', (e) => e.preventDefault());

    /* ---------- 对外 ---------- */
    inst.getDoc = () => inst.doc;
    inst.buildPrintHTML = () => P.buildHTML(inst.doc, inst.patientInfo);
    inst.print = () => P.print(inst.doc, inst.patientInfo);
    inst.setReadonly = (ro) => {
      inst.readonly = !!ro;
      inst.bodyEl.contentEditable = ro ? 'false' : 'true';
    };
    inst.setPatientInfo = (pi) => {
      inst.patientInfo = pi || {};
      inst.headEl.innerHTML = R.headerToHTML(inst.doc.header, inst.patientInfo);
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