# 本地配置 → 线上部署指南

## 📁 文件说明

我已为你创建了两份本地配置文件：

```
/config/
├── config.local.php       ← 基于你的线上配置
└── oidc.local.php         ← OIDC 配置模板
```

## 🚀 部署步骤

### 步骤 1️⃣：检查本地配置

打开 `config/config.local.php` 验证信息：

```php
'domain'=>'https://img.20252049.xyz',      // ✅ 你的域名
'imgurl'=>'https://img.20252049.xyz',      // ✅ 图片 URL
'user'=>'oops',                             // ✅ 管理员用户名
'password'=>'c44fc1dd652901def2af0c0d33e138a52a9372483d05fb1ea833b23faf642b83',  // ✅ 密码
```

### 步骤 2️⃣：配置 OIDC（可选）

如果要启用 OIDC 单点登录，编辑 `config/oidc.local.php`：

```php
'enabled' => true,  // 启用

'provider' => array(
    'name' => 'NextTerminal SSO',
    'authorization_endpoint' => 'https://YOUR-OIDC-SERVER/oauth/authorize',
    'token_endpoint' => 'https://YOUR-OIDC-SERVER/oauth/token',
    'userinfo_endpoint' => 'https://YOUR-OIDC-SERVER/oauth/userinfo',
),

'client' => array(
    'id' => 'easyimages',
    'secret' => 'YOUR-CLIENT-SECRET-HERE',  // ⚠️ 从截图中的应用获取
    'redirect_uri' => 'https://img.20252049.xyz/admin/oidc-callback.php',
),
```

### 步骤 3️⃣：打包部署

#### 方案 A：手动部署（推荐新手）

```bash
# 1. 连接到服务器
ssh user@img.20252049.xyz

# 2. 进入项目目录
cd /var/www/easyimages

# 3. 备份原配置（安全起见）
cp config/config.php config/config.php.bak

# 4. 覆盖配置
# 从本地上传文件：
scp config/config.local.php user@img.20252049.xyz:/var/www/easyimages/config/config.php
scp config/oidc.local.php user@img.20252049.xyz:/var/www/easyimages/config/oidc.php

# 5. 设置权限
ssh user@img.20252049.xyz "chmod 600 /var/www/easyimages/config/oidc.php"

# 6. 验证
ssh user@img.20252049.xyz "tail -5 /var/www/easyimages/config/config.php"
```

#### 方案 B：自动化部署脚本

创建 `deploy.sh`：

```bash
#!/bin/bash

TARGET_HOST="user@img.20252049.xyz"
TARGET_PATH="/var/www/easyimages"

echo "📦 开始部署..."

# 备份
ssh $TARGET_HOST "cp $TARGET_PATH/config/config.php $TARGET_PATH/config/config.php.\$(date +%Y%m%d_%H%M%S)"

# 上传配置
echo "📤 上传配置文件..."
scp config/config.local.php $TARGET_HOST:$TARGET_PATH/config/config.php
scp config/oidc.local.php $TARGET_HOST:$TARGET_PATH/config/oidc.php

# 设置权限
echo "🔐 设置权限..."
ssh $TARGET_HOST "chmod 600 $TARGET_PATH/config/oidc.php"
ssh $TARGET_HOST "chmod 600 $TARGET_PATH/config/config.php"

echo "✅ 部署完成！"
echo "🔗 访问: https://img.20252049.xyz/admin/index.php"
```

运行：
```bash
chmod +x deploy.sh
./deploy.sh
```

### 步骤 4️⃣：验证部署

```bash
# 1. 检查文件
ls -la /var/www/easyimages/config/

# 2. 验证权限（应该是 600）
stat /var/www/easyimages/config/config.php
stat /var/www/easyimages/config/oidc.php

# 3. 测试访问
curl -I https://img.20252049.xyz/admin/index.php

# 4. 查看错误日志
tail -20 /var/log/php-fpm.log
```

## 🔒 安全检查清单

部署前必须检查：

- [ ] `config/oidc.php` 权限为 600（`-rw-------`）
- [ ] 敏感信息未提交到 Git
- [ ] OIDC Client Secret 已填写（如果启用）
- [ ] 回调 URI 与 OIDC 应用一致
- [ ] `/config/` 目录权限正确

```bash
# 快速检查脚本
chmod +x check-permissions.sh
cat > check-permissions.sh << 'EOF'
#!/bin/bash
echo "🔍 安全检查..."
[ -f config/config.php ] && echo "✅ config.php 存在"
[ -f config/oidc.php ] && echo "✅ oidc.php 存在"
stat -c '%a %n' config/*.php | grep -E '^600' && echo "✅ 权限正确"
grep -q "REPLACE-WITH" config/oidc.php && echo "⚠️  警告：OIDC Secret 未配置"
EOF
```

## 🆘 常见问题

### Q1: 部署后页面提示权限错误

**解决：**
```bash
# 检查配置文件权限
ls -la config/config.php config/oidc.php

# 修复权限
chmod 600 config/config.php config/oidc.php

# 检查 Web 服务器用户
ps aux | grep php-fpm | head -1
# 重新设置所有者
chown www-data:www-data config/config.php config/oidc.php
```

### Q2: OIDC 登录无法工作

**检查清单：**
```bash
# 1. 检查 OIDC 配置
grep -A 5 "client.*id" config/oidc.php

# 2. 验证回调 URI
grep "redirect_uri" config/oidc.php

# 3. 查看错误日志
tail -50 /var/log/php-fpm.log | grep -i oidc
```

### Q3: 忘记了管理员密码

**重置方法：**
```bash
# 1. 生成新密码的 SHA256
echo -n "new_password" | sha256sum

# 2. 编辑配置
nano config/config.php
# 找到 'password' => '...' 
# 替换为新的 hash

# 3. 刷新配置（如果使用 OPcache）
php -r "opcache_reset();"
```

## 📊 配置对比：本地 vs 线上

| 项目 | 本地文件 | 线上部署 |
|------|---------|---------|
| **位置** | `config/config.local.php` | `/var/www/easyimages/config/config.php` |
| **权限** | 644 (可读写) | 600 (仅所有者) |
| **Git 追踪** | ✅ Yes | ❌ No (.gitignore) |
| **修改频率** | 开发时 | 生产前 |

## 🎯 后续维护

### 更新配置

1. **本地修改** → `config/config.local.php`
2. **测试验证** → 本地环境测试
3. **部署更新** → `./deploy.sh`

### 版本控制

```bash
# 追踪配置变更（不含敏感信息）
git add config/config.local.php config/oidc.local.php
git add config/.gitignore  # 确保敏感文件被忽略
git commit -m "Update: local config templates"
```

## 📝 文件清单

部署包应包含：

```
easyimages-package/
├── config/
│   ├── config.local.php      ← 你的配置
│   └── oidc.local.php        ← OIDC 配置
├── deploy.sh                 ← 部署脚本
├── README.md                 ← 说明文档
└── [其他源代码文件]
```

## ✅ 验证部署成功

部署后访问：
```
https://img.20252049.xyz/admin/index.php
```

应该能看到：
- ✅ 登录界面能正常加载
- ✅ 背景图片正常显示（Bing 缩略图）
- ✅ OIDC 登录按钮（如果已启用）
- ✅ 输入 oops / password 能成功登录

---

需要我帮你生成完整的部署脚本或者打包文件吗？
