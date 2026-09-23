<?php
// api/core/migration.php - 数据库迁移辅助类
// 统一 SQLite/MySQL/PostgreSQL 迁移语法

class Migration {
    private $pdo;
    private $driver;

    public function __construct() {
        $this->pdo = DB::getPDO();
        $this->driver = DB_TYPE;
    }

    public function getDriver() {
        return $this->driver;
    }

    private function quoteId($name) {
        if ($this->driver === 'postgresql') {
            return '"' . str_replace('"', '""', $name) . '"';
        }
        return '`' . $name . '`';
    }

    // 标准化列类型名称
    private function normalizeType($type, $length = null) {
        $type = strtoupper($type);
        if ($this->driver === 'mysql') {
            switch ($type) {
                case 'VARCHAR': return 'VARCHAR';
                case 'TEXT': return 'TEXT';
                case 'INTEGER': return 'INT';
                case 'REAL': return 'DECIMAL';
                case 'BOOLEAN': return 'TINYINT';
            }
        } elseif ($this->driver === 'postgresql') {
            switch ($type) {
                case 'INTEGER': return 'INTEGER';
                case 'REAL': return 'REAL';
                case 'BOOLEAN': return 'BOOLEAN';
                case 'VARCHAR': return 'VARCHAR';
                case 'TEXT': return 'TEXT';
            }
        }
        return $type;
    }

    public function getAutoIncrementClause() {
        if ($this->driver === 'sqlite') return 'AUTOINCREMENT';
        if ($this->driver === 'mysql') return 'AUTO_INCREMENT';
        if ($this->driver === 'postgresql') return ''; // handled via SERIAL
        return '';
    }

    public function isSerial($type) {
        return $this->driver === 'postgresql' && in_array(strtoupper($type), ['INTEGER', 'INT']);
    }

    // 创建表
    public function createTable($table, $columns, $options = []) {
        $ifNotExists = $options['if_not_exists'] ?? false;
        $foreignKeys = $options['foreign_keys'] ?? [];
        $usesSerial = false;

        $parts = [];
        foreach ($columns as $name => $def) {
            if (is_string($def)) {
                $parts[] = $this->quoteId($name) . ' ' . $def;
            } else {
                $type = $this->normalizeType($def['type'], $def['length'] ?? null);
                
                // PostgreSQL: use SERIAL instead of AUTO_INCREMENT
                if ($this->isSerial($def['type'] ?? '') && isset($def['auto_increment']) && $def['auto_increment']) {
                    $type = 'SERIAL';
                    $usesSerial = true;
                }

                $colSql = $this->quoteId($name) . ' ' . $type;
                if (isset($def['length']) && !$usesSerial) $colSql .= '(' . $def['length'] . ')';
                if (isset($def['default'])) $colSql .= ' DEFAULT ' . $this->formatDefault($def['default']);
                if (isset($def['notnull']) && $def['notnull']) $colSql .= ' NOT NULL';
                if (isset($def['primary']) && $def['primary']) $colSql .= ' PRIMARY KEY';
                if (isset($def['auto_increment']) && $def['auto_increment'] && !$usesSerial) {
                    $colSql .= ' ' . $this->getAutoIncrementClause();
                }
                $parts[] = $colSql;
            }
        }

        // 外键约束 (兼容 SQLite/MySQL/PostgreSQL)
        foreach ($foreignKeys as $fk) {
            $parts[] = 'FOREIGN KEY (' . $this->quoteId($fk['column']) . ') REFERENCES ' .
                $this->quoteId($fk['references']) . '(' . $this->quoteId($fk['on']) . ')';
        }

        $keyword = $ifNotExists ? 'IF NOT EXISTS ' : '';
        if ($this->driver === 'postgresql') {
            // PostgreSQL handles IF NOT EXISTS in CREATE TABLE
        }
        $sql = "CREATE TABLE " . $keyword . $this->quoteId($table) . ' (' . implode(', ', $parts) . ')';

        if ($this->driver === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        }

        $this->pdo->exec($sql);
    }

