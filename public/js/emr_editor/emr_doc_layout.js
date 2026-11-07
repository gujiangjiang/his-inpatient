// public/js/emr_editor/emr_doc_layout.js - 自动分页引擎 (打印/预览)
// 思路: 把正文按打印宽度渲染进隐藏测量容器, 逐块累加高度;
//   块放不下当前页时: 段落按文本行二进制拆分跨页, 表格按行拆分跨页;
//   输出每页的正文 HTML, 页眉/信息栏/页脚由打印模块按页组装
(function () {
  'use strict';
  const R = window.EmrRender;
  const L = (window.EmrLayout = {});
  const PX = 96 / 72     /* px per pt */;
  const PAPER = { A4: [595.3, 841.9], A5: [419.55, 595.3], Letter: [612, 792] };

  /** 页面尺寸 (pt → px); 正文区 top/height 在 paginate 里按页眉实际高度动态计算 */
  L.dim = (page) => {
    const [pw, ph] = PAPER[page.paper] || PAPER.A4;
    const w = pw * PX, h = ph * PX;
    const mt = (page.mt || 40) * PX, mb = (page.mb || 36) * PX;
    const ml = (page.ml || 36) * PX, mr = (page.mr || 36) * PX;
    const gap = (page.header_h != null ? page.header_h : 16) * PX; /* 页眉与正文间隔 */
    const fh = (page.footer_h || 40) * PX;
    return {
      w, h, ml, mr, mt, mb, gap, headerH: 0, footerH: fh,
      bodyLeft: ml, bodyTop: mt,
      bodyW: w - ml - mr,
      bodyH: h - mt - mb - fh,
    };
  };

  /* ---------- 定位与测量 ---------- */
  /** 返回 pc 中模型偏移 off 对应的 {el: pc 的直接子节点, node: 实际文本节点, off} */
  const nodeAtOffset = (pc, off) => {
    for (const node of pc.childNodes) {
      if (node.nodeType === 1) {
        const o = +node.dataset.o, n = +node.dataset.n;
        if (off < o + n) {
          if (node.dataset.item) return { el: node, node, off: 0, atomic: true };
          const tn = node.firstChild;
          return { el: node, node: tn, off: Math.min(off - o, (tn && tn.textContent.length) || 0) };
        }
      } else if (node.nodeType === 3) {
        const len = node.textContent.length;
        if (off < len) return { el: node, node, off };
      }
    }
    const last = pc.childNodes[pc.childNodes.length - 1];
    const tn = last && last.nodeType === 1 ? last.firstChild : last;
    return { el: tn, node: tn, off: tn ? (tn.textContent || '').length : 0 };
  };

  /** 测量段落从起点到模型偏移 off 的高度 */
  const measureUntil = (pc, off) => {
    const top = pc.getBoundingClientRect().top;
    const { node, off: nodeOff } = nodeAtOffset(pc, off);
    if (!node) return 0;
    const r = document.createRange();
    const startNode = pc.firstChild && pc.firstChild.nodeType === 3 ? pc.firstChild : (pc.firstChild && pc.firstChild.firstChild) || pc;
    r.setStart(startNode, 0);
    try { r.setEnd(node, nodeOff); } catch (e) { return 0; }
    const rects = r.getClientRects();
    return rects.length ? Math.max(0, rects[rects.length - 1].bottom - top) : 0;
  };

  /** 二分: 使前缀高度 <= maxH 的最大偏移 */
  const findSplitOffset = (el, maxH) => {
    const pc = el.querySelector('.pc');
    const total = pc.textContent.length;
    if (total <= 0) return 0;
    let lo = 0, hi = total;
    while (lo < hi) {
      const mid = Math.ceil((lo + hi) / 2);
      if (measureUntil(pc, mid) <= maxH) lo = mid;
      else hi = mid - 1;
    }
    return lo || 1;
  };

  /** 在模型偏移 off 处把段落 DOM 拆成 [前缀, 后缀] (原子项不可内部切开) */
  const splitParaEl = (pEl, off) => {
    const pc = pEl.querySelector('.pc');
    const { el: target, off: targetOff } = nodeAtOffset(pc, off);
    const before = pc.cloneNode(false), after = pc.cloneNode(false);
    const pb = pEl.cloneNode(false), pa = pEl.cloneNode(false);
    let inAfter = false;
    for (const ch of [...pc.childNodes]) {
      if (!inAfter) {
        if (ch === target) {
          if (ch.nodeType === 3) {
            const t1 = ch.textContent.slice(0, targetOff), t2 = ch.textContent.slice(targetOff);
            if (t1) before.appendChild(document.createTextNode(t1));
            if (t2) after.appendChild(document.createTextNode(t2));
          } else if (ch.nodeType === 1) {
            if (ch.dataset.item) {
              after.appendChild(ch); /* 原子项整体留到后缀, 保证可续测 */
            } else {
              const tn = ch.firstChild, txt = tn ? tn.textContent : '';
              const c1 = ch.cloneNode(false), c2 = ch.cloneNode(false);
              if (targetOff > 0) c1.appendChild(document.createTextNode(txt.slice(0, targetOff)));
              if (targetOff < txt.length) c2.appendChild(document.createTextNode(txt.slice(targetOff)));
              if (c1.childNodes.length) before.appendChild(c1);
              if (c2.childNodes.length) after.appendChild(c2);
            }
          }
          inAfter = true;
        } else before.appendChild(ch);
      } else after.appendChild(ch);
    }
    pb.appendChild(before); pa.appendChild(after);
    return [pb, pa];
  };

  /* ---------- 分页主流程 ---------- */
  /** 表格按行拆分: 当前页放得下的行克隆到新表, 原表保留剩余行 */
  const splitTableRows = (tableEl, remaining) => {
    const rows = [...tableEl.rows];
    let acc = 0; const keep = [];
    for (const tr of rows) {
      if (acc + tr.offsetHeight <= remaining) { keep.push(tr); acc += tr.offsetHeight; }
      else break;
    }
    if (!keep.length) keep.push(rows[0]);
    const t1 = tableEl.cloneNode(false);
    for (const tr of keep) t1.appendChild(tr);
    return [t1, tableEl];
  };

  /** 把一个块元素按当前页剩余空间放入页面 (必要时拆分) */
  const placeBlock = (el, cur, bodyH, bodyW) => {
    const h = el.offsetHeight;
    if (cur.h + h <= bodyH) { cur.els.push(el); cur.h += h; return; }
    if (h > bodyH && el.classList.contains('p')) {
      const remaining = bodyH - cur.h;
      let rest = el;
      while (rest) {
        const rh = rest.offsetHeight;
        if (cur.h + rh <= bodyH) { cur.els.push(rest); cur.h += rh; break; }
        if (!rest.classList.contains('p') || rest.textContent.length <= 0) { break; }
        const off = findSplitOffset(rest, Math.max(24, remaining));
        const [pb, pa] = splitParaEl(rest, off);
        if (pb.textContent.length) {
          cur.els.push(pb); cur.h += pb.offsetHeight;
          rest = pa.textContent.length ? pa : null;
        } else {
          /* 拆分未取得进展: 整段放入当前页, 交由 overflow:hidden 裁切, 防止死循环 */
          cur.els.push(rest); cur.h += rh;
          rest = null;
        }
      }
      return;
    }
    if (h > bodyH && el.tagName === 'TABLE') {
      const remaining = bodyH - cur.h;
      const [t1, t2] = splitTableRows(el, remaining);
      cur.els.push(t1); cur.h += t1.offsetHeight;
      cur.overflow = [t2];
      return;
    }
    cur.flush();
    cur.els.push(el); cur.h = el.offsetHeight;
  };

  /** 正文块 → 各页正文 HTML 数组 */
  L.paginate = (doc, patientInfo) => {
    const d = L.dim(doc.page);
    const meas = document.createElement('div');
    meas.className = 'emr-measure';
    meas.style.cssText = 'position:absolute;visibility:hidden;left:-99999px;top:0;'
      + 'width:' + d.bodyW + 'px;font-family:' + (doc.page.font || 'serif') + ';'
      + 'font-size:' + (doc.page.sz || 12) * PX + 'px;line-height:' + (doc.page.line || 1.5) + ';'
      + 'white-space:pre-wrap;overflow-wrap:break-word;';
    document.body.appendChild(meas);
    meas.innerHTML = R.headerToHTML(doc.header, patientInfo) + R.blocksToHTML(doc.body);

    /* 页眉实际高度动态测量: 正文从页眉下方 + 间隔开始, 避免信息栏与正文重叠 */
    const headEl = meas.querySelector('.emr-page-head');
    const headH = headEl ? headEl.offsetHeight : 0;
    d.bodyTop = d.mt + headH + d.gap;
    d.bodyH = d.h - d.mb - d.footerH - d.bodyTop;

    const headerHTML = R.headerToHTML(doc.header, patientInfo);
    const pages = [];
    const newPage = () => {
      const c = { els: [], h: 0, overflow: [] };
      c.flush = () => {
        if (c.els.length) pages.push(c.els.map((e) => e.outerHTML).join(''));
        cur = newPage();
      };
      return c;
    };
    let cur = newPage();

    for (const el of [...meas.children]) {
      if (el === headEl) continue; /* 页眉不参与正文分页 */
      placeBlock(el, cur, d.bodyH, d.bodyW);
      while (cur.overflow.length) {
        const next = cur.overflow.shift();
        cur.flush();
        placeBlock(next, cur, d.bodyH, d.bodyW);
      }
    }
    cur.flush();
    meas.remove();

    return { dim: d, headerHTML, pages, count: pages.length };
  };
})();