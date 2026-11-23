// public/js/core/print/print_barcode.js - 通用 Code 128B SVG 条形码（零依赖、打印矢量输出）
(function () {
    'use strict';
    const PB = (window.PrintBarcode = {});
    const PATTERNS = [
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

    PB.svg = function (text, opts) {
        const options = opts || {};
        const value = String(text == null ? '' : text);
        if (!value) return '';
        const height = Math.max(16, Math.min(160, Number(options.height) || 36));
        const moduleWidth = Math.max(.5, Math.min(4, Number(options.moduleWidth) || 1));
        const quiet = Math.max(6, Number(options.quietModules) || 10);
        const showText = options.showText !== false;
        const seq = [104];
        let checksum = 104, position = 0;
        for (let i = 0; i < value.length; i++) {
            const code = value.charCodeAt(i) - 32;
            if (code < 0 || code > 94) continue;
            seq.push(code);
            checksum += code * (++position);
        }
        if (!position) return '';
        seq.push(checksum % 103);
        const totalModules = quiet * 2 + seq.length * 11 + 13;
        const width = totalModules * moduleWidth;
        let x = quiet * moduleWidth, bar = true, rects = '';
        const draw = function (pattern) {
            for (let i = 0; i < pattern.length; i++) {
                const partWidth = parseInt(pattern[i], 10) * moduleWidth;
                if (bar) rects += '<rect x="' + x + '" y="0" width="' + partWidth + '" height="' + height + '"/>';
                x += partWidth;
                bar = !bar;
            }
        };
        seq.forEach(function (code) { draw(PATTERNS[code] || '212222'); });
        draw('2331112');
        let textNode = '', svgHeight = height;
        if (showText) {
            const left = quiet * moduleWidth;
            const barsWidth = x - left;
            const fontSize = Math.max(10, Number(options.fontSize) || Math.round(height * .4));
            textNode = '<text x="' + left + '" y="' + (height + fontSize) + '" textLength="' + barsWidth + '" lengthAdjust="spacing"'
                + ' font-size="' + fontSize + '" text-anchor="start" fill="#111" font-family="Menlo,Consolas,Courier New,monospace">'
                + Format.escape(value) + '</text>';
            svgHeight = height + fontSize + 4;
        }
        const className = Format.escape(options.className || 'print-barcode');
        return '<svg class="' + className + '" viewBox="0 0 ' + width + ' ' + svgHeight + '" xmlns="http://www.w3.org/2000/svg"'
            + ' preserveAspectRatio="xMidYMid meet" role="img" aria-label="' + Format.escape(value) + '">'
            + '<rect width="' + width + '" height="' + svgHeight + '" fill="#fff"/>' + rects + textNode + '</svg>';
    };
})();
