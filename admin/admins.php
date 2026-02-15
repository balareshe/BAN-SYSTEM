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


$pdo = getDBConnection();


createAdminsTable($pdo);


$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_admin':
            
            $newUsername = trim($_POST['new_username'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $level = intval($_POST['level'] ?? 1);

            
            if (empty($newUsername) || empty($newPassword)) {
                $errors[] = '用户名和密码不能为空';
            } elseif (strlen($newUsername) < 3) {
                $errors[] = '用户名至少3个字符';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = '密码长度至少6位';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = '两次输入的密码不一致';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
                $errors[] = '邮箱格式不正确';
            } else {
                
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                $stmt->execute([$newUsername]);
                if ($stmt->fetch()) {
                    $errors[] = '用户名已存在';
                } else {
                    
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, email, level) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$newUsername, $passwordHash, $email, $level])) {
                        logAdminAction('ADD_ADMIN', "添加管理员: {$newUsername}");
                        $success = true;
                    } else {
                        $errors[] = '添加管理员失败，请重试';
                    }
                }
            }
            break;

        case 'change_password':
            
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $currentAdmin = $_SESSION['admin_username'];

            
            $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE username = ?");
            $stmt->execute([$currentAdmin]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
                $errors[] = '当前密码错误';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = '新密码长度至少6位';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = '两次输入的新密码不一致';
            } else {
                
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = ?");
                if ($stmt->execute([$passwordHash, $currentAdmin])) {
                    logAdminAction('CHANGE_PASSWORD', '修改管理员密码');
                    $success = true;
                } else {
                    $errors[] = '修改密码失败，请重试';
                }
            }
            break;

        case 'delete_admin':
            
            $adminId = intval($_POST['admin_id'] ?? 0);
            $currentAdmin = $_SESSION['admin_username'];

            if ($adminId <= 0) {
                $errors[] = '无效的管理员ID';
            } else {
                
                $stmt = $pdo->prepare("SELECT username, level FROM admins WHERE id = ?");
                $stmt->execute([$adminId]);
                $adminToDelete = $stmt->fetch();

                if (!$adminToDelete) {
                    $errors[] = '管理员不存在';
                } elseif ($adminToDelete['username'] === $currentAdmin) {
                    $errors[] = '不能删除自己的账户';
                } elseif ($adminToDelete['level'] <= 1) {
                    $errors[] = '不能删除超级管理员账户';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                    if ($stmt->execute([$adminId])) {
                        logAdminAction('DELETE_ADMIN', "删除管理员: {$adminToDelete['username']}");
                        $success = true;
                    } else {
                        $errors[] = '删除管理员失败，请重试';
                    }
                }
            }
            break;

        case 'update_admin':
            
            $adminId = intval($_POST['admin_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $level = intval($_POST['level'] ?? 1);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($adminId <= 0) {
                $errors[] = '无效的管理员ID';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
                $errors[] = '邮箱格式不正确';
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET email = ?, level = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$email, $level, $isActive, $adminId])) {
                    logAdminAction('UPDATE_ADMIN', "更新管理员信息 ID: {$adminId}");
                    $success = true;
                } else {
                    $errors[] = '更新管理员信息失败，请重试';
                }
            }
            break;
    }
}


$currentAdmin = $_SESSION['admin_username'] ?? '未知';
$adminLevel = $_SESSION['admin_level'] ?? 1;
$loginTime = $_SESSION['login_time'] ?? '未知';


$stmt = $pdo->query("SELECT id, username, email, level, created_at, last_login, is_active FROM admins ORDER BY level, username");
$allAdmins = $stmt->fetchAll();


function createAdminsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        level INT DEFAULT 1 COMMENT '1=超级管理员, 2=普通管理员',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        is_active BOOLEAN DEFAULT TRUE
    )";

    try {
        $pdo->exec($sql);

        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
        if ($stmt->fetchColumn() == 0) {
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO admins (username, password_hash, level) VALUES ('admin', '{$defaultPassword}', 1)");
        }
    } catch (PDOException $e) {
        
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员管理 - 后台管理</title>
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
                    <span><?php echo $adminLevel == 1 ? '超级管理员' : '普通管理员'; ?></span>
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
                    <span>搜索玩家</span>
                </a>
                                <a href="admins.php" class="nav-item active">
                    <i class="fas fa-users-cog"></i>
                    <span>管理员</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>系统设置</span>
                </a>
                <a href="logs.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>操作日志</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <p>管理员管理</p>
                <p>权限控制</p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>管理员管理</h1>
                    <p>管理系统管理员账户和权限</p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <button onclick="showAdminStats()" class="action-button secondary">
                            <i class="fas fa-chart-bar"></i>
                            管理统计
                        </button>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        操作成功！
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>操作失败：</strong>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-circle"></i>
                        当前管理员信息
                    </h3>

                    <div class="admin-info-grid">
                        <div class="admin-info-item">
                            <label>用户名</label>
                            <div class="admin-info-value"><?php echo htmlspecialchars($currentAdmin); ?></div>
                        </div>
                        <div class="admin-info-item">
                            <label>权限级别</label>
                            <div class="admin-info-value"><?php echo $adminLevel == 1 ? '超级管理员' : '普通管理员'; ?></div>
                        </div>
                        <div class="admin-info-item">
                            <label>登录时间</label>
                            <div class="admin-info-value"><?php echo htmlspecialchars($loginTime); ?></div>
                        </div>
                        <div class="admin-info-item">
                            <label>当前IP</label>
                            <div class="admin-info-value"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '未知'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-key"></i>
                        修改密码
                    </h3>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password">
                                    <i class="fas fa-lock"></i>
                                    当前密码
                                </label>
                                <input type="password"
                                       id="current_password"
                                       name="current_password"
                                       placeholder="输入当前密码"
                                       minlength="6"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">
                                    <i class="fas fa-lock"></i>
                                    新密码
                                </label>
                                <input type="password"
                                       id="new_password"
                                       name="new_password"
                                       placeholder="输入新密码"
                                       minlength="6"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">
                                    <i class="fas fa-lock"></i>
                                    确认新密码
                                </label>
                                <input type="password"
                                       id="confirm_password"
                                       name="confirm_password"
                                       placeholder="再次输入新密码"
                                       minlength="6"
                                       required>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="action-button">
                                <i class="fas fa-save"></i>
                                修改密码
                            </button>
                        </div>
                    </form>
                </div>

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        function showAdminStats() {
            alert('其他功能开发中...');
        }

        function editAdmin(adminId) {
            
            
            document.getElementById('edit_admin_id').value = adminId;
            document.getElementById('editAdminModal').style.display = 'block';
        }

        function deleteAdmin(adminId, username) {
            if (confirm(`确定要删除管理员 "${username}" 吗？此操作不可撤销。`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = 'csrf_token';
                csrfToken.value = '<?php echo $csrfToken; ?>';
                form.appendChild(csrfToken);

                const action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'action';
                action.value = 'delete_admin';
                form.appendChild(action);

                const adminIdInput = document.createElement('input');
                adminIdInput.type = 'hidden';
                adminIdInput.name = 'admin_id';
                adminIdInput.value = adminId;
                form.appendChild(adminIdInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = document.getElementById('editAdminModal');
            const closeButtons = document.querySelectorAll('.close-modal, .close-modal-btn');

            closeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    editModal.style.display = 'none';
                });
            });

            window.addEventListener('click', function(e) {
                if (e.target === editModal) {
                    editModal.style.display = 'none';
                }
            });

            
            const addForm = document.querySelector('form[action*="add_admin"]');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('new_password_add').value;
                    const confirmPassword = document.getElementById('confirm_password_add').value;

                    if (newPassword !== confirmPassword) {
                        alert('两次输入的密码不一致！');
                        e.preventDefault();
                        return false;
                    }
                });
            }

            const changePasswordForm = document.querySelector('form[action*="change_password"]');
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (newPassword !== confirmPassword) {
                        alert('两次输入的新密码不一致！');
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    </script>

    <style>
        .admin-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .admin-info-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            padding: 20px;
            border: 1px solid rgba(0, 180, 216, 0.2);
        }

        .admin-info-item label {
            display: block;
            color: #90caf9;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .admin-info-item .admin-info-value {
            color: #ffffff;
            font-size: 18px;
            font-family: 'Orbitron', monospace;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.current-user {
            background: rgba(0, 180, 216, 0.2);
            color: #00b4d8;
            border: 1px solid rgba(0, 180, 216, 0.4);
            margin-left: 8px;
        }

        .badge-superadmin {
            background: rgba(155, 81, 224, 0.2);
            color: #c8a8ff;
            border: 1px solid rgba(155, 81, 224, 0.4);
        }

        .badge-admin {
            background: rgba(0, 180, 216, 0.2);
            color: #90e0ef;
            border: 1px solid rgba(0, 180, 216, 0.4);
        }

        .badge-active {
            background: rgba(40, 167, 69, 0.2);
            color: #6bff8d;
            border: 1px solid #28a745;
        }

        .badge-inactive {
            background: rgba(108, 117, 125, 0.2);
            color: #b0bec5;
            border: 1px solid #6c757d;
        }

        .action-icon.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</body>
</html>