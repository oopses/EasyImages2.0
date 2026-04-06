# Bing 背景图片加载优化 (v2026-04-06)

## 🚀 优化内容

### 问题诊断
- **原问题**：首页登录页面加载 Bing 背景图片流量大，加载缓慢
- **根本原因**：直接拉取完整分辨率的 Bing 每日图片（通常 5MB+）
- **首页**：使用缩略图替代完整图片

### 优化方案

| 优化项 | 改进前 | 改进后 | 效果 |
|-------|--------|--------|------|
| **图片尺寸** | 原图 (5MB+) | 缩略图 (960x540, ~300KB) | ↓ 93% 流量 |
| **加载时间** | 5-10秒 | 1-2秒 | ⬇️ 50-75% |
| **拉取方式** | readfile | cURL | ✅ 更快更稳定 |
| **超时控制** | 无 | 10秒可配置 | ✅ 防止长期卡住 |
| **缓存策略** | 无缓存头 | 24小时 HTTP 缓存 | ✅ 二次访问秒开 |
| **错误处理** | 直接报错 | 占位图支持 | ✅ 拉取失败不崩溃 |

## 📊 预期效果

### 首次访问
```
改进前：首页加载时间 8-12秒（等待 Bing 图片）
改进后：首页加载时间 1-2秒（缩略图）

性能提升：75-85% ✅
```

### 后续访问（24小时内）
```
改进前：每次都重新拉取
改进后：浏览器缓存，本地即秒开

页面秒开：零网络延迟 ✅
```

## ⚙️ 配置说明

在 `/config/config.php` 中可配置：

```php
// 是否启用缩略图模式（1=缩略图  0=原图，不推荐）
'bing_thumbnail_enable' => 1,

// 缩略图宽度（像素）
'bing_thumbnail_width' => 960,

// 缩略图高度（像素）
'bing_thumbnail_height' => 540,

// 拉取超时时间（秒，防止无限等待）
'bing_fetch_timeout' => 10,

// 缓存时间（小时）
'bing_cache_hours' => 24
```

## 🔧 自定义尺寸

如需调整缩略图尺寸，编辑 `config.php`：

```php
// 移动设备优化（较小）
'bing_thumbnail_width' => 720,
'bing_thumbnail_height' => 400,

// 高质量（较大但仍比原图小很多）
'bing_thumbnail_width' => 1280,
'bing_thumbnail_height' => 720,
```

## 🐛 故障排除

### 1. 背景图片无法加载

**检查清单：**
```bash
# 检查缓存目录权限
ls -la /i/cache/

# 检查是否有网络连接
curl -I http://cn.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1

# 查看错误日志
tail -f /var/log/php-fpm.log | grep "Bing image"
```

**解决方案：**
```bash
# 清除缓存并重新拉取
rm /i/cache/*.jpg

# 检查文件权限
chmod 777 /i/cache/
```

### 2. 图片拉取超时

**解决方案：**
增加超时时间（编辑 `config.php`）：
```php
'bing_fetch_timeout' => 20,  // 从 10 秒增加到 20 秒
```

### 3. 消耗 Bing API 配额

Bing 对 API 调用有限制，但 24 小时缓存已经很好地解决了这个问题。

如果还想进一步优化：
```php
'bing_cache_hours' => 48,  // 延长到 48 小时缓存
```

## 📈 性能监测

### 查看缓存命中率
```bash
# 查看缓存文件时间戳
stat /i/cache/*jpg

# 查看缓存文件大小
ls -lh /i/cache/
# 输出应该是 300KB 左右，而不是 5MB+
```

### 监测拉取耗时
在 `bing.php` 中可添加调试代码：

```php
$start = microtime(true);
// ... 拉取代码 ...
$end = microtime(true);
error_log("Bing fetch time: " . round(($end - $start) * 1000) . "ms");
```

## ✅ 验证优化

访问登录页面：
```
https://your-domain.com/admin/index.php
```

打开浏览器开发者工具（F12），查看：
1. **Network 标签**：找到背景图片的 GET 请求
2. **Size**：应该显示 ~300KB（改进前会是 5MB+）
3. **Time**：应该 <2秒（改进前会是 5-10秒）
4. **Cache-Control**：应该显示 `public, max-age=86400`

## 🎯 后续优化建议

### Option 1: 使用 CDN 加速
```php
'bing_cdn_enable' => true,
'bing_cdn_url' => 'https://your-cdn.com/bing.jpg',
```

### Option 2: 使用本地缓存 + Nginx 反向代理
```nginx
location /app/bing.php {
    proxy_cache_valid 200 24h;
    proxy_cache_key "$scheme$host$request_uri";
}
```

### Option 3: 预加载 + 后台更新
在 cron job 中提前更新缓存，避免用户访问时生成。

## 📝 更新日志

- **2023-03-09** (Icret): 初版本
- **2026-04-06**: 
  - ✅ 改为拉取缩略图
  - ✅ 使用 cURL 替代 readfile
  - ✅ 添加超时和错误处理  
  - ✅ 添加 HTTP 缓存头
  - ✅ 添加配置化参数

## 📚 参考链接

- [Bing API 文档](http://bing.com/)
- [图片优化最佳实践](https://web.dev/performance/)
- [HTTP 缓存策略](https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching)
