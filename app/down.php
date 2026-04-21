<?php

/**
 * 下载文件
 * https://www.php.cn/php-weizijiaocheng-394566.html
 */
//获取要下载的文件名
require_once __DIR__ . '/function.php';

// 空GET
if (empty($_GET)) {
    exit('No file path');
}

$allowed_dir = realpath(APP_ROOT . $config['path']) ?: (APP_ROOT . $config['path']);
$dw = null;

// 获取下载路径
if (isset($_GET['dw'])) {
    $input_path = strip_tags($_GET['dw']);
    // 禁止路径穿越字符
    $input_path = str_replace(['../', '..\\', './', '.\\'], '', $input_path);
    $full_path = realpath(APP_ROOT . $config['path'] . $input_path);
    // 验证路径必须在允许目录内
    if ($full_path && strpos($full_path, $allowed_dir) === 0) {
        $dw = $full_path;
    }
}

// 历史上传记录的路径
if (isset($_GET['history']) && $dw === null) {
    $input_path = strip_tags($_GET['history']);
    // 禁止路径穿越字符
    $input_path = str_replace(['../', '..\\', './', '.\\'], '', $input_path);
    if ($config['hide_path']) {
        $full_path = realpath(APP_ROOT . $config['path'] . $input_path);
    } else {
        $full_path = realpath(APP_ROOT . $input_path);
    }
    // 验证路径必须在允许目录内
    if ($full_path && strpos($full_path, $allowed_dir) === 0) {
        $dw = $full_path;
    }
}

// 检查文件是否存在
if (!$dw || !is_file($dw)) {
    exit('No File');
}

// 过滤下载非指定上传文件格式
$dw_extension = pathinfo($dw, PATHINFO_EXTENSION);
$filter_extensions = explode(',', $config['extensions']);

// 过滤下载其他格式
$filter_other = array('php', 'json', 'log', 'lock');

// 先过滤后下载
if (in_array($dw_extension, $filter_extensions) && !in_array($dw_extension, $filter_other)) {
    //设置头信息
    header('Content-Disposition:attachment;filename=' . basename($dw));
    header('Content-Length:' . filesize($dw));
    //读取文件并写入到输出缓冲
    readfile($dw);
    exit;
} else {
    exit('Downfile Type Error');
}
