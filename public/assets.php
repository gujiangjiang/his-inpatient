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
        '/css/core/reset.css',
        '/css/core/layout.css',
        '/css/core/datetime_picker.css',
        '/css/core/select_picker.css',
        '/css/login/login.css',
        '/css/doctor/doctor.css',
        '/css/doctor/order_station.css',
        '/css/doctor/patient_wall.css',
        '/css/emr/emr.css',
        '/css/emr/emr_editor.css',
        '/css/admin/admin.css',
        '/css/clinic/clinic.css',
    ];

    /** 脚本 (按顺序加载: 公共单例 → 图标桥 → 组件 → 登录; 无第三方库, 全部自研) */
    public static $js = [
        '/js/core/app.js',
        '/icons/js.php',
        '/js/core/format.js',
        '/js/core/ajax.js',
        '/js/core/auth.js',
        '/js/core/dom.js',
        '/js/core/components.js',
        '/js/core/scroll_load.js',
        '/js/core/datetime_picker.js',
        '/js/core/select_picker.js',
        '/js/core/rich_editor.js',
        '/js/core/template_picker.js',
        '/js/core/icd_search.js',
        '/js/core/icd_picker.js',
        '/js/core/pdf_generator.js',
        '/js/core/print_helper.js',
        '/js/emr_editor/emr_doc_model.js',
        '/js/emr_editor/emr_doc_hist.js',
        '/js/emr_editor/emr_doc_ops.js',
        '/js/emr_editor/emr_doc_render.js',
        '/js/emr_editor/emr_doc_layout.js',
        '/js/emr_editor/emr_doc_print.js',
        '/js/emr_editor/emr_editor_caret.js',
        '/js/emr_editor/emr_editor_ui.js',
        '/js/emr_editor/emr_editor.js',
        '/js/emr/emr_ui.js',
        '/js/core/patient_context.js',
        '/js/core/discharge_modal.js',
        '/js/core/team_modal.js',
        '/js/core/patient_switcher.js',
        '/js/core/patient_quick_switch.js',
        '/js/patients/admit_panel.js',
        '/js/orders/order_quickadd.js',
        '/js/orders/order_actions.js',
        '/js/orders/order_detail.js',
        '/js/orders/dose_picker.js',
        '/js/orders/cell_picker.js',
        '/js/orders/order_sets.js',
        '/js/clinic/lab_panel.js',
        '/js/clinic/exam_panel.js',
        '/js/case_front_page/home_fields.js',
        '/js/case_front_page/home.js',
        '/js/case_front_page/home_print.js',
        '/js/case_front_page/home_page1.js',
        '/js/case_front_page/home_page2.js',
        '/js/admin/integrations.js',
        '/js/login/login.js',
        '/js/login/setup_wizard.js',
    ];

    /** /lib/ 本地第三方库缺失时跳过 (页面自动降级: Quill→文本域 / html2pdf→浏览器打印), 避免 404 噪音 */
    private static function missingLib($path) {
        return strpos($path, '/lib/') === 0 && !is_file(dirname(__FILE__) . $path);
    }

    /** 输出 <link> 标签 */
    public static function css() {
        foreach (self::$css as $href) {
            if (self::missingLib($href)) continue;
            echo '    <link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . '?v=' . APP_VERSION . '">' . "\n";
        }
    }

    /** 输出 <script> 标签 */
    public static function js() {
        foreach (self::$js as $src) {
            if (self::missingLib($src)) continue;
            echo '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '?v=' . APP_VERSION . '"></script>' . "\n";
        }
    }
}
