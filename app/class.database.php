<?php
/**
 * 数据库操作类
 * 支持 MySQL/MariaDB
 */

class Database
{
    private static $instance = null;
    private $pdo;
    private $tablePrefix = 'easyimg_';

    private function __construct()
    {
        global $config;

        $db = $config['db'] ?? [];

        if (empty($db['enable'])) {
            throw new Exception('Database is not enabled');
        }

        $host = $db['host'] ?? 'localhost';
        $port = $db['port'] ?? 3306;
        $database = $db['database'] ?? '';
        $charset = $db['charset'] ?? 'utf8mb4';
        $this->tablePrefix = $db['table_prefix'] ?? 'easyimages_';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            $this->pdo = new PDO(
                $dsn,
                $db['username'] ?? '',
                $db['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );

            // 设置 MySQL session 时区与 PHP 时区一致，解决时区不一致问题
            $tz = $config['timezone'] ?? 'Asia/Shanghai';
            $offset = $this->getTimezoneOffset($tz);
            $this->pdo->exec("SET time_zone = '{$offset}'");
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * 将 PHP 时区名称转换为 MySQL 时区偏移量
     * @param string $timezone PHP 时区名称 (如 'Asia/Shanghai')
     * @return string MySQL 时区偏移量 (如 '+08:00')
     */
    private function getTimezoneOffset($timezone)
    {
        $offsets = [
            'Pacific/Honolulu' => '-10:00',
            'America/Anchorage' => '-09:00',
            'America/Los_Angeles' => '-08:00',
            'America/Denver' => '-07:00',
            'America/Chicago' => '-06:00',
            'America/New_York' => '-05:00',
            'America/Halifax' => '-04:00',
            'America/Sao_Paulo' => '-03:00',
            'Atlantic/Reykjavik' => '+00:00',
            'Europe/London' => '+00:00',
            'Europe/Paris' => '+01:00',
            'Europe/Berlin' => '+01:00',
            'Africa/Cairo' => '+02:00',
            'Europe/Moscow' => '+03:00',
            'Asia/Tehran' => '+03:30',
            'Asia/Kolkata' => '+05:30',
            'Asia/Dhaka' => '+06:00',
            'Asia/Bangkok' => '+07:00',
            'Asia/Shanghai' => '+08:00',
            'Asia/Hong_Kong' => '+08:00',
            'Asia/Taipei' => '+08:00',
            'Asia/Tokyo' => '+09:00',
            'Australia/Sydney' => '+10:00',
            'Pacific/Auckland' => '+12:00',
        ];
        return $offsets[$timezone] ?? '+08:00';
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * 插入记录
     * @param string $table 表名(不含前缀)
     * @param array $data 字段=>值
     * @return int|string 返回插入ID
     */
    public function insert(string $table, array $data): int|string
    {
        $table = $this->tablePrefix . $table;
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return $this->pdo->lastInsertId();
    }

    /**
     * 更新记录
     * @param string $table 表名
     * @param array $data 字段=>值
     * @param string $where 条件(SQL片段)
     * @param array $params 条件参数
     * @return int 影响行数
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $table = $this->tablePrefix . $table;
        $set = array_map(fn($f) => "$f = :$f", array_keys($data));

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $set),
            $where
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($data, $params));

        return $stmt->rowCount();
    }

    /**
     * 删除记录(软删除)
     * @param string $table 表名
     * @param string $where 条件
     * @param array $params 条件参数
     * @return int 影响行数
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $table = $this->tablePrefix . $table;

        // 软删除: 更新 deleted_at
        $sql = sprintf(
            "UPDATE %s SET deleted_at = NOW() WHERE %s",
            $table,
            $where
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * 查询单条记录
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return array|null
     */
    public function getRow(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * 查询多条记录
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return array
     */
    public function getAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * 查询单个值
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return mixed
     */
    public function getOne(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * 统计数量
     * @param string $table 表名
     * @param string $where 条件
     * @param array $params 参数
     * @return int
     */
    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $table = $this->tablePrefix . $table;
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        return (int) $this->getOne($sql, $params);
    }

    /**
     * 分页查询
     * @param string $table 表名
     * @param int $page 页码(从1开始)
     * @param int $pageSize 每页数量
     * @param string $where 条件
     * @param string $order 排序
     * @param array $params 参数
     * @return array ['data' => [], 'total' => 0, 'page' => 1, 'pageSize' => 20]
     */
    public function paginate(string $table, int $page = 1, int $pageSize = 20, string $where = '1=1', string $order = 'id DESC', array $params = []): array
    {
        $table = $this->tablePrefix . $table;

        // 总数
        $total = $this->count($table, $where, $params);

        // 数据
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT {$offset}, {$pageSize}";

        $data = $this->getAll($sql, $params);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize),
        ];
    }

    /**
     * 检查数据库是否可用
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $config;

        if (empty($config['db']['enable'])) {
            return false;
        }

        try {
            $db = self::getInstance();
            $db->getOne('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 自动初始化数据库表结构
     * @return bool 是否成功
     */
    public static function initDatabase(): bool
    {
        global $config;

        if (empty($config['db']['enable'])) {
            return false;
        }

        try {
            $db = self::getInstance();
            $prefix = $config['db']['table_prefix'] ?? 'easyimages_';

            // 创建 records 表
            $db->exec("CREATE TABLE IF NOT EXISTS {$prefix}records (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
                filename VARCHAR(255) NOT NULL COMMENT '文件名',
                original_name VARCHAR(500) NOT NULL COMMENT '原始文件名',
                path VARCHAR(1000) NOT NULL COMMENT '存储路径',
                url VARCHAR(1000) NOT NULL COMMENT '访问URL',
                thumb_url VARCHAR(1000) DEFAULT NULL COMMENT '缩略图URL',
                del_url VARCHAR(255) DEFAULT NULL COMMENT '删除URL',
                file_size BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件大小',
                size_formatted VARCHAR(50) DEFAULT NULL COMMENT '格式化大小',
                md5 CHAR(32) NOT NULL COMMENT '文件MD5',
                width INT UNSIGNED DEFAULT 0 COMMENT '图片宽度',
                height INT UNSIGNED DEFAULT 0 COMMENT '图片高度',
                mime_type VARCHAR(50) DEFAULT NULL COMMENT 'MIME类型',
                ip VARCHAR(45) NOT NULL COMMENT '上传IP',
                port INT UNSIGNED DEFAULT NULL COMMENT '端口',
                user_agent VARCHAR(500) DEFAULT NULL COMMENT 'User-Agent',
                upload_source VARCHAR(20) NOT NULL DEFAULT 'web' COMMENT '来源',
                check_status TINYINT NOT NULL DEFAULT 0 COMMENT '鉴黄状态',
                expiration VARCHAR(20) NOT NULL DEFAULT 'never' COMMENT '过期选项',
                expire_time BIGINT UNSIGNED DEFAULT NULL COMMENT '过期时间戳',
                expire_time_formatted DATETIME DEFAULT NULL COMMENT '格式化过期时间',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                deleted_at DATETIME DEFAULT NULL COMMENT '删除时间',
                is_deleted TINYINT NOT NULL DEFAULT 0 COMMENT '是否已删除',
                INDEX idx_md5 (md5),
                INDEX idx_ip (ip),
                INDEX idx_created_at (created_at),
                INDEX idx_expire_time (expire_time),
                INDEX idx_upload_source (upload_source),
                INDEX idx_is_deleted (is_deleted)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='图片记录表'");

            // 创建 tokens 表
            $db->exec("CREATE TABLE IF NOT EXISTS {$prefix}tokens (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(64) NOT NULL COMMENT 'Token密钥',
                name VARCHAR(100) NOT NULL COMMENT 'Token名称',
                path_id VARCHAR(50) DEFAULT NULL COMMENT '关联路径ID',
                daily_limit INT NOT NULL DEFAULT 0 COMMENT '日限额',
                today_count INT NOT NULL DEFAULT 0 COMMENT '今日已用',
                total_count INT NOT NULL DEFAULT 0 COMMENT '总使用次数',
                last_date DATE DEFAULT NULL COMMENT '最后使用日期',
                last_ip VARCHAR(45) DEFAULT NULL COMMENT '最后使用IP',
                expires_at DATETIME DEFAULT NULL COMMENT '过期时间',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                is_active TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用',
                note VARCHAR(500) DEFAULT NULL COMMENT '备注',
                UNIQUE KEY uk_token (token),
                INDEX idx_is_active (is_active),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Token表'");

            // 创建 stats 表
            $db->exec("CREATE TABLE IF NOT EXISTS {$prefix}stats (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                stat_date DATE NOT NULL COMMENT '统计日期',
                upload_count INT NOT NULL DEFAULT 0 COMMENT '上传次数',
                total_size BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总大小',
                total_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计图片数',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_stat_date (stat_date),
                INDEX idx_stat_date (stat_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统计汇总表'");

            // 初始化今日统计
            $today = date('Y-m-d');
            $db->exec("INSERT IGNORE INTO {$prefix}stats (stat_date, upload_count, total_size, total_count) VALUES ('{$today}', 0, 0, 0)");

            return true;
        } catch (Exception $e) {
            error_log('initDatabase failed: ' . $e->getMessage());
            return false;
        }
    }
}