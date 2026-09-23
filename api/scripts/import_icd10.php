<?php
// api/scripts/import_icd10.php - ICD-10 独立库：21 章节 + 79 条临床常见分类 + FTS5 重建
require_once __DIR__ . '/../config.php';

$icdDb = ICD_DB_PATH;

// 删除旧库
if (file_exists($icdDb)) {
    unlink($icdDb);
}

$pdo = new PDO('sqlite:' . $icdDb);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 创建表
$pdo->exec("CREATE TABLE icd_chapters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chapter_code TEXT,
    title TEXT
)");

$pdo->exec("CREATE TABLE icd_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT,
    name TEXT,
    chapter_id INTEGER,
    is_common INTEGER DEFAULT 0
)");

// FTS5 虚表
$pdo->exec("CREATE VIRTUAL TABLE icd_codes_fts USING fts5(code, name, content='icd_codes', content_rowid='id')");

// 21 章节
$chapters = [
    ['I', '循环系统疾病'],
    ['II', '肿瘤'],
    ['III', '内分泌、营养代谢及免疫相关疾病'],
    ['IV', '传染病'],
    ['V', '精神和行为障碍'],
    ['VI', '神经系统及颜面肌肉疾病'],
    ['VII', '眼部疾病'],
    ['VIII', '耳部疾病'],
    ['IX', '消化系统疾病'],
    ['X', '呼吸系统疾病'],
    ['XI', '泌尿生殖器疾病'],
    ['XII', '皮肤及皮下组织疾病'],
    ['XIII', '肌肉骨骼及结缔组织疾病'],
    ['XIV', '先天性畸形及二重性疾病'],
    ['XV', '产科相关疾病'],
    ['XVI', '某些传染性和母婴性疾病'],
    ['XVII', '胎生期新生儿特异性疾病'],
    ['XVIII', '致死、致残和缓解疼痛等特异性情况'],
    ['XIX', '普通外科与损伤'],
    ['XX', '烧伤及放射性病伤'],
    ['XXI', '特指病人预后情况'],
];

$stmt = $pdo->prepare("INSERT INTO icd_chapters (chapter_code, title) VALUES (?, ?)");
foreach ($chapters as $ch) {
    $stmt->execute($ch);
}

// 79 条临床常见分类
$commonCodes = [
    // 循环系统
    ['I10', '原发性高血压病'], ['I25.9', '支气管动脉粥样硬化性心脏病'],
    ['I25.103', '病发性心脏病'], ['I48.9', '心房颤动'],
    ['I21.9', '急性心肌梗死'], ['I50.9', '心力衰竭'],

    // 肿瘤
    ['C18.9', '结肠恶性肿瘤'], ['C34.9', '肺恶性肿瘤'],
    ['C25.9', '胰腺恶性肿瘤'], ['C50.9', '乳腺恶性肿瘤'],
    ['C61', '前列腺恶性肿瘤'], ['C44.9', '皮肤恶性肿瘤'],

    // 内分泌
    ['E11.9', '类型2糖尿病'], ['E14.9', '糖尿病'],
    ['E10.9', '类型1糖尿病'], ['E16.3', '低血糖症'],

    // 传染病
    ['J00', '急性慢性扁桃体炎'], ['J02.9', '急性上呼吸道感染'],
    ['J06.9', '急性支气管炎'], ['J18.9', '肺炎'],
    ['J15.9', '细菌性肺炎'], ['A09', '急腹型细菌性痘疹性结肠炎'],

    // 呼吸系统
    ['J44.9', '慢性阻塞性肺疾病'], ['J45.9', '哮喘'],
    ['J42', '慢性支气管炎'], ['R03.9', '高血压性呼吸困难'],

    // 消化系统
    ['K59.9', '便秘'], ['K64.9', '痔'],
    ['K76.9', '肝病'], ['K71.9', '肝病'],

    // 泌尿生殖器
    ['N39.0', '急尿病'], ['N30.9', '膀胱炎'],
    ['N39.4', '尿频'], ['O34', '妊娠期疾病'],

    // 妇科
    ['N94.6', '痛经'], ['N83.3', '子宫肌瘤'],
    ['N85.9', '卵巢囊肿'], ['O22.3', '妊娠期出血'],

    // 骨科
    ['M51.9', '梅尼埃尔病'], ['M54.5', '椎间盘突出症'],
    ['M54.5', '腰背痛'], ['S72.0', '股骨颈骨折'],
    ['S82.0', '膝关节骨折'], ['M17.9', '髋关节骨折'],

    // 皮肤
    ['L30.9', '湿疹'], ['L50.9', '荨麼疹'],
    ['L70.0', '脓疱性皮肤病'], ['L81.5', '色素沉着'],

    // 神经
    ['G43.9', '偏头痛'], ['G47.9', '失眠'],
    ['G56.9', '周围神经病'], ['R52.9', '疼痛'],

    // 精神
    ['F41.9', '焦虑障碍'], ['F32.9', '抑郭性障碍'],
    ['F43.9', '适应障碍'], ['F48.9', '神经症'],
    ['F20.9', '精神分裂病'], ['F50.9', '厌食症'],

    // 其他
    ['R50.9', '发热'], ['R55', '晕厥'],
    ['R63.8', '食欲不振'], ['R53.9', '体重减低'],
    ['Z00.9', '健康检查'], ['Z48.8', '术后恢复'],
    ['R06.8', '呼吸困难'], ['R07.9', '胸痛'],
    ['R09.8', '呼吸困难'], ['R10.9', '腹痛'],
    ['R11', '呕吐'], ['R19.7', '腹泻'],
    ['R20.9', '发热'], ['R25.9', '发热'],
    ['Z79.89', '慢性疾病'], ['Z76.9', '不正常生活习惯'],
    ['Z71.9', '咨询'], ['Z73.9', '生活方式改变'],
];

$chaptersMap = $pdo->query("SELECT id, chapter_code FROM icd_chapters")->fetchAll(PDO::FETCH_KEY_PAIR);
$stmt = $pdo->prepare("INSERT INTO icd_codes (code, name, chapter_id, is_common) VALUES (?, ?, ?, 1)");
foreach ($commonCodes as $code) {
    // 确定章节
    $ch = substr($code[0], 0, 1);
    $chapterId = $chaptersMap[$ch] ?? 1;
    $stmt->execute([$code[0], $code[1], $chapterId]);
}

// 触发 FTS5 索引构建
$pdo->exec("INSERT INTO icd_codes_fts(rowid, code, name) SELECT id, code, name FROM icd_codes");
$pdo->exec("INSERT INTO icd_codes_fts(icd_codes_fts) VALUES('rebuild')");

echo "ICD-10 导入完成：" . count($chapters) . " 章节，" . count($commonCodes) . " 条常见分类。\n";
echo "数据库：" . ICD_DB_PATH . "\n";
