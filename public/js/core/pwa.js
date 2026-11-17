// public/js/core/pwa.js - PWA Service Worker 注册（仅缓存版本化静态资源，不缓存业务/API 数据）
(function () {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
    window.addEventListener('load', function () {
        const version = (typeof App !== 'undefined' && App.version) ? App.version : '1';
        navigator.serviceWorker.register('/service-worker?v=' + encodeURIComponent(version), {
            scope: '/', updateViaCache: 'none'
        }).catch(function (err) { console.warn('PWA 注册失败', err); });
    });
})();
