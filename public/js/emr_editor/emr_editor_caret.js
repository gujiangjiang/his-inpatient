// public/js/emr_editor/emr_editor_caret.js - 光标/选区 ↔ 模型位置 双向映射
// DOM 段落: <div class="p" data-pid><div class="pc">[spans data-o/data-n | <br> | raw text]</div></div>
// 文本 span 内文本节点字符偏移 = 模型偏移 - data-o; 原子项(emr-fx/emr-slot)整体占 1 模型位
(function () {
  'use strict';
  const C = (window.EmrCaret = {});

  /** 单子节点占模型位长度: 元素按 data-n; 裸文本节点按文本长度(仅浏览器临时态) */
  const spanLen = (child) => {
    if (child.nodeType === 3) return child.textContent.length;
    return (+child.dataset.n || 0);
  };
  /** DOM 点 → 模型偏移 */
  C.domToOffset = (pc, startNode, startOffset) => {
    if (startNode === pc) {
      /* pc 直接子节点间的位置: 累加子节点长度 (不能累加结束偏移) */
      let off = 0;
      for (let i = 0; i < Math.min(startOffset, pc.childNodes.length); i++) off += spanLen(pc.childNodes[i]);
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
    /* 关键: 文本节点必须原样传入 (保留节点内相对偏移);
       若传其父级 span, 相对偏移会丢失 → 光标读数恒为该段文字起点
       (表现为: 按删除键光标跳回值开头、删不动文字) */
    const startNode = node === pc ? pc
      : (node.nodeType === 3 ? node : (node.nodeType === 1 ? (node.closest('.pc') || pc) : node) || pc);
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

  /** 模型偏移 → DOM 锚点: {tn,rel} 文本节点内 | {el,after} 原子边界 | {pc,rel} 段首/尾
   *  文本 run 的首/尾优先返回文本节点 (元素边界 Chrome 不渲染光标) */
  const pointAt = (pc, o) => {
    for (const child of pc.childNodes) {
      if (child.nodeType === 1) {
        const co = +child.dataset.o, cn = +child.dataset.n;
        if (child.classList && child.classList.contains('emr-anchor')) {
          /* 零宽锚点: 仅当目标偏移与其一致时使用 (防止旧锚点劫持光标) */
          if (o === co) return { tn: child.firstChild || child, rel: 0 };
          continue;
        }
        if (child.dataset.item) {
          if (o === co) return { el: child, after: false };
          if (o === co + cn) return { el: child, after: true };
        } else {
          const tn = child.firstChild;
          if (tn && tn.nodeType === 3) {
            if (o > co && o < co + cn) return { tn, rel: o - co };
            if (o === co) return { tn, rel: 0 };
            if (o === co + cn) return { tn, rel: tn.textContent.length };
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
    if (pt.tn) { r[method](pt.tn, pt.rel); return; }
    if (pt.el) {
      /* 不能用三元提取方法再调用: 会丢失 Range 接收者导致 Illegal invocation */
      if (pt.after) r[method + 'After'](pt.el);
      else r[method + 'Before'](pt.el);
      return;
    }
    r[method](pt.pc, pt.rel);
  };

  /* ---------- 光标可见性: 元素边界零宽锚点 ----------
     Chrome 在原子项(固定标签/填写槽)边界不渲染光标, 视觉上会"跑到别处";
     折叠光标落在元素边界时: 优先落相邻同偏移文本节点; 否则注入零宽锚点 span (重渲染自动清除) */
  const ZW = '\u200B';
  /** 相邻可落文本节点 (非原子项; 且模型偏移必须与目标一致) */
  const caretTextAt = (node, o, start) => {
    if (!node || node.nodeType !== 1 || (node.dataset && node.dataset.item)) return null;
    const tn = node.firstChild;
    if (!tn || tn.nodeType !== 3) return null;
    const co = +node.dataset.o, cn = +node.dataset.n;
    if (start && co !== o) return null;
    if (!start && co + cn !== o) return null;
    return { tn, rel: start ? 0 : tn.textContent.length };
  };
  const injectAnchor = (pc, ref, o) => {
    /* 光标唯一: 清除同段遗留锚点 (防止累积与布局扰动) */
    for (const a0 of pc.querySelectorAll('.emr-anchor')) a0.remove();
    const span = document.createElement('span');
    span.className = 'emr-anchor';
    span.setAttribute('data-n', '0');
    span.setAttribute('data-o', String(o));
    const tn = document.createTextNode(ZW);
    span.appendChild(tn);
    pc.insertBefore(span, ref || null); /* ref=null → 追加到段尾 */
    return { tn, rel: 0 };
  };
  /** 折叠光标点物化: 保证落在可渲染的文本节点上 */
  const materializeCaret = (pc, pt, o) => {
    if (pt.tn) return pt;
    if (pt.el) {
      const next = pt.after ? pt.el.nextSibling : pt.el.previousSibling;
      const prev = pt.after ? pt.el.previousSibling : pt.el.nextSibling;
      return caretTextAt(next, o, true) || caretTextAt(prev, o, false)
        || injectAnchor(pc, pt.after ? pt.el.nextSibling : pt.el, o);
    }
    /* 段边界 {pc, rel}: 先看左右相邻文本节点, 否则在 rel 处注入锚点 */
    const before = pc.childNodes[pt.rel - 1], after = pc.childNodes[pt.rel];
    return caretTextAt(before, o, false) || caretTextAt(after, o, true) || injectAnchor(pc, after, o);
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

  /** 恢复选区到 bodyEl (end 缺省为光标)
   *  折叠光标: 物化到可渲染文本节点 (元素边界注入零宽锚点); 非折叠选区: 常规映射 */
  C.restore = (bodyEl, pos, end) => {
    const collapsed = !end || end === pos || (end.p === pos.p && end.o === pos.o);
    if (collapsed) {
      const pEl = bodyEl.querySelector('.p[data-pid="' + pos.p.id + '"]');
      if (!pEl) return;
      const pc = pEl.querySelector('.pc');
      const pt = materializeCaret(pc, pointAt(pc, pos.o), pos.o);
      const r = document.createRange();
      r.setStart(pt.tn, pt.rel);
      r.collapse(true);
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(r);
      return;
    }
    const r = C.modelToRange(bodyEl, pos, end);
    if (!r) return;
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(r);
  };
})();