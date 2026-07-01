# Web File Manager

English | [中文](README.zh.md)

A lightweight PHP + vanilla JavaScript web file management system with a desktop-like (MDI multi-window) experience. Supports file preview, upload, download, ZIP export, operation logging, and more.

![Home Page](documents/indexpage.png)

## Features

### File Operations
- Directory browsing (folders sorted first, sortable by name/size/type/date)
- Create folder, rename, delete (recursive deletion of non-empty directories)
- Copy / Cut / Paste (with auto-incrementing names for duplicates)
- Single file download, multi-file/directory ZIP streaming compression (native PHP `ZipArchive`)

### File Preview
- Images: integrated [Viewer.js](https://github.com/fengyuanchen/viewerjs) with zoom/rotate/fullscreen
- Video: `<video>` inline playback (mp4/mkv/avi/webm/mov/wmv/flv) with Range 206 seek support
- Audio: `<audio>` inline playback (mp3/wav/flac/ogg/aac)
- Text: code/config file preview (txt/html/css/js/json/xml/toml/md/ini/yaml/csv/log etc.)
- Image dimensions automatically returned

### UI / Interaction
- **Multi-window MDI**: background window + draggable/resizable/maximizable floating windows
- **Three view modes**: detail list, icon, large icon (image thumbnails)
- **Context menu**: dynamically generated based on context (blank/file/folder/multi-select)
- **Drag & drop**: drag files into folders to move; drag local files into windows to upload
- **Rubber-band selection**: click and drag on blank area to select multiple files
- **Breadcrumb navigation**: click to navigate, click blank area to edit path
- **Keyboard shortcuts**: `Ctrl+A` select all, `Ctrl+C/X/V` copy/cut/paste, `Delete` delete, `F2` rename
- **Upload panel**: concurrent multi-file upload with real-time progress and cancel
- **Lazy thumbnail loading**: only images in the viewport are loaded (IntersectionObserver)

### Security & Administration
- User login authentication (Session + Cookie Token dual mode, "keep logged in" for 60 days)
- Multi-user/roles (admin / normal, configured via PHP array)
- Path traversal protection (`file_safepath` manually resolves `..`, prevents escaping root directory)
- `.php` file upload blocked and hidden from listings
- Filename sanitization (control characters / special characters / Windows reserved names filtered)
- Operation log audit (login/upload/download/delete/rename/copy/cut/zip) with user, IP, timestamp, pagination

### Internationalization (i18n)
- Bilingual support: English (default) and Chinese
- Language files defined as PHP arrays in `lib/lang_en.php` and `lib/lang_zh.php`
- Language preference stored in session + cookie
- Switch language via the System menu (gear icon) in the top bar

## Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 5.5+ (`ZipArchive`) |
| Storage | File-based (logs as JSON Lines in `lib/_logs/`, params in `config.php`) |
| Config | PHP array (`config.php`, zero dependencies) |
| Frontend | Vanilla JavaScript (IIFE module, no framework) |
| Image preview | Viewer.js 1.11.6 |
| Icons | Font Awesome |

## Directory Structure

```
.
├── index.php              # Entry: routing + main page HTML
├── app.js                 # Frontend logic (utils / windows / context menu / upload / preview / log)
├── style.css              # Global styles
├── .htaccess              # Apache rewrite rules
├── config.php             # User & site configuration
├── lib/
│   ├── init.php           # Common initialization (timezone / constants)
│   ├── conf.php           # Config loading + param_get() + i18n language functions
│   ├── auth.php           # Authentication (login/logout/token keep-login)
│   ├── file.php           # File operations API (path safety, filename sanitization)
│   ├── up.php             # Upload handler (multi-file, blocks .php)
│   ├── down.php           # Download + ZIP streaming + Range 206
│   ├── log.php            # Operation log recording & querying
│   ├── lang_en.php        # English language pack (default)
│   └── lang_zh.php        # Chinese language pack
├── viewer1.11.6/          # Viewer.js assets
└── documents/             # Screenshots & docs
```

## Installation & Usage

### Requirements
- PHP 5.5+ (requires `zip` extension; `mbstring` recommended for non-UTF-8 filesystem encoding)
- Web server (Apache / Nginx / IIS, or PHP built-in server)

### Steps
1. Place the project in your web server document root, or create a virtual host pointing to the project directory.
2. Install Font Awesome (`fontawesome/css/all.min.css`) in the project root (already in `.gitignore`).
3. Create the file storage directory `up/` (gitignored, must be writable by the web process).
4. Visit `http://localhost/fs/` to start.

### Quick Start (PHP Built-in Server)
```bash
php -S 127.0.0.1:8000
```
Open `http://127.0.0.1:8000/` in your browser. Default credentials:

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `123456` | Admin |
| `user` | `123456` | Normal |

> Logs are written to `lib/_logs/YYYY-MM.log` (JSON Lines). Params can be added under `params` in `config.php` and read via `param_get('key')`. Be sure to change default passwords in `config.php` for production.

## Configuration

`config.php`:

```php
<?php
return array(
    'system' => array(
        'site_name' => 'Web File Manager',
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

- `system.site_name`: Site name
- `system.fs_encoding`: Filesystem encoding (`UTF-8` or `GBK` for older Windows)
- `system.delete_confirm_file`: Show confirmation dialog when deleting files
- `system.delete_confirm_dir`: Show confirmation dialog when deleting directories
- `users`: User array, each with `name`, `pass`, `level` (`admin` or `normal`)

## API Reference

All endpoints are routed via `index.php?act=xxx` and return JSON.

| act | Method | Description |
| --- | --- | --- |
| `list` | GET | List directory contents |
| `info` | GET | Get file/directory details |
| `read` | GET | Read text file content |
| `down` | GET | Download single file (supports Range resume) |
| `zip` | GET | Multi-file/directory ZIP download |
| `mkdir` | POST | Create new folder |
| `del` | POST | Delete file/directory |
| `ren` | POST | Rename |
| `paste` | POST | Paste (copy/cut) |
| `upload` | POST | Upload files (multipart) |
| `log` | GET | Query operation logs (paginated + filtered) |
| `lang` | GET | Switch language (`?act=lang&l=en` or `&l=zh`) |

## Security Notes

- Default passwords are for demo only. **Change them in production.**
- The file storage root `up/` should not execute PHP (system blocks `.php` uploads and hides them).
- Recommend disabling PHP parsing in the `up/` directory at the web server level.
- `lib/_logs/`, `lib/_tokens/`, and `config.php` should be protected from external access via web server config.

## License

For learning and internal use only.
