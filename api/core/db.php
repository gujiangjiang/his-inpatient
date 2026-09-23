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
}
