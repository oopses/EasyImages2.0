<?php
/*
 * 登录页面
 */
require_once __DIR__ . '/../app/function.php';
require_once APP_ROOT . '/config/config.guest.php';

// 启用 session
session_start();

// OIDC 登录处理
if (isset($_GET['oidc_login'])) {
    require_once APP_ROOT . '/app/OIDCHandler.php';
    $oidc = new OIDCHandler();
    if ($oidc->isEnabled()) {
        $authUrl = $oidc->getAuthorizationUrl();
        header('Location: ' . $authUrl);
        exit();
    }
}

// 处理退出
if (isset($_GET['login'])) {
    // ✅ 修复：由 = 改为 === (修复 issue #264)
    if ($_GET['login'] === 'logout') {
        if (isset($_COOKIE['auth'])) {
            setcookie('auth', '', time() - 3600, '/');
            $logout_msg = "退出成功";
            $target_url = "../index.php";
        } else {
            $logout_msg = "尚未登录";
            $target_url = "./index.php";
        }
        
        require_once APP_ROOT . '/app/header.php';
        echo '<script>
                new $.zui.Messager("' . htmlspecialchars($logout_msg, ENT_QUOTES, 'UTF-8') . '", {
                    type: "success",
                    icon: "ok-sign"
                }).show();
                window.setTimeout("window.location=\'' . htmlspecialchars($target_url, ENT_QUOTES, 'UTF-8') . '\'", 2000);
              </script>';
        exit(require_once APP_ROOT . '/app/footer.php');
    }
}

// 提交登录处理
$login_script = "";
if (isset($_POST['password']) && isset($_POST['user'])) {
    // 验证码校验
    if ($config['captcha']) {
        if (empty($_REQUEST['code']) || strtolower($_REQUEST['code']) !== $_SESSION['code']) {
            $login_script = 'new $.zui.Messager("验证码错误或未填写", {type: "danger"}).show();';
            goto render_page;
        }
    }

    $login_res = _login($_POST['user'], $_POST['password']);
    $login = json_decode($login_res, true);

    if ($login['code'] == 200) {
        // ✅ 修改为 JS 跳转，确保 Cookie 写入生效
        $login_script = '
            new $.zui.Messager("' . htmlspecialchars($login["messege"], ENT_QUOTES, 'UTF-8') . '" , {
                type: "primary",
                icon: "check"
            }).show();
            window.setTimeout(function(){ window.location.href="' . $config['domain'] . '"; }, 2000);';
    } else {
        $login_script = 'new $.zui.Messager("' . htmlspecialchars($login["messege"], ENT_QUOTES, 'UTF-8') . '" , {
            type: "danger",
            icon: "times"
        }).show();';
    }
    write_login_log($_POST['user'], '******', $login["messege"]);
}

render_page:
require_once APP_ROOT . '/app/header.php';
if (!empty($login_script)) echo "<script>" . $login_script . "</script>";
?>
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/login.css">

<!-- 忘记密码提示 -->
<div class="modal fade" id="fogot">
    <div class="modal-dialog ">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">x</span><span class="sr-only">关闭</span></button>
                <h4 class="modal-title">忘记账号/密码?</h4>
            </div>
            <div class="modal-body">
                <p class="text-primary">忘记账号可以打开<code>/config/config.php</code>文件找到<code data-toggle="tooltip" title="'user'=><strong>admin</strong>'">user</code>对应的键值->填入</p>
                <p class="text-success">忘记密码请将密码转换成SHA256(<a href="<?php echo $config['domain'] . '/app/reset_password.php'; ?>" target="_blank" class="text-purple">转换网址</a>)->打开<code>/config/config.php</code>文件->找到<code data-toggle="tooltip" title="'password'=>'<strong>e6e0612609</strong>'">password</code>对应的键值->填入</p>
                <h4 class="text-danger">更改后会立即生效并重新登录,请务必牢记账号和密码! </h4>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<section>
    <div class="container">
        <div class="user singinBx">
            <div class="imgBx">
                <img src="<?php echo $config['login_bg']; ?>" alt="简单图床登陆界面背景图" />
            </div>
            <div class="formBx">
                <form class="form-horizontal" action="index.php" method="post" onsubmit="return md5_post()">
                    <h2>登录</h2>
                    <label for="account" class="col-sm-2"></label>
                    <input type="text" name="user" id="account" class="form-control" value="" placeholder="输入登录账号" autocomplete="off" required="required">
                    <input type="password" name="raw_password" id="raw_password" class="form-control" value="" placeholder="输入登录密码" autocomplete="off" required="required">
                    <input type="hidden" name="password" id="md5_password">
                    
                    <?php if ($config['captcha']) : ?>
                        <input class="form-control" type="text" name="code" value="" placeholder="请输入验证码" autocomplete="off" required="required" />
                        <div class="form-group">
                            <div class="col">
                                <label><img src="../app/captcha.php" width="185px" onClick="this.src='../app/captcha.php?nocache='+Math.random()" title="点击换一张" /></label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-block btn-primary">登 录</button>
                    <?php
                    // 检查 OIDC 是否启用
                    if (file_exists(APP_ROOT . '/config/oidc.php')) {
                        $oidcConfig = require APP_ROOT . '/config/oidc.php';
                        if (!empty($oidcConfig['enabled'])) {
                            echo '<a href="?oidc_login=1" class="btn btn-block btn-info" style="margin-top: 10px;"><i class="icon icon-sign-in"></i> ' . htmlspecialchars($oidcConfig['provider']['name']) . '</a>';
                        }
                    }
                    ?>
                    <p class="signup">忘记账号或密码请查看<a href="#fogot" data-moveable="inside" data-remember-pos="false" data-toggle="modal" data-target="#fogot" data-position="center">帮助信息</a></p>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    function md5_post() {
        var raw_pwd = document.getElementById('raw_password');
        var hidden_pwd = document.getElementById('md5_password');
        // 直接发送原始密码，由服务器端 bcrypt 验证
        hidden_pwd.value = raw_pwd.value;
        raw_pwd.value = "Null";
        return true;
    }

    function topggleForm() {
        var container = document.querySelector('.container');
        container.classList.toggle('active');
    }
</script>
<?php require_once APP_ROOT . '/app/footer.php'; ?>
