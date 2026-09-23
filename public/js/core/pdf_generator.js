// public/js/core/pdf_generator.js
const PdfGenerator = {
    generate(elementId, filename = 'report.pdf') {
        // html2pdf.js CDN 降级方案
        const el = document.getElementById(elementId);
        if (!el) {
            Dom.toast('找不到要导出的内容', 'error');
            return;
        }
        if (typeof html2pdf === 'undefined') {
            // 降级为浏览器打印
            window.print();
            return;
        }
        const opt = {
            margin: 1,
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        html2pdf().from(el).set(opt).save();
    }
};
