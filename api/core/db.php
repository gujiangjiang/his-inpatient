<?php
// api/core/db.php - PDO 单例，SQLite/MySQL/PostgreSQL 三方言

class DB {
    private static $instance = null;
    private static $pdo = null;

    public static function getPDO() {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver = DB_TYPE;
        try {
            if ($driver === 'sqlite') {
                self::$pdo = new PDO(DB_DSN);
                self::$pdo->exec('PRAGMA foreign_keys=ON');
            } elseif ($driver === 'mysql') {
                self::$pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } elseif ($driver === 'postgresql') {
                self::$pdo = new PDO(
                    'pgsql:host=' . DB_HOST . ';dbname=' . DB_NAME,
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            }
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return self::$pdo;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => '数据库连接失败']);
            exit;
        }
    }

    public static function table($name) {
        $db = self::getPDO();
        $stmt = $db->query("SELECT 1 FROM {$name} LIMIT 1");
        return $stmt !== false;
    }

    public static function select($sql, $params = []) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function selectOne($sql, $params = []) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function insert($table, $data) {
        $pdo = self::getPDO();
        $cols = array_keys($data);
        $colList = implode(',', $cols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $stmt = $pdo->prepare("INSERT INTO {$table} ({$colList}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        return $pdo->lastInsertId();
    }

    public static function update($table, $data, $where, $params = []) {
        $pdo = self::getPDO();
        $setParts = [];
        $values = [];
        foreach ($data as $col => $val) {
            $setParts[] = "{$col} = ?";
            $values[] = $val;
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($values, $params));
        return $stmt->rowCount();
    }

    public static function delete($table, $where, $params = []) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function execute($sql, $params = []) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 幂等 upsert（三方言兼容）：按唯一键存在则更新非键列，否则整行插入。
     * 替代「先 SELECT 判存在 → UPDATE/INSERT」的非原子写法（并发窗口不丢更新）：
     *  1) 先 UPDATE（条件为唯一键），命中行数 > 0 即完成；
     *  2) 未命中 → INSERT；并发窗口撞唯一键（如 MySQL 同值更新计 0 行的边界）
     *     捕获后回退再 UPDATE 一次，最终语义幂等。
     * $table/$uniqueCols 仅限调用方字面量（同 insert/update 表名约定）。
     */
    public static function upsert($table, array $data, array $uniqueCols) {
        $whereParts = [];
        $whereParams = [];
        foreach ($uniqueCols as $c) {
            $whereParts[] = "{$c} = ?";
            $whereParams[] = $data[$c];
        }
        $where = implode(' AND ', $whereParts);
        $updCols = array_diff_key($data, array_flip($uniqueCols));
        if ($updCols) {
            $aff = self::update($table, $updCols, $where, $whereParams);
            if ($aff > 0) return 'updated';
        }
        try {
            self::insert($table, $data);
            return 'inserted';
        } catch (PDOException $e) {
            if ($updCols) self::update($table, $updCols, $where, $whereParams);
            return 'updated';
        }
    }
}
