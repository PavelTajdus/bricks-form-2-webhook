=== Bricks Form to Webhook ===
Contributors: Pavel Tajduš
Donate link: https://www.buymeacoffee.com/paveltajdus
Tags: bricks, bricksbuilder, form, webhook, integration
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.2.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends Bricks Builder form submissions to any webhook URL using WordPress Custom Form Action.

== Description ==

This plugin allows you to easily send data from your Bricks Builder forms to a specified webhook URL. It adds a new "Webhook" custom action to your Bricks forms.

Features:

*   **Multiple Webhooks**: Configure multiple webhooks, each triggered by a specific Form ID.
*   **Custom Success and Error Messages**: Define custom messages for successful submissions or errors for each webhook.
*   **Debug Log**: View a log of webhook requests and responses directly in the plugin settings.
*   **Easy Configuration**: Simple settings page to manage your webhooks.
*   **Auto Updates**: Get the latest version directly from GitHub.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/bricks-form-2-webhook` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to Bricks > Webhooks to configure your webhooks.

== Changelog ==

= 1.2.11 - YYYY-MM-DD =
* Test: Re-testing GitHub Actions for automated release after YAML fix.

= 1.2.10 - YYYY-MM-DD =
* Test: Verifying GitHub Actions for automated release.

= 1.2.9 - YYYY-MM-DD =
* Fixed: Improved ZIP archive creation for releases to ensure correct directory structure (`bricks-form-2-webhook/`) within the ZIP. This aims to definitively resolve issues with incorrect folder names after auto-updates.
* Chore: Added debug logging for the resolved download URL during updates.
* Chore: Removed an old, unused debug ZIP file from the repository.

= 1.2.8 - YYYY-MM-DD =
* Fixed: Auto-update process now downloads the correct ZIP asset from GitHub releases, ensuring the plugin directory is named `bricks-form-2-webhook/` without a version suffix. This should resolve the issue of incorrect plugin folder names after updates and the associated "Plugin file does not exist" warnings.
* Chore: Removed early debug logging code.

= 1.2.7 - YYYY-MM-DD =
* Minor update for testing the auto-update process.

= 1.2.6 - 2024-05-15 =
* Fixed: Plugin deactivation after auto-update by ensuring the ZIP archive for releases has a consistent root directory name (`bricks-form-2-webhook/`). The "ghost" error message about the previous version being deactivated might still appear but the plugin should remain active.

= 1.2.5 - 2024-05-15 =
* Test release to debug auto-update deactivation issue.

= 1.2.4 - 2024-05-15 =
* Test release to debug auto-update deactivation issue.

= 1.2.3 - 2024-05-15 =
* Test release to debug auto-update deactivation issue.

= 1.2.2 - 2024-05-15 =
* Test release to debug auto-update deactivation issue (WP_DEBUG enabled).

= 1.2.1 - 2024-05-14 =
* Fixed: GitHub username constant `BF2W_GITHUB_USER` corrected to `PavelTajdus` for auto-updates.
* Chore: Removed unnecessary `dist/` directory and old debug ZIP.

= 1.2.0 - 2024-05-14 =
* Feature: Finalized UI for webhook settings.
* Feature: Updated asset versions.
* Feature: Ensured all texts are translatable.
* Chore: Removed detailed debug logging for production.

= 1.1.1 - 2024-05-14 =
* Fixed: Webhook data not sending. Switched to `bricks/form/custom_action` hook and improved logging. Data is now sent correctly.

= 1.1.0 - 2024-05-13 =
* Feature: Implemented auto-updates from GitHub.
* Chore: Removed emoji from plugin name in the plugins list for cleaner appearance.

= 1.0.9 - 2024-05-13 =
* Feature: Improved Debug Log (view in plugin, download/clear log).
* Chore: Removed test notices.
* Chore: Added emoji to plugin name in settings menu for better visibility.

= 1.0.8 - 2024-05-12 =
* Fixed: Reverted to a simpler, functional admin page structure under "Settings" as the Bricks menu integration was problematic.
* Feature: Retained multi-form support and improved UI from previous attempts.

= 1.0.7 - 2024-05-12 =
* Attempted fix for Bricks menu and settings page issues (still problematic).

= 1.0.6 - 2024-05-12 =
* Minimal test plugin created to diagnose Bricks submenu issue. Identified incorrect URL generation.

= 1.0.5 - 2024-05-11 =
* Feature: Integrated settings page into Bricks menu.
* Feature: Implemented a table layout for webhook management (CRUD).
* Feature: Added debug mode toggle.
* Chore: Created ZIP, GitHub repo, commit, tag, push.

= 1.0.4 - 2024-05-10 =
* Feature: Added support for multiple forms.
* Feature: Dynamic form management with JavaScript.
* Feature: Individual success/error messages per form.
* Chore: Automatic migration from old single-form configuration.

= 1.0.3 - 2024-05-09 =
* Feature: Added Czech translations.

= 1.0.2 - 2024-05-09 =
* Feature: Added basic CSS for admin page.
* Chore: Created README.md.

= 1.0.1 - 2024-05-09 =
* Feature: Admin page under "Settings" for Webhook URL, Form ID, messages.
* Feature: Basic debug system.

= 1.0.0 - 2024-05-09 =
* Initial version: Basic plugin structure, sends one Bricks form to a webhook.

== Frequently Asked Questions ==

= How do I find my form ID? =

You can find your form ID in Bricks Builder in the form's CSS ID. For example, if the CSS ID is "bricks-element-fszxsr", then the form ID is "fszxsr".

= Does it work with any webhook? =

Yes, the plugin works with any webhook that accepts POST requests with JSON data.

== Upgrade Notice ==

= 1.0.1 =
Changes:
- Updated Author URI to official website
- Minor documentation improvements

This is a maintenance release that updates the plugin's author information.
