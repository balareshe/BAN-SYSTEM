<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */


// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


requireAdminLogin();


$pdo = getDBConnection();


$success = false;
$error = '';
$redirect_url = 'bans.php';


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {

    $ban_id = intval($_GET['id']);


    try {
        $stmt = $pdo->prepare("SELECT * FROM bans WHERE id = ?");
        $stmt->execute([$ban_id]);
        $ban = $stmt->fetch();

        if (!$ban) {
            header('Location: bans.php');
            exit;
        }


        displayConfirmationPage($ban);
        exit;
    } catch (PDOException $e) {
        $error = '数据库查询失败: ' . $e->getMessage();
        displayResultPage(false, $error, '', 'bans.php');
        exit;
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        validateCsrfToken();


        if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
            $ban_id = intval($_POST['id'] ?? 0);

            if ($ban_id > 0) {
                try {

                    $stmt = $pdo->prepare("SELECT username, punishment FROM bans WHERE id = ?");
                    $stmt->execute([$ban_id]);
                    $ban_info = $stmt->fetch();

                    if (!$ban_info) {
                        $error = '记录不存在: ID ' . $ban_id;
                        displayResultPage(false, $error, '', $redirect_url);
                        exit;
                    }


                    $stmt = $pdo->prepare("DELETE FROM bans WHERE id = ?");
                    $stmt->execute([$ban_id]);
                    $affected = $stmt->rowCount();

                    if ($affected > 0) {

                        if ($ban_info) {
                            logAdminAction('DELETE_BAN', "删除封禁记录 #{$ban_id}: {$ban_info['username']} - {$ban_info['punishment']}");
                        }

                        $success = true;
                        $message = '记录删除成功';
                    } else {
                        $error = '删除操作未影响任何行，可能记录已被删除';
                    }

                } catch (PDOException $e) {
                    $error = '删除失败: ' . $e->getMessage();
                    error_log("删除错误: " . $e->getMessage() . " (代码: " . $e->getCode() . ")");
                }
            } else {
                $error = '无效的记录ID';
            }
        } else {

            header('Location: bans.php');
            exit;
        }


        if (isset($_POST['redirect'])) {
            $redirect_url = $_POST['redirect'];
        }


        displayResultPage($success, $error, $message ?? '', $redirect_url);
        exit;
    } catch (Exception $e) {
        $error = '操作失败: ' . $e->getMessage();
        displayResultPage(false, $error, '', $redirect_url);
        exit;
    }

}


header('Location: bans.php');
exit;


