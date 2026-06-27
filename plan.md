# Web文件管理系统 - 实现计划

## 1. 项目结构 & 文件清单

```
fs/
├── index.php          # 系统入口（路由 + 页面输出）
├── login.php          # 登录页面 / 登录验证API
├── config.toml        # 配置文件（含用户账号）
├── data.db            # SQLite 系统参数 + 操作日志数据库
├── up/                # 上传文件存储目录（多级）
└── lib/
    ├── init.php       # 公共初始化（session, DB, config, log 加载）
    ├── conf.php       # TOML 配置文件解析
    ├── db.php         # SQLite 数据库封装
    ├── auth.php       # 用户认证
    ├── log.php        # 操作日志记录与查询
    ├── file.php       # 文件操作（列表、删除、重命名、移动、复制）
    ├── up.php         # 文件上传处理
    ├── down.php       # 文件下载 / ZIP流式压缩下载
    └── view.php       # 文件预览（图片/视频/音频/文本 输出）
```

## 2. 实施步骤（共8步）

---

### 步骤1: lib/conf.php — TOML 配置文件解析

**功能**：解析 `config.toml`，提取系统配置和用户列表。

- 使用 Composer 安装 `leonelquinteros/php-toml`（支持 PHP >= 5.2）
- `Toml::parseFile($path)` 直接解析 config.toml 返回数组
- 解析结果返回关联数组：`['system'=>[...], 'users'=>[[...], ...]]`
- 对外暴露函数：`conf_load($path)` 返回配置数组
- 密码使用 `sha1(username . password)` 存储校验

**文件**：`lib/conf.php`

---

### 步骤2: lib/db.php — SQLite 数据库封装

**功能**：操作 `data.db`，存储系统参数键值对和操作日志。

- 使用 PDO + SQLite 驱动（PHP 5.5.12 已内置）
- 表结构：
  - `params` 表：`key TEXT PRIMARY KEY, value TEXT`
  - `logs` 表：`id INTEGER PRIMARY KEY AUTOINCREMENT, user TEXT, act TEXT, path TEXT, detail TEXT, ip TEXT, time DATETIME DEFAULT CURRENT_TIMESTAMP`
- 对外暴露函数：
  - `db_init()` — 创建/打开数据库，自动建表
  - `db_get($key)` — 读取参数值
  - `db_set($key, $value)` — 写入/修改参数值
  - `db_del($key)` — 删除参数
  - `db_exec($sql, $params)` — 执行任意 SQL（供 log.php 使用）
  - `db_query($sql, $params)` — 查询多行（供 log.php 使用）
- SQLite 文件路径从 `config.toml` 读取，默认 `data.db`

**logs 表字段说明**：

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER PK AUTOINCREMENT | 自增主键 |
| `user` | TEXT | 操作者用户名 |
| `act` | TEXT | 操作类型：login/logout/upload/download/del/ren/mkdir/cut/copy/paste/zip |
| `path` | TEXT | 操作的文件/文件夹路径（相对 up/） |
| `detail` | TEXT | 补充信息，如"从 A 移动到 B"、"重命名为 xxx" |
| `ip` | TEXT | 客户端 IP 地址 |
| `time` | DATETIME | 操作时间（自动写入） |

**文件**：`lib/db.php`

---

### 步骤3: lib/auth.php — 用户认证

**功能**：登录验证、Session 管理、权限检查。

- `auth_login($name, $pass)` — 校验用户名密码，成功写入 session
- `auth_check()` — 检查当前是否已登录，未登录跳转 login.php
- `auth_user()` — 返回当前用户信息 `{name, level}`
- `auth_logout()` — 清除 session
- session 存储：`$_SESSION['user'] = ['name'=>'admin', 'level'=>'admin']`

**文件**：`lib/auth.php`

---

### 步骤4: lib/init.php — 公共初始化

**功能**：所有 PHP 页面统一入口。

- 开启 session
- 加载 `lib/conf.php` 解析 `config.toml`
- 加载 `lib/db.php` 初始化数据库
- 加载 `lib/auth.php`
- 定义常用常量：`ROOT_DIR`（项目根目录）、`UP_DIR`（up/ 目录）
- 设置 JSON 响应头等公共函数

**文件**：`lib/init.php`

---

### 步骤5: login.php — 登录页面

**功能**：

- GET 请求：输出登录 HTML 页面（PHP 内嵌 HTML，与原型 login.html 一致）
- POST 请求：接收 `name` + `pass`，调用 `auth_login()`，返回 JSON `{ok, err?}`
- 登录成功后前端跳转 `index.php`

**逻辑**：

- `$_SERVER['REQUEST_METHOD'] === 'POST'` 时处理登录 API
- 否则输出 HTML 页面

**文件**：`login.php`

---

### 步骤6: lib/file.php — 文件操作 API

**功能**：所有文件/文件夹的 CRUD 操作。

API 清单（统一通过 `index.php?act=xxx` 调用，返回 JSON）：

