<?php
// public/assets.php - 前端静态资源统一注册与输出
// 新增/调整 JS/CSS 只需修改本文件; 输出自动附加 APP_VERSION 防缓存
//
// 用法 (public/index.php):
//   require_once __DIR__ . '/assets.php';
//   head 区域调用 Asset::css()  输出所有样式表 link
//   body 末尾调用 Asset::js()   输出所有脚本 script
// 注意: 本文件注释内禁止出现 PHP 关闭标记, 否则会提前结束 PHP 解析
class Asset {
    /** 样式表 (按顺序加载) */
    public static $css = [
        '/lib/quill/quill.snow.css',
        '/css/core/reset.css',
        '/css/core/layout.css',
        '/css/core/datetime_picker.css',
        '/css/core/select_picker.css',
        '/css/login/login.css',
        '/css/doctor/doctor.css',
        '/css/doctor/order_station.css',
        '/css/doctor/patient_wall.css',
        '/css/emr/emr.css',
        '/css/admin/admin.css',
        '/css/clinic/clinic.css',
    ];

    /** 脚本 (按顺序加载: 公共单例 → 图标桥 → 组件 → 登录) */
    public static $js = [
        '/lib/quill/quill.min.js',
        '/lib/html2pdf/html2pdf.bundle.min.js',
        '/js/core/app.js',
        '/icons/js.php',
        '/js/core/format.js',
        '/js/core/ajax.js',
        '/js/core/auth.js',
        '/js/core/dom.js',
        '/js/core/components.js',
        '/js/core/datetime_picker.js',
        '/js/core/select_picker.js',
        '/js/core/rich_editor.js',
        '/js/core/template_picker.js',
        '/js/core/icd_search.js',
        '/js/core/pdf_generator.js',
        '/js/core/print_helper.js',
        '/js/core/patient_context.js',
        '/js/core/patient_switcher.js',
        '/js/core/patient_quick_switch.js',
        '/js/orders/order_quickadd.js',
        '/js/orders/order_actions.js',
        '/js/orders/order_detail.js',
        '/js/orders/dose_picker.js',
        '/js/orders/order_sets.js',
        '/js/clinic/lab_panel.js',
        '/js/admin/integrations.js',
        '/js/login/login.js',
    ];

    /** 输出 <link> 标签 */
    public static function css() {
        foreach (self::$css as $href) {
            echo '    <link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . '?v=' . APP_VERSION . '">' . "\n";
        }
    }

    /** 输出 <script> 标签 */
    public static function js() {
        foreach (self::$js as $src) {
            echo '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '?v=' . APP_VERSION . '"></script>' . "\n";
        }
    }
}
