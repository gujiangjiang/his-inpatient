// public/js/emr_editor/emr_editor_caret.js - 光标/选区 ↔ 模型位置 双向映射
// DOM 段落: <div class="p" data-pid><div class="pc">[spans data-o/data-n | <br> | raw text]</div></div>
// 文本 span 内文本节点字符偏移 = 模型偏移 - data-o; 原子项(emr-fx/emr-slot)整体占 1 模型位
(function () {
  'use strict';
  const C = (window.EmrCaret = {});

  /** 单子节点占模型位: 元素按 data-o/data-n; 裸文本节点按文本长度(仅浏览器临时态) */
  const spanEnd = (child) => {
    if (child.nodeType === 3) return child.textContent.length;
    return (+child.dataset.o || 0) + (+child.dataset.n || 0);
  };

  /** DOM 点 → 模型偏移 */
  C.domToOffset = (pc, startNode, startOffset) => {
    if (startNode === pc) {
      let off = 0;
      for (let i = 0; i < Math.min(startOffset, pc.childNodes.length); i++) off += spanEnd(pc.childNodes[i]);
      return off;
    }
    let acc = 0;
    for (const child of pc.childNodes) {
      if (child.nodeType === 3) {
        if (child === startNode) return acc + (startOffset || 0);
        acc += child.textContent.length;
      } else if (child.nodeType === 1) {
        const o = +child.dataset.o, n = +child.dataset.n;
        if (child === startNode || child.contains(startNode)) {
          if (child.dataset.item) return o; /* 原子项内 → 取起点 */
          const rel = startNode.nodeType === 3 ? (startOffset || 0) : 0;
          return o + Math.min(rel, n);
        }
        acc = o + n;
      }
    }
    return acc;
  };

  /** 单个 DOM 点 → {p, o} (index: id→{p,cont,i}) */
  C.pointToModel = (bodyEl, index, node, offset) => {
    const pEl = node.nodeType === 1
      ? (node.classList && node.classList.contains('p') ? node : (node.closest && node.closest('.p')) || null)
      : (node.parentElement && node.parentElement.closest('.p')) || null;
    if (!pEl || !bodyEl.contains(pEl)) return null;
    const pc = pEl.querySelector('.pc');
    const info = index.get(+pEl.dataset.pid);
    if (!info) return null;
    const startNode = node === pc ? pc : (node.nodeType === 1 ? (node.closest('.pc') || pc) : node.parentElement) || pc;
    const o = C.domToOffset(pc, startNode, offset);
    return { p: info.p, o, cont: info.cont };
  };

  /** 当前 DOM 选区 → 模型 {a,b,collapsed} */
  C.rangeToModel = (bodyEl, index) => {
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount) return null;
    const r = sel.getRangeAt(0);
    const a = C.pointToModel(bodyEl, index, r.startContainer, r.startOffset);
    const b = C.pointToModel(bodyEl, index, r.endContainer, r.endOffset);
    if (!a || !b) return null;
    return { a, b, collapsed: r.collapsed };
  };

  /** 模型偏移 → DOM 锚点: {tn,rel} 文本节点内 | {el,after} 原子边界 | {pc,rel} 段首/尾 */
  const pointAt = (pc, o) => {
    for (const child of pc.childNodes) {
      if (child.nodeType === 1) {
        const co = +child.dataset.o, cn = +child.dataset.n;
        if (child.dataset.item) {
          if (o === co) return { el: child, after: false };
          if (o === co + cn) return { el: child, after: true };
        } else {
          const tn = child.firstChild;
          if (tn && tn.nodeType === 3) {
            if (o > co && o < co + cn) return { tn, rel: o - co };
            if (o === co) return { el: child, after: false };
            if (o === co + cn) return { el: child, after: true };
          }
        }
      } else if (child.nodeType === 3) {
        if (o <= child.textContent.length) return { tn: child, rel: o };
        o -= child.textContent.length;
      }
    }
    return { pc, rel: pc.childNodes.length };
  };
  const apply = (r, method, pt) => {
    if (pt.tn) r[method](pt.tn, pt.rel);
    else if (pt.el) (pt.after ? r[method + 'After'] : r[method + 'Before'])(pt.el);
    else r[method](pt.pc, pt.rel);
  };

  /** 模型位置 → DOM Range (a 必给, b 缺省为光标) */
  C.modelToRange = (bodyEl, a, b) => {
    const pElA = bodyEl.querySelector('.p[data-pid="' + a.p.id + '"]');
    if (!pElA) return null;
    const r = document.createRange();
    apply(r, 'setStart', pointAt(pElA.querySelector('.pc'), a.o));
    if (b && b.p !== a.p) {
      const pElB = bodyEl.querySelector('.p[data-pid="' + b.p.id + '"]');
      if (!pElB) return null;
      apply(r, 'setEnd', pointAt(pElB.querySelector('.pc'), b.o));
    } else if (b && b.p === a.p) {
      apply(r, 'setEnd', pointAt(pElA.querySelector('.pc'), b.o));
    } else {
      apply(r, 'setEnd', pointAt(pElA.querySelector('.pc'), a.o));
    }
    return r;
  };

  /** 恢复选区到 bodyEl (end 缺省为光标) */
  C.restore = (bodyEl, pos, end) => {
    const r = C.modelToRange(bodyEl, pos, end || pos);
    if (!r) return;
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(r);
  };
})();