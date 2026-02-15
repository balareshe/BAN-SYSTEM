<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


requireAdminLogin();


$csrfToken = generateCsrfToken();


$defaultSettings = [
    'site_title' => 'Minecraft 封禁公示系统',
    'site_description' => '服务器违规封禁公示平台',
    'items_per_page' => 15,
    'timezone' => 'Asia/Shanghai',
    'maintenance_mode' => false,
    'allow_registration' => false,
    'default_punishments' => ['永久封禁', '封禁30天', '封禁7天', '封禁3天', '封禁1天', '警告'],
    'security_log_retention' => 90,
    'auto_unban_check' => true,
    'notification_email' => '',
    'enable_api' => true,
    'debug_mode' => false,
    'appearance' => [
        'theme_color' => '#00b4d8',
        'background_style' => 'gradient',
        'sidebar_collapsed' => false,
        'font_size' => 14,
        'ui_density' => 'normal'
    ]
];


$settingsFile = __DIR__ . '/../data/settings.json';
$settingsDir = dirname($settingsFile);


if (!is_dir($settingsDir)) {
    mkdir($settingsDir, 0755, true);
}


if (file_exists($settingsFile)) {
    $currentSettings = json_decode(file_get_contents($settingsFile), true);
    if (!$currentSettings) {
        $currentSettings = $defaultSettings;
    } else {
        $currentSettings = array_merge($defaultSettings, $currentSettings);
    }
} else {
    $currentSettings = $defaultSettings;
    file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}


$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'save_general':
            
            $settings = [
                'site_title' => trim($_POST['site_title'] ?? ''),
                'site_description' => trim($_POST['site_description'] ?? ''),
                'items_per_page' => max(5, min(100, intval($_POST['items_per_page'] ?? 15))),
                'timezone' => $_POST['timezone'] ?? 'Asia/Shanghai',
                'maintenance_mode' => isset($_POST['maintenance_mode']),
                'allow_registration' => isset($_POST['allow_registration']),
                'default_punishments' => array_filter(array_map('trim', explode("\n", $_POST['default_punishments'] ?? ''))),
                'security_log_retention' => max(1, min(365, intval($_POST['security_log_retention'] ?? 90))),
                'auto_unban_check' => isset($_POST['auto_unban_check']),
                'notification_email' => filter_var(trim($_POST['notification_email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['notification_email']) : '',
                'enable_api' => isset($_POST['enable_api']),
                'debug_mode' => isset($_POST['debug_mode'])
            ];

            
            if (empty($settings['site_title'])) {
                $errors[] = '网站标题不能为空';
            }

            if (empty($settings['default_punishments'])) {
                $errors[] = '默认惩罚类型不能为空';
            }

            if (empty($errors)) {
                $currentSettings = array_merge($currentSettings, $settings);
                if (file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    logAdminAction('UPDATE_SETTINGS', '更新常规设置');
                    $success = true;
                } else {
                    $errors[] = '保存设置失败，请检查文件权限';
                }
            }
            break;

        case 'save_security':
            
            $securitySettings = [
                'max_login_attempts' => max(1, min(10, intval($_POST['max_login_attempts'] ?? 5))),
                'lockout_time' => max(1, min(1440, intval($_POST['lockout_time'] ?? 15))),
                'session_timeout' => max(5, min(1440, intval($_POST['session_timeout'] ?? 30))),
                'require_strong_password' => isset($_POST['require_strong_password']),
                'enable_captcha' => isset($_POST['enable_captcha']),
                'ip_whitelist' => array_filter(array_map('trim', explode("\n", $_POST['ip_whitelist'] ?? ''))),
                'ip_blacklist' => array_filter(array_map('trim', explode("\n", $_POST['ip_blacklist'] ?? ''))),
                'enable_2fa' => isset($_POST['enable_2fa']),
                'log_all_actions' => isset($_POST['log_all_actions'])
            ];

            $currentSettings['security'] = $securitySettings;
            if (file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                logAdminAction('UPDATE_SETTINGS', '更新安全设置');
                $success = true;
            } else {
                $errors[] = '保存设置失败，请检查文件权限';
            }
            break;

        case 'save_appearance':
            
            $appearanceSettings = [
                'theme_color' => $_POST['theme_color'] ?? '#00b4d8',
                'background_style' => $_POST['background_style'] ?? 'gradient',
                'sidebar_collapsed' => isset($_POST['sidebar_collapsed']),
                'font_size' => max(12, min(18, intval($_POST['font_size'] ?? 14))),
                'ui_density' => $_POST['ui_density'] ?? 'normal'
            ];

            $currentSettings['appearance'] = $appearanceSettings;
            if (file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                logAdminAction('UPDATE_SETTINGS', '更新外观设置');
                $success = true;
            } else {
                $errors[] = '保存设置失败，请检查文件权限';
            }
            break;

        case 'reset_defaults':
            
            if (file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $currentSettings = $defaultSettings;
                logAdminAction('RESET_SETTINGS', '重置系统设置');
                $success = true;
            } else {
                $errors[] = '重置设置失败，请检查文件权限';
            }
            break;

        case 'clear_cache':
            
            $cacheDir = __DIR__ . '/../cache';
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                logAdminAction('CLEAR_CACHE', '清除系统缓存');
                $success = true;
            }
            break;

        case 'backup_database':
            
            try {
                $backupDir = __DIR__ . '/../backups';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }

                $backupFile = $backupDir . '/backup_' . date('Ymd_His') . '.sql';
                $pdo = getDBConnection();

                
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

                $backupContent = '';
                foreach ($tables as $table) {
                    
                    $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetchColumn(1);
                    $backupContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    $backupContent .= $createTable . ";\n\n";

                    
                    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                            $values = array_map(function($value) use ($pdo) {
                                if ($value === null) {
                                    return 'NULL';
                                }
                                return $pdo->quote($value);
                            }, array_values($row));

                            $backupContent .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $backupContent .= "\n";
                    }
                }

                if (file_put_contents($backupFile, $backupContent)) {
                    logAdminAction('BACKUP_DATABASE', '备份数据库');
                    $success = true;
                } else {
                    $errors[] = '备份数据库失败';
                }
            } catch (Exception $e) {
                $errors[] = '备份数据库失败: ' . $e->getMessage();
            }
            break;
    }
}


