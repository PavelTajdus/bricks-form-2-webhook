# Bricks Form 2 Webhook

WordPress plugin that sends Bricks Builder form submissions to any webhook URL using the WordPress "Custom" Form Action in Bricks.

## ✨ Features

*   Sends Bricks Builder form data to any webhook URL (e.g., Make.com, Zapier).
*   Supports multiple forms, each with its own webhook URL and custom success/error messages.
*   Simple admin interface under **Settings > Bricks Form 2 Webhook** to manage your webhooks.
*   Debug mode to help troubleshoot form submissions.
*   Automatic updates from GitHub.

## 🔧 Installation

1.  Download the latest `bricks-form-2-webhook.zip` from the [GitHub Releases page](https://github.com/PavelTajdus/bricks-form-2-webhook/releases).
2.  In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3.  Upload the downloaded ZIP file and activate the plugin.
4.  Configure your webhooks under **Settings > Bricks Form 2 Webhook**.

## ⚙️ Configuration

1.  **Get Form ID from Bricks:**
    *   In the Bricks editor, select your form element.
    *   Find the CSS ID (e.g., `bricks-element-fszxsr`).
    *   Copy the unique part of the ID (e.g., `fszxsr`). This is your **Form ID**.
2.  **Add Webhook in Plugin Settings:**
    *   Go to **Settings > Bricks Form 2 Webhook** in your WordPress admin.
    *   Enter the **Form ID** you copied.
    *   Enter your **Webhook URL**.
    *   Optionally, customize the **Success Message** and **Error Message**.
    *   Click **"Add Webhook"**.
3.  **Configure Bricks Form Action:**
    *   In your Bricks form settings, go to **Actions**.
    *   Add a new action and select **Custom** as the action type.
    *   Save your Bricks form.

That's it! Your form submissions will now be sent to the configured webhook.

## 🆘 Troubleshooting

*   **Data not sending?**
    *   Double-check that the **Form ID** in the plugin settings exactly matches the ID from your Bricks form (without the `bricks-element-` prefix).
    *   Ensure the form action in Bricks is set to **"Custom"**.
    *   Enable **Debug Mode** in the plugin settings and check the log for errors after a test submission. The log can be found in the plugin's settings page or downloaded.
    *   Verify your webhook URL is correct and publicly accessible.

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🔄 Changelog

See the [Releases page](https://github.com/PavelTajdus/bricks-form-2-webhook/releases) on GitHub for detailed changelogs.

---

*Inspired by the excellent work of [stingray82's webhooks-for-bricks-forms](https://github.com/stingray82/webhooks-for-bricks-forms) plugin.* 