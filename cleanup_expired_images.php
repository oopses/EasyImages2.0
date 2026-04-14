<?php

/**
 * 图片过期清理脚本
 * 此脚本会清理所有过期的图片文件
 *
 * 使用方式:
 *   1. 手动执行: php cleanup_expired_images.php
 *   2. Cron定时执行: 0 2 * * * /usr/bin/php /path/to/cleanup_expired_images.php
 *
 * 支持两种数据源:
 *   - 数据库 (优先)
 *   - 文件日志 (兼容旧版本)
 */

require_once __DIR__ . '/app/base.php';
require_once __DIR__ . '/app/function.php';

echo "========================================\n";
echo "图片过期清理脚本\n";
echo "当前时间: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// 检查是否启用过期清理
if (!$config['image_expiration_enable'] || !$config['image_expiration_cleanup_enable']) {
    echo "[跳过] 图片过期功能未启用或自动清理已禁用\n";
    exit(0);
}

echo "[信息] 图片过期清理功能已启用\n\n";

// 判断使用数据库还是文件模式
$useDatabase = Database::isAvailable();

if ($useDatabase) {
    echo "[信息] 使用数据库模式清理\n\n";
    cleanupWithDatabase();
} else {
    echo "[信息] 使用文件模式清理\n\n";
    cleanupWithFiles();
}

echo "\n========================================\n";
echo "清理完成!\n";
echo "========================================\n";

/**
 * 数据库模式清理
 */
function cleanupWithDatabase()
{
    global $config;

    echo ">>> 开始数据库模式清理 <<<\n\n";

    $expiredRecords = db_get_expired_records();

    if (empty($expiredRecords)) {
        echo "[完成] 没有找到过期图片\n";
        return;
    }

    echo "[信息] 找到 " . count($expiredRecords) . " 条过期记录\n\n";

    $deleted = 0;
    $errors = 0;

    foreach ($expiredRecords as $record) {
        $filePath = APP_ROOT . $record['path'];
        $expireTime = date('Y-m-d H:i:s', $record['expire_time']);

        echo "处理: {$record['path']}\n";
        echo "  - 过期时间: {$expireTime}\n";

        // 删除实际文件
        if (file_exists($filePath)) {
            if (@unlink($filePath)) {
                echo "  - [成功] 已删除文件\n";
            } else {
                echo "  - [错误] 删除文件失败\n";
                $errors++;
                continue;
            }

            // 同时删除缩略图
            $thumbPath = APP_ROOT . '/cache/' . pathinfo($record['path'], PATHINFO_FILENAME) . '_thumb.' . pathinfo($record['path'], PATHINFO_EXTENSION);
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
                echo "  - [信息] 已删除缩略图\n";
            }
        } else {
            echo "  - [警告] 文件不存在(可能已被删除)\n";
        }

        // 软删除数据库记录
        if (db_mark_record_as_deleted($record['id'])) {
            echo "  - [成功] 已标记数据库记录为已删除\n";
            $deleted++;
        } else {
            echo "  - [错误] 标记数据库记录失败\n";
            $errors++;
        }

        echo "\n";
    }

    echo "----------------------------------------\n";
    echo "数据库模式清理结果:\n";
    echo "  - 已处理: " . count($expiredRecords) . " 条\n";
    echo "  - 已删除: {$deleted} 条\n";
    echo "  - 错误数: {$errors} 条\n";
}

/**
 * 文件模式清理 (兼容旧版本)
 */
function cleanupWithFiles()
{
    global $config;

    echo ">>> 开始文件模式清理 <<<\n\n";

    $cleanup_count = 0;
    $log_files_processed = 0;
    $errors = 0;

    // 获取所有日志文件目录
    $log_dir = APP_ROOT . '/admin/logs/upload/';
    if (!is_dir($log_dir)) {
        echo "[错误] 日志目录不存在: $log_dir\n";
        return;
    }

    // 扫描日志目录
    $log_files = glob($log_dir . '*.php');
    $current_time = time();

    foreach ($log_files as $log_file) {
        $log_files_processed++;
        echo "处理日志文件: " . basename($log_file) . "\n";

        // 读取日志文件
        if (!is_file($log_file)) {
            continue;
        }

        include $log_file;

        if (!isset($logs) || !is_array($logs)) {
            continue;
        }

        // 检查每个日志条目
        foreach ($logs as $filename => $log_entry) {
            // 检查是否有过期时间
            if (!isset($log_entry['expire_time']) || $log_entry['expire_time'] === null || $log_entry['expire_time'] === '') {
                continue; // 永久保存的图片
            }

            // 检查是否过期
            if ($current_time > $log_entry['expire_time']) {
                // 图片已过期，删除文件
                $file_path = APP_ROOT . '/' . $log_entry['path'];

                if (file_exists($file_path)) {
                    if (unlink($file_path)) {
                        echo "  - 已删除: {$log_entry['path']} (过期时间: " . date('Y-m-d H:i:s', $log_entry['expire_time']) . ")\n";
                        $cleanup_count++;

                        // 从日志中移除记录
                        unset($logs[$filename]);
                    } else {
                        echo "  - 删除失败: {$log_entry['path']}\n";
                        $errors++;
                    }
                } else {
                    echo "  - 文件不存在(跳过): {$log_entry['path']}\n";
                    // 从日志中移除记录
                    unset($logs[$filename]);
                }
            }
        }

        // 更新日志文件
        if (!empty($logs)) {
            cache_write($log_file, $logs, 'logs');
        } else {
            // 如果日志为空，删除日志文件
            unlink($log_file);
        }
    }

    echo "\n----------------------------------------\n";
    echo "文件模式清理结果:\n";
    echo "  - 处理日志文件: $log_files_processed\n";
    echo "  - 删除过期图片: $cleanup_count\n";
    echo "  - 错误数: $errors\n";
}
