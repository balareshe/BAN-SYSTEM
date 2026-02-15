<?php
/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - 页面未找到</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-container {
            text-align: center;
            padding: 50px;
            max-width: 600px;
        }

        .error-code {
            font-family: 'Orbitron', monospace;
            font-size: 10rem;
            font-weight: 900;
            color: #dc2626;
            text-shadow:
                0 0 10px #dc2626,
                0 0 20px #dc2626,
                0 0 30px #b91c1c,
                0 0 40px #991b1b;
            animation: glitch 2s infinite;
            line-height: 1;
            margin-bottom: 20px;
        }

        .error-title {
            font-family: 'Orbitron', monospace;
            font-size: 2rem;
            color: #ffffff;
            margin-bottom: 20px;
        }

        .error-message {
            color: #b0bec5;
            font-size: 1.1rem;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .error-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .error-button {
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Orbitron', monospace;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .error-button.primary {
            background: linear-gradient(135deg, #991b1b, #dc2626, #ef4444);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        .error-button.primary:hover {
            background: linear-gradient(135deg, #b91c1c, #ef4444, #f87171);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
        }

        .error-button.secondary {
            background: rgba(255, 255, 255, 0.05);
            color: #fca5a5;
            border: 1px solid rgba(220, 38, 38, 0.4);
        }

        .error-button.secondary:hover {
            background: rgba(220, 38, 38, 0.1);
            transform: translateY(-2px);
        }

        @keyframes glitch {
            0%, 100% {
                text-shadow:
                    0 0 10px #dc2626,
                    0 0 20px #dc2626,
                    0 0 30px #b91c1c;
            }
            50% {
                text-shadow:
                    0 0 15px #ef4444,
                    0 0 25px #f87171,
                    0 0 35px #dc2626;
            }
        }

        @media (max-width: 768px) {
            .error-code {
                font-size: 6rem;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .error-actions {
                flex-direction: column;
            }

            .error-button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">页面未找到</h1>
        <p class="error-message">
            抱歉，您访问的页面不存在或已被删除。<br>
            请检查 URL 是否正确，或返回首页继续浏览。
        </p>
        <div class="error-actions">
            <a href="index.php" class="error-button primary">
                <i class="fas fa-home"></i>
                返回首页
            </a>
            <a href="javascript:history.back()" class="error-button secondary">
                <i class="fas fa-arrow-left"></i>
                返回上一页
            </a>
        </div>
    </div>
</body>
</html>
