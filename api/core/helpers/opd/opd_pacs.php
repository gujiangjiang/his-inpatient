<?php
// api/core/helpers/opd/opd_pacs.php - PACS 阅片直链拼装

/** PACS 阅片地址模板 (归一化: 去首尾空白与多余斜杠, 保留协议头的 //) */
function opd_viewer_template() {
    $s = opd_settings();
    return preg_replace('#(?<!:)/{2,}#', '/', trim((string)$s['pacs_viewer_url']));
}

/** 拼 PACS 阅片直链 (模板占位符 {study_uid}) */
function opd_viewer_url($studyUid) {
    $s = opd_settings();
    if ($s['pacs_enabled'] !== '1' || $studyUid === '') return '';
    return str_replace('{study_uid}', rawurlencode($studyUid), opd_viewer_template());
}
