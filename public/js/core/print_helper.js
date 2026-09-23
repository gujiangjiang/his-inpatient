// public/js/core/print_helper.js
const PrintHelper = {
    print(elementId) {
        const el = document.getElementById(elementId);
        if (!el) {
            window.print();
            return;
        }
        const printContents = el.innerHTML;
        const originalBody = document.body.innerHTML;
        document.body.innerHTML = '<style>@media print {body{font-size:12pt}} .no-print{display:none}</style>' + printContents;
        window.print();
        document.body.innerHTML = originalBody;
        window.location.reload();
    }
};
