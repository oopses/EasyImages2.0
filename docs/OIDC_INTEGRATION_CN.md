# EasyImages 2.0 OIDC 集成指南

## 概述

本指南说明如何安全地集成 OIDC (OpenID Connect) 认证到 EasyImages 2.0，以支持通过第三方 OIDC 提供商进行登录。

## 安全设计原则

### 1. 敏感信息隔离
- ✅ **OIDC 配置文件单独存储** (`/config/oidc.php`)
- ✅ **Client Secret 绝不暴露给前端**
- ✅ **配置文件已在 `.gitignore` 中，不会提交**
- ✅ **所有 OIDC 状态参数使用 `session` 存储**

### 2. Token 处理
- ✅ **Authorization Code 流程**（服务端安全）
- ✅ **State 参数验证**（防 CSRF）
- ✅ **ID Token 验证**（可选但推荐）
- ✅ **Token 不存储在本地**

### 3. 用户权限管理
- ✅ **用户白名单验证**
- ✅ **可配置的默认权限级别**
- ✅ **可选的自动创建用户**

## 安装步骤

### 1. 复制配置文件

```bash
cp /config/oidc.php.example /config/oidc.php
```

### 2. 配置 OIDC 参数

编辑 `/config/oidc.php`：

```php
return array(
    'enabled' => true,  // 启用 OIDC
    
    'provider' => array(
        'name' => 'My OIDC Server',
        'authorization_endpoint' => 'https://your-oidc-server.com/oauth/authorize',
        'token_endpoint' => 'https://your-oidc-server.com/oauth/token',
        'userinfo_endpoint' => 'https://your-oidc-server.com/oauth/userinfo',
        'jwks_uri' => 'https://your-oidc-server.com/oauth/discovery/keys',
    ),
    
    'client' => array(
        'id' => 'your_client_id',
        'secret' => 'your_client_secret_keep_this_safe',
        'redirect_uri' => 'https://your-domain.com/admin/oidc-callback.php',
    ),
    
    // 其他选项...
);
```

### 3. 注册重定向 URI

在你的 OIDC 服务中注册重定向 URI：
```
https://your-domain.com/admin/oidc-callback.php
```

必须与配置中的 `redirect_uri` 完全匹配。

## 配置详解

### 必需参数

| 参数 | 说明 | 例子 |
|------|------|------|
| `enabled` | 是否启用 OIDC | `true` |
| `provider.authorization_endpoint` | 授权端点 | `https://...oauth/authorize` |
| `provider.token_endpoint` | Token 端点 | `https://...oauth/token` |
| `provider.userinfo_endpoint` | 用户信息端点 | `https://...oauth/userinfo` |
| `client.id` | 应用 ID | `abc123xyz` |
| `client.secret` | 应用密钥 | `keep_secret` |
| `client.redirect_uri` | 回调 URI | `https://your-domain/admin/oidc-callback.php` |

### 可选参数

| 参数 | 说明 | 默认值 |
|------|------|--------|
| `scopes` | 请求的权限范围 | `['openid', 'profile', 'email']` |
| `user_mapping` | 字段映射 | `['preferred_username' => 'user']` |
| `default_level` | 新用户权限级别 | `2` (上传者) |
| `auto_create_user` | 自动创建用户 | `false` |
| `user_whitelist` | 用户白名单 | `[]` (允许所有) |

## 权限级别

- `1` - 管理员 (全部权限)
- `2` - 上传者 (仅上传权限)

## 安全建议

### 生产环境部署

1. **HTTPS 必须**
   ```bash
   # 在生产环境中，redirect_uri 必须使用 HTTPS
   'redirect_uri' => 'https://your-domain.com/admin/oidc-callback.php',
   ```

2. **文件权限**
   ```bash
   # 限制配置文件权限
   chmod 600 /config/oidc.php
   
   # 限制目录权限
   chmod 750 /config/
   ```

