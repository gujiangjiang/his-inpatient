// public/js/core/print_helper.js - 打印品牌抬头助手（条形码由 PrintBarcode 统一提供）
const PrintHelper = {
    brandHeaderHtml(extraClass) {
        return HospitalBranding.headerHtml(extraClass || '');
    }
};
