<?php
// api/core/schema_guard.php - 请求级自动迁移守卫
// 目的: 部署新版本后无需手动执行迁移脚本, 首个请求自动补齐未应用的数据库迁移
// 机制: 比对迁移目录最大版本号与 migrations 表已应用最大版本, 不一致时加锁执行 Migration::runAll()
// 约束: 仅在系统初始化完成后生效; 失败只记日志不阻断请求 (对应接口会给出明确错误)
class SchemaGuard {
    private static $checked = false;

    public static function ensure() {
        if (self::$checked) return;
        self::$checked = true;
        try {
            if (!SetupGuard::check()) return;          // 未初始化: 交给初始化向导
            $dir = dirname(__DIR__) . '/migrations/php';
            $files = glob($dir . '/*.php');
            if (!$files) return;
            $target = 0;
            foreach ($files as $f) {
                if (preg_match('/^(\d+)_/', basename($f), $m)) $target = max($target, (int)$m[1]);
            }
            if ($target === 0) return;
            if (self::applied() >= $target) return;    // 已是最新: 仅一条轻量查询
            self::run($dir, $target);
        } catch (Throwable $e) {
            error_log('[SchemaGuard] 自动迁移检查失败: ' . $e->getMessage());
        }
    }

    private static function applied() {
        $row = DB::selectOne('SELECT MAX(version) AS v FROM migrations');
        return ($row && $row['v'] !== null) ? (int)$row['v'] : 0;
    }

    private static function run($dir, $target) {
        $fp = @fopen(DATA_DIR . '/.schema.lock', 'c');
        try {
            if ($fp) @flock($fp, LOCK_EX);
            if (self::applied() >= $target) return;    // 双检: 并发请求中其他进程可能已完成
            Migration::runAll($dir);
            error_log('[SchemaGuard] 自动迁移完成 -> v' . $target);
        } finally {
            if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
        }
    }
}