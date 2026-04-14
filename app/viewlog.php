<?php

/**
 * 读取上传日志
 */

require_once __DIR__ . '/function.php';

// 非管理员不可访问!
if (!is_who_login('admin')) exit('Permission denied');
// 禁止直接访问
if (empty($_REQUEST['sign']) || $_REQUEST['sign'] !== md5($config['password'] . date('ymdh'))) exit('Authentication error');

// 登录日志
if (isset($_GET['login_log'])) {
    $file = APP_ROOT . '/admin/logs/login/' . date('/Y-m-') . 'logs.php';
    echo '<pre class="pre-scrollable" style="background-color: rgba(0, 0, 0, 0);border-color:rgba(0, 0, 0, 0);">';
    if (is_file($file)) {
        echo file_get_contents($file);
    } else {
        echo '并未生成登录日志,请检查文件权限!';
    }
    exit('</pre>');
}

// 上传日志
require_once APP_ROOT . '/app/header.php';

// 判断数据来源: 数据库优先
$useDatabase = Database::isAvailable();

// 获取日期筛选
$logDate = isset($_POST['logDate']) ? $_POST['logDate'] : date('Y-m');
$logFile = APP_ROOT . '/admin/logs/upload/' . $logDate . '.php';

// 如果启用数据库，从数据库获取数据
$dbLogs = [];
if ($useDatabase) {
    // 转换日期格式 Y-m -> Y-m-d
    $startDate = $logDate . '-01 00:00:00';
    $endDate = date('Y-m-t 23:59:59', strtotime($logDate));

    try {
        $db = Database::getInstance();
        $sql = "SELECT filename, original_name, path, url, file_size, size_formatted, md5,
                       ip, port, user_agent, upload_source, check_status, expiration,
                       expire_time, expire_time_formatted, created_at
                FROM easyimages_records
                WHERE is_deleted = 0 AND created_at BETWEEN :start AND :end
                ORDER BY id DESC";

        $dbLogs = $db->getAll($sql, ['start' => $startDate, 'end' => $endDate]);
    } catch (Exception $e) {
        $dbLogs = [];
        error_log('viewlog db error: ' . $e->getMessage());
    }
}

// 如果没有数据库数据，使用文件日志
$logs = [];
if (!$useDatabase || empty($dbLogs)) {
    try {
        if (is_file($logFile)) {
            require_once $logFile;
        } else {
            throw new Exception('<h3 class="alert alert-danger">日志文件不存在, 请在图床安全中开启上传日志!</h3>');
        }
        if (empty($logs)) {
            throw new Exception('<div class="alert alert-info">没有上传日志!<div>');
        }
    } catch (Exception $e) {
        require_once APP_ROOT . '/app/footer.php';
        exit;
    }
}
?>
<div class="col-md-12">
    <!-- 日期选择 -->
    <div class="btn-toolbar" style="margin-bottom: 10px;">
        <div class="btn-group">
            <form action="" method="post" class="form-inline" style="display:inline-flex; gap:5px;">
                <input type="month" name="logDate" value="<?php echo $logDate; ?>" class="form-control input-sm">
                <button type="submit" class="btn btn-primary btn-sm">查看</button>
            </form>
            <?php if ($useDatabase) : ?>
                <span class="label label-success">数据库模式</span>
            <?php else : ?>
                <span class="label label-warning">文件模式</span>
            <?php endif; ?>
        </div>
    </div>

    <div id="logs" class="datagrid table-bordered">
        <div class="input-control search-box search-box-circle has-icon-left has-icon-right" id="searchboxExample2" style="margin-bottom: 10px;">
            <input id="inputSearchExample2" type="search" class="form-control search-input input-sm" placeholder="日志搜索...">
            <label for="inputSearchExample2" class="input-control-icon-left search-icon"><i class="icon icon-search"></i></label>
            <a href="#" class="input-control-icon-right search-clear-btn"><i class="icon icon-remove"></i></a>
        </div>
        <div class="datagrid-container"></div>
    </div>
    <p class="text-muted" style="font-size:10px;">
        <i class="modal-icon icon-info"></i> 建议使用分辨率 ≥ 1366*768px;
        <?php if ($useDatabase) : ?>
            数据来源: 数据库 (<?php echo count($dbLogs); ?> 条记录)
        <?php else : ?>
            当前日志文件: <?php echo $logFile; ?>
        <?php endif; ?>
    </p>
