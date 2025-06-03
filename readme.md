# Bricks Form 2 Webhook

WordPress plugin that sends Bricks Builder form submissions to any webhook URL using WordPress Custom Form Action.

## 📋 Features

- ✅ **Multiple Forms Support** - Configure multiple forms with different webhook URLs
- ✅ **Professional Admin Interface** - Clean table layout inspired by best practices
- ✅ **Edit & Delete Webhooks** - Full CRUD operations for webhook management
- ✅ **Integrated with Bricks Menu** - Accessible directly from Bricks Builder menu
- ✅ Send Bricks Builder form data to any webhook URL
- ✅ Support for Make.com, Zapier, and other webhook services
- ✅ Enhanced debug system with toggle mode
- ✅ Auto-update system via GitHub
- ✅ Customizable success/error messages per form
- ✅ Secure data handling with WordPress standards
- ✅ Migration from previous versions

## 🔧 Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Bricks > Webhook for Forms** to configure

## ⚙️ Configuration

### Step 1: Access Plugin Settings
1. Navigate to **Bricks > Webhook for Forms** in your WordPress admin
2. The plugin integrates seamlessly with Bricks Builder menu

### Step 2: Add Form Webhooks
1. Fill in the **"Add New Form Webhook"** section:
   - **Form ID**: Enter the Bricks form ID (without "bricks-element-" prefix)
   - **Webhook URL**: Enter your webhook URL (Make.com, Zapier, etc.)
   - **Success Message**: Custom message for successful submissions (optional)
   - **Error Message**: Custom message for failed submissions (optional)
2. Click **"Add Webhook"**

### Step 3: Get Form ID from Bricks
1. Go to your Bricks form in the editor
2. Select the form element
3. Look at the CSS ID field (e.g., `bricks-element-fszxsr`)
4. Copy only the part after `bricks-element-` (e.g., `fszxsr`)
5. Use this ID in the plugin settings

### Step 4: Configure Bricks Form
1. In Bricks form settings, go to **Actions**
2. Add a new action
3. Select **Custom** as the action type
4. Save the form

## 🎛️ Management Features

### Webhook Management
- **Add**: Create new webhook configurations
- **Edit**: Modify existing webhook settings
- **Delete**: Remove webhook configurations with confirmation
- **View**: See all configured webhooks in a clean table format

### Debug System
- **Toggle Debug Mode**: Enable/disable debug logging
- **Real-time Information**: View last submission details when debug is enabled
- **Comprehensive Logging**: Form ID matching, webhook responses, error details

### Table Features
- **Sortable Columns**: Form ID, Webhook URL, Messages, Actions
- **External Links**: Click webhook URLs to test them
- **Quick Actions**: Edit and Delete buttons for each webhook
- **Responsive Design**: Works on all device sizes

## 🚀 Auto-Update

The plugin supports automatic updates via GitHub releases. When a new version is available, you'll see an update notification in your WordPress admin.

## 📊 How It Works

1. User submits a Bricks form with "Custom" action
2. Plugin extracts form ID from submission
3. Finds matching webhook configuration by Form ID
4. If found, sends form data as JSON to configured webhook URL
5. Displays configured success/error message to the user
6. Logs debug information (if debug mode is enabled)

## 🔄 Migration Support

### From v1.0.4 and Earlier
All previous configurations are automatically migrated to the new table-based interface. No manual action required!

### From Other Webhook Plugins
The plugin uses standard WordPress practices and can coexist with other webhook solutions.

## 🔒 Security

- All data is properly sanitized and escaped
- WordPress nonces protect admin forms
- Capability checks ensure only administrators can modify settings
- Secure HTTP requests with proper error handling
- Form ID validation prevents common mistakes

## 📝 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Bricks Builder theme/plugin
- Valid webhook URL(s)

## 🆘 Troubleshooting

### Form not sending data?
1. Check that Form ID matches exactly (without `bricks-element-` prefix)
2. Ensure form action is set to "Custom" in Bricks
3. Enable debug mode and check debug information
4. Verify webhook URL is accessible

### Multiple forms issues?
1. Ensure each form has unique Form ID
2. Check that all required fields are filled
3. Review configured forms list in debug information
4. Test each webhook URL independently

### Plugin not appearing in Bricks menu?
1. Ensure Bricks Builder is active
2. Plugin will fallback to Settings menu if Bricks is not detected
3. Check user permissions (requires `manage_options` capability)

### Getting errors?
1. Enable debug mode for detailed error information
2. Test webhook URLs independently
3. Ensure proper PHP/WordPress versions
4. Check server error logs

## 🎨 Interface Highlights

### Clean Table Layout
- Professional appearance matching WordPress standards
- Easy-to-scan webhook configurations
- Quick action buttons for common tasks

### Smart Form Validation
- Real-time validation feedback
- Common mistake prevention (e.g., including "bricks-element-" prefix)
- Required field highlighting

### Enhanced User Experience
- Auto-focus on form fields
- Keyboard shortcuts (Ctrl+S to save, Esc to cancel)
- Confirmation dialogs for destructive actions
- Success/error notifications

## 🤝 Support

For support and questions:
- Author: Pavel Tajdus
- Website: https://www.tajdus.cz
- GitHub: https://github.com/paveltajdus/bricks-form-2-webhook

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🔄 Changelog

### Version 1.0.5
- ✨ **NEW**: Integrated with Bricks Builder menu system
- ✨ **NEW**: Professional table-based admin interface
- ✨ **NEW**: Edit webhook functionality
- ✨ **NEW**: Enhanced form validation with real-time feedback
- ✨ **NEW**: Debug mode toggle (only logs when enabled)
- ✨ **NEW**: Improved user experience with keyboard shortcuts
- ✨ **NEW**: Smart Form ID validation (prevents common mistakes)
- 🎨 **IMPROVED**: Clean, modern interface inspired by best practices
- 🎨 **IMPROVED**: Better responsive design
- 🎨 **IMPROVED**: Enhanced accessibility
- 🐛 **FIXED**: Better error handling and user feedback
- 🔧 **CHANGED**: Simplified JavaScript (removed complex dynamic forms)

### Version 1.0.4
- ✨ **NEW**: Multiple forms support
- ✨ **NEW**: Dynamic form management interface
- ✨ **NEW**: Individual success/error messages per form
- ✨ **NEW**: Enhanced debug system for multiple forms
- ✨ **NEW**: Form validation with visual feedback
- ✨ **NEW**: Auto-migration from single form configuration
- 🎨 **IMPROVED**: Modern admin interface with better UX
- 🎨 **IMPROVED**: Responsive design for mobile devices
- 🐛 **FIXED**: Better error handling and user feedback

### Version 1.0.3
- Initial release
- Basic webhook functionality
- Admin settings page
- Debug system
- Auto-update support

---

**Made with ❤️ for the WordPress and Bricks Builder community**

*Inspired by the excellent work of [stingray82's webhooks-for-bricks-forms](https://github.com/stingray82/webhooks-for-bricks-forms) plugin.* 