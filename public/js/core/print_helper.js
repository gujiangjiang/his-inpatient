// public/js/core/print_helper.js - 品牌与条形码模板助手（不承载预览或打印流程）
const PrintHelper = {
    brandHeaderHtml(extraClass) {
        return HospitalBranding.headerHtml(extraClass || '');
    },

    /* 前端 Code 128 条形码（SVG，供打印模板右上角用） */
    barcodeSvg(text, height = 36, scale = 1, withText = true) {
        const P = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232'
        ];
        text = String(text == null ? '' : text);
        if (!text) return '';
        const seq = [104]; let check = 104; let n = 0;
        for (let i = 0; i < text.length; i++) {
            const value = text.charCodeAt(i) - 32;
            if (value < 0 || value > 94) continue;
            seq.push(value); check += value * (++n);
        }
        if (!n) return '';
        seq.push(check % 103);
        const total = 20 + seq.length * 11 + 13;
        const width = total * scale;
        let x = 10 * scale, isBar = true, rects = '';
        const draw = function (pattern) {
            for (let k = 0; k < pattern.length; k++) {
                const moduleWidth = parseInt(pattern[k], 10) * scale;
                if (isBar) rects += '<rect x="' + x + '" y="0" width="' + moduleWidth + '" height="' + height + '"/>';
                x += moduleWidth; isBar = !isBar;
            }
        };
        seq.forEach(function (value) { draw(P[value] || '212222'); });
        draw('2331112');
        let textEl = '', svgHeight = height;
        if (withText) {
            const left = 10 * scale, barWidth = x - left;
            const textHeight = Math.max(14, Math.round(height * .4));
            textEl = '<text x="' + left + '" y="' + (height + textHeight) + '" textLength="' + barWidth + '" lengthAdjust="spacing"'
                + ' font-size="' + textHeight + '" text-anchor="start" fill="#111" font-family="Menlo, Consolas, Courier New, monospace">'
                + Format.escape(text) + '</text>';
            svgHeight = height + textHeight + 6;
        }
        return '<svg viewBox="0 0 ' + width + ' ' + svgHeight + '" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" role="img" aria-label="' + Format.escape(text) + '">'
            + '<rect width="' + width + '" height="' + svgHeight + '" fill="#fff"/>' + rects + textEl + '</svg>';
    }
};
