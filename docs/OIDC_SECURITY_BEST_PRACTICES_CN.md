# EasyImages OIDC 安全最佳实践

## 🔒 核心安全原则

### 1. 敏感信息管理

#### ❌ 不要做的
```php
// ❌ 绝不要在代码中硬编码密钥
'client' => array(
    'secret' => 'sk-12345abcde',  // ❌ 泄露风险
),
```

#### ✅ 应该做的
```php
// ✅ 方案 1：使用环境变量
'client' => array(
    'secret' => getenv('OIDC_CLIENT_SECRET'),
),

// ✅ 方案 2：使用 .env 文件
// 在 .env.local（不提交）中设置
// 在 .gitignore 中排除

// ✅ 方案 3：使用秘密管理系统
// Vault, AWS Secrets Manager, 等
```

### 2. 传输安全

#### HTTPS 必需
```php
// ✅ 生产环境必须使用 HTTPS
'redirect_uri' => 'https://your-domain.com/admin/oidc-callback.php',

// ❌ HTTP 仅用于开发
'redirect_uri' => 'http://localhost:8080/admin/oidc-callback.php',
```

#### SSL 验证
```php
// ✅ 生产环境必须验证 SSL
'verify_ssl' => true,

// ❌ 开发时可以禁用（但生产环境绝不能）
'verify_ssl' => false,
```

### 3. 文件权限

```bash
# ✅ 限制配置文件权限
chmod 600 /config/oidc.php

# ✅ 限制目录权限
chmod 750 /config/

# ✅ 验证文件所有者
ls -la /config/oidc.php
# 输出示例: -rw------- 1 www-data www-data 2048 Apr 06 10:00 oidc.php
```

### 4. 版本控制安全

```bash
# ✅ .gitignore 中应包含
/config/oidc.php
/config/.env.local
/config/.env*.local
/.env.local
/.env*.local

# ✅ 验证敏感文件不会被提交
git status --porcelain | grep -E "(oidc\.php|\.env)"

# ✅ 移除已提交的敏感信息（如果意外提交）
git rm --cached /config/oidc.php
git commit --amend "Remove sensitive OIDC config"
```

## 🔐 认证流程安全

### CSRF 防护

```php
// ✅ 使用 State 参数验证
$state = bin2hex(random_bytes(16));  // 强随机性
$_SESSION['oidc_state'] = $state;

// ✅ 验证 State 参数
if ($_SESSION['oidc_state'] !== $state) {
    throw new Exception('CSRF attack detected');
}
```

### Token 安全

```php
// ✅ Authorization Code 流程（推荐）
// - 授权码在公开通道传输（安全，因为只能用一次）
// - Token 在建立的 TLS 连接中获取（安全）

// ❌ Implicit 流程（不安全）
// - Token 直接在 URL 中返回（可能被拦截）
// - 应避免使用
```

### Token 存储

```php
// ✅ Token 仅在服务端使用
// - 不存储在 Local Storage（XSS 风险）
// - 不存储在 Session Storage（同上）
// - 使用后立即丢弃

// ✅ 会话 Cookie 使用安全标志
setcookie('auth', $browser_cookie, [
    'expires' => time() + 3600 * 24 * 14,
    'path' => '/',
    'secure' => true,      // HTTPS only
    'httponly' => true,    // JavaScript 无法访问
    'samesite' => 'Strict' // CSRF 防护
]);
```

## 🛡️ 用户授权管理

### 权限检查

```php
// ✅ 实施严格的权限控制
if (!is_who_login('admin')) {
    exit('Permission denied');
}

// ✅ 使用白名单
if (!empty($oidcConfig['user_whitelist']) && 
    !in_array($username, $oidcConfig['user_whitelist'])) {
    throw new Exception('User not authorized');
}
```

### 最小权限原则

```php
// ✅ 新用户默认权限为上传者
'default_level' => 2,

// ❌ 绝不要默认给管理员权限
'default_level' => 1,
```

### 自动创建用户的风险

```php
// ⚠️ 谨慎使用自动创建用户
'auto_create_user' => false,  // ✅ 推荐：手动创建

// ❌ 自动创建存在风险
'auto_create_user' => true,   // 任何有效的 OIDC 用户都能登录
```

## 📋 审计和监控

### 日志记录

```php
// ✅ 记录所有登录尝试
@write_login_log($username, 'OIDC', 'Login attempt');

// ✅ 记录失败原因（便于调查）
@write_login_log($username, 'OIDC', 'Login failed: ' . $error);

// ❌ 绝不要记录密码或 Token
error_log($accessToken);  // ❌ 泄露敏感信息
```

### 日志分析

```bash
# 查看登录日志
tail -f /app/logs/login.log | grep OIDC

# 检测异常活动
grep "failed" /app/logs/login.log | tail -20

# 统计登录次数
grep "OIDC" /app/logs/login.log | wc -l
```

### 告警规则

设置以下情况的告警：

- 短时间内多次失败登录
- 来自异常 IP 地址的登录
- 权限级别异常改变
- 配置文件被修改

## 🔄 定期安全检查清单

### 每周
- [ ] 检查登录日志中的异常活动
- [ ] 验证 OIDC 提供商状态
- [ ] 检查 SSL 证书有效期

### 每月
- [ ] 审查用户白名单
- [ ] 检查配置文件权限
- [ ] 更新依赖库

### 每季度
- [ ] 进行安全审计
- [ ] 更新 OIDC 库
- [ ] 审查权限分配

## 🚀 生产部署清单

```bash
# ✅ 前置检查
[ ] HTTPS 已启用且证书有效
[ ] OIDC 配置文件权限正确 (600)
[ ] 敏感文件在 .gitignore 中
[ ] 环境变量已正确设置
[ ] SSL 验证已启用

# ✅ 功能检查
[ ] OIDC 登录按钮显示正确
[ ] 重定向 URI 已在 OIDC 提供商注册
[ ] State 参数验证工作正常
[ ] 用户权限分配正确

# ✅ 安全检查
[ ] 未在代码中发现硬编码密钥
[ ] 日志记录正常工作
[ ] 错误消息不泄露敏感信息
[ ] CSRF 保护已启用

# ✅ 监控检查
[ ] 日志收集已配置
[ ] 告警规则已设置
[ ] 备份策略已制定
```

## 📚 参考资源

- [OWASP: OpenID Connect Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/OpenID_Connect_Cheat_Sheet.html)
- [OAuth 2.0 Security Best Current Practice](https://datatracker.ietf.org/doc/html/draft-ietf-oauth-security-topics)
- [NextTerminal OIDC 文档](https://docs.next-terminal.typesafe.cn/zh/usage/oidc_server.html)

## ⚠️ 安全事件处理

如果发生以下情况，立即采取行动：

1. **怀疑 Client Secret 泄露**
   ```bash
   # 立即在 OIDC 提供商重置 Secret
   # 更新配置文件
   # 检查日志中的异常活动
   ```

2. **发现配置文件被篡改**
   ```bash
   # 恢复备份
   # 检查访问日志
   # 更新所有凭证
   ```

3. **检测到大量登录失败**
   ```bash
   # 检查日志中的 IP 地址
   # 配置 WAF 规则阻止来源
   # 通知管理员
   ```

---

**记住：安全是持续的过程，不是一次性的任务。定期审查和更新你的安全措施。**
