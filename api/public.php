<?php

/**
 * 图床公共信息查询API
 * 2024年04月07日 08:00:00
 * @author Icret
 */

// 定义常量以替换魔术字符串
const TIME_KEY = 'total_time';
const TODAY_UPLOAD_KEY = 'todayUpload';
const YESTERDAY_UPLOAD_KEY = 'yestUpload';
const USAGE_SPACE_KEY = 'usage_space';
const FILENUM_KEY = 'filenum';
const DIRNUM_KEY = 'dirnum';

require_once '../app/chart.php';
require_once '../app/function.php';

// 检查是否开启查询 (history 动作除外)
if ($config['public'] === 0 && (!isset($_POST['action']) || $_POST['action'] !== 'history')) {
    http_response_code(403); // 返回403 Forbidden
    die('开放数据接口已关闭!');
}

// 处理 POST 请求 (用于 history 等需要 post 的接口)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // history 端点 - 获取上传历史记录 (不需要 public 权限)
    if ($action === 'history' && Database::isAvailable()) {
        header('Content-Type: application/json');

        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $pageSize = isset($_POST['pageSize']) ? min(100, max(1, (int)$_POST['pageSize'])) : 20;
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';

        $filters = [];
        if (!empty($search)) {
            $filters['search'] = $search;
        }

        $result = db_get_records($page, $pageSize, 'id DESC', $filters);

        echo json_encode([
            'code' => 200,
            'msg' => 'success',
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pageSize' => $result['pageSize'],
            'totalPages' => $result['totalPages']
        ]);
        exit;
    }

    // stats 端点 - 获取统计数据
    if ($action === 'stats' && Database::isAvailable()) {
        header('Content-Type: application/json');

        $stats = db_get_stats();
        $sourceStats = db_get_source_stats();

        echo json_encode([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'stats' => $stats,
                'source' => $sourceStats
            ]
        ]);
        exit;
    }

    http_response_code(400);
    die('未知action或数据库未启用');
}

// 获取并验证GET参数
$show = isset($_GET['show']) ? trim($_GET['show']) : '';
if (!$show || !in_array($show, $config['public_list'])) {
    http_response_code(400); // 返回400 Bad Request
    die('没有权限或参数错误!');
}

try {
    // 根据请求返回值
    switch ($show) {
        // 统计时间
        case 'time':
            echo read_total_json(TIME_KEY);
            break;

        // 今日上传
        case 'today':
            // 优先使用数据库
            if (Database::isAvailable()) {
                $stats = db_get_stats();
                echo $stats['today_count'];
            } else {
                echo read_total_json(TODAY_UPLOAD_KEY);
            }
            break;

        // 昨日上传
        case 'yesterday':
            echo read_total_json(YESTERDAY_UPLOAD_KEY);
            break;

        // 总空间
        case 'total_space':
            echo getDistUsed(disk_total_space('.'));
            break;

        // 已用空间
        case 'used_space':
            $totalSpace = disk_total_space('.');
            if ($totalSpace !== false && is_numeric($totalSpace)) {
                $freeSpace = disk_free_space('.');
                if ($freeSpace !== false && is_numeric($freeSpace)) {
                    echo getDistUsed($totalSpace - $freeSpace);
                } else {
                    throw new Exception('无法获取磁盘剩余空间');
                }
            } else {
                throw new Exception('无法获取磁盘总空间');
            }
            break;

        // 剩余空间
        case 'free_space':
            $freeSpace = disk_free_space('/');
            if ($freeSpace !== false && is_numeric($freeSpace)) {
                echo getDistUsed($freeSpace);
            } else {
                throw new Exception('无法获取磁盘剩余空间');
            }
            break;

        // 图床使用空间
        case 'image_used':
            // 优先使用数据库
            if (Database::isAvailable()) {
                $stats = db_get_stats();
                echo getDistUsed($stats['total_size']);
            } else {
                echo read_total_json(USAGE_SPACE_KEY);
            }
            break;

        // 文件数量
        case 'file':
            // 优先使用数据库
            if (Database::isAvailable()) {
                $stats = db_get_stats();
                echo $stats['total_count'];
            } else {
                echo read_total_json(FILENUM_KEY);
            }
            break;

        // 文件夹数量
        case 'dir':
            echo read_total_json(DIRNUM_KEY);
            break;

        // 修复”month”分支的逻辑
        case 'month':
            $chartTotal = read_chart_total();
            if (isset($chartTotal['number']) && is_array($chartTotal['number'])) {
                foreach ($chartTotal['number'] as $value) {
                    echo $value;
                }
            } else {
                throw new Exception('无法获取图表总数中的”number”数据');
            }
            break;

        default:
            echo read_chart_total();
            break;
    }
} catch (Exception $e) {
    http_response_code(500); // 返回500 Internal Server Error
    die(“发生错误: “ . $e->getMessage());
}