# Web文件管理系统

[English](README.md) | 中文

一个轻量级 PHP + 原生 JavaScript 的 Web 文件管理系统，提供类似桌面（MDI 多窗口）的使用体验。支持文件预览、上传、下载、ZIP 导出、操作日志等功能。

![首页](documents/indexpage.png)

## 功能特性

### 文件操作
- 目录浏览（文件夹优先排列，支持按名称/大小/类型/日期排序）
- 新建文件夹、重命名、删除（非空目录递归删除）
- 复制 / 剪切 / 粘贴（重名自动递增编号）
- 单文件下载，多文件/目录 ZIP 流式压缩（原生 PHP `ZipArchive`）

### 文件预览
- 图片：集成 [Viewer.js](https://github.com/fengyuanchen/viewerjs)，支持缩放/旋转/全屏
- 视频：`<video>` 内联播放（mp4/mkv/avi/webm/mov/wmv/flv），支持 Range 206 拖拽跳转
- 音频：`<audio>` 内联播放（mp3/wav/flac/ogg/aac）
- 文本：代码/配置文件预览（txt/html/css/js/json/xml/toml/md/ini/yaml/csv/log 等）
- 图片尺寸自动识别返回

### 界面 / 交互
- **多窗口 MDI**：后台窗口 + 可拖拽/缩放/最大化的浮动窗口
- **三种视图模式**：详细列表、图标、大图标（图片缩略图）
- **右键菜单**：根据上下文动态生成（空白/文件/文件夹/多选）
- **拖拽**：拖拽文件到文件夹移动；拖拽本地文件到窗口上传
- **框选**：空白区域按住拖拽框选多个文件
- **面包屑导航**：点击导航，点击空白编辑路径
- **键盘快捷键**：`Ctrl+A` 全选，`Ctrl+C/X/V` 复制/剪切/粘贴，`Delete` 删除，`F2` 重命名
- **上传面板**：多文件并发上传，实时进度显示，支持取消
- **缩略图懒加载**：仅加载视窗内图片（IntersectionObserver）

### 安全与管理
- 用户登录认证（Session + Cookie Token 双模式，"保持登录" 60 天免登录）
- 多用户/角色（admin / normal，PHP 数组配置）
- 路径穿越防护（`file_safepath` 手动解析 `..`，防止逃逸根目录）
- 禁止 `.php` 文件上传且在列表中隐藏
- 文件名净化（控制字符 / 特殊字符 / Windows 保留名过滤）
- 操作日志审计（登录/上传/下载/删除/重命名/复制/剪切/打包），记录用户、IP、时间，支持分页查询

### 国际化（i18n）
- 中英双语支持：默认英文，可切换中文
- 语言文件定义为 PHP 数组：`lib/lang_en.php` 和 `lib/lang_zh.php`
- 语言偏好存储于 session + cookie
- 通过顶栏系统菜单（齿轮图标）切换语言

## 技术栈

| 层级 | 技术 |
| --- | --- |
| 后端 | PHP 5.5+（`ZipArchive`） |
| 存储 | 文件存储（日志为 JSON Lines 存于 `lib/_logs/`，参数存于 `config.php`） |
| 配置 | PHP 数组（`config.php`，零依赖） |
| 前端 | 原生 JavaScript（IIFE 模块，无框架） |
| 图片预览 | Viewer.js 1.11.6 |
| 图标 | Font Awesome |

## 目录结构

```
.
├── index.php              # 入口：路由 + 主页面 HTML
├── app.js                 # 前端逻辑（工具 / 窗口 / 右键菜单 / 上传 / 预览 / 日志）
├── style.css              # 全局样式
├── .htaccess              # Apache 重写规则
├── config.php             # 用户与站点配置
├── lib/
│   ├── init.php           # 公共初始化（时区 / 常量）
│   ├── conf.php           # 配置加载 + param_get() + i18n 语言函数
│   ├── auth.php           # 认证（登录/登出/Token 保持登录）
│   ├── file.php           # 文件操作 API（路径安全、文件名净化）
│   ├── up.php             # 上传处理（多文件，禁止 .php）
│   ├── down.php           # 下载 + ZIP 流式 + Range 206
│   ├── log.php            # 操作日志记录与查询
│   ├── lang_en.php        # 英文语言包（默认）
│   └── lang_zh.php        # 中文语言包
├── viewer1.11.6/          # Viewer.js 资源
└── documents/             # 截图与文档
```

## 安装与使用

### 环境要求
- PHP 5.5+（需 `zip` 扩展；非 UTF-8 文件系统编码时建议安装 `mbstring` 扩展）
- Web 服务器（Apache / Nginx / IIS，或 PHP 内置服务器）

### 步骤
1. 将项目放入 Web 服务器根目录，或创建虚拟主机指向项目目录。
2. 在项目根目录安装 Font Awesome（`fontawesome/css/all.min.css`，已在 `.gitignore` 中）。
3. 创建文件存储目录 `up/`（gitignore，需 Web 进程可写）。
4. 访问 `http://localhost/fs/` 即可使用。

### 快速启动（PHP 内置服务器）
```bash
php -S 127.0.0.1:8000
```
在浏览器打开 `http://127.0.0.1:8000/`。默认账号：

| 用户名 | 密码 | 角色 |
| --- | --- | --- |
| `admin` | `123456` | 管理员 |
| `user` | `123456` | 普通用户 |

> 日志写入 `lib/_logs/YYYY-MM.log`（JSON Lines 格式）。参数可在 `config.php` 的 `params` 节中添加，通过 `param_get('key')` 读取。生产环境请务必在 `config.php` 中修改默认密码。

## 配置说明

`config.php`：

```php
<?php
return array(
    'system' => array(
        'site_name' => 'Web文件管理系统',
        'fs_encoding' => 'UTF-8',
        'delete_confirm_file' => true,
        'delete_confirm_dir' => true,
    ),
    'users' => array(
        array('name' => 'admin', 'pass' => '123456', 'level' => 'admin'),
        array('name' => 'user', 'pass' => '123456', 'level' => 'normal'),
    ),
);
```

- `system.site_name`：站点名称
- `system.fs_encoding`：文件系统编码（`UTF-8` 或旧版 Windows 用 `GBK`）
- `system.delete_confirm_file`：删除文件时是否弹出确认对话框
- `system.delete_confirm_dir`：删除目录时是否弹出确认对话框
- `users`：用户数组，每项含 `name`、`pass`、`level`（`admin` 或 `normal`）

## API 接口

所有接口通过 `index.php?act=xxx` 路由，返回 JSON。

| act | 方法 | 说明 |
| --- | --- | --- |
| `list` | GET | 列出目录内容 |
| `info` | GET | 获取文件/目录详情 |
| `read` | GET | 读取文本文件内容 |
| `down` | GET | 下载单个文件（支持 Range 断点续传） |
| `zip` | GET | 多文件/目录 ZIP 下载 |
| `mkdir` | POST | 新建文件夹 |
| `del` | POST | 删除文件/目录 |
| `ren` | POST | 重命名 |
| `paste` | POST | 粘贴（复制/剪切） |
| `upload` | POST | 上传文件（multipart） |
| `log` | GET | 查询操作日志（分页 + 筛选） |
| `lang` | GET | 切换语言（`?act=lang&l=en` 或 `&l=zh`） |

## 安全须知

- 默认密码仅供演示，**生产环境请务必修改**。
- 文件存储根目录 `up/` 不应执行 PHP（系统已禁止 `.php` 上传并在列表中隐藏）。
- 建议在 Web 服务器层面禁用 `up/` 目录的 PHP 解析。
- `lib/_logs/`、`lib/_tokens/`、`config.php` 应通过 Web 服务器配置禁止外部访问。

## 许可

仅供学习与内部使用。