3. **隐藏配置文件**
   ```bash
   # 确保 Web 服务器不会直接暴露 PHP 配置文件
   # 在 Nginx/Apache 中配置禁止访问
   ```

4. **环境变量（推荐）**
   
   对于高安全性应用，可以使用环境变量替代硬编码的密钥：
   
   ```php
   // /config/oidc.php
   return array(
       'client' => array(
           'id' => getenv('OIDC_CLIENT_ID'),
           'secret' => getenv('OIDC_CLIENT_SECRET'),
       ),
   );
   ```
   
   然后设置环境变量：
   ```bash
   export OIDC_CLIENT_ID="your_client_id"
   export OIDC_CLIENT_SECRET="your_client_secret"
   ```

### 白名单保护

启用白名单以限制允许的用户：

```php
'user_whitelist' => array(
    'user1@example.com',
    'user2@example.com',
),
```

### 监控和日志

登录尝试被记录在 `/app/logs/login.log` 中，检查异常活动：

```bash
tail -f /app/logs/login.log | grep OIDC
```

## 故障排除

### 问题 1: "OIDC is not enabled"

**原因**: 配置文件不存在或 `enabled` 不为 `true`

**解决**:
```bash
cp /config/oidc.php.example /config/oidc.php
# 编辑文件，设置 'enabled' => true
```

### 问题 2: "Invalid state parameter"

**原因**: Session 已过期或被篡改

**解决**:
- 清除浏览器 Cookie
- 确保 PHP Session 配置正确
- 检查服务器时间同步

### 问题 3: "Cannot get username from OIDC user info"

**原因**: 用户字段映射错误

**解决**:
```php
// 调试：打印 OIDC 返回的用户信息
// 在 /app/OIDCHandler.php 中临时添加日志
error_log(json_encode($userInfo));

// 然后在 oidc.php 中更正映射
'user_mapping' => array(
    'sub' => 'user',  // 或 'email' 等
),
```

### 问题 4: SSL 证书错误

**原因**: CURL SSL 验证失败

**解决** (仅在开发环境):
```php
// 在 /app/OIDCHandler.php 中修改
// 生产环境不应禁用此项！
CURLOPT_SSL_VERIFYPEER => false,
```

## OIDC 流程图

```
用户  
  ├─→ 点击 "OIDC 登录"
  │   ↓
  ├─→ 重定向到 OIDC 授权端点
  │   ↓
  ├─→ 用户在 OIDC 提供商认证
  │   ↓
  ├─→ OIDC 提供商重定向到 oidc-callback.php
  │   (带 authorization code)
  │   ↓
  ├─→ 本地处理器与 OIDC 服务交换 token
  │   ↓
  ├─→ 验证用户信息和权限
  │   ↓
  └─→ 设置本地会话 Cookie
  │   ↓
  └─→ 用户已登录
```

## 常见的 OIDC 提供商配置示例

### Keycloak
```php
'provider' => array(
    'name' => 'Keycloak',
    'authorization_endpoint' => 'https://keycloak.example.com/realms/master/protocol/openid-connect/auth',
    'token_endpoint' => 'https://keycloak.example.com/realms/master/protocol/openid-connect/token',
    'userinfo_endpoint' => 'https://keycloak.example.com/realms/master/protocol/openid-connect/userinfo',
    'jwks_uri' => 'https://keycloak.example.com/realms/master/protocol/openid-connect/certs',
)
```

### OAuth2 通用提供商
```php
'provider' => array(
    'name' => 'Custom OAuth2',
    'authorization_endpoint' => 'https://provider.example.com/authorize',
    'token_endpoint' => 'https://provider.example.com/token',
    'userinfo_endpoint' => 'https://provider.example.com/userinfo',
)
```

## 卸载 OIDC

要禁用 OIDC 登录：

```php
// /config/oidc.php
'enabled' => false,  // 改为 false
```

或删除配置文件：
```bash
rm /config/oidc.php
```

## 许可证

本 OIDC 模块遵循 EasyImages 主项目的许可证。