| act | 参数 | 说明 |
|-----|------|------|
| `list` | `path` | 列出目录内容（返回 items 数组，含 name/type/size/time） |
| `mkdir` | `path` | 新建文件夹 |
| `del` | `path` | 删除文件/文件夹（递归） |
| `ren` | `path, name` | 重命名文件/文件夹 |
| `cut` | `path` | 剪切（记录到 session 剪贴板） |
| `copy` | `path` | 复制（记录到 session 剪贴板） |
| `paste` | `path` | 粘贴（将剪贴板内容粘贴到目标目录） |
| `info` | `path` | 获取单个文件信息 |

- `list` 返回数据格式：
  ```json
  {"ok":true,"path":"图片","items":[
    {"name":"风景.jpg","type":"file","ext":"jpg","size":4928300,"time":"2026-06-26 14:20"},
    {"name":"截图","type":"dir","size":0,"time":"2026-06-25 09:00"}
  ]}
  ```
- 路径均为相对于 `up/` 的相对路径
- 安全过滤：防止路径穿越（`../`）
- 权限检查：管理员可操作所有文件，普通用户只能操作自己上传的文件（通过 `owner` 字段）

**文件**：`lib/file.php`

---

### 步骤7: lib/up.php — 文件上传处理

**功能**：

- 接收 POST 上传文件（multipart/form-data）
- 支持同时上传多个文件
- 上传后存入 `up/<相对路径>/` 下
- 支持通过 POST 参数 `dir` 指定上传目标目录
- 返回 JSON 上传结果 `{ok, err?, file?}`
- 断点续传暂不实现

**文件名冲突处理**：如果目标文件已存在，自动追加 `(1)`、`(2)` 序号。

**文件**：`lib/up.php`

---

### 步骤8: lib/down.php — 下载 & ZIP 流式压缩

**功能**：

- 单个文件下载：设置 `Content-Disposition: attachment` 直接输出
- 多个文件/文件夹下载为 ZIP 包：流式压缩输出，不生成临时文件
  - 使用 PHP 的 `ZipArchive`（PHP 5.5.12 已内置）或手动写入 ZIP 格式
  - 边压缩边输出，不占用内存
  - 支持选择单个文件夹或批量文件打包下载

**对外暴露函数**：

- `down_file($relpath)` — 直接下载单个文件
- `down_zip($paths, $zipname)` — 流式下载 ZIP 包，`$paths` 是相对路径数组

**文件**：`lib/down.php`

---

### 步骤9: index.php — 前端页面 & 路由

**功能**：

- GET `index.php` 且无 `act` 参数：输出主页面 HTML
- GET/POST `index.php?act=xxx`：分发到对应的 API 处理

**页面输出**：

