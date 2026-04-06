<?php

/**
 * php抓取bing每日图片（缩略图）并保存到服务器
 * 作者：mengkun (mkblog.cn)
 * 日期：2016/12/23
 * 修改：Icret
 * 修改日期：2023-03-09
 * 优化：改为拉取缩略图以加快加载速度 2026-04-06
 */
include_once __DIR__ . '/function.php';
include_once APP_ROOT . '/config/config.php';

$path = APP_ROOT . $config['path'] . $config['delDir']; // 设置图片缓存文件夹
$filename = date("Ymd") . '.jpg';          // 用年月日来命名新的文件名

if (file_exists($path . $filename)) {
    // 如果缓存文件存在，直接返回
    header("Content-type: image/jpeg");
    header("Cache-Control: public, max-age=" . ($config['bing_cache_hours'] * 3600));  // 使用配置的缓存时间
    exit(file_get_contents($path . $filename, true));
} else {
    // 创建缓存目录
    if (!file_exists($path)) {
        @mkdir($path, 0777, true);
    }

    try {
        // 获取 Bing API 数据
        $context = stream_context_create(array('http' => array('timeout' => $config['bing_fetch_timeout'])));
        $str = @file_get_contents('http://cn.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1', false, $context);
        
        if ($str === false) {
            throw new Exception('Failed to fetch Bing API');
        }

        $data = json_decode($str, true);
        
        if (empty($data['images'][0])) {
            throw new Exception('Invalid Bing API response');
        }

        $imageData = $data['images'][0];
        
        // ✅ 优化：使用缩略图 URL
        $thumbnailUrl = 'http://cn.bing.com' . $imageData['url'];
        
        // 如果启用了缩略图模式，添加尺寸参数
        if ($config['bing_thumbnail_enable']) {
            $thumbnailUrl .= '&h=' . $config['bing_thumbnail_height'] . '&w=' . $config['bing_thumbnail_width'];
        }
        
        // 改进的图片拉取函数（支持超时、重试）
        $img = grabImageOptimized($thumbnailUrl, $path . $filename, $config['bing_fetch_timeout']);
        
        if (!$img) {
            throw new Exception('Failed to grab image');
        }

    } catch (Exception $e) {
        // 错误处理：返回一个占位符或简单的 1x1 图片
        error_log('Bing image fetch failed: ' . $e->getMessage());
        
        // 返回一个简单的成功响应，避免加载失败
        if (!file_exists($path . $filename)) {
            createPlaceholder($path . $filename);
        }
    }
}

header("Content-type: image/jpeg");
header("Cache-Control: public, max-age=" . ($config['bing_cache_hours'] * 3600));
exit(file_get_contents($path . $filename, true));

/**
 * 优化的远程抓取图片并保存（支持超时和错误处理）
 * @param $url 图片url
 * @param $filename 保存名称和路径
 * @param $timeout 超时时间（秒）
 * @return bool
 */
function grabImageOptimized($url, $filename = "", $timeout = 10) {
    if (empty($url)) {
        return false;
    }

    // cURL 方式（更可靠）
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => ceil($timeout / 2),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ));

        $img = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$img) {
            return false;
        }
    } else {
        // 降级方案：使用 file_get_contents
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => $timeout,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        ));
        
        $img = @file_get_contents($url, false, $context);
        
        if (!$img) {
            return false;
        }
    }

    // 验证图片数据
    if (strlen($img) < 1000) {  // 图片太小，可能是错误页面
        return false;
    }

    // 保存图片
    $fp = @fopen($filename, "w+");
    if (!$fp) {
        return false;
    }

    $bytes = fwrite($fp, $img);
    fclose($fp);

    return $bytes > 0;
}

/**
 * 创建占位符图片（1x1 透明 PNG）
 * 当 Bing 图片拉取失败时使用
 */
function createPlaceholder($filename) {
    $placeholder = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
    @file_put_contents($filename, $placeholder);
}
