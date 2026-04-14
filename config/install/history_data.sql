-- EasyImages2.0 历史数据导入脚本
-- 生成时间: 2026-04-11
-- 说明: 根据用户提供的历史日志数据生成

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 导入图片记录
-- ----------------------------
INSERT INTO `easyimg_records`
(filename, original_name, path, url, file_size, size_formatted, md5, ip, port, user_agent, upload_source, check_status, expiration, expire_time, expire_time_formatted, created_at)
VALUES
('qrllzy.jpg', '1.jpg', '/i/2025/08/24/qrllzy.jpg', 'https://img.20252049.xyz/i/2025/08/24/qrllzy.jpg', 934615, '912.71KB', 'da422f5657f93ccbaf3b54d335963947', '104.224.156.4', 9939, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'web', 0, 'never', NULL, NULL, '2025-08-24 23:48:58'),

('12t0oju.png', '1969380017662070784.png', '/i/2026/04/11/12t0oju.png', 'https://img.20252049.xyz/i/2026/04/11/12t0oju.png', 975441, '952.58KB', '1935d1bcd34c79f525b3d9187365e694', '104.224.156.4', 29169, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'web', 0, '1day', 1776008805, '2026-04-12 15:46:45', '2026-04-11 23:46:45'),

('12uafkn.jpg', '1.jpg', '/i/2026/04/11/12uafkn.jpg', 'https://img.20252049.xyz/i/2026/04/11/12uafkn.jpg', 934615, '912.71KB', 'da422f5657f93ccbaf3b54d335963947', '104.224.156.4', 9939, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'web', 0, 'never', NULL, NULL, '2026-04-11 23:48:58');

-- ----------------------------
-- 更新统计表
-- ----------------------------
INSERT INTO `easyimg_stats` (`stat_date`, `upload_count`, `total_size`, `total_count`)
VALUES (CURDATE(), 3, 2844671, 3)
ON DUPLICATE KEY UPDATE
    upload_count = upload_count + 3,
    total_size = total_size + 2844671,
    total_count = total_count + 3;

SET FOREIGN_KEY_CHECKS = 1;

-- 完成！共导入 3 条记录
