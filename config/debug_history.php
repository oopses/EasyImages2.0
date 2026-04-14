<?php
/**
 * 调试 history API
 */
require_once __DIR__ . '/../app/function.php';

echo "<h2>调试 History API</h2>";

if (!Database::isAvailable()) {
    die("<p>数据库不可用</p>");
}

echo "<h3>1. 直接测试 db_get_records</h3>";
$result = db_get_records(1, 100, 'id DESC', []);
echo "<p>total: " . $result['total'] . "</p>";
echo "<p>data count: " . count($result['data']) . "</p>";
if (count($result['data']) > 0) {
    echo "<pre>";
    var_dump($result['data'][0]);
    echo "</pre>";
}

echo "<h3>2. 直接 SQL 查询所有记录</h3>";
$db = Database::getInstance();
$prefix = $db->getPrefix();
$all = $db->getAll("SELECT id, filename, path, url, original_name, created_at FROM {$prefix}records WHERE is_deleted = 0 ORDER BY id DESC LIMIT 10");
echo "<p>记录数: " . count($all) . "</p>";
foreach ($all as $r) {
    echo "<li>ID: {$r['id']}, filename: {$r['filename']}, path: {$r['path']}, created_at: {$r['created_at']}</li>";
}

echo "<h3>3. 模拟 API 返回格式</h3>";
$_POST['action'] = 'history';
$_POST['page'] = 1;
$_POST['pageSize'] = 100;

// 调用 db_get_records
$page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$pageSize = isset($_POST['pageSize']) ? min(100, max(1, (int)$_POST['pageSize'])) : 20;
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

$filters = [];
if (!empty($search)) {
    $filters['search'] = $search;
}

$result = db_get_records($page, $pageSize, 'id DESC', $filters);

$response = [
    'code' => 200,
    'msg' => 'success',
    'data' => $result['data'],
    'total' => $result['total'],
    'page' => $result['page'],
    'pageSize' => $result['pageSize'],
    'totalPages' => $result['totalPages']
];

echo "<p>API 响应:</p>";
echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";