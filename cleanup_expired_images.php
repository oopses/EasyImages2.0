<?php

/**
 * 图片过期清理脚本
 * 此脚本会清理所有过期的图片文件
 * 建议通过 cron 定时执行：0 2 * * * /usr/bin/php /path/to/cleanup_expired_images.php
 */

require_once __DIR__ . '/app/base.php';
require_once __DIR__ . '/app/function.php';

echo "开始清理过期图片...\n";
echo "当前时间: " . date('Y-m-d H:i:s') . "\n";

// 检查是否启用过期清理
if (!$config['image_expiration_enable'] || !$config['image_expiration_cleanup_enable']) {
    echo "图片过期功能未启用或自动清理已禁用\n";
    exit(0);
}

$cleanup_count = 0;
$log_files_processed = 0;

// 获取所有日志文件目录
$log_dir = APP_ROOT . '/admin/logs/upload/';
if (!is_dir($log_dir)) {
    echo "日志目录不存在: $log_dir\n";
    exit(0);
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
                    echo "已删除过期图片: {$log_entry['path']} (过期时间: " . date('Y-m-d H:i:s', $log_entry['expire_time']) . ")\n";
                    $cleanup_count++;

                    // 从日志中移除记录
                    unset($logs[$filename]);
                } else {
                    echo "删除失败: {$log_entry['path']}\n";
                }
            } else {
                echo "文件不存在: {$log_entry['path']}\n";
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

echo "清理完成!\n";
echo "处理日志文件数: $log_files_processed\n";
echo "删除过期图片数: $cleanup_count\n";

?>