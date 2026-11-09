<?php
// api/core/emr_template_defs.php - 内置住院病历模板定义 (字处理 word 模式)
// 返回 function(string $hospital): array<[name, recordType, categoryCode, schema]>
// 依据《病历书写基本规范》: 入院记录(4 类) / 病程记录 / 出院记录
return function ($hospital) {
    $P = function (array $runs, array $pPr = []) { return ['t' => 'p', 'pPr' => $pPr, 'runs' => $runs]; };
    $fx = function ($key, $text) { return ['t' => 'fx', 'key' => $key, 'text' => $text]; };
    $slot = function ($key, $ph) { return ['t' => 'slot', 'key' => $key, 'ph' => $ph]; };
    $sel = function ($key, $ph, array $opts) { return ['t' => 'slot', 'key' => $key, 'ph' => $ph, 'opts' => $opts]; };
    $dyn = function ($key, $text = '') { return ['t' => 'dyn', 'key' => $key, 'text' => $text]; };
    $head = function ($text) { return ['t' => 'p', 'pPr' => ['align' => 'center', 'borders' => ['top' => true]], 'rPr' => ['b' => true, 'sz' => 13], 'runs' => [['t' => 'text', 'text' => $text]]]; };
    $row = function (array $runs) { return ['t' => 'p', 'pPr' => ['flex' => 'between'], 'runs' => $runs]; };
    $line = function ($label, $key, $ph = '') use ($fx, $slot, $P) {
        return $P([$fx($key, $label), $slot($key . '_val', $ph !== '' ? $ph : ('请输入' . rtrim($label, '：')))]);
    };
    $sig = function () use ($fx, $dyn, $P) { return $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']); };
    /* 12 项患者信息表单元格助手 (label|value|label|value, 无边框) */
    $cText = function ($text, $w) { return ['tcPr' => ['w' => $w], 'blocks' => [['t' => 'p', 'runs' => [['t' => 'text', 'text' => $text]]]]]; };
    $cItem = function ($item, $w) { return ['tcPr' => ['w' => $w], 'blocks' => [['t' => 'p', 'runs' => [$item]]]]; };

    $page = ['paper' => 'A4', 'mt' => 40, 'mb' => 36, 'ml' => 36, 'mr' => 36, 'header_h' => 16, 'footer_h' => 40,
        'font' => '仿宋_GB2312, FangSong, SimSun, serif', 'sz' => 12, 'line' => 1.5];
    $infoFields = [['姓名', 'name'], ['性别', 'gender'], ['科别', 'department'], ['病区', 'ward_name'],
        ['年龄', 'age'], ['床号', 'bed_no'], ['住院号', 'admission_no']];
    $mk = function ($docName, array $body) use ($hospital, $infoFields, $page) {
        return ['mode' => 'word', 'title' => $docName, 'page' => $page,
            'header' => ['hospital' => $hospital, 'doc_name' => $docName, 'info_fields' => $infoFields],
            'body' => $body];
    };

    $NATION = ['汉族', '蒙古族', '回族', '藏族', '维吾尔族', '苗族', '彝族', '壮族', '布依族', '朝鲜族', '满族', '侗族', '瑶族', '白族', '土家族', '其他'];
    $MARRIAGE = ['未婚', '已婚', '离异', '丧偶'];
    $OCCUPATION = ['工人', '农民', '学生', '教师', '职员', '军人', '自由职业', '退休', '无业', '其他'];
    $TELLER = ['患者本人', '家属', '同事', '其他'];
    $YESNO = ['无', '有'];

    /** 入院记录正文 (通用) */
    $admissionBody = function () use ($fx, $slot, $sel, $dyn, $head, $row, $line, $P, $sig, $cText, $cItem, $NATION, $MARRIAGE, $OCCUPATION, $TELLER, $YESNO) {
        $body = [];
        /* 患者信息 12 项 (两列: 左 姓名/性别/年龄/婚姻/籍贯/民族, 右 职业/单位/住址/病史陈述者/入院日期/记录日期)
           编辑状态下与病案首页单项同步 (未手动修改过的字段自动更新, 手动修改并保存后停止同步) */
        $body[] = [
            't' => 'tbl',
            'tblPr' => ['plain' => true, 'center' => true],
            'grid' => [46, 204, 76, 174],
            'rows' => [
                ['cells' => [$cText('姓名：', 46), $cItem($slot('p_name_val', '请输入姓名'), 204),
                    $cText('职业：', 76), $cItem($sel('p_occupation_val', '请选择职业', $OCCUPATION), 174)]],
                ['cells' => [$cText('性别：', 46), $cItem($sel('p_gender_val', '请选择性别', ['男', '女']), 204),
                    $cText('单位：', 76), $cItem($slot('p_workunit_val', '请输入工作单位'), 174)]],
                ['cells' => [$cText('年龄：', 46), $cItem($slot('p_age_val', '请输入年龄'), 204),
                    $cText('住址：', 76), $cItem($slot('p_address_val', '请输入住址'), 174)]],
                ['cells' => [$cText('婚姻：', 46), $cItem($sel('p_marriage_val', '请选择婚姻状况', $MARRIAGE), 204),
                    $cText('病史陈述者：', 76), $cItem($sel('p_teller_val', '请选择', $TELLER), 174)]],
                ['cells' => [$cText('籍贯：', 46), $cItem($slot('p_native_val', '请输入籍贯'), 204),
                    $cText('入院日期：', 76), $cItem($slot('p_admitdate_val', '请输入'), 174)]],
                ['cells' => [$cText('民族：', 46), $cItem($sel('p_nation_val', '请选择民族', $NATION), 204),
                    $cText('记录日期：', 76), $cItem($slot('p_recorddate_val', '请输入'), 174)]],
            ],
        ];
        $body[] = $line('主诉：', 'chief_complaint', '主要症状（或体征）及持续时间');
        $body[] = $line('现病史：', 'present_illness', '发病情况、症状特点及演变、伴随症状、诊疗经过、一般情况变化等（按时间顺序）');
        $body[] = $line('既往史：', 'past_history', '既往健康状况、疾病史、预防接种史等');
        $body[] = $row([$fx('infect_dis', '传染病史：'), $sel('infect_dis_val', '无/有', $YESNO),
            $fx('surgery_hx', '手术外伤史：'), $sel('surgery_hx_val', '无/有', $YESNO),
            $fx('transfusion', '输血史：'), $sel('transfusion_val', '无/有', $YESNO),
            $fx('allergy', '药物过敏史：'), $sel('allergy_val', '无/有/不详', ['无', '有', '不详'])]);
        $body[] = $line('个人史：', 'personal_history', '居住、生活、嗜好等');
        $body[] = $line('婚育史：', 'marital_history', '婚姻、生育情况');
        $body[] = $line('月经史：', 'menstrual_history', '女性患者填写（正常/异常/已绝经）');
        $body[] = $line('家族史：', 'family_history', '家族中同类疾病等');
        $body[] = $head('体格检查');
        $body[] = $row([$fx('vital_temp', '体温：'), $slot('vital_temp_val', '℃'),
            $fx('vital_pulse', '脉搏：'), $slot('vital_pulse_val', '次/分'),
            $fx('vital_resp', '呼吸：'), $slot('vital_resp_val', '次/分'),
            $fx('vital_bp', '血压：'), $slot('vital_bp_val', 'mmHg')]);
        $body[] = $row([$fx('vital_height', '身高：'), $slot('vital_height_val', 'cm'),
            $fx('vital_weight', '体重：'), $slot('vital_weight_val', 'kg'),
            $fx('vital_bmi', 'BMI：'), $dyn('vital_bmi_value', '')]);
        $body[] = $line('一般情况：', 'exam_general', '发育、营养、神志、体位等');
        $body[] = $line('皮肤、粘膜：', 'exam_skin');
        $body[] = $line('全身浅表淋巴结：', 'exam_nodes');
        $body[] = $line('头部及其器官：', 'exam_head');
        $body[] = $line('颈部：', 'exam_neck');
        $body[] = $line('胸部（胸廓、肺部、心脏、血管）：', 'exam_chest');
        $body[] = $line('腹部（肝、脾等）：', 'exam_abdomen');
        $body[] = $line('直肠肛门：', 'exam_rectum');
        $body[] = $line('外生殖器：', 'exam_genital');
        $body[] = $line('脊柱、四肢：', 'exam_spine');
        $body[] = $line('神经系统：', 'exam_nervous');
        $body[] = $head('专科情况');
        $body[] = $P([$slot('special_exam_val', '根据专科需要记录专科特殊情况')]);
        $body[] = $head('辅助检查');
        $body[] = $P([$slot('aux_exam_val', '入院前与本次疾病相关的主要检查及结果（注明日期；外院检查注明机构名称）')]);
        $body[] = $P([$fx('initial_diagnosis', '初步诊断：'), $dyn('initial_diagnosis_value', '')], ['align' => 'right']);
        $body[] = $sig();
        return $body;
    };

    /** 24 小时内入出院记录 */
    $inOut24 = function () use ($line, $P, $fx, $dyn) {
        return [
            $line('主诉：', 'chief_complaint', '主要症状（或体征）及持续时间'),
            $line('入院情况：', 'admission_condition', '入院时病情、主要症状体征及检查结果'),
            $line('入院诊断：', 'admission_diag', '入院时的诊断'),
            $line('诊疗经过：', 'course', '住院期间诊疗经过'),
            $line('出院情况：', 'discharge_condition', '出院时病情、主要症状体征'),
            $line('出院诊断：', 'discharge_diag', '出院时的诊断'),
            $line('出院医嘱：', 'discharge_orders', '出院后用药、复诊等注意事项'),
            $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']),
        ];
    };

    /** 24 小时内入院死亡记录 */
    $death24 = function () use ($line, $P, $fx, $dyn) {
        return [
            $line('主诉：', 'chief_complaint', '主要症状（或体征）及持续时间'),
            $line('入院情况：', 'admission_condition', '入院时病情、主要症状体征及检查结果'),
            $line('入院诊断：', 'admission_diag', '入院时的诊断'),
            $line('诊疗经过：', 'course', '重点记录抢救经过及抢救措施'),
            $line('死亡时间：', 'death_time', '精确到分钟'),
            $line('死亡原因：', 'death_cause'),
            $line('死亡诊断：', 'death_diag'),
            $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']),
        ];
    };

    /** 日常查房记录 */
    $dailyRound = function () use ($row, $fx, $slot, $line, $P, $dyn) {
        return [
            $row([$fx('round_date', '记录日期：'), $slot('round_date_val', '请输入'),
                $fx('round_time', '记录时间：'), $slot('round_time_val', '请输入')]),
            $line('病情变化及查体：', 'round_change', '症状、体征变化及新出现的病情'),
            $line('处理意见：', 'round_plan', '检查、治疗及注意事项'),
            $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']),
        ];
    };

    /** 上级医师查房记录 */
    $seniorRound = function () use ($row, $fx, $slot, $line, $P, $dyn) {
        return [
            $row([$fx('round_date', '查房日期：'), $slot('round_date_val', '请输入'),
                $fx('round_time', '查房时间：'), $slot('round_time_val', '请输入')]),
            $line('上级医师查房意见：', 'senior_opinion', '诊断依据、鉴别诊断及下一步诊疗意见'),
            $line('执行情况：', 'senior_exec', '落实情况'),
            $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']),
        ];
    };

    /** 首次病程记录 (病例特点 + 拟诊讨论 + 诊疗计划) */
    $firstProgress = function () use ($line, $P, $fx, $dyn) {
        return [
            $line('病例特点：', 'case_feature', '主要临床特点归纳（症状、体征、辅助检查）'),
            $line('诊断依据：', 'diag_basis', '拟诊讨论: 诊断依据'),
            $line('鉴别诊断：', 'diff_diag', '需要鉴别的疾病及依据'),
            $line('诊疗计划：', 'care_plan', '进一步检查与治疗计划'),
            $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']),
        ];
    };

    /** 医患沟通记录 */
    $communicate = function () use ($row, $fx, $slot, $line, $P, $dyn) {
        return [
            $row([$fx('comm_date', '沟通时间：'), $slot('comm_date_val', '请输入'),
                $fx('comm_place', '沟通地点：'), $slot('comm_place_val', '请输入')]),
            $line('参加人员：', 'comm_people', '医师及患者（家属）参加人员'),
            $line('沟通内容：', 'comm_content', '病情、诊疗方案、风险及注意事项等'),
            $line('患者（家属）意见：', 'comm_opinion', '理解并同意 / 其他意见'),
            $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']),
            $P([$fx('sign_patient', '患者（家属）签名：'), $slot('sign_patient_val', '请输入')], ['align' => 'right']),
        ];
    };

    /** 出院记录 */
    $dischargeRecord = function () use ($row, $fx, $slot, $line, $P, $dyn) {
        return [
            $row([$fx('admission_date', '入院日期：'), $slot('admission_date_val', '请输入'),
                $fx('discharge_date', '出院日期：'), $slot('discharge_date_val', '请输入')]),
            $line('入院情况：', 'adm_condition', '入院时病情、主要症状体征'),
            $line('入院诊断：', 'adm_diag'),
            $line('诊疗经过：', 'course', '住院期间检查、治疗及转归'),
            $line('出院诊断：', 'discharge_diag'),
            $line('出院情况：', 'discharge_condition', '出院时病情、主要症状体征'),
            $line('出院医嘱：', 'discharge_orders', '出院后用药、复诊等注意事项'),
            $P([$fx('sign_line', '医师签名：'), $dyn('sign_names', '')], ['align' => 'right']),
        ];
    };

    return [
        ['入院记录', 'admission', 'admission', $mk('入院记录', $admissionBody())],
        ['再次或多次入院记录', 'admission', 'admission', $mk('再次或多次入院记录', $admissionBody())],
        ['24小时内入出院记录', 'admission', 'admission', $mk('24小时内入出院记录', $inOut24())],
        ['24小时内入院死亡记录', 'admission', 'admission', $mk('24小时内入院死亡记录', $death24())],
        ['日常查房记录', 'progress', 'progress', $mk('日常查房记录', $dailyRound())],
        ['上级医师查房记录', 'progress', 'progress', $mk('上级医师查房记录', $seniorRound())],
        ['首次病程记录', 'progress', 'progress', $mk('首次病程记录', $firstProgress())],
        ['医患沟通记录', 'communication', 'communication', $mk('医患沟通记录', $communicate())],
        ['出院记录', 'discharge', 'discharge', $mk('出院记录', $dischargeRecord())],
    ];
};
