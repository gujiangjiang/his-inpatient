<?php
// api/core/emr_dicts.php - 全院统一字典 (单一数据源)
// 用途: 病历模板下拉字段 (民族/婚姻/职业/病史陈述者/有无等) 引用字典键 (slot.dict),
//       模板 schema 不再内嵌选项列表; 字典更新全院模板即时生效; 导出 JSON 干净可迁移。
// 前端: public/js/core/emr_dicts_js.php 由本文件生成 window.EmrDicts (assets 统一注册)。
function emr_dicts()
{
    return [
        /* 民族 (与入院登记/首页口径一致的中文名列表) */
        'nation' => ['汉族', '蒙古族', '回族', '藏族', '维吾尔族', '苗族', '彝族', '壮族', '布依族', '朝鲜族', '满族', '侗族', '瑶族', '白族', '土家族', '其他'],
        /* 婚姻状况 (病历书写口径; 首页标准编码见 CaseHomeFields.marriage) */
        'marriage' => ['未婚', '已婚', '离异', '丧偶'],
        /* 职业 */
        'occupation' => ['工人', '农民', '学生', '教师', '职员', '军人', '自由职业', '退休', '无业', '其他'],
        /* 病史陈述者 */
        'teller' => ['患者本人', '家属', '同事', '其他'],
        /* 通用有无 */
        'yesno' => ['无', '有'],
        /* 有无/不详 */
        'yesno_unknown' => ['无', '有', '不详'],
    ];
}

/** 已知全局字典字段键 → 字典名 (内置模板与存量数据迁移共用) */
function emr_dict_key_for_slot($slotKey)
{
    $map = [
        'p_nation_val' => 'nation',
        'p_marriage_val' => 'marriage',
        'p_occupation_val' => 'occupation',
        'p_teller_val' => 'teller',
        'infect_dis_val' => 'yesno',
        'surgery_hx_val' => 'yesno',
        'transfusion_val' => 'yesno',
        'allergy_val' => 'yesno_unknown',
    ];
    return $map[$slotKey] ?? null;
}
