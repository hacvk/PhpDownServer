<?php
declare(strict_types=1);

class Db {
    private \PDO $pdo;

    public function __construct(string $file) {
        $this->pdo = new \PDO('sqlite:' . $file);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function migrate(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            );
            CREATE TABLE IF NOT EXISTS files (
                id INTEGER PRIMARY KEY,
                path TEXT UNIQUE,
                is_dir INTEGER,
                size INTEGER,
                mtime INTEGER,
                allowed_ranges TEXT,
                access_password TEXT
            );
            CREATE TABLE IF NOT EXISTS audit (
                id INTEGER PRIMARY KEY,
                event TEXT,
                path TEXT,
                ip TEXT,
                ts INTEGER,
                detail TEXT
            );
        ");
        // 兼容旧表，补充列
        try {
            $this->pdo->exec("ALTER TABLE files ADD COLUMN access_password TEXT");
        } catch (\Throwable $e) {
            // 已存在则忽略
        }
    }

    public function getSettings(): array {
        $stmt = $this->pdo->query("SELECT key, value FROM settings");
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
    }

    public function setSetting(string $key, string $value): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO settings(key, value) VALUES(:k, :v)
            ON CONFLICT(key) DO UPDATE SET value = :v
        ");
        $stmt->execute([':k' => $key, ':v' => $value]);
    }

    public function pdo(): \PDO {
        return $this->pdo;
    }
}