    private function formatDefault($value) {
        if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
        if (is_numeric($value)) return (string)$value;
        if ($value === null) return 'NULL';
        return "'" . str_replace("'", "''", $value) . "'";
    }

    public function addColumn($table, $column, $definition) {
        $type = $this->normalizeType($definition['type'] ?? 'TEXT', $definition['length'] ?? null);
        $default = isset($definition['default']) ? ' DEFAULT ' . $this->formatDefault($definition['default']) : '';
        $notnull = isset($definition['notnull']) && $definition['notnull'] ? ' NOT NULL' : '';

        $sql = "ALTER TABLE " . $this->quoteId($table) . " ADD COLUMN " . $this->quoteId($column) .
               ' ' . $type . $default . $notnull;
        $this->pdo->exec($sql);
    }

    public function hasColumn($table, $column) {
        if ($this->driver === 'postgresql') {
            $stmt = $this->pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return $stmt->fetch() !== false;
        } elseif ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM " . $this->quoteId($table) . " WHERE Field = ?");
            $stmt->execute([$column]);
            return $stmt->fetch() !== false;
        } else {
            $stmt = $this->pdo->query("PRAGMA table_info(" . $this->quoteId($table) . ")");
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return in_array($column, array_column($cols, 'name'));
        }
    }

    public function hasTable($table) {
        if ($this->driver === 'postgresql') {
            $stmt = $this->pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_name = ?");
            $stmt->execute([$table]);
            return $stmt->fetch() !== false;
        } elseif ($this->driver === 'mysql') {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
            return $stmt->fetch() !== false;
        } else {
            $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            return $stmt->fetch() !== false;
        }
    }

    public function createIndex($table, $name, $columns) {
        if (!$this->hasIndex($table, $name)) {
            $cols = implode(', ', array_map([$this, 'quoteId'], $columns));
            $sql = "CREATE INDEX " . $this->quoteId($name) . " ON " . $this->quoteId($table) . " ({$cols})";
            if ($this->driver === 'postgresql') {
                $sql = 'CREATE INDEX ' . $name . ' ON ' . $this->quoteId($table) . ' (' . implode(', ', $columns) . ')';
            }
            $this->pdo->exec($sql);
        }
    }

    public function hasIndex($table, $indexName) {
        if ($this->driver === 'postgresql') {
            $stmt = $this->pdo->prepare("SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?");
            $stmt->execute([$table, $indexName]);
            return $stmt->fetch() !== false;
        } elseif ($this->driver === 'mysql') {
            $stmt = $this->pdo->query("SHOW INDEX FROM " . $this->quoteId($table) . " WHERE Key_name = '{$indexName}'");
            return $stmt->fetch() !== false;
        } else {
            $stmt = $this->pdo->query("PRAGMA index_list(" . $this->quoteId($table) . ")");
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return in_array($indexName, array_column($indexes, 'name'));
        }
    }

    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function enableForeignKeys() {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys=ON');
        }
    }

    public function recordMigration($version) {
        if (!$this->hasTable('migrations')) {
            $this->createTable('migrations', [
                'version' => ['type' => 'VARCHAR', 'length' => '20', 'primary' => true],
                'applied_at' => ['type' => 'TEXT']
            ]);
        }
        $sql = $this->driver === 'postgresql'
            ? "INSERT INTO migrations (version, applied_at) VALUES (?, ?) ON CONFLICT (version) DO UPDATE SET applied_at = EXCLUDED.applied_at"
            : "INSERT OR REPLACE INTO migrations (version, applied_at) VALUES (?, ?)";
        $this->execute($sql, [$version, DateHelper::now()]);
    }

    public function isMigrated($version) {
        if (!$this->hasTable('migrations')) return false;
        $check = DB::selectOne("SELECT version FROM migrations WHERE version = ?", [$version]);
        return $check !== false;
    }
}
