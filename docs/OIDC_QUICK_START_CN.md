# OIDC 快速开始

## 5分钟快速配置

### 1️⃣ 复制示例配置

```bash
cp config/oidc.php.example config/oidc.php
```

### 2️⃣ 编辑配置文件

```bash
nano config/oidc.php
```

修改以下关键字段：

```php
return array(
    'enabled' => true,  // ← 改为 true
    
    'provider' => array(
        'name' => 'My OIDC',  // ← 你的 OIDC 名称
        'authorization_endpoint' => 'https://your-server.com/oauth/authorize',  // ← 你的授权链接
        'token_endpoint' => 'https://your-server.com/oauth/token',  // ← 你的 Token 链接
        'userinfo_endpoint' => 'https://your-server.com/oauth/userinfo',  // ← 用户信息链接
    ),
    
    'client' => array(
        'id' => 'your_client_id_here',  // ← 从 OIDC 提供商获取
        'secret' => 'your_client_secret_here',  // ← 从 OIDC 提供商获取
        'redirect_uri' => 'https://your-domain.com/admin/oidc-callback.php',  // ← 改为你的域名
    ),
);
```

### 3️⃣ 在 OIDC 服务中注册

在你的 OIDC 提供商后台，新增应用并添加重定向 URI：
```
https://your-domain.com/admin/oidc-callback.php
```

### 4️⃣ 完成！

打开登录页面，应该能看到 "My OIDC 登录" 按钮。

---

## 配置选项速查表

| 选项 | 说明 | 必需 |
|-----|------|------|
| `enabled` | 启用 OIDC | ✅ |
| `provider.authorization_endpoint` | 授权链接 | ✅ |
| `provider.token_endpoint` | Token 链接 | ✅ |
| `provider.userinfo_endpoint` | 用户信息链接 | ✅ |
| `client.id` | Client ID | ✅ |
| `client.secret` | Client Secret | ✅ |
| `client.redirect_uri` | 回调链接 | ✅ |
| `user_mapping` | 用户字段映射 | ❌ |
| `default_level` | 默认权限级别 | ❌ |
| `auto_create_user` | 自动创建用户 | ❌ |
| `user_whitelist` | 用户白名单 | ❌ |

---

## 安全检查清单

- [ ] 已将 `oidc.php` 从版本控制中排除（`.gitignore`）
- [ ] Client Secret 已保密，未暴露在代码中
- [ ] 重定向 URI 使用 HTTPS（生产环境）
- [ ] 配置文件权限设置正确 (`chmod 600`)
- [ ] 已在 OIDC 提供商中注册回调 URI
- [ ] 已测试 OIDC 登录流程

---

## 常见问题

**Q: 配置文件应该放在哪里？**
A: `/config/oidc.php` - 注意不要将此文件提交到 Git，已在 `.gitignore` 中配置

**Q: Client Secret 会泄露吗？**
A: 不会。Client Secret 仅在服务端使用，从不发送到前端或浏览器

**Q: 支持多个 OIDC 提供商吗？**
A: 目前单个配置文件支持一个提供商，可通过修改代码支持多个

**Q: 之前的本地密码认证还能用吗？**
A: 可以。OIDC 是额外的登录方式，本地密码认证仍然有效

---

## 下一步

- 📖 查看详细文档：[OIDC_INTEGRATION_CN.md](OIDC_INTEGRATION_CN.md)
- 🔗 参考链接：https://docs.next-terminal.typesafe.cn/zh/usage/oidc_server.html
- 🐛 调试技巧：检查 `/app/logs/login.log` 查看登录日志
