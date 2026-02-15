<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    
    require_once $configFile;
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SHOW TABLES LIKE 'bans'");
        if ($stmt->rowCount() > 0) {
            die('系统似乎已经安装完成。如需重新安装，请删除 includes/config.php 文件。');
        }
    } catch (Exception $e) {
        
    }
}


$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = trim($_POST['db_pass'] ?? '');
    $dbName = trim($_POST['db_name'] ?? 'minecraft_bans');

    
    if (empty($dbHost)) $errors[] = '数据库主机不能为空';
    if (empty($dbUser)) $errors[] = '数据库用户名不能为空';
    if (empty($dbName)) $errors[] = '数据库名不能为空';

    if (empty($errors)) {
        
        try {
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            
            $sql = "CREATE TABLE IF NOT EXISTS `bans` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL,
                `ban_time` DATETIME NOT NULL,
                `unban_time` DATETIME DEFAULT NULL,
                `punishment` VARCHAR(100) NOT NULL,
                `reason` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_username` (`username`),
                INDEX `idx_ban_time` (`ban_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $pdo->exec($sql);

            
            $configContent = <<<PHP
<?php


define('DB_HOST', '{$dbHost}');
define('DB_USER', '{$dbUser}');
define('DB_PASS', '{$dbPass}');
define('DB_NAME', '{$dbName}');


date_default_timezone_set('Asia/Shanghai');


error_reporting(E_ALL);
ini_set('display_errors', 1);


function getDBConnection() {
    try {
        \$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS);
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        \$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return \$pdo;
    } catch (PDOException \$e) {
        die("数据库连接失败: " . \$e->getMessage());
    }
}


spl_autoload_register(function (\$class_name) {
    \$file = __DIR__ . '/' . \$class_name . '.php';
    if (file_exists(\$file)) {
        require_once \$file;
    }
});
?>
PHP;

            if (file_put_contents($configFile, $configContent) !== false) {
                
                $stmt = $pdo->prepare("INSERT INTO `bans` (username, ban_time, unban_time, punishment, reason) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    'ExamplePlayer',
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s', strtotime('+7 days')),
                    '封禁7天',
                    '示例违规行为'
                ]);

                $success = true;
            } else {
                $errors[] = '无法写入配置文件，请检查目录权限。';
            }

        } catch (PDOException $e) {
            $errorMsg = '数据库连接失败: ' . $e->getMessage();
            $errorMsg .= '<br><small>请检查：';
            $errorMsg .= '<br>1. MySQL 服务是否正在运行';
            $errorMsg .= '<br>2. 数据库用户名和密码是否正确';
            $errorMsg .= '<br>3. 数据库主机地址是否正确（尝试使用 127.0.0.1 代替 localhost）';
            $errorMsg .= '<br>4. 确保用户有权限访问数据库</small>';
            $errors[] = $errorMsg;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 封禁公示系统 - 安装向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 100%);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 500px;
            background: rgba(30, 30, 46, 0.9);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(100, 200, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00b4d8, #0077b6, #03045e);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #00b4d8;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 0 10px rgba(0, 180, 216, 0.5);
        }

        .success-box {
            background: rgba(0, 200, 83, 0.1);
            border: 1px solid #00c853;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .error-box {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid #f44336;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .error-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #90caf9;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(100, 200, 255, 0.3);
            border-radius: 8px;
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #00b4d8;
            box-shadow: 0 0 0 2px rgba(0, 180, 216, 0.2);
        }

        input[type="submit"] {
            width: 100%;
            padding: 15px;
            background: linear-gradient(90deg, #0077b6, #00b4d8);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        input[type="submit"]:hover {
            background: linear-gradient(90deg, #0096c7, #48cae4);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 180, 216, 0.4);
        }

        .links {
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
        }

        .links a {
            color: #48cae4;
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s;
        }

        .links a:hover {
            color: #90e0ef;
            text-decoration: underline;
        }

        .note {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>安装向导</h1>

        <?php if ($success): ?>
            <div class="success-box">
                <h3>🎉 安装成功！</h3>
                <p>系统已成功安装并配置完成。</p>
                <ul style="margin-top: 15px; margin-left: 20px;">
                    <li>数据库表已创建</li>
                    <li>配置文件已生成</li>
                    <li>示例数据已插入</li>
                </ul>
                <p style="margin-top: 20px;">
                    <strong>下一步：</strong>
                </p>
                <div style="margin-top: 15px;">
                    <a href="index.php" style="display: inline-block; background: #00b4d8; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin-right: 10px;">查看前端页面</a>
                    <a href="admin/index.php" style="display: inline-block; background: #0077b6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">进入后台管理</a>
                </div>
                <p class="note" style="margin-top: 20px;">
                    注意：为安全起见，建议删除 install.php 文件或限制其访问权限。
                </p>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <strong>安装过程中出现错误：</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="db_host">数据库主机</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                    <div class="note">通常为 localhost 或 127.0.0.1</div>
                </div>

                <div class="form-group">
                    <label for="db_user">数据库用户名</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="db_pass">数据库密码</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="db_name">数据库名称</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'minecraft_bans'); ?>" required>
                    <div class="note">如果数据库不存在，系统将尝试创建</div>
                </div>

                <input type="submit" value="开始安装">
            </form>

            <div class="links">
                <a href="https:
                <a href="https:
            </div>
        <?php endif; ?>
    </div>
</body>
</html>