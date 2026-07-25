# openct
<img width="3680" height="1120" alt="openct-image-1" src="https://github.com/user-attachments/assets/62a7a86f-9d71-4a44-95b5-1efae2a3e990" />

OpenCT班级积分管理系统

# OPenCT班级积分管理系统

一个专为中小学班级管理设计的积分与抽奖系统，支持积分管理、积分抽奖、惩罚抽奖、学生排名、班级风采展示等功能。教师可全面控制，学生可参与互动，界面采用极简风格，响应式设计适配所有设备。

---

## ✨ 功能特性

### 🏆 积分管理
教师可对学生进行加分、减分操作，支持批量导入 CSV 文件调整积分，所有变动自动记录日志。

### 🎁 积分抽奖
消耗学生积分进行转盘抽奖，奖品名称、中奖权重、库存数量完全可配置。教师可代表学生抽奖，抽奖后积分实时扣除。

### 😈 惩罚抽奖
教师指定积分最低的若干名学生，抽取惩罚奖品（不消耗积分）。奖品及惩罚人数均可后台设置。

### 📊 排行榜
首页展示积分前三名；后台仪表盘显示前十名（含进度条）和倒数五名；全部排名页支持按积分升降序排列，显示最近变动原因及时间。

### 🖼️ 班级风采
首页轮播图，教师可在后台上传、删除、排序照片，支持多图展示。

### 👥 多角色权限

- **教师**：完整后台（仪表盘、积分管理、用户管理、首页编辑、系统设置、操作日志、抽奖设置）。
- **学生管理员（积分管理员）**：登录后进入个人主页，可点击按钮进入积分管理页，仅能加减分。
- **普通学生**：查看个人积分与变动记录，修改密码、上传头像，参与积分抽奖。

### 🔐 安装引导
首次访问自动跳转安装向导，填写数据库信息和管理员账号即可完成部署。

### 📱 响应式设计
手机、平板、电脑均可完美浏览，后台表格支持水平滚动，抽奖转盘自动适配屏幕。

---

## 🛠️ 技术栈

| 类别 | 技术 |
| :--- | :--- |
| 后端 | PHP 7.4+（原生，无框架） |
| 数据库 | MySQL 5.7+ / MariaDB，使用 PDO 预处理防 SQL 注入 |
| 密码安全 | `password_hash()` / `password_verify()` |
| 前端 | Bootstrap 5 栅格系统 + 自定义 Apple 风格 CSS |
| 会话 | PHP Session 管理登录状态 |
| 图表 | Canvas 绘制抽奖转盘 |

---

## 📦 安装指南

### 环境要求

- Web 服务器：Apache / Nginx，开启 PHP 和 MySQL 支持
- PHP：≥ 7.4，需开启 PDO、mysqli、session、fileinfo 扩展
- MySQL：≥ 5.7，字符集 utf8mb4
- 权限：`uploads/` 目录需可写（755 或 777）

### 步骤

1. **上传文件**  
   将项目所有文件/文件夹上传至网站根目录（如 `public_html`）。

