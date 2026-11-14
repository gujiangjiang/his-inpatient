// public/js/emr_editor/emr_doc_calc.js - 自动计算项 (dyn.calc): 公式词法/求值/缺失校验
// 公式仅依据「当前文书」内的字段代码 (slot 值/dyn/ref 文本); 变量全部有效才出结果, 否则回退为空
// 安全: 词法白名单 (数字/标识符/运算符/括号), 标识符仅限变量键与内置函数; 不允许任意脚本
(function () {
  'use strict';
  const M = window.EmrDoc;
  const EC = (window.EmrCalc = {});
  EC.FUNCS = ['round', 'abs', 'sqrt', 'min', 'max', 'ceil', 'floor'];
  const TOKEN_RE = /\d+(?:\.\d+)?|[A-Za-z_][A-Za-z0-9_]*|\*\*|[+\-*/^(),\s]+/g;

  /** 词法校验: 返回 token 数组 (含空白 token); 存在非法字符返回 null */
  EC.lex = (expr) => {
    const src = String(expr || '');
    if (!src) return null;
    const tokens = src.match(TOKEN_RE);
    if (!tokens || tokens.join('') !== src) return null;
    return tokens;
  };
  /** 公式引用的字段代码 (排除函数名); 非法表达式返回 null */
  EC.exprKeys = (expr) => {
    const tokens = EC.lex(expr);
    if (!tokens) return null;
    const keys = [];
    tokens.forEach((tk) => {
      if (!/^[A-Za-z_]/.test(tk) || EC.FUNCS.indexOf(tk) >= 0) return;
      if (keys.indexOf(tk) < 0) keys.push(tk);
    });
    return keys;
  };
  /** 槽位严格值 (val 标记 run; 与 EmrOps.valueAfterSlot 同语义) */
  const slotValue = (p, key) => {
    let on = false, s = '';
    for (const it of p.runs) {
      if (!on) { if (it.t === 'slot' && it.key === key) on = true; continue; }
      if (it.t === 'br') continue;
      if (it.t === 'text' && it.val) { s += it.text; continue; }
      break;
    }
    return s;
  };
  /** 当前文书内变量取值: slot → 值文本; dyn/ref → 文本; 不存在 → null */
  const varValue = (body, key) => {
    let out = null;
    M.walk(body || [], (b) => {
      if (out != null || b.t !== 'p') return true;
      for (const it of b.runs || []) {
        if (it.t === 'slot' && it.key === key) { out = slotValue(b, key); return false; }
        if ((it.t === 'dyn' || it.t === 'ref') && it.key === key) { out = String(it.text || ''); return false; }
      }
      return true;
    });
    return out;
  };
  EC.value = varValue;
  /** 数值提取: 去掉单位等非数字字符; 无效返回 null */
  const numOf = (s) => {
    if (s == null) return null;
    const m = String(s).replace(/[^\d.\-]/g, '');
    const v = parseFloat(m);
    return isFinite(v) ? v : null;
  };
  /** 求值: 任一变量缺失/非数值或表达式非法 → null (调用方回退为空) */
  EC.eval = (expr, body) => {
    const tokens = EC.lex(expr);
    if (!tokens) return null;
    let js = '';
    let hasVar = false;
    for (const tk of tokens) {
      if (/^\s+$/.test(tk)) { js += ' '; continue; }
      if (/^\d/.test(tk)) { js += tk; continue; }
      if (/^[A-Za-z_]/.test(tk)) {
        if (EC.FUNCS.indexOf(tk) >= 0) { js += 'Math.' + tk; continue; }
        const v = numOf(varValue(body, tk));
        if (v == null) return null;
        hasVar = true;
        js += '(' + v + ')';
        continue;
      }
      /* 运算符/括号/空白 token: ^ → JS 幂运算 ** (token 可能与其它运算符连写, 需整体替换) */
      js += tk.replace(/\^/g, '**');
    }
    if (!hasVar) return null;
    try {
      const fn = new Function('return (' + js + ');');
      const r = fn();
      return (typeof r === 'number' && isFinite(r)) ? r : null;
    } catch (e) { return null; }
  };
  /** 计算项显示文本: 按 dec 小数位格式化; 失败回退空字符串 */
  EC.calcText = (calc, body) => {
    const v = EC.eval(calc && calc.expr, body);
    if (v == null) return '';
    const dec = calc && calc.dec != null ? parseInt(calc.dec, 10) : -1;
    return dec >= 0 ? v.toFixed(dec) : String(v);
  };
  /** 保存前校验: [{name, keys}] — 公式引用了当前文书中不存在的字段代码 */
  EC.missing = (body) => {
    const out = [];
    M.walk(body || [], (b) => {
      if (b.t !== 'p') return;
      for (const it of b.runs || []) {
        if (it.t !== 'dyn' || !it.calc) continue;
        const keys = EC.exprKeys(it.calc.expr || '') || [];
        const miss = keys.filter((k) => varValue(body, k) == null);
        if (miss.length) out.push({ name: it.name || it.key, keys: miss });
      }
    });
    return out;
  };
})();
