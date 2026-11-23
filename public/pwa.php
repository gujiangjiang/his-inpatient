<?php
// public/pwa.php - 动态 Manifest / Service Worker / PWA 图标（不进入认证 API）
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/core/db/db.php';
require_once __DIR__ . '/../api/core/site/branding.php';

function pwa_manifest()
{
    $brand = branding_public();
    $name = $brand['hospital_name'];
    $full = $brand['hospital_name2'] !== '' ? $name . ' · ' . $brand['hospital_name2'] : $name . ' · 住院一体化系统';
    $short = function_exists('mb_substr') ? mb_substr($name, 0, 12, 'UTF-8') : substr($name, 0, 24);
    $manifest = [
        'id' => '/',
        'name' => $full,
        'short_name' => $short,
        'description' => '医院住院一体化信息系统',
        'lang' => 'zh-CN',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'any',
        'background_color' => '#f3f5f8',
        'theme_color' => '#149a9a',
        'icons' => [
            ['src' => $brand['icon_192_url'], 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $brand['icon_512_url'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $brand['icon_512_url'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ];
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function pwa_icon()
{
    $size = (int)($_GET['size'] ?? 192);
    $path = branding_icon_path($size);
    if (!is_file($path)) { http_response_code(404); return; }
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($path));
    readfile($path);
}

function pwa_service_worker()
{
    require_once __DIR__ . '/assets.php';
    $assets = [];
    foreach (array_merge(Asset::$css, Asset::$js) as $path) {
        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['css', 'js'], true) || !is_file(__DIR__ . $path)) continue;
        $assets[] = $path . '?v=' . APP_VERSION;
    }
    $cache = 'his-static-' . preg_replace('/[^0-9A-Za-z_.-]/', '-', APP_VERSION);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-store');
    header('Service-Worker-Allowed: /');
    echo "'use strict';\n";
    echo 'const CACHE=' . json_encode($cache) . ";\n";
    echo 'const PRECACHE=' . json_encode(array_values(array_unique($assets)), JSON_UNESCAPED_SLASHES) . ";\n";
    echo <<<'JS'
const URLS=new Set(PRECACHE.map(x=>new URL(x,self.location.origin).href));
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE).then(cache=>Promise.all(PRECACHE.map(async url=>{
    try { const res=await fetch(url,{cache:'reload'}); if(res.ok) await cache.put(url,res); } catch(e) {}
  }))).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('his-static-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});
self.addEventListener('fetch',event=>{
  const req=event.request;
  if(req.method!=='GET'||req.mode==='navigate') return;
  const url=new URL(req.url);
  if(url.origin!==self.location.origin||url.pathname.startsWith('/api/')) return;
  if(!URLS.has(url.href)) return;
  event.respondWith(caches.open(CACHE).then(async cache=>{
    const hit=await cache.match(req);
    if(hit) return hit;
    const res=await fetch(req);
    if(res.ok&&!res.headers.has('Set-Cookie')&&!/no-store/i.test(res.headers.get('Cache-Control')||'')) await cache.put(req,res.clone());
    return res;
  }));
});
JS;
}

function pwa_dispatch($uri)
{
    if ($uri === '/manifest') { pwa_manifest(); return true; }
    if ($uri === '/service-worker') { pwa_service_worker(); return true; }
    if ($uri === '/branding/icon') { pwa_icon(); return true; }
    return false;
}
