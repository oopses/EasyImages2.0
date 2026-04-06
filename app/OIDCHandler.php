<?php
/**
 * OIDC 认证处理逻辑
 * @author EasyImages OIDC Module
 */

require_once __DIR__ . '/../app/function.php';

class OIDCHandler {
    private $config;
    private $oidcConfig;
    
    public function __construct() {
        global $config;
        $this->config = $config;
        
        // 加载 OIDC 配置
        if (file_exists(APP_ROOT . '/config/oidc.php')) {
            $this->oidcConfig = require APP_ROOT . '/config/oidc.php';
        } else {
            return false;
        }
        
        if (empty($this->oidcConfig['enabled'])) {
            return false;
        }
    }
    
    /**
     * 生成授权 URL
     */
    public function getAuthorizationUrl() {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oidc_state'] = $state;
        
        $params = array(
            'client_id' => $this->oidcConfig['client']['id'],
            'redirect_uri' => $this->oidcConfig['client']['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $this->oidcConfig['scopes']),
            'state' => $state,
        );
        
        return $this->oidcConfig['provider']['authorization_endpoint'] . '?' . http_build_query($params);
    }
    
    /**
     * 处理回调
     */
    public function handleCallback($code, $state) {
        // 验证 state
        if (!isset($_SESSION['oidc_state']) || $_SESSION['oidc_state'] !== $state) {
            throw new Exception('Invalid state parameter');
        }
        
        unset($_SESSION['oidc_state']);
        
        // 交换授权码获取 token
        $token = $this->exchangeCodeForToken($code);
        if (!$token) {
            throw new Exception('Failed to exchange authorization code');
        }
        
        // 获取用户信息
        $userInfo = $this->getUserInfo($token['access_token']);
        if (!$userInfo) {
            throw new Exception('Failed to get user info');
        }
        
        // 验证 ID Token（可选但推荐）
        if (!empty($token['id_token'])) {
            $this->verifyIdToken($token['id_token']);
        }
        
        return $userInfo;
    }
    
    /**
     * 交换授权码获取 token
     */
    private function exchangeCodeForToken($code) {
        $data = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->oidcConfig['client']['redirect_uri'],
            'client_id' => $this->oidcConfig['client']['id'],
            'client_secret' => $this->oidcConfig['client']['secret'],
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->oidcConfig['provider']['token_endpoint'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            CURLOPT_TIMEOUT => 30,
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * 获取用户信息
     */
    private function getUserInfo($accessToken) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->oidcConfig['provider']['userinfo_endpoint'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
            ),
            CURLOPT_TIMEOUT => 30,
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * 验证 ID Token
     */
    private function verifyIdToken($idToken) {
        // 这是一个简化版本，生产环境应使用专业的 JWT 库
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new Exception('Invalid ID Token format');
        }
        
        // 解码 header 和 payload
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        
        // 检查 exp
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('ID Token expired');
        }
        
        // 在生产环境中，应该从 jwks_uri 获取公钥验证签名
        // 这里仅做基本验证
        
        return true;
    }
    
    /**
     * 获取 OIDC 配置
     */
    public function getConfig() {
        return $this->oidcConfig;
    }
    
    /**
     * 检查是否启用 OIDC
     */
    public function isEnabled() {
        return !empty($this->oidcConfig['enabled']);
    }
}
