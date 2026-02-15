# BAN-SYSTEM
在现代游戏服务器管理中，账号封禁公示系统是维护游戏环境秩序的重要工具。本文将详细介绍一个基于PHP + MySQL技术栈的《我的世界》服务器账号封禁公示系统，该系统采用现代化UI设计，具备科技感风格，能够有效展示和管理服务器封禁记录。

🚀 安装教程

步骤1：环境准备

安装Web服务器：
Nginx 1.28.1+
PHP 8.x
MySQL 5.7+

启用PHP扩展：

PDO_MySQL
session
mysqli
步骤2：部署项目

将项目文件复制到Web目录：

   /www/wwwroot/域名

步骤3：运行安装向导

访问安装页面：

   http://localhost/install.php

填写数据库配置：

   数据库主机: localhost
   数据库名: minecraft_bans
   用户名: root
   密码: (您的数据库密码)
   表前缀: bans_

管理员账户：

   管理员默认账号: admin
   管理员默认密码: admin123

先后完成之后一定要在管理页面更改默认密码[http://localhost/admin/admins.php]

点击"一键安装"，等待安装完成

步骤4：删除安装文件
删除根目录下的 install.php 文件
