<?php
/**
 * OIDC 回调处理页面
 * 路由: /admin/oidc-callback.php
 * 
 * OIDC 提供商将用户重定向回此页面
 */

session_start();

require_once __DIR__ . '/../app/function.php';
require_once __DIR__ . '/../app/OIDCHandler.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 初始化 OIDC 处理器
    $oidc = new OIDCHandler();
    
    if (!$oidc->isEnabled()) {
        throw new Exception('OIDC is not enabled');
    }
    
    // 检查回调参数
    if (empty($_GET['code']) || empty($_GET['state'])) {
        throw new Exception('Missing required parameters');
    }
    
    $code = htmlspecialchars($_GET['code'], ENT_QUOTES, 'UTF-8');
    $state = htmlspecialchars($_GET['state'], ENT_QUOTES, 'UTF-8');
    $error = !empty($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : null;
    
    // 检查错误
    if (!empty($error)) {
        throw new Exception('OIDC AuthorizationError: ' . $error);
    }
    
    // 处理回调
    $userInfo = $oidc->handleCallback($code, $state);
    $oidcConfig = $oidc->getConfig();
    
    // 映射用户信息
    $mapping = $oidcConfig['user_mapping'];
    $username = $userInfo[$mapping['preferred_username']] ?? null;
    
    if (empty($username)) {
        throw new Exception('Cannot get username from OIDC user info');
    }
    
    // 检查用户白名单
    if (!empty($oidcConfig['user_whitelist']) && !in_array($username, $oidcConfig['user_whitelist'])) {
        throw new Exception('User not in whitelist');
    }
    
    // 检查或创建用户
    global $config, $guestConfig;
    
    if ($username === $config['user']) {
        // 管理员登录 - 使用默认级别
        $loginResult = json_decode(_login($config['user'], $config['password']), true);
    } else if (array_key_exists($username, $guestConfig)) {
        // 已存在的上传者
        $loginResult = json_decode(_login($username, $guestConfig[$username]['password']), true);
    } else if ($oidcConfig['auto_create_user']) {
        // 自动创建用户（仅适用于上传者权限）
        // 这里生成一个临时密码，实际使用中应该跳过本地密码验证
        $tempPassword = bin2hex(random_bytes(16));
        $loginResult = array(
            'code' => 200,
            'level' => $oidcConfig['default_level'],
            'messege' => 'OIDC login success'
        );
    } else {
        throw new Exception('User not found and auto_create_user is disabled');
    }
    
    if ($loginResult['code'] !== 200) {
        throw new Exception('Login failed: ' . $loginResult['messege']);
    }
    
    // 设置 cookie
    $browser_cookie = json_encode(array($username, bin2hex(random_bytes(16))));
    setcookie('auth', $browser_cookie, time() + 3600 * 24 * 14, '/');
    
    // 记录登录
    @write_login_log($username, 'OIDC', 'OIDC login success');
    
    // 重定向回首页
    header('Location: ' . $config['domain']);
    exit();
    
} catch (Exception $e) {
    // 安全记录错误（不输出敏感信息）
    @write_login_log('OIDC', 'error', $e->getMessage());
    
    // 返回友好的错误信息
    exit(json_encode(array(
        'code' => 400,
        'message' => 'OIDC authentication failed',
        'detail' => $e->getMessage()
    ), JSON_UNESCAPED_UNICODE));
}
