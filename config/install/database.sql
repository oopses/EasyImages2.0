-- EasyImages2.0 数据库初始化脚本
-- 执行方式: 登录 phpMyAdmin -> 选择数据库 -> 导入此文件

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 表结构: 图片记录表
-- ----------------------------
DROP TABLE IF EXISTS `easyimg_records`;
CREATE TABLE `easyimg_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `filename` VARCHAR(255) NOT NULL COMMENT '文件名(不含路径)',
  `original_name` VARCHAR(500) NOT NULL COMMENT '原始文件名',
  `path` VARCHAR(1000) NOT NULL COMMENT '存储路径',
  `url` VARCHAR(1000) NOT NULL COMMENT '访问URL',
  `thumb_url` VARCHAR(1000) DEFAULT NULL COMMENT '缩略图URL',
  `del_url` VARCHAR(255) DEFAULT NULL COMMENT '删除URL',
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件大小(字节)',
  `size_formatted` VARCHAR(50) DEFAULT NULL COMMENT '格式化大小(如 912.71KB)',
  `md5` CHAR(32) NOT NULL COMMENT '文件MD5',
  `width` INT UNSIGNED DEFAULT 0 COMMENT '图片宽度',
  `height` INT UNSIGNED DEFAULT 0 COMMENT '图片高度',
  `mime_type` VARCHAR(50) DEFAULT NULL COMMENT 'MIME类型',
  `ip` VARCHAR(45) NOT NULL COMMENT '上传IP(v4/v6)',
  `port` INT UNSIGNED DEFAULT NULL COMMENT '端口',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'User-Agent',
  `upload_source` VARCHAR(20) NOT NULL DEFAULT 'web' COMMENT '来源: web/api/guest',
  `check_status` TINYINT NOT NULL DEFAULT 0 COMMENT '鉴黄状态: 0未检查 1正常 2违规',
  `expiration` VARCHAR(20) NOT NULL DEFAULT 'never' COMMENT '过期选项',
  `expire_time` BIGINT UNSIGNED DEFAULT NULL COMMENT '过期时间戳',
  `expire_time_formatted` DATETIME DEFAULT NULL COMMENT '格式化过期时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at` DATETIME DEFAULT NULL COMMENT '删除时间(软删除)',
  `is_deleted` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已删除: 0否 1是',

  PRIMARY KEY (`id`),
  INDEX `idx_md5` (`md5`),
  INDEX `idx_ip` (`ip`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_expire_time` (`expire_time`),
  INDEX `idx_upload_source` (`upload_source`),
  INDEX `idx_is_deleted` (`is_deleted`),
  INDEX `idx_original_name` (`original_name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='图片记录表';

-- ----------------------------
-- 表结构: API Token表
-- ----------------------------
DROP TABLE IF EXISTS `easyimg_tokens`;
CREATE TABLE `easyimg_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `token` VARCHAR(64) NOT NULL COMMENT 'Token密钥',
  `name` VARCHAR(100) NOT NULL COMMENT 'Token名称/备注',
  `path_id` VARCHAR(50) DEFAULT NULL COMMENT '关联的上传路径ID',
  `daily_limit` INT NOT NULL DEFAULT 0 COMMENT '日限额(0不限制)',
  `today_count` INT NOT NULL DEFAULT 0 COMMENT '今日已使用次数',
  `total_count` INT NOT NULL DEFAULT 0 COMMENT '总使用次数',
  `last_date` DATE DEFAULT NULL COMMENT '最后使用日期',
  `last_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后使用IP',
  `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间(NULL永久)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `is_active` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用: 0禁用 1启用',
  `note` VARCHAR(500) DEFAULT NULL COMMENT '备注',

  UNIQUE KEY `uk_token` (`token`),
  INDEX `idx_is_active` (`is_active`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Token表';

-- ----------------------------
-- 表结构: 统计汇总表(用于快速查询)
-- ----------------------------
DROP TABLE IF EXISTS `easyimg_stats`;
CREATE TABLE `easyimg_stats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `stat_date` DATE NOT NULL COMMENT '统计日期',
  `upload_count` INT NOT NULL DEFAULT 0 COMMENT '上传次数',
  `total_size` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总大小(字节)',
  `total_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计图片数',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `uk_stat_date` (`stat_date`),
  INDEX `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统计汇总表';

-- ----------------------------
-- 初始化统计数据(从零开始)
-- ----------------------------
INSERT INTO `easyimg_stats` (`stat_date`, `upload_count`, `total_size`, `total_count`) VALUES (CURDATE(), 0, 0, 0);

SET FOREIGN_KEY_CHECKS = 1;
