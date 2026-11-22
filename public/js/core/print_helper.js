// public/js/core/print_helper.js - 打印品牌抬头助手（条形码由 PrintBarcode 统一提供）
const PrintHelper = {
    brandHeaderHtml(extraClass, source) {
        if (!source) return HospitalBranding.headerHtml(extraClass || '');
        const brand = HospitalBranding.normalize(source);
        const second = brand.hospital_name2
            ? '<div class="brand-name-line print-hospital2">' + Format.escape(brand.hospital_name2) + '</div>' : '';
        return '<div class="brand-name-lockup print-brand-lockup ' + Format.escape(extraClass || '') + '">'
            + '<div class="brand-name-line print-hospital">' + Format.escape(brand.hospital_name) + '</div>' + second + '</div>';
    }
};
