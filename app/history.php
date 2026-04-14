<?php
include_once __DIR__ . "/header.php";

$useDatabase = Database::isAvailable();

// 获取当前用户的标识 (基于IP或登录状态)
$userHash = md5(real_ip() . ($config['password'] ?? ''));
?>
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/EasyImage.css">
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/viewjs/viewer.min.css">
<div class="row">
    <div class="col-md-12">
        <?php if ($useDatabase) : ?>
        <!-- 数据库模式: 服务器端历史记录 -->
        <div id="history-db" class="datagrid table-bordered">
            <div class="input-control search-box search-box-circle has-icon-left has-icon-right" style="margin-bottom: 10px;">
                <input id="inputSearchHistory" type="search" class="form-control search-input input-sm" placeholder="搜索文件名...">
                <label for="inputSearchHistory" class="input-control-icon-left search-icon"><i class="icon icon-search"></i></label>
                <a href="#" class="input-control-icon-right search-clear-btn"><i class="icon icon-remove"></i></a>
            </div>
            <div class="datagrid-container"></div>
        </div>
        <?php else : ?>
        <!-- 浏览器localStorage模式 -->
        <ul id="viewjs">
            <div class="cards listNum">
                <!-- 历史上传列表 -->
            </div>
        </ul>
        <?php endif; ?>
    </div>
</div>
<div class="col-md-12 history_clear">
</div>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/EasyImage.js"></script>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/lazyload/lazyload.min.js"></script>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/viewjs/viewer.min.js"></script>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/clipboard/clipboard.min.js"></script>
<?php if ($useDatabase) : ?>
<!-- 数据库模式 -->
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/zui/lib/datagrid/zui.datagrid.min.css">
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/datagrid/zui.datagrid.min.js"></script>
<script>
$(document).ready(function() {
    // 从数据库加载历史记录
    loadHistoryFromDB();
});

function loadHistoryFromDB() {
    $.post('../api/public.php', {
        action: 'history',
        page: 1,
        pageSize: 100
    }, function(res) {
        if (res.code === 200 && res.data.length > 0) {
            renderHistoryDB(res.data);
            $('.history_clear').append('<h3 class="header-dividing" style="text-align: center;" data-toggle="tooltip" title="服务器端历史记录<br/>基于上传IP分组"><button class="btn btn-mini btn-primary" type="button"><i class="icon icon-cloud"></i> 服务器历史记录 (共 ' + res.total + ' 条)</button></h3>');
        } else {
            $('.listNum').append('<h2 class="alert alert-danger">服务器端历史记录为空~~ <br /><small>可能是数据库未启用或没有上传记录~!</small></h2>');
        }
    }, 'json').fail(function() {
        // API调用失败，显示浏览器端历史
        loadHistoryFromLocal();
    });
}

function renderHistoryDB(records) {
    var html = '<ul id="viewjs"><div class="cards listNum">';
    records.forEach(function(record) {
        html += '<div class="col-md-4 col-sm-6 col-lg-3"><div class="card">';
        html += '<li><img src="../public/images/loading.svg" data-image="' + (record.thumb_url || record.url) + '" data-original="" alt="简单图床-EasyImage"></li>';
        html += '<div class="bottom-bar">';
        html += '<a href="' + record.url + '" target="_blank"><i class="icon icon-picture" data-toggle="tooltip" title="打开" style="margin-left:10px;"></i></a>';
        html += '<a href="#" class="copy" data-clipboard-text="' + record.url + '" data-toggle="tooltip" title="复制链接" style="margin-left:10px;"><i class="icon icon-copy"></i></a>';
        html += '<a href="info.php?img=' + record.path + '" data-toggle="tooltip" title="详细信息" target="_blank" style="margin-left:10px;"><i class="icon icon-info-sign"></i></a>';
        html += '<a href="down.php?dw=' + record.path + '" data-toggle="tooltip" title="下载文件" target="_blank" style="margin-left:10px;"><i class="icon icon-cloud-download"></i></a>';
        if (record.del_url) {
            html += '<a href="' + record.del_url + '" target="_blank"><i class="icon icon-trash" data-toggle="tooltip" title="删除文件" style="margin-left:10px;"></i></a>';
        }
        html += '<span class="text-ellipsis" style="margin-left:10px;" title="' + record.original_name + '">' + record.original_name + '</span>';
        html += '</div></div></div>';
    });
    html += '</div></ul>';
    $('#history-db').after(html);

    // 初始化懒加载和viewer
    initLazyAndViewer();
}

function loadHistoryFromLocal() {
    if ($.zui.store.length() > 1) {
        console.log('saved: ' + $.zui.store.length());
        $.zui.store.forEach(function(key, value) {
            console.log('url list: ' + value['url']);
            if (value['url'] !== undefined) {
                let v_url = parseURL(value['url']);
                $('.listNum').append('<div class="col-md-4 col-sm-6 col-lg-3"><div class="card"><li><img src="../public/images/loading.svg" data-image="' + value['thumb'] + '" data-original="" alt="简单图床-EasyImage"></li><div class="bottom-bar"><a href="' + value['url'] + '" target="_blank"><i class="icon icon-picture" data-toggle="tooltip" title="打开" style="margin-left:10px;"></i></a><a href="#" class="copy" data-clipboard-text="' + value['url'] + '" data-toggle="tooltip" title="复制链接" style="margin-left:10px;"><i class="icon icon-copy"></i></a><a href="info.php?history=' + v_url.path + '" data-toggle="tooltip" title="详细信息" target="_blank" style="margin-left:10px;"><i class="icon icon-info-sign"></i></a><a href="down.php?history=' + v_url.path + '" data-toggle="tooltip" title="下载文件" target="_blank" style="margin-left:10px;"><i class="icon icon-cloud-download"></i></a><a href="#" data-toggle="tooltip" title="删除记录" class="Remove"id="' + value['srcName'] + '" style="margin-left:10px;"><i class="icon icon-remove-sign"></i></a><a href="' + value['del'] + '" target="_blank"><i class="icon icon-trash" data-toggle="tooltip" title="删除文件" style="margin-left:10px;"></i></a><a href="#" data-toggle="tooltip" title="源文件名" class="copy text-ellipsis" data-clipboard-text="' + value['srcName'] + '" style="margin-left:10px;">' + value['srcName'] + '</a></div></div></div>');
            }
        });
        $('.history_clear').append('<h3 class="header-dividing" style="text-align: center;" data-toggle="tooltip" title="非上传记录|清空缓存|浏览器版本低不显示<br/>点击清空历史上传记录"><button class="btn btn-mini btn-primary" type="button"><i class="icon icon-eye-open"></i> 历史上传记录</button></h3>');
    } else {
        $('.listNum').append('<h2 class="alert alert-danger">上传历史记录不存在~~ <br /><small>非上传记录 | 清空缓存 | 浏览器版本低不显示~!</small></h2>');
    }
}

