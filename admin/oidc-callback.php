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
    $usernameField = $mapping['preferred_username'];
    $username = $userInfo[$usernameField] ?? null;
    
    // 如果主字段不存在，尝试其他可能的字段
    if (empty($username)) {
        // 尝试常见的备选字段名
        $possibleFields = array('sub', 'user_id', 'login', 'name', 'email', 'username', 'preferred_username');
        foreach ($possibleFields as $field) {
            if (!empty($userInfo[$field])) {
                $username = $userInfo[$field];
                break;
            }
        }
    }
    
    if (empty($username)) {
        // 列出所有可用字段供调试
        $availableFields = implode(', ', array_keys($userInfo));
        throw new Exception('Cannot get username from OIDC user info. Available fields: ' . $availableFields);
    }
    
    // 检查用户白名单
    if (!empty($oidcConfig['user_whitelist']) && !in_array($username, $oidcConfig['user_whitelist'])) {
        throw new Exception('User not in whitelist');
    }
    
    // 检查或创建用户
    global $config, $guestConfig;
    
    // 尝试多种方式匹配用户
    $matchedUser = null;
    
    // 方式1：直接用户名匹配
    if ($username === $config['user']) {
        $matchedUser = $config['user'];
    } else if (array_key_exists($username, $guestConfig)) {
        $matchedUser = $username;
    }
    
    // 方式2：如果主字段匹配失败，尝试按邮箱匹配
    if (empty($matchedUser) && !empty($userInfo['email'])) {
        $email = $userInfo['email'];
        
        // 检查来宾用户邮箱
        foreach ($guestConfig as $guestUsername => $guestInfo) {
            if (isset($guestInfo['email']) && $guestInfo['email'] === $email) {
                $matchedUser = $guestUsername;
                break;
            }
        }
    }
    
    if (empty($matchedUser)) {
        $debugInfo = "OIDC user: $username, Email: " . ($userInfo['email'] ?? 'N/A');
        throw new Exception("User not found: $debugInfo");
    }
    
    // 使用匹配的用户进行登录
    if ($matchedUser === $config['user']) {
        // 管理员登录
        $loginResult = json_decode(_login($config['user'], $config['password']), true);
    } else {
        // 来宾用户登录
        $guestPassword = $guestConfig[$matchedUser]['password'] ?? null;
        if (empty($guestPassword)) {
            throw new Exception("Guest user exists but no password configured");
        }
        $loginResult = json_decode(_login($matchedUser, $guestPassword), true);
    }
    
    if ($loginResult['code'] !== 200) {
        throw new Exception('Login failed: ' . $loginResult['messege']);
    }
    
    // 设置 cookie - 使用匹配到的本地用户名
    $browser_cookie = json_encode(array($matchedUser, bin2hex(random_bytes(16))));
    setcookie('auth', $browser_cookie, time() + 3600 * 24 * 14, '/');
    
    // 记录登录 - 使用匹配到的本地用户名
    @write_login_log($matchedUser, 'OIDC', 'OIDC login success');
    
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
