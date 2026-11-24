// public/js/nurse/print/temperature_chart.js - 电子护理体温单 (三测单) 渲染引擎
// 纯原生 SVG (零第三方库), 一套绘图内核同时服务:
//   1) 页面交互视图 TempChart.render(container, data) —— 鼠标悬停 Tooltip + 高亮;
//   2) A4 打印静态文档 TempChart.buildPrintPages(data, patient) —— 预渲染 SVG 内嵌 HTML,
//      经 PrintPreview.combine 输出 (打印文档不执行脚本, 由本文件在父页面预先生成全部 SVG)。
// 数据契约见 api/modules/nurse/vitals/chart.php (接口 nurse/temperature-chart, demo=1 为全场景演示数据)。
// 坐标系: 42 列 (7 天×6 时点) × 40 小格; 体温 34~42℃ / 脉搏 20~180 次 (同 40 格, 每小格 0.2℃·4 次);
//         呼吸 0~70 次 (35 小格); 37℃=80 次=30 次/分 同一红线; 疼痛 0~10 分独立区 (5 行)。
(function () {
    'use strict';

    /* ==================== 尺寸与配色常量 ==================== */
    var SLOT_W = 16, GRID_W = 42 * SLOT_W;      // 672px
    var RAIL_W = 50;                            // 左侧三轴刻度栏
    var ROWS = 40, SH = 11, GRID_H = ROWS * SH; // 主网格 40 小格 × 11px
    var PAIN_SH = 12, PAIN_H = 5 * PAIN_SH;     // 疼痛区 5 行 × 12px
    var SLOTS = [2, 6, 10, 14, 18, 22];
    var COL = {
        temp: '#1d5bd8', pulse: '#dc2626', breath: '#1d5bd8', pain: '#1d5bd8',
        grid: '#d7dee8', gridBig: '#93a3b8', red: '#dc2626', blue: '#1d5bd8', text: '#303133'
    };
    var TYPE_NAME = { oral: '口表', axillary: '腋表', rectal: '肛表', ear: '耳表' };

    /* ==================== 坐标换算 (独立函数, 像素级对齐底层网格) ==================== */
    function yTemp(t) { return (ROWS - (t - 34) / 0.2) * SH; }        // 34℃→底, 42℃→顶 (40 格)
    function yPulse(v) { return (ROWS - (v - 20) / 4) * SH; }         // 20→底, 180→顶
    function yBreath(v) { return (ROWS - v / 2) * SH; }               // 0→底, 70→35 格
    function yPain(v) { return (5 - v / 2) * PAIN_SH; }               // 0→底, 10→顶
    function xSlot(i) { return i * SLOT_W + SLOT_W / 2; }             // 槽位列中心
    function clampY(v) { return Math.max(1, Math.min(GRID_H - 1, v)); }

    /* ==================== 基础元件 ==================== */
    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function n(v) { return Math.round(v * 10) / 10; }
    function line(x1, y1, x2, y2, stroke, w, crisp) {
        return '<line x1="' + n(x1) + '" y1="' + n(y1) + '" x2="' + n(x2) + '" y2="' + n(y2) + '" stroke="' + stroke + '" stroke-width="' + w + '"' + (crisp ? ' shape-rendering="crispEdges"' : '') + '/>';
    }
    function text(x, y, str, o) {
        o = o || {};
        return '<text x="' + n(x) + '" y="' + n(y) + '" font-size="' + (o.size || 8) + '" fill="' + (o.fill || COL.text) + '"'
            + ' text-anchor="' + (o.anchor || 'middle') + '"' + (o.italic ? ' font-style="italic"' : '') + (o.weight ? ' font-weight="' + o.weight + '"' : '') + '>' + esc(str) + '</text>';
    }
    /* 竖排文字 (顶部事件/底部未测标注): 旋转 -90° */
    function vtext(x, y, str, fill, size) {
        return '<text x="' + n(x) + '" y="' + n(y) + '" transform="rotate(-90 ' + n(x) + ' ' + n(y) + ')" font-size="' + (size || 9) + '" fill="' + fill + '" text-anchor="middle" font-weight="600">' + esc(str) + '</text>';
    }
    function svg(w, h, body, cls) {
        return '<svg class="' + (cls || '') + '" viewBox="0 0 ' + w + ' ' + h + '" width="' + w + '" height="' + h + '" xmlns="http://www.w3.org/2000/svg" font-family="inherit">' + body + '</svg>';
    }
    function dot(x, y, r, color, tip) {
        return '<circle cx="' + n(x) + '" cy="' + n(y) + '" r="' + r + '" fill="' + color + '"' + (tip ? ' data-tip="' + esc(tip) + '"' : '') + '/>';
    }
    function circle(x, y, r, color, tip, fill) {
        return '<circle cx="' + n(x) + '" cy="' + n(y) + '" r="' + r + '" fill="' + (fill || '#fff') + '" stroke="' + color + '" stroke-width="1.3"' + (tip ? ' data-tip="' + esc(tip) + '"' : '') + '/>';
    }
    function polyline(pts, color, w, dash) {
        if (pts.length < 2) return '';
        var str = pts.map(function (p) { return n(p.x) + ',' + n(p.y); }).join(' ');
        return '<polyline points="' + str + '" fill="none" stroke="' + color + '" stroke-width="' + w + '" stroke-linejoin="round" stroke-linecap="round"' + (dash ? ' stroke-dasharray="' + dash + '"' : '') + '/>';
    }
    /* 箭头 (向上/向下, len 像素): 用于越界体温/脉搏与 35℃ 下箭头 */
    function upArrow(x, yTip, len, color, dx) {
        var x0 = x + (dx || 0), y0 = yTip + len;
        return '<g stroke="' + color + '" stroke-width="1.2" fill="none">'
            + line(x0, y0, x0, yTip, color, 1.2, false)
            + '<polyline points="' + n(x0 - 2.2) + ',' + n(yTip + 3) + ' ' + n(x0) + ',' + n(yTip) + ' ' + n(x0 + 2.2) + ',' + n(yTip + 3) + '" fill="none"/></g>';
    }
    function downArrow(x, yTip, len, color, dx) {
        var x0 = x + (dx || 0), y0 = yTip + len;
        return '<g stroke="' + color + '" stroke-width="1.2" fill="none">'
            + line(x0, yTip, x0, y0, color, 1.2, false)
            + '<polyline points="' + n(x0 - 2.2) + ',' + n(y0 - 3) + ' ' + n(x0) + ',' + n(y0) + ' ' + n(x0 + 2.2) + ',' + n(y0 - 3) + '" fill="none"/></g>';
    }
    function painTriangle(x, y, tip) {
        return '<polygon points="' + n(x) + ',' + n(y - 3.2) + ' ' + n(x - 3.2) + ',' + n(y + 2.6) + ' ' + n(x + 3.2) + ',' + n(y + 2.6) + '" fill="#fff" stroke="' + COL.pain + '" stroke-width="1.3"' + (tip ? ' data-tip="' + esc(tip) + '"' : '') + '/>';
    }
    /* 起搏器心率: 带 H 的红圈 */
    function pacemaker(x, y, tip) {
        return '<g data-tip="' + esc(tip) + '"><circle cx="' + n(x) + '" cy="' + n(y) + '" r="4.4" fill="#fff" stroke="' + COL.pulse + '" stroke-width="1.3"/>'
            + text(x, y + 2.6, 'H', { size: 6.5, fill: COL.pulse, weight: '600' }) + '</g>';
    }
    /* 体温符号 (按测量途径): 口表●/腋表×/肛表○/耳表△ */
    function tempSymbol(x, y, type, tip) {
        switch (type) {
            case 'rectal': return circle(x, y, 3, COL.temp, tip);
            case 'ear': return painTriangle(x, y, tip).replace('stroke="' + COL.pain, 'stroke="' + COL.temp);
            case 'axillary': return '<g data-tip="' + esc(tip) + '">'
                + line(x - 3, y - 3, x + 3, y + 3, COL.temp, 1.4, false)
                + line(x - 3, y + 3, x + 3, y - 3, COL.temp, 1.4, false) + '</g>';
            default: return dot(x, y, 2.7, COL.temp, tip);   // 口表
        }
    }
    function tipDateOf(date, i) { return parseInt(date.slice(5, 7), 10) + '月' + parseInt(date.slice(8, 10), 10) + '日 ' + SLOTS[i] + ':00'; }

    /* ==================== 主网格: 表格底纹由 SVG 精确绘制 ==================== */
    function gridLines() {
        var s = '', i, r, d;
        for (i = 0; i <= 42; i++) {
            if (i % 6 === 0 && i > 0 && i < 42) continue;      // 天分隔红线单独绘制
            s += line(i * SLOT_W, 0, i * SLOT_W, GRID_H, COL.grid, 1, true);
        }
        for (r = 0; r <= ROWS; r++) {
            var big = r % 5 === 0;
            s += line(0, r * SH, GRID_W, r * SH, big ? COL.gridBig : COL.grid, big ? 1 : 0.6, true);
        }
        s += line(0, yTemp(37), GRID_W, yTemp(37), COL.red, 1.6, true);   // 37℃/80次/30次 红粗线
        for (d = 1; d < 7; d++) s += line(d * 6 * SLOT_W, 0, d * 6 * SLOT_W, GRID_H, COL.red, 2, true);
        return s;
    }
    /* 左侧三轴刻度栏: 体温(蓝)/呼吸(蓝)/脉搏(红) 三列数字 + 大小刻度 */
    function railSvg() {
        var s = '', r, t, b, p;
        for (r = 0; r <= ROWS; r++) {
            var big = r % 5 === 0, y = r * SH;
            s += line(46, y, RAIL_W - 1, y, big ? COL.gridBig : COL.grid, big ? 1 : 0.6, true);
            if (big) s += line(2, y, RAIL_W - 1, y, COL.grid, 0.5, true);
        }
        for (t = 34; t <= 42; t++) s += text(12, yTemp(t) + 2.6, t, { size: 7.5, fill: COL.temp, anchor: 'end' });
        for (b = 0; b <= 70; b += 10) s += text(28, yBreath(b) + 2.6, b, { size: 7.5, fill: COL.breath, anchor: 'end' });
        for (p = 20; p <= 180; p += 20) s += text(43, yPulse(p) + 2.6, p, { size: 7.5, fill: COL.pulse, anchor: 'end' });
        return svg(RAIL_W, GRID_H, s, 'tc-rail-svg');
    }

    /* ==================== 折线分段: 相邻有效点相连; 拒测/外出槽位截断 ==================== */
    function curveSegments(days, getPoint) {
        var segs = [], cur = null;
        days.forEach(function (day) {
            day.slots.forEach(function (s, i) {
                if (s.unmeasured) { if (cur && cur.length > 1) segs.push(cur); cur = null; return; }
                var pt = getPoint(s, i, day);
                if (!pt) return;
                if (!cur) cur = [];
                cur.push(pt);
            });
        });
        if (cur && cur.length > 1) segs.push(cur);
        return segs;
    }
    function clampTempY(v) { return v < 35 ? yTemp(35) : (v > 42 ? yTemp(42) : yTemp(v)); }

    function tempCurve(days) {
        return curveSegments(days, function (s, i) {
            return s.temp && s.temp.v != null ? { x: xSlot(i), y: clampTempY(s.temp.v) } : null;
        }).map(function (seg) { return polyline(seg, COL.temp, 1.3); }).join('');
    }
    function pulseCurve(days) {
        return curveSegments(days, function (s, i) {
            return s.pulse != null ? { x: xSlot(i), y: yPulse(Math.min(s.pulse, 180)) } : null;
        }).map(function (seg) { return polyline(seg, COL.pulse, 1.2); }).join('');
    }
    function hrCurve(days) {
        return curveSegments(days, function (s, i) {
            return s.hr != null ? { x: xSlot(i), y: yPulse(Math.min(s.hr, 180)) } : null;
        }).map(function (seg) { return polyline(seg, COL.pulse, 1.2); }).join('');
    }
    function breathCurve(days) {
        return curveSegments(days, function (s, i) {
            return s.breath != null ? { x: xSlot(i), y: yBreath(Math.min(s.breath, 70)) } : null;
        }).map(function (seg) { return polyline(seg, COL.breath, 1.1); }).join('');
    }

    /* ==================== 脉搏短绌: 心率折线与脉搏折线围成区域 → 红斜线填充 ==================== */
    function deficitFills(days, seq) {
        var out = [], run = [];
        var hatch = 'url(#tcHatch' + (seq || 0) + ')';
        var flush = function () {
            if (run.length >= 2) {
                var top = run.map(function (p) { return n(p.x) + ',' + n(p.yh); }).join(' ');
                var bottom = run.slice().reverse().map(function (p) { return n(p.x) + ',' + n(p.yp); }).join(' ');
                out.push('<polygon points="' + top + ' ' + bottom + '" fill="' + hatch + '" stroke="none"/>');
            }
            run = [];
        };
        days.forEach(function (day) {
            day.slots.forEach(function (s, i) {
                if (s.unmeasured) { flush(); return; }
                if (s.hr != null && s.pulse != null && s.hr > s.pulse) {
                    run.push({ x: xSlot(i), yh: yPulse(Math.min(s.hr, 180)), yp: yPulse(Math.min(s.pulse, 180)) });
                } else flush();
            });
        });
        flush();
        return out.join('');
    }

    /* ==================== 槽位符号绘制 (含重叠规则) ==================== */
    function slotSymbols(days) {
        var out = [];
        days.forEach(function (day) {
            day.slots.forEach(function (s, i) {
                if (s.unmeasured) return;
                var x = xSlot(i);
                var when = tipDateOf(day.date, i);
                var temp = s.temp && s.temp.v != null ? s.temp : null;
                var pulse = s.pulse != null ? Math.min(s.pulse, 180) : null;
                var hr = s.hr != null ? s.hr : null;
                var breath = s.breath != null ? s.breath : null;

                var yT = temp ? clampTempY(temp.v) : null;
                var yP = pulse != null ? yPulse(pulse) : null;
                var yH = hr != null ? yPulse(Math.min(hr, 180)) : null;
                var yB = breath != null ? yBreath(Math.min(breath, 70)) : null;

                /* 重叠判定 (坐标重合 ε) */
                var oTP = temp && pulse != null && Math.abs(yT - yP) < 0.9;                       // 体温×脉搏
                var oTH = temp && hr != null && !s.pacemaker && yH != null && Math.abs(yT - yH) < 0.9; // 心率×体温 → H
                var oBP = breath && pulse != null && Math.abs(yB - yP) < 0.9;                     // 呼吸×脉搏

                /* 1) 体温 + 脉搏 (重叠组合按规范) */
                if (oTP) {
                    var tipT = when + '｜' + TYPE_NAME[temp.type] + ' ' + Number(temp.v).toFixed(1) + '℃';
                    var tipP = when + '｜脉搏 ' + s.pulse;
                    if (temp.type === 'rectal') {
                        out.push(tempSymbol(x, yT, 'rectal', tipT));       // 先画蓝空心圈
                        out.push(dot(x, yP, 2, COL.pulse, tipP));          // 内画红点
                    } else {
                        out.push(tempSymbol(x, yT, temp.type, tipT));      // 蓝点/蓝叉
                        out.push(circle(x, yT, 4.4, COL.pulse, tipP));     // 外画红圈
                    }
                } else {
                    if (temp) {
                        var tName = TYPE_NAME[temp.type] || '';
                        var tipT2 = temp.no_rise ? when + '｜体温不升' : when + '｜' + tName + ' ' + Number(temp.v).toFixed(1) + '℃';
                        out.push(tempSymbol(x, yT, temp.type, tipT2));
                        if (temp.v < 35) {
                            if (temp.no_rise) out.push(text(x + 12, yT + 15, '不升', { size: 8.5, fill: COL.temp }));
                            else out.push(downArrow(x, yT + 3, 2 * SH - 4, COL.temp));   // ≤2 小格
                        }
                        if (temp.v > 42) out.push(upArrow(x, 3, SH - 3, COL.temp, 4.5));   // 顶格内侧
                        if (temp.verified) out.push(text(x, yT - 6.5, 'v', { size: 8.5, fill: COL.temp, italic: true }));
                    }
                    if (pulse != null) {
                        out.push(dot(x, yP, 2.4, COL.pulse, when + '｜脉搏 ' + s.pulse));
                        if (s.pulse > 180) out.push(upArrow(x, 3, SH - 3, COL.pulse, 4.5));
                    }
                }
                /* 2) 心率 (起搏器→带 H 红圈; 与体温重叠→标注 H; >180→上箭头) */
                if (hr != null) {
                    if (s.pacemaker) {
                        out.push(pacemaker(x, yH, when + '｜起搏心率 ' + hr));
                    } else {
                        out.push(circle(x, yH, 3, COL.pulse, when + '｜心率 ' + hr));
                        if (hr > 180) out.push(upArrow(x, 3, SH - 3, COL.pulse, 4.5));
                        if (oTH) out.push(text(x + 6.5, yH - 2.5, 'H', { size: 7.5, fill: COL.pulse, weight: '600' }));
                    }
                }
                /* 3) 呼吸 (与脉搏重叠→呼吸符号外红圈; 呼吸机→20次线上 ®; >70→上箭头) */
                if (breath != null) {
                    out.push(dot(x, yB, 2.2, COL.breath, when + '｜呼吸 ' + breath));
                    if (oBP) out.push(circle(x, yB, 4.2, COL.pulse, when + '｜脉搏 ' + s.pulse));
                    if (s.ventilator) out.push(text(x + 7, yBreath(20) - 3, '®', { size: 8, fill: COL.text }));
                    if (s.breath > 70) out.push(upArrow(x, yB - 3, SH - 4, COL.breath));
                }
                /* 4) 降温后复测: 同一纵格红空心圈 + 红虚线连降温前体温 */
                if (temp && temp.cooling != null) {
                    var yc = yTemp(temp.cooling);
                    out.push('<line x1="' + n(x) + '" y1="' + n(yT) + '" x2="' + n(x) + '" y2="' + n(yc) + '" stroke="' + COL.red + '" stroke-width="1.2" stroke-dasharray="3,2"/>');
                    out.push(circle(x, yc, 2.8, COL.red, when + '｜降温后复测 ' + Number(temp.cooling).toFixed(1) + '℃'));
                }
                /* 5) 疼痛评分在独立区绘制 (painSvg) */
            });
        });
        return out.join('');
    }

    /* 顶部事件 (40~42℃ 区, 红竖排) 与底部未测标注 (34~35℃ 区, 竖排, 曲线断开) */
    function slotAnnotations(days) {
        var out = [];
        var topY = yTemp(40) / 2;                      // 40~42 区中心
        var botY = (yTemp(35) + GRID_H) / 2;           // 34~35 区中心
        days.forEach(function (day) {
            day.slots.forEach(function (s, i) {
                if (s.event) out.push(vtext(xSlot(i), topY, s.event, COL.red, 9));
                if (s.unmeasured) out.push(vtext(xSlot(i), botY, s.unmeasured, s.unmeasured === '拒测' ? COL.blue : COL.red, 9));
            });
        });
        return out.join('');
    }

    /* ==================== 疼痛评分区 (0~10 分, 5 行) ==================== */
    function painRailSvg() {
        var s = vtext(14, PAIN_H / 2, '疼痛评分', COL.text, 8.5);
        for (var v = 0; v <= 10; v += 2) s += text(46, yPain(v) + 2.6, v, { size: 7.5, fill: COL.text, anchor: 'end' });
        return svg(RAIL_W, PAIN_H, s, 'tc-rail-svg');
    }
    function painSvg(days) {
        var s = '', i, r, d;
        for (i = 0; i <= 42; i++) {
            if (i % 6 === 0 && i > 0 && i < 42) continue;
            s += line(i * SLOT_W, 0, i * SLOT_W, PAIN_H, COL.grid, 0.8, true);
        }
        for (r = 0; r <= 5; r++) s += line(0, r * PAIN_SH, GRID_W, r * PAIN_SH, COL.grid, 0.8, true);
        for (d = 1; d < 7; d++) s += line(d * 6 * SLOT_W, 0, d * 6 * SLOT_W, PAIN_H, COL.red, 2, true);
        var pts = [];
        days.forEach(function (day) {
            day.slots.forEach(function (sl, i2) {
                if (sl.pain != null) pts.push({ x: xSlot(i2), y: yPain(sl.pain), tip: tipDateOf(day.date, i2) + '｜疼痛 ' + sl.pain + ' 分' });
            });
        });
        for (var k = 1; k < pts.length; k++) s += line(pts[k - 1].x, pts[k - 1].y, pts[k].x, pts[k].y, COL.pain, 1.2, false);
        pts.forEach(function (p) { s += painTriangle(p.x, p.y, p.tip); });
        return svg(GRID_W, PAIN_H, s);
    }

    /* ==================== 表格框架 (HTML table: 日期/天数/时点/特殊项目栏) ==================== */
    function dateCellText(days, i, sheetFirst) {
        var d = days[i].date, prev = i > 0 ? days[i - 1].date : null;
        var crossYear = prev && prev.slice(0, 4) !== d.slice(0, 4);
        var crossMonth = prev && prev.slice(0, 7) !== d.slice(0, 7);
        if ((i === 0 && sheetFirst) || crossYear) return d;
        if (crossMonth || i === 0) return d.slice(5);
        return d.slice(8);
    }
    function itemRow(label, days, value) {
        var h = '<tr><td class="tc-rail tc-ilabel">' + esc(label) + '</td>';
        days.forEach(function (d) { h += '<td colspan="6" class="tc-item-cell tc-day-edge">' + value(d) + '</td>'; });
        return h + '</tr>';
    }
    function chartTable(days, sheetFirst) {
        var h = '<table class="tc-table"><colgroup><col style="width:' + RAIL_W + 'px">';
        for (var c = 0; c < 42; c++) h += '<col style="width:' + SLOT_W + 'px">';
        h += '</colgroup><tbody>';
        /* 日期 */
        h += '<tr><td class="tc-rail tc-hlabel">日期</td>';
        days.forEach(function (d, i) {
            h += '<td colspan="6" class="tc-date-cell' + (i > 0 ? ' tc-day-edge' : '') + '">' + esc(dateCellText(days, i, sheetFirst)) + '</td>';
        });
        h += '</tr>';
        /* 住院天数 / 手术(分娩)后天数 (红) */
        h += '<tr><td class="tc-rail tc-hlabel">住院天数</td>';
        days.forEach(function (d, i) { h += '<td colspan="6" class="' + (i > 0 ? 'tc-day-edge' : '') + '">' + esc(d.day_no) + '</td>'; });
        h += '</tr><tr><td class="tc-rail tc-hlabel">术后天数</td>';
        days.forEach(function (d, i) { h += '<td colspan="6" class="tc-surgery' + (i > 0 ? ' tc-day-edge' : '') + '">' + esc(d.surgery_days) + '</td>'; });
        h += '</tr>';
        /* 时间分组 (早2,6/中10,14/晚18,22) + 时点数字 (2,18,22 红) */
        h += '<tr><td class="tc-rail tc-hlabel">时间</td>';
        days.forEach(function () {
            h += '<td colspan="2" class="tc-tgroup">早</td><td colspan="2" class="tc-tgroup">中</td><td colspan="2" class="tc-tgroup">晚</td>';
        });
        h += '</tr><tr><td class="tc-rail tc-hlabel"></td>';
        for (var i = 0; i < 42; i++) {
            var slot = SLOTS[i % 6];
            h += '<td class="tc-slotnum' + ((slot === 2 || slot === 18 || slot === 22) ? ' tc-red' : '') + (i % 6 === 0 && i > 0 ? ' tc-day-edge' : '') + '">' + slot + '</td>';
        }
        h += '</tr>';
        /* 主网格 + 疼痛评分区 (SVG 覆盖层) */
        h += '<tr><td class="tc-rail tc-rail-main">' + railSvg() + '</td>'
            + '<td colspan="42" class="tc-main-cell">' + mainSvg(days) + '</td></tr>';
        h += '<tr><td class="tc-rail">' + painRailSvg() + '</td>'
            + '<td colspan="42" class="tc-pain-cell">' + painSvg(days) + '</td></tr>';
        /* 底部特殊项目栏 (按天) */
        h += itemRow('大便次数', days, function (d) { return esc(d.daily.stool); });
        /* 血压: 每天上午/下午两小列, 收缩压/舒张压 */
        h += '<tr><td class="tc-rail tc-ilabel">血压mmHg</td>';
        days.forEach(function (d) {
            h += '<td colspan="3" class="tc-item-cell tc-bp-cell">' + esc(d.daily.bp_am) + '</td>'
                + '<td colspan="3" class="tc-item-cell tc-bp-cell tc-day-edge' + '">' + esc(d.daily.bp_pm) + '</td>';
        });
        h += '</tr>';
        h += itemRow('入量ml', days, function (d) { return esc(d.daily.intake); });
        h += itemRow('出量ml', days, function (d) { return esc(d.daily.output); });
        h += itemRow('小便量ml', days, function (d) { return esc(d.daily.urine); });
        h += itemRow('身高体重', days, function (d) {
            return esc([d.daily.height, d.daily.weight].filter(function (v) { return v !== '' && v != null; }).join('/'));
        });
        h += itemRow('皮试', days, function (d) {
            var v = String(d.daily.skin_test || '');
            if (!v) return '';
            var pos = v.indexOf('阳性') >= 0;
            return '<span class="' + (pos ? 'tc-skin-pos' : 'tc-skin-neg') + '">' + esc(v) + '</span>';
        });
        h += itemRow('过敏药物', days, function (d) { return esc(d.daily.allergy_drugs); });
        h += itemRow('特殊药物', days, function (d) { return esc(d.daily.special_drugs); });
        h += itemRow('其他', days, function (d) { return esc(d.daily.other); });
        h += '</tbody></table>';
        return h;
    }
    function mainSvg(days) {
        var defs = '<defs><pattern id="tcHatch' + hatchSeq + '" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
            + '<line x1="0" y1="0" x2="0" y2="6" stroke="' + COL.red + '" stroke-width="0.9"/></pattern></defs>';
        var body = gridLines() + deficitFills(days, hatchSeq) + tempCurve(days) + pulseCurve(days) + hrCurve(days) + breathCurve(days) + slotSymbols(days) + slotAnnotations(days);
        return svg(GRID_W, GRID_H, defs + body);
    }


    /* ==================== 样式 (页面与打印文档共用一份源) ==================== */
    var CHART_CSS = [
        '.tc-table { width: 100%; border-collapse: collapse; table-layout: fixed; background: #fff; }',
        '.tc-table td { border: 1px solid #c9d4e0; padding: 0 2px; font-size: 10px; text-align: center; color: #303133; overflow: hidden; word-break: break-all; }',
        '.tc-rail { font-size: 9px; color: #475569; background: #fbfcfe; }',
        '.tc-hlabel, .tc-ilabel { font-size: 9px; line-height: 1.25; color: #475569; }',
        '.tc-date-cell { height: 20px; font-size: 10.5px; font-weight: 600; }',
        '.tc-surgery { color: #dc2626; font-weight: 600; }',
        '.tc-tgroup { height: 16px; font-size: 9px; background: #f4f7fb; letter-spacing: 8px; padding-left: 8px !important; color: #475569; }',
        '.tc-slotnum { height: 16px; font-size: 9.5px; font-weight: 600; }',
        '.tc-red { color: #dc2626; }',
        '.tc-day-edge { border-left: 2px solid #dc2626 !important; }',
        '.tc-main-cell, .tc-pain-cell { padding: 0 !important; line-height: 0; }',
        '.tc-main-cell svg, .tc-pain-cell svg, .tc-rail svg { display: block; }',
        '.tc-item-cell { height: 19px; font-size: 10px; }',
        '.tc-bp-cell { font-size: 9.5px; }',
        '.tc-skin-pos { color: #dc2626; font-weight: 700; }',
        '.tc-skin-neg { color: #303133; }',
        '.tc-wrap { width: ' + (RAIL_W + GRID_W) + 'px; }',
        '.tc-header-slot { min-height: 8px; }',
        '.tc-wrap [data-tip]:hover { filter: drop-shadow(0 0 3px rgba(37,99,235,.9)); cursor: pointer; }',
        '.tc-tooltip { position: absolute; z-index: 4000; display: none; background: rgba(23,32,48,.94); color: #fff;',
        '  font-size: 12px; padding: 6px 10px; border-radius: 6px; pointer-events: none; white-space: nowrap;',
        '  box-shadow: 0 4px 14px rgba(0,0,0,.28); }'
    ].join('\n');
    /* A4 打印页面样式 (打印文档专用: 患者抬头占位 + 页脚 + 色彩保真) */
    var PAGE_CSS = [
        '@page { size: A4 portrait; margin: 0; }',
        'html, body { margin: 0; padding: 0; background: #fff; }',
        '* { box-sizing: border-box; print-color-adjust: exact; -webkit-print-color-adjust: exact; }',
        '.page { position: relative; width: 794px; height: 1123px; margin: 0 auto; background: #fff; padding: 22px 36px 26px; overflow: hidden; }',
        '#patient-header-slot { margin-bottom: 8px; }',
        '.brand-name-lockup { display: inline-grid; grid-template-columns: minmax(0,1fr); width: max-content; max-width: 100%; min-width: 0; }',
        '.brand-name-line { width: 100%; min-width: 0; text-align: justify; text-align-last: justify; text-justify: inter-character; }',
        '.emr-brand-lockup { display: grid; margin: 0 auto; }',
        '.emr-hos { font-size: 19pt; font-weight: bold; letter-spacing: 4px; line-height: 1.35; }',
        '.emr-hos2 { font-size: 11pt; font-weight: normal; line-height: 1.3; }',
        '.emr-docname { font-size: 15pt; font-weight: bold; letter-spacing: 3px; line-height: 1.45; }',
        '.emr-infobar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 18px; margin-top: 7px; padding: 5px 4px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 10.5pt; }',
        '.emr-hf { display: inline-flex; gap: 4px; }',
        '.emr-hf b { font-weight: 600; }',
        '.emr-hf-val { min-width: 34px; text-align: center; }',
        '.tc-page-foot { position: absolute; bottom: 12px; left: 0; right: 0; text-align: center; font-size: 10pt; color: #303133; }'
    ].join('\n');

    var cssInjected = false, hatchSeq = 0;
    function injectCss() {
        if (cssInjected) return;
        var st = document.createElement('style');
        st.setAttribute('data-tc', '1');
        st.textContent = CHART_CSS + '\n' + PAGE_CSS;
        document.head.appendChild(st);
        cssInjected = true;
    }

    /* ==================== 对外 API ==================== */
    var TempChart = {
        SLOTS: SLOTS,

        /** 页面交互视图: 渲染 7 天三测单 (含 Tooltip 悬停与高亮) */
        render: function (container, data) {
            if (typeof container === 'string') container = document.getElementById(container);
            if (!container) return;
            injectCss();
            var days = (data && data.days) || [];
            var sheetFirst = !!(data && data.start && data.patient && data.patient.admission_date
                && data.start.slice(0, 10) === String(data.patient.admission_date).slice(0, 10));
            container.innerHTML = '<div class="tc-wrap">'
                + '<div id="patient-header-slot" class="tc-header-slot"></div>'
                + chartTable(days, sheetFirst)
                + '</div>';
            bindTooltip(container);
        },

        /** A4 打印: 每页 7 天, 返回完整 HTML 文档数组 (交 PrintPreview.combine) */
        buildPrintPages: function (data, patient) {
            var days = (data && data.days) || [];
            if (!days.length) return [];
            var sheetFirst = !!(data.start && data.patient && data.patient.admission_date
                && data.start.slice(0, 10) === String(data.patient.admission_date).slice(0, 10));
            var count = Math.ceil(days.length / 7);
            var pages = [];
            for (var i = 0; i < count; i++) {
                hatchSeq = i;   // 多页同 iframe 时 pattern id 隔离
                pages.push(buildPageDoc(days.slice(i * 7, i * 7 + 7), data, patient, sheetFirst, i + 1, count));
            }
            return pages;
        },

        CSS: function () { return CHART_CSS + '\n' + PAGE_CSS; }
    };

    function buildPageDoc(days, data, patient, sheetFirst, pageNo, pageCount) {
        var info = patient || (data && data.patient) || {};
        var header = '';
        if (typeof PrintPreview !== 'undefined' && PrintPreview.patientInfo && PrintPreview.sheetHeader) {
            header = PrintPreview.sheetHeader(PrintPreview.patientInfo(info, '体温单'));
        }
        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>体温单</title>'
            + '<style>' + CHART_CSS + '\n' + PAGE_CSS + '</style></head><body>'
            + '<div class="page tc-page">'
            + '<div id="patient-header-slot">' + header + '</div>'
            + chartTable(days, sheetFirst)
            + '<div class="tc-page-foot">第 ' + pageNo + ' 页 / 共 ' + pageCount + ' 页</div>'
            + '</div></body></html>';
    }

    /* Tooltip: 事件委托 (仅页面交互视图) */
    function bindTooltip(root) {
        if (root._tcTipBound) return;
        root._tcTipBound = true;
        var tip = document.createElement('div');
        tip.className = 'tc-tooltip';
        document.body.appendChild(tip);
        root.addEventListener('mousemove', function (e) {
            var t = e.target && e.target.closest ? e.target.closest('[data-tip]') : null;
            if (!t) { tip.style.display = 'none'; return; }
            tip.textContent = t.getAttribute('data-tip');
            tip.style.display = 'block';
            var x = e.pageX + 12, y = e.pageY + 12;
            if (x + tip.offsetWidth > window.innerWidth - 8) x = e.pageX - tip.offsetWidth - 10;
            if (y + tip.offsetHeight > window.innerHeight - 8) y = e.pageY - tip.offsetHeight - 10;
            tip.style.left = x + 'px';
            tip.style.top = y + 'px';
        });
        root.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    }

    window.TempChart = TempChart;
})();