function initLazyAndViewer() {
    new Viewer(document.getElementById('viewjs'), { url: 'data-original' });
    var lazy = new Lazy({ onload: function(elem) { console.log(elem) }, delay: 300 });
}
</script>
<?php else : ?>
<!-- 浏览器localStorage模式 -->
<script>
if ($.zui.store.length() > 1) {
    console.log('saved: ' + $.zui.store.length());
    $.zui.store.forEach(function(key, value) {
        console.log('url list: ' + value['url']);
        if (value['url'] !== undefined) {
            let v_url = parseURL(value['url']);
            $('.listNum').append('<div class="col-md-4 col-sm-6 col-lg-3"><div class="card"><li><img src="../public/images/loading.svg" data-image="' + value['thumb'] + '" data-original="" alt="简单图床-EasyImage"></li><div class="bottom-bar"><a href="' + value['url'] + '" target="_blank"><i class="icon icon-picture" data-toggle="tooltip" title="打开" style="margin-left:10px;"></i></a><a href="#" class="copy" data-clipboard-text="' + value['url'] + '" data-toggle="tooltip" title="复制链接" style="margin-left:10px;"><i class="icon icon-copy"></i></a><a href="info.php?history=' + v_url.path + '" data-toggle="tooltip" title="详细信息" target="_blank" style="margin-left:10px;"><i class="icon icon-info-sign"></i></a><a href="down.php?history=' + v_url.path + '" data-toggle="tooltip" title="下载文件" target="_blank" style="margin-left:10px;"><i class="icon icon-cloud-download"></i></a><a href="#" data-toggle="tooltip" title="删除记录" class="Remove"id="' + value['srcName'] + '" style="margin-left:10px;"><i class="icon icon-remove-sign"></i></a><a href="' + value['del'] + '" target="_blank"><i class="icon icon-trash" data-toggle="tooltip" title="删除文件" style="margin-left:10px;"></i></a><a href="#" data-toggle="tooltip" title="源文件名" class="copy text-ellipsis" data-clipboard-text="' + value['srcName'] + '" style="margin-left:10px;">' + value['srcName'] + '</a></div></div></div>');
        }
    });
    $('.history_clear').append('<h3 class="header-dividing" style="text-align: center;" data-toggle="tooltip" title="非上传记录|清空缓存|浏览器版本低不显示<br/>点击清空历史上传记录"><button class="btn btn-mini btn-primary" type="button"><i class="icon icon-eye-open"></i> 历史上传记录</button></h3>');
} else {
    $('.listNum').append('<h2 class="alert alert-danger">上传历史记录不存在~~ <br /><small>非上传记录 | 清空缓存 | 浏览器版本低不显示~!</small></h2>');
};
</script>
<?php endif; ?>

<script>
// 复制url
var clipboard = new Clipboard('.copy');
clipboard.on('success', function(e) {
    new $.zui.Messager("复制成功", {
        type: "success",
        icon: "ok-sign"
    }).show();
});
clipboard.on('error', function(e) {
    document.querySelector('.copy');
    new $.zui.Messager("复制失败", {
        type: "danger",
        icon: "exclamation-sign"
    }).show();
});

// 删除指定存储条目
$('.Remove').on('click', function() {
    let Remove = $('.Remove').attr("id");
    $.zui.store.remove(Remove);
    new $.zui.Messager('已删除 ' + Remove + ' 上传记录', {
        type: "success",
        icon: "ok-sign"
    }).show();
    setTimeout(location.reload.bind(location), 2000);
});

// 清空所有本地存储的条目
$('button').on('click', function() {
    new $.zui.Messager('已清空' + $.zui.store.length() + "条历史记录", {
        type: "success",
        icon: "ok-sign"
    }).show();
    $.zui.store.clear();
    setTimeout(location.reload.bind(location), 2000);
});

// 返回顶部
var back_to_top_button = jQuery('.btn-back-to-top');
jQuery(window).scroll(function() {
    if (jQuery(this).scrollTop() > 100 && !back_to_top_button.hasClass('scrolled')) {
        back_to_top_button.addClass('scrolled');
    } else if (jQuery(this).scrollTop() < 100 && back_to_top_button.hasClass('scrolled')) {
        back_to_top_button.removeClass('scrolled');
    }
});
back_to_top_button.click(function() {
    jQuery('html, body').animate({ scrollTop: 0 }, 800);
    return false;
});

// 更改网页标题
document.title = "上传记录 - <?php echo $config['title']; ?>";
</script>
<?php
/** 引入底部 */
require_once __DIR__ . '/footer.php';
