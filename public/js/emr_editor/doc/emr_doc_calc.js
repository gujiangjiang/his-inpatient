// public/js/emr_editor/doc/emr_doc_calc.js - 自动计算项 (dyn.calc): 表达式解析/求值/缺失校验
// 公式仅依据「当前文书」内的字段代码 (slot 值/dyn/ref 文本); 变量全部有效才出结果, 否则回退为空
// 数值函数: round/abs/sqrt/min/max/ceil/floor ; 时间函数: minutes(a,b)/hours(a,b) (取字段原始时间值)
// 输出格式: num 数值(小数位) | dur 时长(分钟智能分段: <60 → "X分钟"; ≥60 → "X小时X分钟")
// 安全: 自研词法+递归下降解析 (白名单), 不使用 eval/Function
(function () {
  'use strict';
  const M = window.EmrDoc;
  const EC = (window.EmrCalc = {});
  EC.NUM_FUNCS = ['round', 'abs', 'sqrt', 'min', 'max', 'ceil', 'floor'];
  EC.TIME_FUNCS = ['minutes', 'hours'];
  EC.FUNCS = EC.NUM_FUNCS.concat(EC.TIME_FUNCS);

  /* ---------- 词法: number / ident / ( ) , / 运算符 (空白忽略; 非法字符整体拒绝) ---------- */
  const TOKEN_RE = /(\d+(?:\.\d+)?)|([A-Za-z_][A-Za-z0-9_]*)|(\*\*|[+\-*/^(),])|(\s+)/g;
  EC.lex = (expr) => {
    const src = String(expr || '');
    if (!src) return null;
    const tokens = [];
    let consumed = 0;
    let m;
    TOKEN_RE.lastIndex = 0;
    while ((m = TOKEN_RE.exec(src))) {
      if (m.index !== consumed) return null; /* 存在未覆盖的非法字符 */
      consumed = TOKEN_RE.lastIndex;
      if (m[4]) continue; /* 空白 */
      if (m[1]) tokens.push({ t: 'num', v: parseFloat(m[1]) });
      else if (m[2]) tokens.push({ t: 'id', v: m[2] });
      else if (m[3] === '(') tokens.push({ t: 'lp' });
      else if (m[3] === ')') tokens.push({ t: 'rp' });
      else if (m[3] === ',') tokens.push({ t: 'comma' });
      else tokens.push({ t: 'op', v: m[3] === '**' ? '^' : m[3] });
    }
    if (consumed !== src.length) return null;
    return tokens;
  };
  /** 公式引用的字段代码 (排除函数名); 非法表达式返回 null */
  EC.exprKeys = (expr) => {
    const tokens = EC.lex(expr);
    if (!tokens) return null;
    const keys = [];
    tokens.forEach((tk) => {
      if (tk.t !== 'id' || EC.FUNCS.indexOf(tk.v) >= 0) return;
      if (keys.indexOf(tk.v) < 0) keys.push(tk.v);
    });
    return keys;
  };

  /* ---------- 变量取值: slot → 值文本 (val, 留痕删除不计); dyn/ref → 文本 ---------- */
  const slotValue = (p, key) => {
    let on = false, s = '';
    for (const it of p.runs) {
      if (!on) { if (it.t === 'slot' && it.key === key) on = true; continue; }
      if (it.t === 'br') continue;
      if (it.t === 'text') {
        if (it.del) continue;
        if (it.val) { s += it.text; continue; }
      }
      break;
    }
    return s;
  };
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
  const numOf = (s) => {
    if (s == null) return null;
    const m = String(s).replace(/[^\d.\-]/g, '');
    const v = parseFloat(m);
    return isFinite(v) ? v : null;
  };

  /* ---------- 时间解析 (兼容多种书写格式; 未匹配/带多余内容 → null) ----------
     'YYYY-MM-DD[ HH:mm[:ss]]' (分隔符 - / . ; 也可 T)  e.g. 2026-10-11 23:50 / 2026/10/11 23:50:30
     'YYYY年M月D日[ HH时mm分[ss秒]]'                   e.g. 2026年10月11日 23时50分
     'M月D日[ HH:mm]' (无年份, 同年推演, 跨年自动)      e.g. 10月11日 23:50
     'HH:mm[:ss]' (纯时间, 跨零点自动 +24h)             e.g. 23:50 */
  const parseTime = (s) => {
    const str = String(s == null ? '' : s).trim();
    if (!str) return null;
    let m = str.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/);
    if (m) return { d: new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0)) };
    m = str.match(/^(\d{4})年\s*(\d{1,2})月\s*(\d{1,2})日(?:\s*(\d{1,2})\s*[时:]\s*(\d{1,2})\s*分?(?:(\d{1,2})\s*秒)?)?$/);
    if (m) return { d: new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0)) };
    m = str.match(/^(\d{1,2})月\s*(\d{1,2})日(?:\s*(\d{1,2})\s*[时:]\s*(\d{1,2})\s*分?(?:(\d{1,2})\s*秒)?)?$/);
    if (m) return { d: new Date(2000, +m[1] - 1, +m[2], +(m[3] || 0), +(m[4] || 0), +(m[5] || 0)), noYear: true };
    m = str.match(/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/);
    if (m) return { only: true, mins: +m[1] * 60 + +m[2] + (+(m[3] || 0)) / 60 };
    return null;
  };
  /** 时间差(分钟):
   *  完整日期(含年月日): 时间戳差 — 跨天/跨月/跨年均正确 (如 2026-10-11 23:50 → 2026-10-12 01:50 = 120)
   *  纯时间: 结束早于开始自动按跨零点 +24h
   *  无年份(月日): 结束早于开始按跨年 +1 年
   *  完整日期结束早于开始 → null (数据有误, 回退为空) */
  const diffMinutes = (a, b) => {
    const ta = parseTime(a), tb = parseTime(b);
    if (!ta || !tb) return null;
    if (ta.only || tb.only) {
      if (!(ta.only && tb.only)) return null; /* 纯时间与完整日期混用不支持 */
      let d = tb.mins - ta.mins;
      if (d < 0) d += 1440;
      return d;
    }
    let d = (tb.d.getTime() - ta.d.getTime()) / 60000;
    if (d < 0) {
      if (ta.noYear && tb.noYear) {
        const tb2 = new Date(tb.d.getTime());
        tb2.setFullYear(tb2.getFullYear() + 1); /* 无年份: 视为跨年 (12月31日 → 1月1日) */
        d = (tb2.getTime() - ta.d.getTime()) / 60000;
      } else {
        return null;
      }
    }
    return d;
  };

  /* ---------- 递归下降求值 ---------- */
  const evalTokens = (tokens, lookup) => {
    let i = 0;
    const peek = () => tokens[i];
    const next = () => tokens[i++];
    const fail = () => { throw 'calc'; };

    const parseExpr = () => {
      let v = parseTerm();
      while (peek() && peek().t === 'op' && (peek().v === '+' || peek().v === '-')) {
        const op = next().v; const r = parseTerm();
        v = op === '+' ? v + r : v - r;
      }
      return v;
    };
    const parseTerm = () => {
      let v = parseFactor();
      while (peek() && peek().t === 'op' && (peek().v === '*' || peek().v === '/')) {
        const op = next().v; const r = parseFactor();
        v = op === '*' ? v * r : v / r;
      }
      return v;
    };
    const parseFactor = () => {
      const v = parseUnary();
      if (peek() && peek().t === 'op' && peek().v === '^') { next(); return Math.pow(v, parseFactor()); }
      return v;
    };
    const parseUnary = () => {
      if (peek() && peek().t === 'op' && peek().v === '-') { next(); return -parseUnary(); }
      if (peek() && peek().t === 'op' && peek().v === '+') { next(); return parseUnary(); }
      return parsePrimary();
    };
    /** 函数参数: 裸字段代码 → 原始字符串值 (供时间函数); 其余 → 数值表达式 */
    const parseArg = () => {
      const tk = peek();
      const nx = tokens[i + 1];
      if (tk && tk.t === 'id' && EC.FUNCS.indexOf(tk.v) < 0 && nx && (nx.t === 'comma' || nx.t === 'rp')) {
        i++;
        const raw = lookup(tk.v);
        return { raw: raw == null ? null : String(raw), num: numOf(raw) };
      }
      const num = parseExpr();
      return { raw: null, num };
    };
    const callFn = (name, args) => {
      if (EC.TIME_FUNCS.indexOf(name) >= 0) {
        if (args.length !== 2 || args[0].raw == null || args[1].raw == null) fail();
        const d = diffMinutes(args[0].raw, args[1].raw);
        if (d == null) fail();
        return name === 'hours' ? d / 60 : d;
      }
      const nums = args.map((a) => a.num);
      if (nums.some((x) => x == null || !isFinite(x))) fail();
      switch (name) {
        case 'round': return nums.length >= 2 ? Math.round(nums[0] * Math.pow(10, nums[1])) / Math.pow(10, nums[1]) : Math.round(nums[0]);
        case 'abs': return Math.abs(nums[0]);
        case 'sqrt': return Math.sqrt(nums[0]);
        case 'ceil': return Math.ceil(nums[0]);
        case 'floor': return Math.floor(nums[0]);
        case 'min': return Math.min.apply(null, nums);
        case 'max': return Math.max.apply(null, nums);
        default: fail();
      }
    };
    const parsePrimary = () => {
      const tk = peek();
      if (!tk) fail();
      if (tk.t === 'num') { next(); return tk.v; }
      if (tk.t === 'lp') { next(); const v = parseExpr(); if (!peek() || peek().t !== 'rp') fail(); next(); return v; }
      if (tk.t === 'id') {
        next();
        const name = tk.v;
        if (peek() && peek().t === 'lp') {
          next();
          const args = [];
          if (!peek() || peek().t !== 'rp') {
            for (;;) {
              args.push(parseArg());
              if (peek() && peek().t === 'comma') { next(); continue; }
              break;
            }
          }
          if (!peek() || peek().t !== 'rp') fail();
          next();
          return callFn(name, args);
        }
        if (EC.FUNCS.indexOf(name) >= 0) fail(); /* 函数名后缺括号 */
        const raw = lookup(name);
        const v = numOf(raw);
        if (v == null) fail();
        return v;
      }
      fail();
    };

    const out = parseExpr();
    if (i !== tokens.length) fail(); /* 存在未消费 token (表达式不完整/非法) */
    return out;
  };

  /** 求值: 任一变量缺失/非数值/时间无效或表达式非法 → null (调用方回退为空) */
  EC.eval = (expr, body) => {
    const tokens = EC.lex(expr);
    if (!tokens) return null;
    try {
      const r = evalTokens(tokens, (k) => varValue(body, k));
      return (typeof r === 'number' && isFinite(r)) ? r : null;
    } catch (e) { return null; }
  };

  /** 时长智能分段: <60 → 'X分钟'; ≥60 → 'X小时X分钟' (整分为 0 时省略) */
  EC.formatDuration = (minutes) => {
    if (minutes == null || !isFinite(minutes) || minutes < 0) return '';
    const m = Math.round(minutes);
    if (m < 60) return m + '分钟';
    const h = Math.floor(m / 60), rem = m % 60;
    return rem === 0 ? (h + '小时') : (h + '小时' + rem + '分钟');
  };

  /** 计算项显示文本: fmt=dur → 时长分段; fmt=num(默认) → 按 dec 小数位; 失败回退空 */
  EC.calcText = (calc, body) => {
    const v = EC.eval(calc && calc.expr, body);
    if (v == null) return '';
    if (calc && calc.fmt === 'dur') return EC.formatDuration(v);
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
