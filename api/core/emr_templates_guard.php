<?php
// api/core/emr_templates_guard.php - 内置病历模板自动落库 (部署自愈, 免 CLI/免 npm)
// 与数据库迁移同一思路: 代码更新后首个 API 请求即自动生成/更新全部内置模板,
// 无需在服务器执行任何命令 (无 npm / 无终端环境的部署同样生效)。
// 变更内置模板定义后: 递增 VERSION, 下个请求自动重新落库 (按模板名更新, 既有文书不受影响)。
class EmrTemplatesGuard {
    const VERSION = '2'; /* 内置模板定义/版式变更时 +1 */
    private static $checked = false;

    public static function ensure() {
        if (self::$checked) return;
        self::$checked = true;
        try {
            if (!SetupGuard::check()) return; /* 未初始化: 交给初始化向导 */
            $row = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = 'emr_templates_version'");
            if ($row && (string)$row['config_value'] === self::VERSION) return;
            /* 表未就绪(迁移尚未执行)时跳过, 下个请求再试 */
            if (!class_exists('Migration')) return;
            $mig = new Migration();
            if (!$mig->hasTable('emr_templates') || !$mig->hasTable('emr_categories') || !$mig->hasTable('emr_documents')) return;
            self::run();
        } catch (Throwable $e) {
            error_log('[EmrTemplatesGuard] 内置模板检查失败: ' . $e->getMessage());
        }
    }

    /** 并发保护 + 双检 + 落库 + 版本标记 */
    private static function run() {
        $fp = @fopen(DATA_DIR . '/.templates.lock', 'c');
        try {
            if ($fp) @flock($fp, LOCK_EX);
            $row = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = 'emr_templates_version'");
            if ($row && (string)$row['config_value'] === self::VERSION) return;
            self::provision();
            try {
                DB::execute(
                    "INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES ('emr_templates_version', ?, 'emr', '内置病历模板版本', ?)",
                    [self::VERSION, DateHelper::now()]
                );
            } catch (Exception $e) { /* 标记写入失败仅导致下次重跑(幂等), 不阻断 */ }
            error_log('[EmrTemplatesGuard] 内置模板已落库 -> v' . self::VERSION);
        } finally {
            if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
        }
    }

    /**
     * 生成/更新全部内置模板 (按名称幂等), 并清理早期版本的测试模板。
     * 返回内置模板名称列表。
     */
    public static function provision() {
        $hospital = '';
        try {
            $h = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = 'hospital_name'");
            if ($h) $hospital = (string)$h['config_value'];
        } catch (Exception $e) { /* 忽略 */ }
        if ($hospital === '') $hospital = '医院名称';

        $defsFn = require __DIR__ . '/emr_template_defs.php';
        $defs = $defsFn($hospital);

        $pdo = DB::getPDO();

        /* 清理早期版本种子产生的测试模板: 先解除文书引用(文书自有守卫, 不依赖模板), 再删除 */
        $legacyRows = DB::select("SELECT id, name FROM emr_templates WHERE name IN ('常规入院记录','入院记录(字处理)') OR name LIKE '%(字处理)%' OR name LIKE '%测试%'");
        foreach ($legacyRows as $t) {
            $pdo->prepare("UPDATE emr_documents SET template_id = NULL WHERE template_id = ?")->execute([$t['id']]);
            $pdo->prepare("DELETE FROM emr_templates WHERE id = ?")->execute([$t['id']]);
        }

        $names = [];
        foreach ($defs as $d) {
            list($name, $recordType, $catCode, $schema) = $d;
            $cat = DB::selectOne("SELECT id FROM emr_categories WHERE code = ?", [$catCode]);
            if (!$cat) continue;
            $json = json_encode($schema, JSON_UNESCAPED_UNICODE);
            $row = DB::selectOne("SELECT id FROM emr_templates WHERE name = ?", [$name]);
            if ($row) {
                DB::execute("UPDATE emr_templates SET schema_json = ?, category_id = ?, record_type = ? WHERE id = ?",
                    [$json, $cat['id'], $recordType, $row['id']]);
            } else {
                DB::execute("INSERT INTO emr_templates (name, record_type, schema_json, category_id, usage_count, created_at) VALUES (?,?,?,?,0,?)",
                    [$name, $recordType, $json, $cat['id'], DateHelper::now()]);
            }
            $names[] = $name;
        }
        return $names;
    }
}