- 与原型 [index.html](file:///d:/VS_Projects/php/Web文件管理/设计原型/index.html) 结构一致
- PHP 内嵌输出当前用户信息（用户名、权限等级）
- 加载 CSS：`style.css`（从原型直接搬运）
- 加载 JS：`app.js`（前端逻辑）

**CSS**：

- 直接从原型 [style.css](file:///d:/VS_Projects/php/Web文件管理/设计原型/style.css) 搬运，无需修改
- 图标使用 Font Awesome 6（已本地化到 `fontawesome/` 目录）

---

### 步骤10: app.js — 前端交互逻辑

**架构**：遵循编码规范，使用 IIFE 包裹。

**模块划分**（每个模块用 `initxxx()` 模式）：

1. **initajax** — AJAX 请求封装
   - `F.get(act, params)` → GET 请求
   - `F.post(act, data)` → POST 请求
   - 统一处理响应，错误时弹出提示

2. **initwin** — 窗口管理
   - 全局窗口数组 `wins[]`
   - 创建窗口：`Win.create(path, view)` → 生成窗口 DOM，插入 `.winlist`
   - 窗口拖拽：鼠标 down/move/up 改变 `left/top`（非 z-index）
   - 窗口最大化/还原：toggle `.max` class
   - 窗口关闭：移除 DOM + 从数组删除
   - 背景窗口固定在底层（初始为 up/ 根目录）

3. **initfile** — 文件列表渲染
   - `loadDir(win, path)` → AJAX 请求 `act=list`，渲染列表
   - 两种视图模式切换：
     - 详细视图（`.filelist.detail`）：表格行列
     - 图标视图（`.filelist.icon`）：网格缩略
   - 地址栏面包屑根据 path 渲染
   - 状态栏更新：项目数、选中数、路径

4. **initctx** — 右键菜单
   - 阻止 `contextmenu` 默认事件
   - 根据点击对象（文件/文件夹/空白区域）显示不同菜单
   - 菜单项：
     - **文件夹**：新窗口打开、新建文件夹、刷新、下载为ZIP包
     - **文件**：下载、预览、删除、重命名、剪切、复制
     - **空白**：新建文件夹、刷新、粘贴
   - 点击菜单项执行对应操作
   - 点击其他区域关闭菜单

5. **initupload** — 上传功能
   - 文件选择上传：`<input type=file multiple>` 触发
   - 拖拽上传：监听 `dragenter/dragover/drop` 事件，获取 `DataTransfer.files`
   - 上传队列管理：`tasks[]`，每个任务 `{id, name, size, xhr}`
   - 进度显示：`XMLHttpRequest.upload.onprogress`
   - 取消上传：`xhr.abort()`
   - 底部面板显示任务列表（原型 uploadpanel 结构）

6. **initpreview** — 预览窗口
   - 全局唯一预览窗口对象
   - 打开预览：`preview.open(path)` → 根据文件类型决定展示方式
     - 图片：`<img>` 标签，src 指向 `?act=down&path=...`
     - 视频：`<video>` 标签 + controls
     - 音频：`<audio>` 标签 + controls
     - 文本：`<pre>` 标签，AJAX 获取内容
     - 其他：显示不支持图标
   - 窗口可拖拽、可关闭
   - 再次调用 `preview.open` 时复用已有窗口

7. **initdnd** — 文件夹间拖拽
   - 为 `.win` 设置 `dragover/drop` 事件
   - 拖拽源：记录拖拽的文件路径
   - 拖拽目标：松开鼠标时执行文件移动操作
   - 拖拽过程中显示虚线高亮提示（`.dragover` class）

8. **initaction** — 文件操作
   - `delFile(path)` — 确认后调用 `act=del`
   - `renFile(path)` — 行内编辑（显示 `<input class=renameinput>`）
   - `cutFile(path)` — 调用 `act=cut`
   - `copyFile(path)` — 调用 `act=copy`
   - `pasteFile(win)` — 调用 `act=paste`
   - 全部操作后刷新当前窗口

9. **initnav** — 侧栏导航
   - 快速访问：点击跳转到对应目录
   - 操作按钮：上传、新建文件夹（触发对应功能）

**文件**：`app.js`（位于项目根目录，与 `index.php` 同级）

---

## 3. 请求路由流程

```
浏览器 → login.php（未登录）
         ↓ 提交表单
         login.php (POST) → auth_login() → JSON {ok}
         ↓ 跳转
         index.php → auth_check() → 输出页面 HTML + app.js
         ↓ 前端操作
         index.php?act=list → lib/file.php → JSON
         index.php?act=upload → lib/up.php → JSON
         index.php?act=down → lib/down.php → 文件流
         index.php?act=zip → lib/down.php → ZIP 流
         index.php?act=log → lib/log.php → JSON 日志列表
         ...
```

## 4. 关键设计决策

| 决策 | 选择 | 理由 |
|------|------|------|
| 前端 JS 框架 | 原生 JS + IIFE 模块化 | 无依赖，兼容老浏览器，符合编码规范 |
| 文件上传方式 | XMLHttpRequest 2（FormData + xhr.upload） | 支持进度回调，可取消 |
| ZIP 压缩 | PHP ZipArchive 类 | PHP 5.5.12 内置，支持流式输出 |
| 拖拽上传 | HTML5 Drag & Drop API | 现代浏览器广泛支持 |
| 窗口拖拽 | 原生 mousedown/mousemove/mouseup | 轻量，无第三方依赖 |
| 路径表示 | 全部用相对于 up/ 的 Unix 路径（`/`分隔） | 统一、安全，前端后端一致 |
| 后端响应格式 | 全部 JSON：`{ok:true/false, err?, ...}` | 易于前端统一处理 |

## 5. 实施顺序

```
步骤1: lib/conf.php       ← TOML 解析（无依赖）
步骤2: lib/db.php         ← SQLite 封装（依赖 conf）
步骤3: lib/auth.php       ← 认证（依赖 session）
步骤4: lib/init.php       ← 公共初始化（依赖 conf, db, auth）
步骤5: login.php          ← 登录页（依赖 init, auth）
步骤6: lib/file.php       ← 文件操作 API（依赖 init）
步骤7: lib/up.php         ← 上传处理（依赖 init, file）
步骤8: lib/down.php       ← 下载 & ZIP（依赖 init）
步骤9: index.php          ← 主页面 + AJAX 路由（依赖所有 lib）
步骤10: app.js            ← 前端全部交互逻辑
```

## 6. PHP 5.5.12 兼容性注意事项

- 不使用 `[]` 短数组语法（PHP 5.4+ 支持，实际可用，但为保证兼容用 `array()`）
- 不使用 `...` 展开运算符（PHP 5.6+）
- 不使用 `??` 合并运算符（PHP 7+），用 `isset()` 替代
- 不使用类型声明（PHP 7+）
- 不使用 `random_int()`（PHP 7+），用 `mt_rand()` 替代
- PDO SQLite 驱动需确认 WAMP 环境下已启用 `php_pdo_sqlite.dll`
- ZipArchive 需确认已启用 `php_zip.dll`
- `json_encode`/`json_decode` 在 PHP 5.5.12 中可用
- `hash('sha1', ...)` 在 PHP 5.5.12 中可用