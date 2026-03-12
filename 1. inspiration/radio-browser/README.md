# RubaTron's Radio Browser Installer

![Radio Browser](https://img.shields.io/badge/Radio-Browser-blue?style=for-the-badge&logo=radio)
![Moode Audio](https://img.shields.io/badge/Moode-Audio-red?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.4+-purple?style=for-the-badge)
![Bash](https://img.shields.io/badge/Bash-Script-green?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)

## 🎵 About

**RubaTron's Radio Browser Installer** is a comprehensive installation package for the Radio Browser extension in Moode Audio. This extension allows you to browse and play thousands of internet radio stations directly from your Moode Audio interface.

## 📸 Screenshots

### CLI Installer Menu
```
                    Radio Browser Extension Installer v2.0
                    ==========================================

╔══════════════════════════════════════════════════════════════════════════════╗
║ RADIO BROWSER INSTALLER MENU                                               ║
╠══════════════════════════════════════════════════════════════════════════════╣
║ 1) Install Radio Browser Extension                                      ║
║ 2) Create Backup Only                                                   ║
║ 3) Restore from Backup                                                ║
║ 4) Check System Requirements                                        ║
║ 5) Uninstall Radio Browser Extension                               ║
║ 6) Show Installation Log                                           ║
║ 7) About / Help                                                     ║
║ 0) Exit                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝
```

### Radio Browser Interface
*The Radio Browser interface provides an intuitive way to browse and play thousands of internet radio stations with advanced search and filtering capabilities.*

## 🎵 About

**RubaTron's Radio Browser Installer** is a comprehensive installation package for the Radio Browser extension in Moode Audio. This extension allows you to browse and play thousands of internet radio stations directly from your Moode Audio interface.

## ✨ Features

- 🔄 **Automatic Backup** before installation
- ✅ **System Requirements Check** with detailed reporting
- 🛠️ **One-Click Installation** with proper permissions
- 🔄 **Restore Functionality** from backups
- 🧹 **Clean Uninstall** option
- 📊 **Installation Logging** for troubleshooting
- 🌐 **API Integration** with Radio-Browser.info
- 🎯 **Country Selection** including custom ISO codes
- ⭐ **Top Stations** and search functionality

## 📋 Requirements

- **Moode Audio** (Raspberry Pi based music player)
- **PHP 8.4+** with cURL extension

## 🚀 Installation

### Option 1: Download Complete ZIP (Recommended - No Git Required)
```bash
# Download the complete installer ZIP from GitHub
# Go to: https://github.com/rubatron/RadioBrowser/tree/develop
# Download: radio-browser-installer-complete.zip

# Extract the ZIP file
unzip radio-browser-installer-complete.zip
cd radio-browser-installer-complete

# Make scripts executable
chmod +x installer/*.sh

# Run the beautiful CLI installer
sudo ./installer/radio-browser-cli.sh
```

### Option 2: Clone Repository (Requires Git)
```bash
# Clone the repository
git clone https://github.com/rubatron/RadioBrowser.git
cd RadioBrowser

# Switch to develop branch
git checkout develop

# Make scripts executable
chmod +x installer/*.sh

# Run the installer
sudo ./installer/radio-browser-cli.sh
```

### Option 2: Advanced Script

```bash
# Use the advanced installer script
sudo ./installer/install-radio-browser-advanced.sh
```

## 📖 Usage

### CLI Menu Options

1. **Install Radio Browser Extension** - Complete installation with backup
2. **Create Backup Only** - Backup existing files without installing
3. **Restore from Backup** - Restore from previous backup
4. **Check System Requirements** - Verify system compatibility
5. **Uninstall Radio Browser Extension** - Remove all extension files
6. **Show Installation Log** - View detailed installation logs
7. **About / Help** - Show information about the installer

### Manual Installation

If you prefer manual installation:

```bash
# Copy files to correct locations
sudo cp www/extensions/installed/radio-browser/backend/api.php /var/www/extensions/installed/radio-browser/backend/
sudo cp www/radio-browser.php /var/www/
sudo cp www/js/scripts-radio-browser.js /var/www/js/
sudo cp www/templates/radio-browser.html /var/www/templates/

# Set permissions
sudo chown -R www-data:www-data /var/www/extensions/installed/radio-browser
sudo chown www-data:www-data /var/www/radio-browser.php
sudo chown www-data:www-data /var/www/js/scripts-radio-browser.js
sudo chown www-data:www-data /var/www/templates/radio-browser.html

# Create cache and log directories
sudo mkdir -p /var/local/www/extensions/cache/radio-browser
sudo mkdir -p /var/local/www/extensions/logs
sudo chown -R www-data:www-data /var/local/www/extensions/cache/radio-browser
sudo chown -R www-data:www-data /var/local/www/extensions/logs

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.4-fpm
```

## 📁 Project Structure

```
radio-browser-installer/
├── installer/
│   ├── radio-browser-cli.sh          # Interactive CLI installer with ASCII art
│   └── install-radio-browser-advanced.sh  # Advanced installation script
├── www/
│   ├── extensions/installed/radio-browser/
│   │   ├── backend/
│   │   │   └── api.php               # Radio Browser API handler
│   │   └── radio-browser.css         # Extension-specific CSS styling
│   ├── radio-browser.php             # Main Radio Browser page (loads extension CSS)
│   ├── js/
│   │   └── scripts-radio-browser.js  # Frontend JavaScript
│   └── templates/
│       └── radio-browser.html        # HTML template
├── backups/                          # Auto-created backup directory
└── README.md                         # This file
```

## 🎨 Styling & CSS

The Radio Browser extension uses a dedicated CSS file located in the extension directory:

- **Extension CSS**: `www/extensions/installed/radio-browser/radio-browser.css`
- **Main CSS**: `www/css/extensions.css` (general extensions styling)

The extension loads both CSS files for optimal styling:
- General extensions CSS for shared components
- Extension-specific CSS for Radio Browser unique elements

### CSS Features

- 🎯 **Responsive Grid Layout** - 1-4 columns based on screen size
- 🎨 **Modern Card Design** - Clean station cards with hover effects
- 🌈 **moOde Theme Integration** - Uses CSS variables for consistent theming
- 📱 **Mobile Optimized** - Touch-friendly interface
- ⚡ **Smooth Animations** - Subtle transitions and hover effects
- 🎵 **Playing State Indicators** - Visual feedback for active stations

### Cache Settings

Cache files are stored in `/var/local/www/extensions/cache/radio-browser/` and are automatically managed by the extension.

### Log Files

Installation and runtime logs are available in `/var/local/www/extensions/logs/radio-browser.log`.

## 🐛 Troubleshooting

### Common Issues

1. **HTTP 500 Error**
   - Check PHP cURL extension: `php -m | grep curl`
   - Verify file permissions: `ls -la /var/www/extensions/installed/radio-browser/`
   - Check PHP error logs: `tail -f /var/log/php8.4-fpm.log`

2. **No Radio Stations Loading**
   - Check internet connectivity
   - Verify API endpoints in `api.php`
   - Check cache permissions

3. **Permission Errors**
   - Run installer as root/sudo
   - Verify www-data user exists
   - Check directory permissions

### Debug Mode

Enable debug logging by modifying the `api.php` file:

```php
define('DEBUG_MODE', true);
```

## 🔄 Updates

To update the extension:

1. Create a backup using the CLI menu
2. Download the latest version
3. Run the installer again
4. The backup will be preserved

## 🗑️ Uninstall

To completely remove the extension:

```bash
sudo ./installer/radio-browser-cli.sh
# Select option 5: Uninstall Radio Browser Extension
```

Or manually:

```bash
sudo rm -rf /var/www/extensions/installed/radio-browser
sudo rm -f /var/www/radio-browser.php
sudo rm -f /var/www/js/scripts-radio-browser.js
sudo rm -f /var/www/templates/radio-browser.html
sudo rm -rf /var/local/www/extensions/cache/radio-browser
sudo systemctl restart nginx
```

## 📝 Changelog

### Version 2.0 (January 4, 2026)
- ✨ Added beautiful CLI interface with ASCII art
- 🔄 Improved backup and restore functionality
- ✅ Enhanced system requirements checking
- 🐛 Fixed HTTP 500 errors
- 🎯 Added "Other" ISO country code option
- 📊 Added comprehensive logging

### Version 1.0
- Initial release
- Basic Radio Browser functionality
- Manual installation process

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

If you encounter any issues:

1. Check the troubleshooting section above
2. Review the installation logs
3. Create an issue on GitHub with:
   - Your Moode Audio version
   - PHP version and extensions
   - Error messages/logs
   - Steps to reproduce

## 🤝 Contributing

### Ways to Contribute
- 🐛 **Bug Reports**: Found a bug? [Open an issue](https://github.com/rubatron/radio-browser-installer/issues)
- 💡 **Feature Requests**: Have an idea? [Suggest it](https://github.com/rubatron/radio-browser-installer/issues)
- 🔧 **Code Contributions**: Want to improve the code? Fork and submit a PR
- 📖 **Documentation**: Help improve this README or add tutorials

### Development Setup
```bash
# Clone the repository
git clone https://github.com/rubatron/radio-browser-installer.git
cd radio-browser-installer

# Make scripts executable
chmod +x installer/*.sh

# Test on your Moode Audio system
sudo ./installer/radio-browser-cli.sh
```

### Guidelines
- Follow the existing code style
- Test your changes on a real Moode Audio system
- Update documentation for any new features
- Keep the CLI interface user-friendly
---

## 📊 Project Status

✅ **Production Ready** - Fully tested on Moode Audio systems  
✅ **GitHub Ready** - Complete with documentation, licensing, and structure  
✅ **User Friendly** - Beautiful CLI interface with ASCII art  
✅ **Well Documented** - Comprehensive README with screenshots and examples  
✅ **Modular Design** - Clean separation of concerns and organized CSS  

**Version:** 2.0 - CLI Edition  
**Date:** January 4, 2026  
**Compatibility:** Moode Audio 8.x+ with PHP 8.4+

---

**Made by RubaTron**

*Enjoy listening to internet radio on your Moode Audio player!* 🎵