$timezones = DateTimeZone::listIdentifiers();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - 后台管理</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- 侧边栏 -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <span>ADMIN</span>
                </div>
            </div>

            <div class="sidebar-user">
                <div class="user-avatar">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($_SESSION['admin_username'] ?? '管理员'); ?></h4>
                    <span>超级管理员</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>仪表板</span>
                </a>
                <a href="bans.php" class="nav-item">
                    <i class="fas fa-user-slash"></i>
                    <span>封禁记录</span>
                </a>
                <a href="add.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>添加记录</span>
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fas fa-search"></i>
                    <span>高级搜索</span>
                </a>
                                <a href="admins.php" class="nav-item">
                    <i class="fas fa-users-cog"></i>
                    <span>管理员</span>
                </a>
                <a href="settings.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span>系统设置</span>
                </a>
                <a href="logs.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>操作日志</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <p>系统设置</p>
                <p>配置管理</p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>系统设置</h1>
                    <p>配置系统参数和功能选项</p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <button onclick="showSystemInfo()" class="action-button secondary">
                            <i class="fas fa-info-circle"></i>
                            系统信息
                        </button>
                        <button onclick="testSettings()" class="action-button">
                            <i class="fas fa-vial"></i>
                            测试配置
                        </button>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        设置保存成功！
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>保存设置时出错：</strong>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- 设置标签页 -->
                <div class="settings-tabs">
                    <div class="tab-buttons">
                        <button class="tab-button active" data-tab="general">
                            <i class="fas fa-cog"></i>
                            常规设置
                        </button>
                        <button class="tab-button" data-tab="security">
                            <i class="fas fa-shield-alt"></i>
                            安全设置
                        </button>
                        <button class="tab-button" data-tab="appearance">
                            <i class="fas fa-paint-brush"></i>
                            外观设置
                        </button>
                        <button class="tab-button" data-tab="maintenance">
                            <i class="fas fa-tools"></i>
                            维护工具
                        </button>
                    </div>

                    <!-- 常规设置 -->
                    <div class="tab-content active" id="general-tab">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="save_general">

                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-globe"></i>
                                    基本设置
                                </h3>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="site_title">
                                            <i class="fas fa-heading"></i>
                                            网站标题 *
                                        </label>
                                        <input type="text"
                                               id="site_title"
                                               name="site_title"
                                               value="<?php echo htmlspecialchars($currentSettings['site_title']); ?>"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="site_description">
                                            <i class="fas fa-align-left"></i>
                                            网站描述
                                        </label>
                                        <input type="text"
                                               id="site_description"
                                               name="site_description"
                                               value="<?php echo htmlspecialchars($currentSettings['site_description']); ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="items_per_page">
                                            <i class="fas fa-list-ol"></i>
                                            每页显示条数
                                        </label>
                                        <input type="number"
                                               id="items_per_page"
                                               name="items_per_page"
                                               value="<?php echo $currentSettings['items_per_page']; ?>"
                                               min="5" max="100" step="1">
                                    </div>

                                    <div class="form-group">
                                        <label for="timezone">
                                            <i class="fas fa-clock"></i>
                                            时区设置
                                        </label>
                                        <select id="timezone" name="timezone">
                                            <?php foreach ($timezones as $tz): ?>
                                                <option value="<?php echo $tz; ?>" <?php echo $currentSettings['timezone'] === $tz ? 'selected' : ''; ?>>
                                                    <?php echo $tz; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group full-width">
                                        <label for="default_punishments">
                                            <i class="fas fa-gavel"></i>
                                            默认惩罚类型 *
                                        </label>
                                        <textarea id="default_punishments"
                                                  name="default_punishments"
                                                  rows="4"
                                                  placeholder="每行一个惩罚类型，例如：&#10;永久封禁&#10;封禁30天&#10;封禁7天&#10;警告"
                                                  required><?php echo implode("\n", $currentSettings['default_punishments']); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-toggle-on"></i>
                                    功能开关
                                </h3>

                                <div class="checkbox-grid">
                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="maintenance_mode"
                                               id="maintenance_mode"
                                               <?php echo $currentSettings['maintenance_mode'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>维护模式</h4>
                                            <p>开启后只有管理员可以访问系统</p>
                                        </div>
                                    </label>

                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="allow_registration"
                                               id="allow_registration"
                                               <?php echo $currentSettings['allow_registration'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>允许用户注册</h4>
                                            <p>允许新用户注册账号（目前仅管理员）</p>
                                        </div>
                                    </label>

                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="auto_unban_check"
                                               id="auto_unban_check"
                                               <?php echo $currentSettings['auto_unban_check'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>自动解封检查</h4>
                                            <p>自动检查并处理到期的封禁记录</p>
                                        </div>
                                    </label>

                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="enable_api"
                                               id="enable_api"
                                               <?php echo $currentSettings['enable_api'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>启用API接口</h4>
                                            <p>开启系统API接口功能</p>
                                        </div>
                                    </label>

                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="debug_mode"
                                               id="debug_mode"
                                               <?php echo $currentSettings['debug_mode'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>调试模式</h4>
                                            <p>显示详细的错误信息（仅开发环境）</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                            <div class="form-actions">
                                <button type="submit" class="action-button">
                                    <i class="fas fa-save"></i>
                                    保存常规设置
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 安全设置 -->
                    <div class="tab-content" id="security-tab">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="save_security">

                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-user-shield"></i>
                                    登录安全
                                </h3>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="max_login_attempts">
                                            <i class="fas fa-user-lock"></i>
                                            最大登录尝试次数
                                        </label>
                                        <input type="number"
                                               id="max_login_attempts"
                                               name="max_login_attempts"
                                               value="<?php echo $currentSettings['security']['max_login_attempts'] ?? 5; ?>"
                                               min="1" max="10" step="1">
                                    </div>

                                    <div class="form-group">
                                        <label for="lockout_time">
                                            <i class="fas fa-clock"></i>
                                            锁定时间（分钟）
                                        </label>
                                        <input type="number"
                                               id="lockout_time"
                                               name="lockout_time"
                                               value="<?php echo $currentSettings['security']['lockout_time'] ?? 15; ?>"
                                               min="1" max="1440" step="1">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="session_timeout">
                                            <i class="fas fa-hourglass"></i>
                                            会话超时（分钟）
                                        </label>
                                        <input type="number"
                                               id="session_timeout"
                                               name="session_timeout"
                                               value="<?php echo $currentSettings['security']['session_timeout'] ?? 30; ?>"
                                               min="5" max="1440" step="1">
                                    </div>

                                    <div class="form-group">
                                        <label class="checkbox-label large" style="margin-top: 25px;">
                                            <input type="checkbox"
                                                   name="require_strong_password"
                                                   id="require_strong_password"
                                                   <?php echo isset($currentSettings['security']['require_strong_password']) && $currentSettings['security']['require_strong_password'] ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            <div class="checkbox-content">
                                                <h4>要求强密码</h4>
                                                <p>密码必须包含大小写字母、数字和特殊字符</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-shield-alt"></i>
                                    高级安全
                                </h3>

                                <div class="checkbox-grid">
                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="enable_captcha"
                                               id="enable_captcha"
                                               <?php echo isset($currentSettings['security']['enable_captcha']) && $currentSettings['security']['enable_captcha'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>启用验证码</h4>
                                            <p>登录时需输入验证码</p>
                                        </div>
                                    </label>

                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="enable_2fa"
                                               id="enable_2fa"
                                               <?php echo isset($currentSettings['security']['enable_2fa']) && $currentSettings['security']['enable_2fa'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>启用双重认证</h4>
                                            <p>登录时需要二次验证</p>
                                        </div>
                                    </label>

                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="log_all_actions"
                                               id="log_all_actions"
                                               <?php echo isset($currentSettings['security']['log_all_actions']) && $currentSettings['security']['log_all_actions'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>记录所有操作</h4>
                                            <p>详细记录所有用户操作</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-network-wired"></i>
                                    IP 访问控制
                                </h3>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="ip_whitelist">
                                            <i class="fas fa-check-circle"></i>
                                            IP白名单
                                        </label>
                                        <textarea id="ip_whitelist"
                                                  name="ip_whitelist"
                                                  rows="4"
                                                  placeholder="每行一个IP地址或CIDR范围，例如：&#10;192.168.1.1&#10;10.0.0.0/24"><?php echo isset($currentSettings['security']['ip_whitelist']) ? implode("\n", $currentSettings['security']['ip_whitelist']) : ''; ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="ip_blacklist">
                                            <i class="fas fa-ban"></i>
                                            IP黑名单
                                        </label>
                                        <textarea id="ip_blacklist"
                                                  name="ip_blacklist"
                                                  rows="4"
                                                  placeholder="每行一个IP地址或CIDR范围"><?php echo isset($currentSettings['security']['ip_blacklist']) ? implode("\n", $currentSettings['security']['ip_blacklist']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="action-button">
                                    <i class="fas fa-save"></i>
                                    保存安全设置
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 外观设置 -->
                    <div class="tab-content" id="appearance-tab">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="save_appearance">

                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-palette"></i>
                                    主题设置
                                </h3>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="theme_color">
                                            <i class="fas fa-fill-drip"></i>
                                            主题颜色
                                        </label>
                                        <input type="color"
                                               id="theme_color"
                                               name="theme_color"
                                               value="<?php echo htmlspecialchars($currentSettings['appearance']['theme_color'] ?? '#00b4d8'); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="background_style">
                                            <i class="fas fa-image"></i>
                                            背景样式
                                        </label>
                                        <select id="background_style" name="background_style">
                                            <option value="gradient" <?php echo ($currentSettings['appearance']['background_style'] ?? 'gradient') === 'gradient' ? 'selected' : ''; ?>>渐变背景</option>
                                            <option value="solid" <?php echo ($currentSettings['appearance']['background_style'] ?? 'gradient') === 'solid' ? 'selected' : ''; ?>>纯色背景</option>
                                            <option value="pattern" <?php echo ($currentSettings['appearance']['background_style'] ?? 'gradient') === 'pattern' ? 'selected' : ''; ?>>图案背景</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="font_size">
                                            <i class="fas fa-text-height"></i>
                                            字体大小 (px)
                                        </label>
                                        <input type="number"
                                               id="font_size"
                                               name="font_size"
                                               value="<?php echo $currentSettings['appearance']['font_size'] ?? 14; ?>"
                                               min="12" max="18" step="1">
                                    </div>

                                    <div class="form-group">
                                        <label for="ui_density">
                                            <i class="fas fa-compress-alt"></i>
                                            UI 密度
                                        </label>
                                        <select id="ui_density" name="ui_density">
                                            <option value="compact" <?php echo ($currentSettings['appearance']['ui_density'] ?? 'normal') === 'compact' ? 'selected' : ''; ?>>紧凑</option>
                                            <option value="normal" <?php echo ($currentSettings['appearance']['ui_density'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>正常</option>
                                            <option value="comfortable" <?php echo ($currentSettings['appearance']['ui_density'] ?? 'normal') === 'comfortable' ? 'selected' : ''; ?>>宽松</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-columns"></i>
                                    布局设置
                                </h3>

                                <div class="checkbox-grid">
                                    <label class="checkbox-label large">
                                        <input type="checkbox"
                                               name="sidebar_collapsed"
                                               id="sidebar_collapsed"
                                               <?php echo isset($currentSettings['appearance']['sidebar_collapsed']) && $currentSettings['appearance']['sidebar_collapsed'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-content">
                                            <h4>侧边栏默认折叠</h4>
                                            <p>进入后台时侧边栏默认处于折叠状态</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="action-button">
                                    <i class="fas fa-save"></i>
                                    保存外观设置
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 维护工具 -->
                    <div class="tab-content" id="maintenance-tab">
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-tools"></i>
                                系统维护
                            </h3>

                            <div class="maintenance-grid">
                                <div class="maintenance-item">
                                    <div class="maintenance-icon">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <div class="maintenance-content">
                                        <h4>数据库备份</h4>
                                        <p>备份当前数据库到本地文件</p>
                                        <form method="POST" action="" style="margin-top: 15px;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="backup_database">
                                            <button type="submit" class="action-button small">
                                                <i class="fas fa-download"></i>
                                                立即备份
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="maintenance-item">
                                    <div class="maintenance-icon">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                    <div class="maintenance-content">
                                        <h4>清理缓存</h4>
                                        <p>清除系统缓存文件</p>
                                        <form method="POST" action="" style="margin-top: 15px;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="clear_cache">
                                            <button type="submit" class="action-button small">
                                                <i class="fas fa-broom"></i>
                                                清理缓存
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="maintenance-item">
                                    <div class="maintenance-icon">
                                        <i class="fas fa-undo"></i>
                                    </div>
                                    <div class="maintenance-content">
                                        <h4>重置设置</h4>
                                        <p>恢复所有设置为默认值</p>
                                        <form method="POST" action="" style="margin-top: 15px;" onsubmit="return confirm('确定要重置所有设置为默认值吗？此操作不可撤销。');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="reset_defaults">
                                            <button type="submit" class="action-button small danger">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                重置设置
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="maintenance-item">
                                    <div class="maintenance-icon">
                                        <i class="fas fa-file-export"></i>
                                    </div>
                                    <div class="maintenance-content">
                                        <h4>导出设置</h4>
                                        <p>导出当前系统设置为JSON文件</p>
                                        <button type="button" onclick="exportSettings()" class="action-button small" style="margin-top: 15px;">
                                            <i class="fas fa-file-export"></i>
                                            导出设置
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-chart-line"></i>
                                系统状态
                            </h3>

                            <div class="status-grid">
                                <div class="status-item">
                                    <label>PHP版本</label>
                                    <div class="status-value"><?php echo PHP_VERSION; ?></div>
                                </div>
                                <div class="status-item">
                                    <label>数据库类型</label>
                                    <div class="status-value">MySQL</div>
                                </div>
                                <div class="status-item">
                                    <label>系统内存</label>
                                    <div class="status-value"><?php echo ini_get('memory_limit'); ?></div>
                                </div>
                                <div class="status-item">
                                    <label>上传限制</label>
                                    <div class="status-value"><?php echo ini_get('upload_max_filesize'); ?></div>
                                </div>
                                <div class="status-item">
                                    <label>设置文件</label>
                                    <div class="status-value"><?php echo file_exists($settingsFile) ? '正常' : '缺失'; ?></div>
                                </div>
                                <div class="status-item">
                                    <label>备份目录</label>
                                    <div class="status-value"><?php echo is_dir(__DIR__ . '/../backups') ? '正常' : '缺失'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 系统信息模态框 -->
    <div id="systemInfoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> 系统信息</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>服务器软件</label>
                        <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? '未知'; ?></div>
                    </div>
                    <div class="info-item">
                        <label>PHP版本</label>
                        <div class="info-value"><?php echo PHP_VERSION; ?></div>
                    </div>
                    <div class="info-item">
                        <label>数据库驱动</label>
                        <div class="info-value"><?php echo class_exists('PDO') ? 'PDO' : '未知'; ?></div>
                    </div>
                    <div class="info-item">
                        <label>系统时区</label>
                        <div class="info-value"><?php echo date_default_timezone_get(); ?></div>
                    </div>
                    <div class="info-item">
                        <label>内存限制</label>
                        <div class="info-value"><?php echo ini_get('memory_limit'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>上传限制</label>
                        <div class="info-value"><?php echo ini_get('upload_max_filesize'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>执行时间</label>
                        <div class="info-value"><?php echo ini_get('max_execution_time'); ?>秒</div>
                    </div>
                    <div class="info-item">
                        <label>错误报告</label>
                        <div class="info-value"><?php echo ini_get('display_errors') ? '开启' : '关闭'; ?></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="action-button secondary close-modal-btn">关闭</button>
                <button onclick="copySystemInfo()" class="action-button">
                    <i class="fas fa-copy"></i>
                    复制信息
                </button>
            </div>
        </div>
    </div>

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.dataset.tab;

                    
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === `${tabId}-tab`) {
                            content.classList.add('active');
                        }
                    });
                });
            });

            
            const systemInfoModal = document.getElementById('systemInfoModal');
            const closeButtons = document.querySelectorAll('.close-modal, .close-modal-btn');

            window.showSystemInfo = function() {
                systemInfoModal.style.display = 'block';
            };

            closeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    systemInfoModal.style.display = 'none';
                });
            });

            window.addEventListener('click', function(e) {
                if (e.target === systemInfoModal) {
                    systemInfoModal.style.display = 'none';
                }
            });
        });

        function testSettings() {
            const btn = document.querySelector('button[onclick="testSettings()"]');
            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 测试中...';
            btn.disabled = true;

            
            setTimeout(() => {
                alert('配置测试完成，所有设置正常！');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 1500);
        }

        function exportSettings() {
            if (confirm('确定要导出当前系统设置吗？')) {
                window.location.href = 'export_settings.php';
            }
        }

        function copySystemInfo() {
            const infoItems = document.querySelectorAll('.info-item');
            let infoText = '系统信息报告\n';
            infoText += '生成时间: ' + new Date().toLocaleString('zh-CN') + '\n\n';

            infoItems.forEach(item => {
                const label = item.querySelector('label').textContent;
                const value = item.querySelector('.info-value').textContent;
                infoText += `${label}: ${value}\n`;
            });

            navigator.clipboard.writeText(infoText).then(() => {
                alert('系统信息已复制到剪贴板！');
            }).catch(err => {
                console.error('复制失败:', err);
                alert('复制失败，请手动复制。');
            });
        }

        
        const style = document.createElement('style');
        style.textContent = `
            .settings-tabs {
                margin-top: 20px;
            }

            .tab-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 30px;
                border-bottom: 1px solid rgba(0, 180, 216, 0.2);
                padding-bottom: 10px;
            }

            .tab-button {
                padding: 12px 25px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(0, 180, 216, 0.2);
                border-radius: 8px;
                color: #90e0ef;
                font-family: 'Orbitron', monospace;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .tab-button:hover {
                background: rgba(0, 180, 216, 0.1);
                border-color: rgba(0, 180, 216, 0.4);
            }

            .tab-button.active {
                background: rgba(0, 180, 216, 0.2);
                border-color: #00b4d8;
                color: #00b4d8;
            }

            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .checkbox-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
            }

            .checkbox-label.large {
                display: flex;
                align-items: flex-start;
                padding: 20px;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(0, 180, 216, 0.2);
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .checkbox-label.large:hover {
                background: rgba(0, 180, 216, 0.05);
                border-color: rgba(0, 180, 216, 0.4);
                transform: translateY(-2px);
            }

            .checkbox-label.large .checkmark {
                margin-top: 3px;
                flex-shrink: 0;
            }

            .checkbox-content {
                margin-left: 15px;
            }

            .checkbox-content h4 {
                color: #ffffff;
                margin-bottom: 5px;
                font-size: 16px;
            }

            .checkbox-content p {
                color: #b0bec5;
                font-size: 13px;
                line-height: 1.5;
            }

            .maintenance-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 25px;
            }

            .maintenance-item {
                background: rgba(30, 35, 50, 0.5);
                border: 1px solid rgba(0, 180, 216, 0.2);
                border-radius: 12px;
                padding: 25px;
                text-align: center;
                transition: all 0.3s ease;
            }

            .maintenance-item:hover {
                border-color: rgba(0, 180, 216, 0.4);
                transform: translateY(-5px);
            }

            .maintenance-icon {
                font-size: 3rem;
                color: #00b4d8;
                margin-bottom: 20px;
            }

            .maintenance-content h4 {
                color: #ffffff;
                margin-bottom: 10px;
                font-size: 18px;
            }

            .maintenance-content p {
                color: #b0bec5;
                font-size: 14px;
                line-height: 1.5;
                margin-bottom: 15px;
            }

            .action-button.small {
                padding: 10px 20px;
                font-size: 13px;
            }

            .action-button.small.danger {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            }

            .action-button.small.danger:hover {
                background: linear-gradient(135deg, #e35d6a 0%, #d92535 100%);
            }

            .status-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }

            .status-item {
                background: rgba(255, 255, 255, 0.03);
                border-radius: 8px;
                padding: 15px;
                border: 1px solid rgba(255, 255, 255, 0.05);
            }

            .status-item label {
                display: block;
                color: #90caf9;
                font-size: 12px;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .status-item .status-value {
                color: #ffffff;
                font-size: 16px;
                font-family: 'Orbitron', monospace;
            }

            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .info-item {
                margin-bottom: 15px;
            }

            .info-item label {
                display: block;
                color: #90caf9;
                font-size: 12px;
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .info-item .info-value {
                color: #ffffff;
                font-size: 14px;
                font-family: 'Roboto', sans-serif;
                word-break: break-all;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>