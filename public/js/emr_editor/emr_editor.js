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
          O.assertGuard(inst.doc.body, inst.guard);
        }, beforePos);
      } catch (e) {
        if (e.code === 'protected') Dom.toast(e.message, 'error');
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
      if (inst.composing) { if (t === 'insertCompositionText') return; e.preventDefault(); return; }
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

    const onClick = (e) => {
      const slot = e.target.closest && e.target.closest('.emr-slot');
      if (!slot) return;
      e.preventDefault();
      const pEl = slot.closest('.p');
      const info = inst.index.get(+pEl.dataset.pid);
      if (!info) return;
      const o = (+slot.dataset.o) + 1;
      inst.bodyEl.focus();
      C.restore(inst.bodyEl, { p: info.p, o, cont: info.cont });
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