function displayConfirmationPage($ban) {
    $ban_id = $ban['id'];
    $username = htmlspecialchars($ban['username']);
    $punishment = htmlspecialchars($ban['punishment']);
    $reason = htmlspecialchars(mb_substr($ban['reason'], 0, 100) . (mb_strlen($ban['reason']) > 100 ? '...' : ''));
    $ban_time = formatDateTime($ban['ban_time']);
    $unban_time = formatDateTime($ban['unban_time']);


    $csrfToken = generateCsrfToken();
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>确认删除 - 后台管理</title>
        <link rel="stylesheet" href="../assets/css/admin.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
        <style>
            .confirmation-container {
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                width: 100%;
                box-sizing: border-box;
            }

            .confirmation-card {
                background: rgba(20, 25, 40, 0.95);
                border: 1px solid rgba(220, 53, 69, 0.4);
                border-radius: 15px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                width: 100%;
            }

            .warning-icon {
                font-size: 4rem;
                color: #ff6b6b;
                margin-bottom: 30px;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }

            .ban-details {
                background: rgba(255, 255, 255, 0.05);
                border-radius: 10px;
                padding: 25px;
                margin: 30px 0;
                text-align: left;
                width: 100%;
            }

            .detail-row {
                display: flex;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                width: 100%;
            }

            .detail-row:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }

            .detail-label {
                width: 140px;
                color: #90e0ef;
                font-weight: 500;
                flex-shrink: 0;
            }

            .detail-value {
                flex: 1;
                color: #ffffff;
                word-break: break-word;
            }

            .confirmation-actions {
                display: flex;
                justify-content: center;
                gap: 25px;
                margin-top: 35px;
            }

            .btn-cancel {
                background: rgba(108, 117, 125, 0.2);
                border: 1px solid #6c757d;
                color: #b0bec5;
                padding: 12px 24px;
                font-size: 16px;
                border-radius: 8px;
                transition: all 0.3s ease;
            }

            .btn-cancel:hover {
                background: rgba(108, 117, 125, 0.4);
                transform: translateY(-2px);
            }

            .btn-delete {
                background: rgba(220, 53, 69, 0.2);
                border: 1px solid #dc3545;
                color: #ff6b6b;
                padding: 12px 24px;
                font-size: 16px;
                border-radius: 8px;
                transition: all 0.3s ease;
            }

            .btn-delete:hover {
                background: rgba(220, 53, 69, 0.4);
                transform: translateY(-2px);
            }

            .info-text {
                color: #888;
                font-size: 14px;
                margin-top: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .back-link {
                color: #90e0ef;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-top: 25px;
                font-size: 16px;
                transition: color 0.3s ease;
            }

            .back-link:hover {
                color: #ffffff;
            }

            @media (max-width: 768px) {
                .confirmation-container {
                    margin: 20px auto;
                    padding: 15px;
                }

                .confirmation-card {
                    padding: 25px;
                }

                .detail-label {
                    width: 100px;
                }

                .confirmation-actions {
                    flex-direction: column;
                    gap: 15px;
                }

                .btn-cancel, .btn-delete {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
    </head>
    <body class="login-page">
        <div class="confirmation-container">
            <div class="confirmation-card">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>

                <h2 style="color: #ff6b6b; margin-bottom: 15px; font-size: 24px;">确认删除</h2>
                <p style="color: #b0bec5; margin-bottom: 30px; font-size: 16px;">您即将删除一条封禁记录，此操作不可撤销。</p>

                <div class="ban-details">
                    <div class="detail-row">
                        <div class="detail-label">记录ID</div>
                        <div class="detail-value">#<?php echo $ban_id; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">玩家用户名</div>
                        <div class="detail-value"><?php echo $username; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">封禁时间</div>
                        <div class="detail-value"><?php echo $ban_time; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">解封时间</div>
                        <div class="detail-value"><?php echo $unban_time; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">违规惩罚</div>
                        <div class="detail-value"><?php echo $punishment; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">违规原因</div>
                        <div class="detail-value"><?php echo $reason; ?></div>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="id" value="<?php echo $ban_id; ?>">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'bans.php'); ?>">

                    <div class="confirmation-actions">
                        <button type="submit" name="confirm" value="no" class="action-button btn-cancel">
                            <i class="fas fa-times"></i>
                            取消删除
                        </button>
                        <button type="submit" name="confirm" value="yes" class="action-button btn-delete">
                            <i class="fas fa-trash"></i>
                            确认删除
                        </button>
                    </div>

                    <p class="info-text">
                        <i class="fas fa-info-circle"></i>
                        删除后，该记录将无法恢复。请谨慎操作。
                    </p>
                </form>

                <div>
                    <a href="bans.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> 返回封禁记录列表
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}


function displayResultPage($success, $error, $message, $redirect_url) {
    $icon = $success ? 'check-circle' : 'exclamation-circle';
    $title = $success ? '删除成功' : '删除失败';
    $color = $success ? '#6bff8d' : '#ff6b6b';
    $auto_redirect = $success ? 3 : 5;
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $title; ?> - 后台管理</title>
        <link rel="stylesheet" href="../assets/css/admin.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
        <style>
            .result-container {
                max-width: 600px;
                margin: 50px auto;
                padding: 30px;
                width: 100%;
                box-sizing: border-box;
            }

            .result-card {
                background: rgba(20, 25, 40, 0.95);
                border: 1px solid <?php echo $color; ?>40;
                border-radius: 15px;
                padding: 50px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                width: 100%;
            }

            .result-icon {
                font-size: 5rem;
                color: <?php echo $color; ?>;
                margin-bottom: 35px;
                animation: fadeIn 0.5s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: scale(0.8); }
                to { opacity: 1; transform: scale(1); }
            }

            .result-title {
                color: <?php echo $color; ?>;
                font-size: 28px;
                margin-bottom: 25px;
                font-weight: 600;
            }

            .result-message {
                color: #ffffff;
                font-size: 18px;
                margin-bottom: 35px;
                line-height: 1.6;
            }

            .result-error {
                color: #ff6b6b;
                font-size: 18px;
                margin-bottom: 35px;
                line-height: 1.6;
            }

            .redirect-countdown {
                margin-top: 40px;
                color: #888;
                font-size: 16px;
            }

            .countdown-number {
                color: <?php echo $color; ?>;
                font-weight: bold;
                font-family: 'Orbitron', monospace;
                font-size: 20px;
            }

            .result-actions {
                display: flex;
                justify-content: center;
                gap: 25px;
                margin-top: 40px;
            }

            .action-button {
                background: rgba(107, 255, 141, 0.2);
                border: 1px solid #6bff8d;
                color: #6bff8d;
                padding: 12px 30px;
                border-radius: 8px;
                font-size: 16px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .action-button:hover {
                background: rgba(107, 255, 141, 0.4);
                transform: translateY(-2px);
            }

            .action-button.secondary {
                background: rgba(108, 117, 125, 0.2);
                border: 1px solid #6c757d;
                color: #b0bec5;
            }

            .action-button.secondary:hover {
                background: rgba(108, 117, 125, 0.4);
            }

            .action-button i {
                font-size: 18px;
            }

            @media (max-width: 768px) {
                .result-container {
                    margin: 20px auto;
                    padding: 20px;
                }

                .result-card {
                    padding: 30px;
                }

                .result-icon {
                    font-size: 4rem;
                }

                .result-title {
                    font-size: 24px;
                }

                .result-message, .result-error {
                    font-size: 16px;
                }

                .result-actions {
                    flex-direction: column;
                    gap: 15px;
                }

                .action-button {
                    width: 100%;
                    justify-content: center;
                    padding: 15px;
                }
            }
        </style>
        <script>

            let countdown = <?php echo $auto_redirect; ?>;
            function updateCountdown() {
                document.getElementById('countdown').textContent = countdown;
                if (countdown <= 0) {
                    window.location.href = '<?php echo htmlspecialchars($redirect_url); ?>';
                } else {
                    countdown--;
                    setTimeout(updateCountdown, 1000);
                }
            }
            window.onload = function() {
                setTimeout(updateCountdown, 1000);
            };
        </script>
    </head>
    <body class="login-page">
        <div class="result-container">
            <div class="result-card">
                <div class="result-icon">
                    <i class="fas fa-<?php echo $icon; ?>"></i>
                </div>

                <h2 class="result-title">
                    <?php echo $title; ?>
                </h2>

                <?php if ($success && !empty($message)): ?>
                    <p class="result-message">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                <?php elseif (!empty($error)): ?>
                    <p class="result-error">
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                <?php endif; ?>

                <div class="redirect-countdown">
                    <i class="fas fa-clock"></i>
                    将在 <span id="countdown" class="countdown-number"><?php echo $auto_redirect; ?></span> 秒后自动跳转
                </div>

                <div class="result-actions">
                    <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="action-button">
                        <i class="fas fa-arrow-right"></i>
                        立即跳转
                    </a>
                    <a href="bans.php" class="action-button secondary">
                        <i class="fas fa-list"></i>
                        返回列表
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}