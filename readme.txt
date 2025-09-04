=== Job Killer ===
Contributors: jobkillerteam
Tags: jobs, employment, rss, import, automation, job board, wp job manager
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate job listing imports from RSS feeds and APIs with advanced deduplication, scheduling, and WP Job Manager integration.

== Description ==

Job Killer is a comprehensive WordPress plugin that automates the import of job listings from RSS feeds and external APIs. Perfect for job boards, recruitment sites, and businesses looking to aggregate job opportunities from multiple sources.

= Key Features =

* **RSS Feed Import**: Support for multiple RSS feeds with automatic parsing
* **Smart Deduplication**: Prevent duplicate listings with intelligent filtering
* **Automated Scheduling**: Configurable cron jobs for hands-off operation
* **WP Job Manager Integration**: Full compatibility with the popular job board plugin
* **Advanced Filtering**: Age, description length, and content-based filters
* **SEO Optimized**: Automatic structured data (JSON-LD) generation
* **Comprehensive Admin**: Dashboard, logs, statistics, and testing tools
* **API Support**: Integration with Indeed, Adzuna, and other job APIs
* **Performance Optimized**: Built-in caching and rate limiting

= Supported RSS Providers =

* Indeed Brasil
* Catho
* InfoJobs
* Vagas.com
* LinkedIn
* Generic RSS feeds with custom field mapping

= Admin Features =

* **Dashboard**: Real-time statistics and performance monitoring
* **Feed Management**: Easy CRUD interface for RSS feeds
* **API Testing**: Built-in tools for testing connections and debugging
* **Scheduling**: Flexible cron job configuration
* **Logs**: Comprehensive logging with filtering and export
* **Settings**: Organized configuration panels

= Frontend Features =

* **Shortcodes**: `[job-killer-list]`, `[job-killer-search]`, `[job-killer-stats]`, `[job-killer-recent]`
* **Widgets**: Recent jobs, search form, statistics, categories
* **AJAX Search**: Fast, responsive job searching
* **Responsive Design**: Mobile-optimized templates

= Developer Friendly =

* Clean, documented code following WordPress standards
* Extensive hook system for customization
* Modular architecture for easy extension
* Translation ready (i18n)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/job-killer/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Job Killer->Settings screen to configure the plugin
4. Add your first RSS feed via Job Killer->RSS Feeds
5. Configure scheduling via Job Killer->Scheduling

= Minimum Requirements =

* WordPress 6.8 or higher
* PHP 8.1 or higher
* MySQL 5.7 or higher
* cURL extension enabled
* JSON extension enabled

== Frequently Asked Questions ==

= Does this work with WP Job Manager? =

Yes! Job Killer is fully compatible with WP Job Manager and will automatically use its post types and taxonomies when detected.

= Can I import from multiple RSS feeds? =

Absolutely. You can configure unlimited RSS feeds, each with their own settings, categories, and regions.

= How does deduplication work? =

Job Killer uses a combination of job title, company name, and location to identify and prevent duplicate listings.

= Can I customize the import schedule? =

Yes, you can set imports to run every 30 minutes, hourly, every 2 hours, every 6 hours, twice daily, or daily.

= Is there API support? =

Yes, Job Killer supports APIs from Indeed, Adzuna, RemoteOK, and GitHub Jobs, with more providers being added regularly.

= Can I customize the job templates? =

Yes, all templates can be overridden in your theme by copying them to a `job-killer` folder in your theme directory.

== Screenshots ==

1. Dashboard with statistics and performance monitoring
2. RSS Feeds management interface
3. Comprehensive settings panel
4. Real-time logs with filtering
5. API testing and debugging tools
6. Frontend job listings with search
7. Setup wizard for easy configuration

== Changelog ==

= 1.0.0 =
* Initial release
* RSS feed import functionality
* WP Job Manager integration
* Admin dashboard and management tools
* Frontend shortcodes and widgets
* API support for major job boards
* Comprehensive logging system
* SEO optimization with structured data
* Performance optimization with caching
* Translation ready

== Upgrade Notice ==

= 1.0.0 =
Initial release of Job Killer. No upgrade necessary.

== Support ==

For support, feature requests, and bug reports, please visit our [GitHub repository](https://github.com/jobkiller/job-killer) or contact us through the WordPress.org support forums.

== Contributing ==

We welcome contributions! Please see our [GitHub repository](https://github.com/jobkiller/job-killer) for contribution guidelines.

== License ==

This plugin is licensed under the GPLv2 or later. See the LICENSE file for details.