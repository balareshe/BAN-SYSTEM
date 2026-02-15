<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



session_start();


if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';


$valid_username = 'admin';
$valid_password_hash = password_hash('admin123', PASSWORD_DEFAULT); 


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        
        
        if ($username === $valid_username && password_verify($password, $valid_password_hash)) {
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['login_time'] = time();

            
            if ($remember) {
                
                $_SESSION['remember_me'] = true;
                $lifetime = 30 * 24 * 3600; 
                session_set_cookie_params($lifetime);
            }

            
            session_regenerate_id(true);

            
            $ip = getClientIP();
            error_log("管理员登录成功: {$username} from {$ip}");

            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = '用户名或密码错误';
            
            $ip = getClientIP();
            error_log("管理员登录失败: {$username} from {$ip}");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - 登录</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https:
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-shield-alt"></i>
                <span>ADMIN PANEL</span>
            </div>
            <div class="login-subtitle">Minecraft 封禁管理系统</div>
        </div>

        <div class="login-card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> 用户名
                    </label>
                    <input type="text"
                           id="username"
                           name="username"
                           placeholder="输入管理员用户名"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           required
                           autofocus>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> 密码
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="输入管理员密码"
                           required>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember">
                        <span class="checkmark"></span>
                        记住登录状态
                    </label>
                    <a href="#" class="forgot-password">忘记密码？</a>
                </div>

                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> 登录系统
                </button>
            </form>

            <div class="login-footer">
                <p><i class="fas fa-info-circle"></i> 默认管理员账号: admin / admin123</p>
                <p class="security-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    请及时修改默认密码以确保系统安全
                </p>
            </div>
        </div>

        <div class="login-links">
            <a href="../index.php">
                <i class="fas fa-arrow-left"></i> 返回前台
            </a>
            <a href="#">
                <i class="fas fa-question-circle"></i> 帮助文档
            </a>
            <a href="#">
                <i class="fas fa-cog"></i> 系统设置
            </a>
        </div>
    </div>

    <script>
        
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                alert('请输入用户名和密码');
                return false;
            }
        });

        
        const passwordInput = document.getElementById('password');
        const togglePassword = document.createElement('button');
        togglePassword.type = 'button';
        togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
        togglePassword.className = 'toggle-password';
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        passwordInput.parentNode.appendChild(togglePassword);
    </script>
</body>
</html>