</div>
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/zui/lib/datagrid/zui.datagrid.min.css">
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/datagrid/zui.datagrid.min.js"></script>
<script>
    // 更改页面布局
    $(document).ready(function() {
        $("body").removeClass("container").addClass("container-fixed-lg");
    });

    // POST 删除提交
    function ajax_post(url, mode = 'delete') {
        $.post("del.php", {
                url: url,
                mode: mode
            },
            function(data, status) {
                console.log(data)
                let res = JSON.parse(data);
                new $.zui.Messager(res.msg, {
                    type: res.type,
                    icon: res.icon
                }).show();
                // 延时2秒刷新
                window.setTimeout(function() {
                    window.location.reload();
                }, 2000)
            });
    }

    // logs 数据表格
    $('#logs').datagrid({
        dataSource: {
            height: 800,
            cols: [{
                    label: '当前名称',
                    name: 'orgin',
                    width: 0.07
                },
                {
                    label: '源文件名',
                    name: 'source',
                    html: true,
                    width: 0.08
                },
                {
                    label: '上传时间',
                    name: 'date',
                    html: true,
                    width: 0.09
                },
                {
                    label: '上传IP及端口',
                    name: 'ip',
                    html: true,
                    width: 0.08
                },
                {
                    label: '上传地址',
                    name: 'ip2region',
                    html: true,
                    width: 0.1
                },
                {
                    label: 'User-Agent',
                    name: 'user_agent',
                    html: true,
                    width: 0.11
                },
                {
                    label: 'FILE-MD5',
                    name: 'md5',
                    html: true,
                    width: 0.1
                },
                {
                    label: '文件路径',
                    name: 'path',
                    html: true,
                    width: 0.11
                },
                {
                    label: '文件大小',
                    name: 'size',
                    html: true,
                    width: 0.06
                },
                {
                    label: '鉴黄?',
                    name: 'checkImg',
                    html: true,
                    width: 0.05
                },
                {
                    label: '来源',
                    name: 'from',
                    html: true,
                    width: 0.05
                },
                {
                    label: '过期时间',
                    name: 'expiration',
                    html: true,
                    width: 0.08
                },
                {
                    label: '管理',
                    name: 'manage',
                    html: true,
                    width: 0.1
                },
            ],
            array: [
                <?php
                if ($useDatabase && !empty($dbLogs)) :
                    // 数据库模式
                    foreach ($dbLogs as $v) :
                ?> {
                        orgin: '<?php echo htmlspecialchars($v['filename']); ?>',
                        source: '<input class="form-control input-sm" type="text" value="<?php echo htmlspecialchars($v['original_name']); ?>" readonly>',
                        date: '<?php echo $v['created_at']; ?>',
                        ip: '<a href="http://freeapi.ipip.net/<?php echo $v['ip']; ?>" target="_blank"><?php echo $v['ip'] . ':' . $v['port']; ?></a>',
                        ip2region: '<?php echo ip2region($v['ip']); ?>',
                        user_agent: '<input class="form-control input-sm" type="text" value="<?php echo htmlspecialchars(substr($v['user_agent'], 0, 100)); ?>" readonly>',
                        path: '<input class="form-control input-sm" type="text" value="<?php echo $v['path']; ?>" readonly>',
                        md5: '<input class="form-control input-sm" type="text" value="<?php echo $v['md5']; ?>" readonly>',
                        size: '<?php echo $v['size_formatted']; ?>',
                        checkImg: '<?php echo $v['check_status'] ? '是' : '否'; ?>',
                        from: '<?php echo $v['upload_source'] === 'web' ? '网页' : ($v['upload_source'] === 'api' ? 'API' : $v['upload_source']); ?>',
                        expiration: '<?php
                            if ($v['expiration'] === 'never' || empty($v['expiration'])) {
                                echo '永久';
                            } elseif ($v['expire_time']) {
                                $remaining = $v['expire_time'] - time();
                                if ($remaining > 0) {
                                    echo $v['expire_time_formatted'] . '<br><small class="text-success">剩余 ' . ceil($remaining / 86400) . ' 天</small>';
                                } else {
                                    echo '<span class="text-danger">已过期</span>';
                                }
                            } else {
                                echo $v['expiration'];
                            }
                        ?>',
                        manage: '<div class="btn-group"><a href="<?php echo rand_imgurl(); ?><?php echo $v['path']; ?>" target="_blank" class="btn btn-mini btn-success">查看</a> <a href="/app/info.php?img=<?php echo $v['path']; ?>" target="_blank" class="btn btn-mini">信息</a><a href="#" onclick="ajax_post(\'<?php echo $v['path']; ?>\',\'recycle\')" class="btn btn-mini btn-info">回收</a> <a href="#" onclick="ajax_post(\'<?php echo $v['path']; ?>\',\'delete\')" class="btn btn-mini btn-danger">删除</a></div>',
                    },
                <?php
                    endforeach;
                else :
                    // 文件模式 (原始逻辑)
                    foreach ($logs as $k => $v) :
                ?> {
                        orgin: '<?php echo $k; ?>',
                        source: '<input class="form-control input-sm" type="text" value="<?php echo $v['source']; ?>" readonly>',
                        date: '<?php echo $v['date']; ?>',
                        ip: '<a href="http://freeapi.ipip.net/<?php echo $v['ip']; ?>" target="_blank"><?php echo $v['ip'] . ':' . $v['port']; ?></a>',
                        ip2region: '<?php echo ip2region($v['ip']); ?>',
                        user_agent: '<input class="form-control input-sm" type="text" value="<?php echo $v['user_agent']; ?>" readonly>',
                        path: '<input class="form-control input-sm" type="text" value="<?php echo $v['path']; ?>" readonly>',
                        md5: '<input class="form-control input-sm" type="text" value="<?php echo $v['md5']; ?>" readonly>',
                        size: '<?php echo $v['size']; ?>',
                        checkImg: '<?php echo strstr("OFF", $v['checkImg']) ? "否" : "是"; ?>',
                        from: '<?php echo is_string($v['from']) ? "网页" : "API: " . $v["from"]; ?>',
                        expiration: '<?php
                            if (isset($v["expiration"]) && isset($v["expire_time"])) {
                                if ($v["expiration"] == "never") {
                                    echo "永久";
                                } elseif ($v["expire_time"]) {
                                    $remaining = $v["expire_time"] - time();
                                    if ($remaining > 0) {
                                        echo date("Y-m-d H:i:s", $v["expire_time"]) . "<br><small class=\"text-success\">剩余 " . ceil($remaining / 86400) . " 天</small>";
                                    } else {
                                        echo "<span class=\"text-danger\">已过期</span>";
                                    }
                                } else {
                                    echo $v["expiration"];
                                }
                            } else {
                                echo "永久";
                            }
                        ?>',
                        manage: '<div class="btn-group"><a href="<?php echo rand_imgurl() . $v["path"]; ?>" target="_blank" class="btn btn-mini btn-success">查看</a> <a href="/app/info.php?img=<?php echo $v["path"]; ?>" target="_blank" class="btn btn-mini">信息</a><a href="#" onclick="ajax_post(\'<?php echo $v["path"]; ?>\',\'recycle\')" class="btn btn-mini btn-info">回收</a> <a href="#" onclick="ajax_post(\'<?php echo $v["path"]; ?>\',\'delete\')" class="btn btn-mini btn-danger">删除</a></div>',
                    },
                <?php endforeach; endif; ?>
            ]
        },
        sortable: true,
        hoverCell: true,
        showRowIndex: true,
        responsive: true,
        height: 666,
        configs: {
            R1: {
                style: {
                    color: '#00b8d4',
                    backgroundColor: '#e0f7fa'
                }
            },
        }
    });

    // 获取数据表格实例
    var logMyDataGrid = $('#logs').data('zui.datagrid');

    var myDate = new Date();
    logMyDataGrid.showMessage('已获取 <?php echo $logDate; ?> 月上传日志...... ', 'primary', 2000);

    // 按照 `name` 列降序排序
    logMyDataGrid.sortBy('date', 'desc');

    // 更改网页标题
    document.title = "<?php echo $logDate; ?>月上传日志 - <?php echo $config['title']; ?>"
</script>
<?php
require_once APP_ROOT . '/app/footer.php';
