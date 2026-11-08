// public/js/emr_editor/emr_doc_hist.js - 病历文档事务历史 (撤销/重做)
// 采用整文档快照 (病历 doc_json 体量小, JSON 深拷贝开销可接受):
//   EmrHist.tx(label, run, pos): 事务内 run() 操作模型; 抛错自动回滚并上抛
//   连续打字 (label='typing', 800ms 内) 合并为一个撤销步; 撤销/重做还原光标位置
(function () {
  'use strict';
  const H = (window.EmrHist = {
    undo: [], redo: [], limit: 100, at: 0,
    get: null, set: null, /* 由编辑器 bind(getDoc, setDoc) 注入 */
  });

  H.bind = (get, set) => { H.get = get; H.set = set; };

  H.snap = () => JSON.stringify(H.get());  /* 快照为 JSON 字符串 (undoOne/redoOne 按字符串还原) */

  /** 执行一个事务: run() 修改模型; 失败回滚并上抛, 供调用方提示"受保护内容不可修改" */
  H.tx = function (label, run, pos) {
    if (!H.get || !H.set) { run(); return; }
    const before = H.snap();
    let after = null;
    try {
      run();
      after = H.snap();
    } catch (e) {
      H.set(JSON.parse(before)); /* 回滚到事务前 */
      throw e;
    }
    H.push(label, before, after, pos);
  };

  /** 内部入栈: 打字事务合并 */
  H.push = (label, before, after, pos) => {
    const last = H.undo[H.undo.length - 1];
    if (label === 'typing' && last && last.label === 'typing' && Date.now() - last.at < 800) {
      last.after = after; last.at = Date.now(); last.pos = pos;
      return;
    }
    H.undo.push({ label, before, after, at: Date.now(), pos });
    if (H.undo.length > H.limit) H.undo.shift();
    H.redo = [];
  };

  H.undoOne = function () {
    const e = H.undo.pop();
    if (!e) return null;
    H.redo.push(e);
    H.set(JSON.parse(e.before));
    return e.pos || null;
  };

  H.redoOne = function () {
    const e = H.redo.pop();
    if (!e) return null;
    H.undo.push(e);
    H.set(JSON.parse(e.after));
    return e.pos || null;
  };

  H.canUndo = () => H.undo.length > 0;
  H.canRedo = () => H.redo.length > 0;

  /** 清空历史 (打开新文书时) */
  H.clear = () => { H.undo = []; H.redo = []; };
})();