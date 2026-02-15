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


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: bans.php');
    exit;
}

$ban_id = intval($_GET['id']);


$csrfToken = generateCsrfToken();


$pdo = getDBConnection();


$stmt = $pdo->prepare("SELECT * FROM bans WHERE id = ?");
$stmt->execute([$ban_id]);
$ban = $stmt->fetch();

if (!$ban) {
    header('Location: bans.php');
    exit;
}


$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    validateCsrfToken();

    
    $username = trim($_POST['username'] ?? '');
    $ban_time = trim($_POST['ban_time'] ?? '');
    $unban_time = trim($_POST['unban_time'] ?? '');
    $punishment = trim($_POST['punishment'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    
    if (empty($username)) {
        $errors[] = '玩家用户名不能为空';
    } elseif (strlen($username) > 50) {
        $errors[] = '玩家用户名不能超过50个字符';
    }

    if (empty($ban_time)) {
        $errors[] = '封禁时间不能为空';
    } else {
        
        $ban_timestamp = strtotime($ban_time);
        if ($ban_timestamp === false) {
            $errors[] = '封禁时间格式无效';
        }
    }

    if (!empty($unban_time)) {
        $unban_timestamp = strtotime($unban_time);
        if ($unban_timestamp === false) {
            $errors[] = '解封时间格式无效';
        } elseif ($ban_timestamp && $unban_timestamp <= $ban_timestamp) {
            $errors[] = '解封时间必须晚于封禁时间';
        }
    } else {
        
        $unban_time = null;
    }

    if (empty($punishment)) {
        $errors[] = '违规惩罚不能为空';
    } elseif (strlen($punishment) > 100) {
        $errors[] = '违规惩罚不能超过100个字符';
    }

    if (empty($reason)) {
        $errors[] = '违规行为描述不能为空';
    }

    
    if (empty($errors)) {
        try {
            
            $sql = "UPDATE bans SET
                    username = ?,
                    ban_time = ?,
                    unban_time = ?,
                    punishment = ?,
                    reason = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);

            
            $stmt->execute([
                $username,
                date('Y-m-d H:i:s', $ban_timestamp),
                $unban_time ? date('Y-m-d H:i:s', $unban_timestamp) : null,
                $punishment,
                $reason,
                $ban_id
            ]);

            
            logAdminAction('EDIT_BAN', "编辑封禁记录 #{$ban_id}: {$username} - {$punishment}");

            
            $stmt = $pdo->prepare("SELECT * FROM bans WHERE id = ?");
            $stmt->execute([$ban_id]);
            $ban = $stmt->fetch();

            
            $success = true;

        } catch (PDOException $e) {
            $errors[] = '更新数据时出错: ' . $e->getMessage();
        }
    }
}


