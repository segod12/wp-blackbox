# WP Blackbox - Nayan The Developer

WP Blackbox is a local WordPress incident recorder for developers, freelancers, and agencies.

It answers the question every WordPress support ticket eventually asks:

> The site was working before. What changed before it broke?

WP Blackbox records important WordPress changes, errors, slow requests, database growth, and background-job issues into a searchable incident timeline. It also includes an Advisor that turns raw events into evidence, likely cause, confidence score, and suggested next actions.

## Why Install It Early

Install and activate WP Blackbox as soon as you start working on a WordPress site.

The plugin can only explain incidents that happen **after it is activated**. If you install it before plugin/theme/core updates, client changes, WooCommerce work, or maintenance tasks, the timeline will already have the evidence you need when something breaks later.

Recommended setup:

1. Install WordPress.
2. Install and activate WP Blackbox.
3. Continue building or maintaining the site.
4. When an issue happens, open **WP Blackbox -> Incident Timeline**.

## Core Use Cases

Use WP Blackbox when:

- A client says the site suddenly broke.
- A plugin update causes a fatal error.
- A theme update breaks the frontend.
- WooCommerce checkout stops working.
- The WordPress admin becomes slow.
- `admin-ajax.php` becomes overloaded.
- REST API requests become slow.
- Database tables grow unexpectedly.
- WP-Cron events are overdue.
- WooCommerce Action Scheduler jobs are pending or failing.
- A new administrator user is created.
- Important site options are changed.
- An agency needs evidence for a client report.

## Current Features

### Incident Timeline

Records and displays chronological technical events:

- Plugin activated
- Plugin deactivated
- Plugin updated
- Theme switched
- Theme updated
- WordPress core updated
- Important option changed
- New admin user created
- User role changed
- Fatal PHP error detected
- Slow frontend/admin/AJAX/REST request
- HTTP 500 response
- Database snapshot
- Database table growth
- Cron overdue
- Action Scheduler failed jobs
- Action Scheduler pending spike

### Advisor Engine

Each issue event can show:

- Likely cause
- Confidence score
- Evidence
- Related changes before the issue
- Suggested next actions

Example:

> Likely cause: Payment Gateway plugin  
> Confidence: 87/100  
> Evidence: Plugin updated 8 minutes before the fatal error. Fatal error file belongs to that plugin.  
> Suggested action: Roll back or disable the plugin on staging and check PHP compatibility.

### Fatal Error Attribution

Detects fatal PHP errors at shutdown and tries to identify whether the source belongs to:

- Plugin
- MU plugin
- Theme
- WordPress core
- Unknown source

### Slow Request Detection

Tracks slow requests only when thresholds are exceeded:

- Frontend
- Admin
- AJAX
- REST
- Cron
- Login

This avoids logging every normal request.

### Database Growth Monitor

Creates database table snapshots and shows:

- Largest tables
- Fastest-growing tables
- Likely table owner
- Growth warnings

Known table ownership examples:

- `wp_actionscheduler_*` -> Action Scheduler / WooCommerce
- `wp_woocommerce_*` -> WooCommerce
- `wp_wf*` -> Wordfence
- `wp_rank_math*` -> Rank Math
- `wp_yoast*` -> Yoast SEO
- `wp_postmeta` -> WordPress core / many plugins

### Cron / Queue Health

Checks:

- Whether `DISABLE_WP_CRON` is enabled
- Total scheduled WP-Cron events
- Overdue cron events
- Action Scheduler pending actions
- Action Scheduler failed actions
- Top failed Action Scheduler hooks

### Incident Report

Generates a developer-friendly report with:

- Event summary
- Recent changes
- Issues after changes
- Possible root causes
- Evidence
- Confidence score
- Suggested next actions
- Markdown export

## Admin Screens

WP Blackbox adds a top-level WordPress admin menu:

- **Incident Timeline**
- **Database Growth**
- **Cron / Queue Health**
- **Reports**
- **Settings**

## Settings

Configurable options include:

- Enable change tracking
- Enable fatal error tracking
- Enable slow request tracking
- Enable database growth tracking
- Enable cron tracking
- Event retention days
- Slow frontend threshold
- Slow admin threshold
- Slow AJAX threshold
- Slow REST threshold
- Slow cron threshold
- Delete data on uninstall

## Privacy And Safety

WP Blackbox is designed to run locally inside WordPress.

Current version:

- Does not send data to an external server.
- Does not auto-delete data.
- Does not auto-disable plugins.
- Does not perform rollbacks.
- Does not store passwords, cookies, auth headers, payment data, or full POST bodies.
- Restricts admin screens to users with `manage_options`.

## What WP Blackbox Is Not

WP Blackbox is not:

- A security firewall
- A malware scanner
- A backup plugin
- A cache plugin
- A full profiler
- A Query Monitor replacement
- A one-click rollback system

It is a root-cause investigation tool.

## Agency Workflow

For agencies and freelancers:

1. Install WP Blackbox on client sites early.
2. Keep it running during updates and maintenance.
3. When a client reports an issue, open the Incident Timeline.
4. Review what changed before the issue.
5. Open issue events and check Advisor suggestions.
6. Generate an incident report.
7. Send evidence-based findings to the client or plugin/theme vendor.

## Example Incident

Timeline:

- 10:02 AM: Plugin updated: WooCommerce Payments
- 10:08 AM: Fatal PHP error detected
- 10:12 AM: Slow AJAX request detected
- 10:18 AM: HTTP 500 detected on checkout

Advisor output:

- Likely cause: WooCommerce Payments
- Confidence: 89/100
- Evidence: Plugin updated shortly before errors started. Fatal file belongs to the same plugin.
- Suggested next action: Confirm on staging, roll back the plugin, check changelog/PHP compatibility, and send evidence to vendor support.

## Developer Notes

Main plugin structure:

```txt
wp-blackbox/
  wp-blackbox.php
  includes/
    class-activator.php
    class-admin.php
    class-advisor.php
    class-change-collector.php
    class-cron-collector.php
    class-database-collector.php
    class-event-types.php
    class-fatal-error-collector.php
    class-logger.php
    class-option-collector.php
    class-plugin.php
    class-report-generator.php
    class-slow-request-collector.php
    class-source-resolver.php
    class-table-owner-map.php
    class-user-collector.php
  templates/
  assets/
  uninstall.php
```


## Important Disclaimer

WP Blackbox provides evidence-based debugging guidance. It should say “likely cause” or “possible cause,” not “guaranteed cause.”

Final confirmation should happen on staging or with developer review.
