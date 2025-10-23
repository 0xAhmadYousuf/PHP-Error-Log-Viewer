# PHP Error Log Viewer v2.0

[![GitHub](https://img.shields.io/github/license/0xAhmadYousuf/PHP-Error-Log-Viewer)](https://github.com/0xAhmadYousuf/PHP-Error-Log-Viewer)
[![GitHub stars](https://img.shields.io/github/stars/0xAhmadYousuf/PHP-Error-Log-Viewer)](https://github.com/0xAhmadYousuf/PHP-Error-Log-Viewer/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/0xAhmadYousuf/PHP-Error-Log-Viewer)](https://github.com/0xAhmadYousuf/PHP-Error-Log-Viewer/network)

**Ultra-lightweight PHP Error Log Viewer - Single file, zero configuration, production-ready!**

## Version Comparison

| Feature | v2.0 | v1.0 | Other Projects |
|---------|------|------|----------------|
| **Setup** | Copy & paste - Done! | Multiple files | Complex installation |
| **Configuration** | Auto-detect + 3 lines max | Manual config | Database setup required |
| **File Count** | 1 single file | 5+ files | 10-50+ files |
| **Dependencies** | Pure PHP only | Template files | Frameworks/Libraries |
| **API Architecture** | Built-in REST API | HTML only | External dependencies |
| **Self-contained** | 100% portable | Requires assets | Database + config files |
| **Production Ready** | Instant deployment | Setup required | Complex deployment |
| **Error Tracking** | Advanced with reoccurrence | Basic tracking | Limited or none |
| **Theme System** | Built-in dark/light | Basic styling | External CSS required |
| **Mobile Ready** | Responsive design | Desktop only | Varies |

## Top Features

- **Copy-Paste Setup** - Download, upload, access. That's it!
- **Zero Configuration** - Auto-detects error log path via `ini_get()`
- **3-Line Config** - Only customize if needed (lines 3-11)
- **Single File Solution** - Everything bundled: HTML, CSS, JS, PHP API
- **Built-in Themes** - Dark/light mode with VS Code styling
- **Advanced Analytics** - Real-time stats, reoccurrence detection
- **REST API** - Full JSON API for integration
- **Error Tracking** - Mark solved, track developers, detect reoccurrence
- **Modern UI** - Material icons, responsive design, smooth animations
- **Production Ready** - No external dependencies, works anywhere PHP runs
- **Time-based Filtering**: Focus on recent errors that need attention
- **Reoccurrence Alerts**: Immediate notification when solved errors return

## 🚀 Quick Installation

### Method 1: One-Click Installer (Recommended)

Create a file called `install.php` in your web root and run it:


**Option 1: Direct Copy-Paste**
1. Go to: `https://raw.githubusercontent.com/0xAhmadYousuf/PHP-Error-Log-Viewer/refs/heads/main/PELV_v2.0.php`
2. Copy the entire content
3. Paste it into a new file (e.g., `index.php`) on your server
4. Ready to go!

**Option 2: Auto-Installer**
Create `installer.php` with this code, then run/visit it:

```php
<?php
$url = 'https://raw.githubusercontent.com/0xAhmadYousuf/PHP-Error-Log-Viewer/refs/heads/main/PELV_v2.0.php';
$content = file_get_contents($url);
if ($content !== false) {
    file_put_contents('index.php', $content);
    echo "Installation complete! <a href='index.php'>Click here to access PHP Error Log Viewer</a>";
} else {
    echo "Installation failed. Please try Option 1.";
}
?>
```

## Configuration

**Custom Error Log Path (Optional)**
```php
// Edit line 5 if needed
define('ERROR_LOG_FILE', '/custom/path/to/error_log');
```

## Screenshots

<p align="center">
  <img src="assets/Screenshot_001.png" alt="PHP Error Log Viewer v2.0" width="400"/>
</p>

## Requirements

- PHP 7.4+ (Pure PHP, no extensions needed)
- Web server with file read/write permissions

## API Endpoints

```bash
GET  ?action=errors        # All errors with stats
GET  ?action=reoccurred    # Reoccurred errors only  
GET  ?action=get_statics   # Analytics dashboard
POST ?action=add_solver    # Mark error as solved
POST ?action=clear_log     # Clear error log
```

## License

MIT License - see [LICENSE](LICENSE) file

## Author

**Ahmad Yousuf** - [0xAhmadYousuf](https://github.com/0xAhmadYousuf)

---
<div align="center">
  <strong>Star this repo if it helped you!</strong>
</div>