$ban_time_formatted = $ban['ban_time'] ? date('Y-m-d\TH:i', strtotime($ban['ban_time'])) : '';
$unban_time_formatted = $ban['unban_time'] && $ban['unban_time'] != '0000-00-00 00:00:00'
    ? date('Y-m-d\TH:i', strtotime($ban['unban_time']))
    : '';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑封禁记录 - 后台管理</title>
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
                <a href="edit.php?id=<?php echo $ban_id; ?>" class="nav-item active">
                    <i class="fas fa-edit"></i>
                    <span>编辑记录</span>
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fas fa-search"></i>
                    <span>搜索玩家</span>
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
                <p>记录ID: #<?php echo $ban_id; ?></p>
                <p>创建于: <?php echo formatDateTime($ban['created_at']); ?></p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>编辑封禁记录</h1>
                    <p>记录ID: #<?php echo $ban_id; ?> | 玩家: <?php echo htmlspecialchars($ban['username']); ?></p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <a href="bans.php" class="action-button secondary">
                            <i class="fas fa-arrow-left"></i>
                            返回列表
                        </a>
                        <a href="delete.php?id=<?php echo $ban_id; ?>" class="action-button danger" onclick="return confirm('确定要删除这条记录吗？');">
                            <i class="fas fa-trash"></i>
                            删除记录
                        </a>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <div class="form-container">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            封禁记录更新成功！
                            <a href="bans.php" style="margin-left: 15px; color: #6bff8d; text-decoration: underline;">返回列表</a>
                            <a href="edit.php?id=<?php echo $ban_id; ?>" style="margin-left: 10px; color: #90e0ef; text-decoration: underline;">继续编辑</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>发现以下错误：</strong>
                            <ul style="margin-top: 10px; margin-left: 20px;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-edit"></i>
                            编辑封禁信息
                        </h3>

                        <form method="POST" action="" id="banForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">
                                        <i class="fas fa-user"></i>
                                        玩家用户名 *
                                    </label>
                                    <input type="text"
                                           id="username"
                                           name="username"
                                           placeholder="输入玩家游戏ID"
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? $ban['username']); ?>"
                                           required
                                           maxlength="50"
                                           autofocus>
                                    <div class="form-help">最大50个字符</div>
                                </div>

                                <div class="form-group">
                                    <label for="punishment">
                                        <i class="fas fa-gavel"></i>
                                        违规惩罚 *
                                    </label>
                                    <select id="punishment" name="punishment" required>
                                        <option value="">请选择惩罚类型</option>
                                        <option value="永久封禁" <?php echo ((isset($_POST['punishment']) ? $_POST['punishment'] : $ban['punishment']) === '永久封禁') ? 'selected' : ''; ?>>永久封禁</option>
                                        <option value="封禁30天" <?php echo ((isset($_POST['punishment']) ? $_POST['punishment'] : $ban['punishment']) === '封禁30天') ? 'selected' : ''; ?>>封禁30天</option>
                                        <option value="封禁7天" <?php echo ((isset($_POST['punishment']) ? $_POST['punishment'] : $ban['punishment']) === '封禁7天') ? 'selected' : ''; ?>>封禁7天</option>
                                        <option value="封禁3天" <?php echo ((isset($_POST['punishment']) ? $_POST['punishment'] : $ban['punishment']) === '封禁3天') ? 'selected' : ''; ?>>封禁3天</option>
                                        <option value="封禁1天" <?php echo ((isset($_POST['punishment']) ? $_POST['punishment'] : $ban['punishment']) === '封禁1天') ? 'selected' : ''; ?>>封禁1天</option>
                                        <option value="警告" <?php echo ((isset($_POST['punishment']) ? $_POST['punishment'] : $ban['punishment']) === '警告') ? 'selected' : ''; ?>>警告</option>
                                        <option value="其他" <?php echo (!in_array((isset($_POST['punishment']) ? $_POST['punishment'] : $ban['punishment']), ['永久封禁', '封禁30天', '封禁7天', '封禁3天', '封禁1天', '警告'])) ? 'selected' : ''; ?>>其他</option>
                                    </select>
                                    <div class="form-help">选择或自定义惩罚类型</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ban_time">
                                        <i class="fas fa-clock"></i>
                                        封禁时间 *
                                    </label>
                                    <input type="datetime-local"
                                           id="ban_time"
                                           name="ban_time"
                                           value="<?php echo htmlspecialchars($_POST['ban_time'] ?? $ban_time_formatted); ?>"
                                           required>
                                    <div class="form-help">封禁生效的时间</div>
                                </div>

                                <div class="form-group">
                                    <label for="unban_time">
                                        <i class="fas fa-calendar-check"></i>
                                        解封时间
                                    </label>
                                    <input type="datetime-local"
                                           id="unban_time"
                                           name="unban_time"
                                           value="<?php echo htmlspecialchars($_POST['unban_time'] ?? $unban_time_formatted); ?>">
                                    <div class="form-help">留空表示永久封禁</div>
                                    <div class="form-options">
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="permanent_ban" <?php echo empty($unban_time_formatted) ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            永久封禁
                                        </label>
                                        <button type="button" id="calc_unban" class="cyber-button small">
                                            <i class="fas fa-calculator"></i>
                                            计算解封时间
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="reason">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        违规行为描述 *
                                    </label>
                                    <textarea id="reason"
                                              name="reason"
                                              placeholder="详细描述玩家的违规行为..."
                                              rows="6"
                                              required><?php echo htmlspecialchars($_POST['reason'] ?? $ban['reason']); ?></textarea>
                                    <div class="form-help">请详细描述违规行为，这将显示在前台公示页面</div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="action-button secondary" onclick="window.location.href='bans.php'">
                                    <i class="fas fa-times"></i>
                                    取消编辑
                                </button>
                                <button type="reset" class="action-button secondary">
                                    <i class="fas fa-redo"></i>
                                    重置修改
                                </button>
                                <button type="submit" class="action-button">
                                    <i class="fas fa-save"></i>
                                    保存修改
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 记录信息 -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            记录信息
                        </h3>
                        <div class="record-info">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>记录ID</label>
                                    <div class="info-value">#<?php echo $ban_id; ?></div>
                                </div>
                                <div class="info-item">
                                    <label>创建时间</label>
                                    <div class="info-value"><?php echo formatDateTime($ban['created_at']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>最后修改</label>
                                    <div class="info-value"><?php echo formatDateTime($ban['created_at']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>数据库大小</label>
                                    <div class="info-value"><?php echo strlen(serialize($ban)); ?> 字节</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('banForm');
            const permanentCheckbox = document.getElementById('permanent_ban');
            const unbanTimeInput = document.getElementById('unban_time');
            const calcButton = document.getElementById('calc_unban');
            const punishmentSelect = document.getElementById('punishment');

            
            permanentCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    unbanTimeInput.value = '';
                    unbanTimeInput.disabled = true;
                } else {
                    unbanTimeInput.disabled = false;
                    if (!unbanTimeInput.value) {
                        
                        const now = new Date();
                        now.setDate(now.getDate() + 7);
                        unbanTimeInput.value = now.toISOString().slice(0, 16);
                    }
                }
            });

            
            if (permanentCheckbox.checked) {
                unbanTimeInput.disabled = true;
            }

            
            calcButton.addEventListener('click', function() {
                const banTime = document.getElementById('ban_time').value;
                if (!banTime) {
                    alert('请先设置封禁时间');
                    return;
                }

                const punishment = punishmentSelect.value;
                let days = 7; 

                
                if (punishment.includes('30')) days = 30;
                else if (punishment.includes('7')) days = 7;
                else if (punishment.includes('3')) days = 3;
                else if (punishment.includes('1')) days = 1;
                else if (punishment === '永久封禁' || punishment === '警告') {
                    alert('该惩罚类型无需计算解封时间');
                    return;
                }

                
                const banDate = new Date(banTime);
                banDate.setDate(banDate.getDate() + days);

                
                unbanTimeInput.value = banDate.toISOString().slice(0, 16);
                permanentCheckbox.checked = false;
                unbanTimeInput.disabled = false;
            });

            
            punishmentSelect.addEventListener('change', function() {
                if (this.value === '永久封禁') {
                    permanentCheckbox.checked = true;
                    unbanTimeInput.value = '';
                    unbanTimeInput.disabled = true;
                } else if (this.value === '警告') {
                    permanentCheckbox.checked = false;
                    unbanTimeInput.value = '';
                    unbanTimeInput.disabled = true;
                } else {
                    permanentCheckbox.checked = false;
                    unbanTimeInput.disabled = false;
                }
            });

            
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const reason = document.getElementById('reason').value.trim();

                if (!username) {
                    e.preventDefault();
                    alert('请输入玩家用户名');
                    document.getElementById('username').focus();
                    return false;
                }

                if (!reason) {
                    e.preventDefault();
                    alert('请输入违规行为描述');
                    document.getElementById('reason').focus();
                    return false;
                }

                
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
                submitBtn.disabled = true;

                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });

            
            punishmentSelect.addEventListener('change', function() {
                if (this.value === '其他') {
                    const customInput = document.createElement('input');
                    customInput.type = 'text';
                    customInput.id = 'custom_punishment';
                    customInput.name = 'punishment';
                    customInput.placeholder = '输入自定义惩罚类型';
                    customInput.value = '<?php echo htmlspecialchars((isset($_POST['punishment']) && !in_array($_POST['punishment'], ['永久封禁', '封禁30天', '封禁7天', '封禁3天', '封禁1天', '警告'])) ? $_POST['punishment'] : $ban['punishment']); ?>';
                    customInput.required = true;
                    customInput.maxLength = 100;
                    customInput.style.marginTop = '10px';
                    customInput.style.width = '100%';
                    customInput.style.padding = '12px 15px';

                    const container = this.parentElement;
                    if (!document.getElementById('custom_punishment')) {
                        container.appendChild(customInput);
                    }
                    this.style.display = 'none';
                } else {
                    const customInput = document.getElementById('custom_punishment');
                    if (customInput) {
                        customInput.remove();
                    }
                    this.style.display = 'block';
                }
            });

            
            const currentPunishment = punishmentSelect.value;
            if (currentPunishment === '其他') {
                punishmentSelect.dispatchEvent(new Event('change'));
            }
        });

        
        const style = document.createElement('style');
        style.textContent = `
            .form-help {
                font-size: 12px;
                color: #888;
                margin-top: 5px;
            }

            .full-width {
                grid-column: 1 / -1;
            }

            .record-info {
                margin-top: 20px;
            }

            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }

            .info-item {
                background: rgba(30, 35, 50, 0.5);
                border: 1px solid rgba(0, 180, 216, 0.2);
                border-radius: 8px;
                padding: 15px;
            }

            .info-item label {
                display: block;
                color: #90caf9;
                font-size: 12px;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .info-item .info-value {
                color: #ffffff;
                font-size: 16px;
                font-family: 'Orbitron', monospace;
            }

            .form-options {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 15px;
                flex-wrap: wrap;
                gap: 10px;
            }

            .cyber-button.small {
                padding: 8px 15px;
                font-size: 12px;
                margin-top: 10px;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>