2. **设置权限**  
   确保 `uploads/` 及子目录（`avatars/`、`slides/`）有写入权限，Linux 下可执行：
   ```bash
   chmod -R 755 uploads/
3. **启动安装**
在浏览器中访问你的域名，系统会自动检测未安装状态并跳转到 install.php。

填写数据库信息

主机：通常为 localhost

数据库名：新建或指定一个数据库（系统会尝试创建）

用户名、密码：具有创建表和写入数据的权限

4.3. **设置管理员**

账号：教师用户名（如 admin）

姓名：管理员显示名称

密码：至少 6 位

5.3. **完成**
点击“完成安装”后，系统自动创建数据表、写入默认配置，并跳转到前台首页。

## **⚠️ 安装完成后，建议立即删除 install.php 文件，防止他人重新安装。**

# **📘 使用教程**
## **一、教师**
**1. 登录与后台**
安装时设置的管理员账号即为教师角色。
登录后页面顶部导航栏会显示“后台”入口，点击进入。

**2. 仪表盘**
展示总学生数、最高积分、平均积分；前十名排行榜（带进度条）和倒数五名提示。

**3. 积分管理**
所有学生列表，显示当前积分。

点击“加分”或“减分”按钮，输入分值和原因，自动记录操作日志。

批量导入 CSV：上传 CSV 文件，格式为 学号或姓名,变动分值（正数加、负数减），可快速调整大量学生积分。

**4. 用户管理**
搜索、分页查看所有用户。

添加学生：手动填写学号、姓名、初始密码（默认 123456）、邮箱（选填）。

批量导入学生：上传 CSV 文件（格式：学号,姓名,密码），自动跳过已存在的学号。

角色修改：可将普通学生提升为“积分管理员”，该学生登录后可进入积分管理页。

删除用户：可删除学生或学生管理员（教师不可删除自己）。

**5. 首页编辑**
修改班级名称、积分开始和结束时间（首页会显示倒计时）。

上传、删除、排序班级风采轮播图。

点击“预览首页效果”新窗口查看前台变化。

**6. 系统设置**
网站标题（浏览器标签页显示）。

是否开放注册（关闭后学生只能在后台由教师添加）。

**7. 操作日志**
所有积分变动记录（加分、减分、抽奖消耗），支持按学生姓名或操作人搜索，不可编辑。

**8. 抽奖设置**
包含两个子模块：

**积分抽奖**

开关：控制前台是否显示抽奖入口。

每次消耗积分：学生每抽一次扣除的积分数。

奖品管理：添加奖品名称、权重（概率）、总数量；可删除奖品。中奖概率 = 该奖品权重 / 所有奖品权重之和。

**惩罚抽奖**

开关：控制教师是否可进行惩罚抽奖。

最后几名人数：系统自动筛选积分最低的 N 名学生。

惩罚奖品管理：同积分奖品，但不消耗积分。

**9. 抽奖操作（教师在前台）**
教师进入抽奖页面后可看到“积分抽奖”和“惩罚抽奖”两个标签。

积分抽奖：选择学生（下拉框），点击转盘按钮，扣除学生积分并随机出奖品。

惩罚抽奖：系统默认列出积分最低的学生，教师选择后点击“惩罚”，转盘抽取惩罚奖品，不扣积分。

## **二、学生管理员（积分管理员）**
**登录**
使用教师分配的学生管理员账号登录。登录后进入个人主页（不再是后台首页）。

**个人主页**
可查看自己的积分、修改密码、上传头像。左侧显示“⭐ 管理积分加减分”按钮。

**积分管理**
点击上述按钮进入后台积分管理页，仅有“积分管理”一个菜单。可对学生进行加分、减分操作，权限与教师相同但无法访问其他后台功能。

## **三、普通学生**
**登录与注册**
若教师开启了注册功能，登录页会显示“注册新账号”链接。填写学号、姓名、邮箱（选填）、密码完成注册。忘记密码可通过注册邮箱重置。

**个人中心**
登录后默认进入个人主页。查看当前积分、修改密码、上传头像。下方列表显示近 20 条积分变动记录（谁操作、原因、时间）。

**首页与排行**
首页显示班级名称、积分截止倒计时、前三名、班级风采轮播图。点击“全部排名”可查看所有同学的积分及最近变动。

**积分抽奖**
当教师开启抽奖开关后，首页会显示“🎁 积分抽奖”按钮。进入抽奖页，看到彩色转盘，每次消耗教师设定的积分（如 10 分）。点击转盘中央按钮，指针旋转后停止，显示中奖结果，积分实时减少。若积分不足，按钮自动禁用；可连续抽奖直到积分不够。


## 📁 目录结构

```text
.
├── admin/                  # 后台管理页面
│   ├── index.php           # 仪表盘
│   ├── points.php          # 积分管理
│   ├── users.php           # 用户管理
│   ├── homepage.php        # 首页编辑
│   ├── settings.php        # 系统设置
│   ├── lottery.php         # 抽奖设置
│   ├── logs.php            # 操作日志
│   └── logout.php          # 退出登录
├── assets/                 # 静态资源
│   ├── css/
│   │   └── style.css       # 自定义 Apple 风格样式
│   └── default-avatar.png  # 默认头像
├── includes/               # 公共包含文件
│   ├── config.php          # 数据库配置（安装后自动生成）
│   ├── db.php              # PDO 数据库连接
│   ├── functions.php       # 全局函数（权限、配置等）
│   ├── header.php          # 前台头部（含导航栏）
│   ├── footer.php          # 前台尾部
│   ├── admin_header.php    # 后台头部
│   ├── admin_footer.php    # 后台尾部
│   └── admin_sidebar.php   # 后台侧边栏
├── uploads/                # 用户上传文件
│   ├── avatars/            # 学生头像
│   └── slides/             # 班级风采轮播图
├── index.php               # 班级首页
├── login.php               # 登录页（左右分栏）
├── register.php            # 注册页（可后台关闭）
├── forgot_password.php     # 忘记密码
├── profile.php             # 个人中心
├── rankings.php            # 全部排名（支持升/降序）
├── lottery.php             # 抽奖转盘（积分/惩罚双标签）
├── install.php             # 安装向导
├── upgrade_all.php         # 数据库升级脚本（建议删除）
├── .htaccess               # Apache 安全规则（可选）
└── README.md               # 本文件
```

# **⚙️ 常见问题**
Q: 安装时提示“数据库连接失败”
A: 检查主机地址（虚拟主机可能不是 localhost）、用户名、密码是否正确，以及该用户是否有创建数据库的权限。

Q: 上传头像或图片失败
A: 检查 uploads/avatars/ 和 uploads/slides/ 目录的权限是否可写（至少 755）。

Q: 后台抽奖设置白屏
A: 数据库缺少抽奖相关表，请运行 upgrade_all.php（上传后访问一次，然后立即删除），或手动执行建表 SQL（见下方附录）。

Q: 学生忘记密码
A: 若注册时填写了邮箱，可通过登录页“忘记密码”重置；否则教师可在后台删除该学生后重新添加。

Q: 如何重新安装？
A: 删除 includes/config.php 文件，再次访问首页会自动进入安装向导。注意：此操作会清空所有数据！


## 📄 附录：手动建表 SQL

如果自动升级脚本无效，可在数据库管理工具中执行以下 SQL（先选择正确的数据库）。

```sql
-- 积分奖品表
CREATE TABLE IF NOT EXISTS `prizes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `probability` int(11) NOT NULL DEFAULT '10',
  `total` int(11) NOT NULL DEFAULT '100',
  `drawn` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 惩罚奖品表
CREATE TABLE IF NOT EXISTS `penalty_prizes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `probability` int(11) NOT NULL DEFAULT '10',
  `total` int(11) NOT NULL DEFAULT '100',
  `drawn` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 积分抽奖记录表
CREATE TABLE IF NOT EXISTS `lottery_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `prize_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 惩罚抽奖记录表
CREATE TABLE IF NOT EXISTS `penalty_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `prize_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 配置项（若缺失）
INSERT IGNORE INTO `config` (`config_key`, `config_value`) VALUES
('lottery_enabled', '0'),
('lottery_cost', '10'),
('penalty_enabled', '0'),
('penalty_count', '3');
```

## **📜 许可证**
本项目基于 MIT License 开源，欢迎自由使用、修改和分发。
若用于商业用途，无需额外授权，但请保留原始作者信息（可选）。

## **如果对项目有任何建议或问题，欢迎提交 Issue 或 Pull Request！**
## **您的 Star ⭐ 是对我们最大的鼓励。**
