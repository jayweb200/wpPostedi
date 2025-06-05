=== AI Powered Post Updater ===
Contributors: Jules AI Agent
Tags: seo, content, posts, links, broken links, internal links, automation, ai
Requires at least: 5.0
Tested up to: 6.2
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhance your blog posts with AI-driven insights, automated checks, and content improvements. Features content analysis, (mocked) external research, (mocked) trending keywords, content refinement suggestions, (mocked) search engine indexing requests, basic SEO enhancements, a broken link checker, and automated internal linking.

== Description ==

The AI Powered Post Updater is a comprehensive WordPress plugin designed to help you maintain and improve your website's content quality and SEO performance.

**Key Features (Phase 1 & 2):**

*   **Content Analysis:** Extracts keywords and main topics from your posts.
*   **External Research (Mocked):** Simulates searching the web for updated information related to your post topics and scanning specific websites mentioned in your posts for new content.
*   **Trending Keywords (Mocked):** Simulates fetching trending keywords related to your post's topics to help you stay current.
*   **Content Refinement Suggestions:** Provides basic advice on readability (long sentences/paragraphs), potential passive voice usage, and synonym suggestions for common words.
*   **Search Engine Indexing (Mocked):** Allows you to simulate requests to Google's Indexing API for your posts.
*   **Basic SEO Enhancements:**
    *   Suggests relevant internal links based on post keywords.
    *   Checks for images missing alt text and suggests appropriate alt text.
    *   Automatically adds basic `Article` schema.org markup to your posts.
*   **Dedicated Admin Dashboard:** A central hub ("AI Updater" in the admin menu) to manage and monitor automated features:
    *   **Status & Info:** View plugin version and scheduled tasks.
    *   **Automation Settings:** Configure the Broken Link Checker and Automated Internal Linking modules.
    *   **Logs:** View a running log of plugin activities and clear logs.
    *   **Broken Links Report:** View detailed reports of broken internal and external links, edit or unlink them directly, and trigger manual rescans.
*   **Broken Link Checker:**
    *   Automatically scans your posts for broken internal and external links on a configurable schedule.
    *   Stores results and allows you to manage them from the admin dashboard.
    *   Features per-post re-scan option in the post editor meta box.
*   **Automated Internal Linking (Rule-Based):**
    *   When enabled, automatically adds relevant internal links to your posts when they are saved, based on configurable rules (max links, target categories/tags, etc.).
*   **Post Editor Meta Box:** Integrates all these features directly into the post editing screen, providing suggestions, summaries, and actions relevant to the post being edited.
*   **Task Scheduler & Logger:** Backend systems for managing cron jobs and logging plugin operations.

**Note on "Mocked" Features:** Several features that rely on external APIs or complex AI (like web research, trending keywords, indexing, and advanced content humanization) are currently implemented with "mock" data or simulated actions. This means they demonstrate the intended functionality and user interface but do not make actual external API calls for these specific tasks without further setup (e.g., API keys provided by you for real services).

== Installation ==

1.  Upload the `ai-powered-post-updater` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Configure API keys (for future real integrations) and feature toggles under "Settings" > "AI Post Updater".
4.  Configure automation features (Broken Link Checker, Automated Internal Linking) under the "AI Updater" dashboard menu.

== Frequently Asked Questions ==

= Is this plugin really using AI? =

The plugin is designed with AI concepts in mind for future expansion. Currently, many advanced "AI" features like external research or trending keywords are "mocked" to show functionality. Content analysis and rule-based suggestions are implemented. True AI capabilities (like integration with large language models for content generation/humanization) would require separate API keys and significant setup.

= How often does the Broken Link Checker run? =

You can configure the frequency (e.g., daily, weekly) in the "AI Updater" > "Automation Settings" tab.

= Can the Automated Internal Linking mess up my content? =

The feature is designed to be cautious. It uses DOM manipulation to insert links and has settings to control how many links are added and where they can point. However, as with any automated content modification tool, it's recommended to review its actions, especially initially. You can enable/disable it and configure its aggressiveness in the "AI Updater" > "Automation Settings" tab.

= Where are API keys stored for mocked features? =

The settings page ("Settings" > "AI Post Updater") has fields for API keys. While these are not used by the current mocked implementations for external services, they are there for when you integrate the plugin with actual third-party APIs for those features.

= Are the automated tasks resource-intensive? =

Features like the Broken Link Checker (especially for external links) and Automated Internal Linking (if processing many posts) can be resource-intensive. The Broken Link Checker processes posts in batches to mitigate this. Configure schedules thoughtfully, especially on shared hosting.

== Screenshots ==

1.  The AI Powered Post Updater Meta Box in the post editor.
2.  The main "AI Updater" Admin Dashboard - Status Tab.
3.  The "AI Updater" Admin Dashboard - Automation Settings Tab.
4.  The "AI Updater" Admin Dashboard - Logs Tab.
5.  The "AI Updater" Admin Dashboard - Broken Links Report Tab.
6.  The original Settings page (Settings > AI Post Updater).

== Changelog ==

= 0.2.0 (Current Version - Phase 2) =
*   NEW: Dedicated "AI Updater" admin dashboard with tabs for Status, Automation Settings, Logs, and Broken Links.
*   NEW: Broken Link Checker module - scheduled scanning for internal and external broken links.
*   NEW: Broken Links report in the dashboard with options to edit/unlink broken links.
*   NEW: Manual rescan option for Broken Link Checker.
*   NEW: Automated Internal Linking module (rule-based) - adds internal links on post save based on keywords and settings.
*   NEW: Core Task Scheduler system for managing WP-Cron jobs.
*   NEW: Core Logging module for plugin activities.
*   ENHANCEMENT: Meta box refined to show broken link summary for current post and allow per-post re-scan.
*   ENHANCEMENT: Meta box shows informational messages about Automated Internal Linking status.
*   DEV: Updated plugin structure and classes for new features.

= 0.1.0 (Initial Release - Phase 1) =
*   NEW: Plugin foundation with settings page (for API keys and feature toggles) and meta box.
*   NEW: Content Analysis module (keywords, topics).
*   NEW: External Information Retrieval module (mocked web search, outbound link extraction, (mocked) scan linked sites).
*   NEW: Trending Keywords module (mocked).
*   NEW: Content Refinement/Suggestion module (readability, passive voice, basic synonyms).
*   NEW: Search Engine Indexing module (mocked Google Indexing API requests).
*   NEW: Basic SEO Enhancements module (internal link suggestions, image alt text check, Article schema markup).
*   NEW: User Interface Integration in post editor via a meta box.

== Upgrade Notice ==

= 0.2.0 =
This version introduces significant new features including a dedicated admin dashboard, a broken link checker, and automated internal linking. Please review the new settings under the "AI Updater" admin menu after upgrading.

```
