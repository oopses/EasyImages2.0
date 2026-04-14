<?php
/**
 * 调试 SQL 查询问题
 */
require_once __DIR__ . '/../app/function.php';

echo "<h2>SQL 查询调试</h2>";

if (!Database::isAvailable()) {
    die("<p>数据库不可用</p>");
}

$db = Database::getInstance();
$prefix = $db->getPrefix();

echo "<h3>1. 直接 COUNT 查询 April 15</h3>";
$pathPattern = '/i/2026/04/15/%';
$sql1 = "SELECT COUNT(*) as cnt FROM {$prefix}records WHERE is_deleted = 0 AND path LIKE :p";
$stmt1 = $db->getOne($sql1, ['p' => $pathPattern]);
echo "<p>SQL: {$sql1}</p>";
echo "<p>Pattern: {$pathPattern}</p>";
echo "<p>Result: {$stmt1}</p>";

echo "<h3>2. 直接 SELECT 查询 April 15</h3>";
$sql2 = "SELECT id, filename, path FROM {$prefix}records WHERE is_deleted = 0 AND path LIKE :p ORDER BY id DESC LIMIT 0, 20";
$stmt2 = $db->getAll($sql2, ['p' => $pathPattern]);
echo "<p>SQL: {$sql2}</p>";
echo "<p>Result count: " . count($stmt2) . "</p>";
echo "<pre>";
var_dump($stmt2);
echo "</pre>";

echo "<h3>3. 直接拼接 LIMIT 查询 April 15</h3>";
$sql3 = "SELECT id, filename, path FROM {$prefix}records WHERE is_deleted = 0 AND path LIKE '{$pathPattern}' ORDER BY id DESC LIMIT 0, 20";
$stmt3 = $db->getAll($sql3);
echo "<p>SQL: {$sql3}</p>";
echo "<p>Result count: " . count($stmt3) . "</p>";
echo "<pre>";
var_dump($stmt3);
echo "</pre>";

echo "<h3>4. 测试函数 db_get_list_count_by_date</h3>";
$count = db_get_list_count_by_date('2026/04/15/');
echo "<p>Result: {$count}</p>";

echo "<h3>5. 测试函数 db_get_list_by_date</h3>";
$list = db_get_list_by_date('2026/04/15/', 20, 0, '*.*');
echo "<p>Result count: " . count($list) . "</p>";
echo "<pre>";
var_dump($list);
echo "</pre>";

echo "<h3>6. 最近有文件的日期</h3>";
$sql = "SELECT SUBSTRING(MAX(path), 4, 10) as latest_date FROM {$prefix}records WHERE is_deleted = 0 AND path LIKE :p";
$latest = $db->getOne($sql, ['p' => '/i/%']);
echo "<p>Latest date: {$latest}</p>";

echo "<h3>7. April 14 查询对比</h3>";
$sql4 = "SELECT COUNT(*) as cnt FROM {$prefix}records WHERE is_deleted = 0 AND path LIKE '/i/2026/04/14/%'";
$cnt14 = $db->getOne($sql4);
echo "<p>April 14 count: {$cnt14}</p>";