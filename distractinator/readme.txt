=== Distractinator ===
Contributors:      yourname
Tags:              random, fun, redirect, entertainment, useless
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPL-2.0+
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A curated random redirect to useless-but-fun websites. One button. Infinite procrastination.

== Description ==

Distractinator lets you add a single-button page to your WordPress site that sends visitors to a random "useless" website from your curated list — inspired by theuselessweb.com.

**Features:**

* Add `[distractinator]` to any page or post
* 50 seed websites included on activation
* Fully customisable heading, subheading, button text, and colours via Settings
* Admin panel to add, edit, enable/disable, and delete sites
* Community submission form with admin approval queue
* Bulk import via CSV (url, title columns)
* Weekly dead-link checker (automatic) + crowdsourced reporting from visitors (3 reports = auto-flag)
* WordPress Dashboard widget with live stats
* Rate-limited submissions (3 per IP per hour)
* REST API endpoint: `GET /wp-json/distractinator/v1/random`

== Installation ==

1. Upload the `distractinator` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Create or edit a page and add the shortcode `[distractinator]`
4. Visit **Distractinator** in the admin menu to manage your site list

== Shortcode ==

`[distractinator]`

Optional attributes (override global settings for a specific instance):

* `heading` — Page heading text
* `subheading` — Subheading text
* `button` — Button label

Example:
`[distractinator heading="Waste Some Time" button="Go Somewhere Weird"]`

== CSV Import ==

Your CSV must have a header row. Supported columns:

* `url` — required, must be a valid URL
* `title` — optional, defaults to the hostname

Example:
```
url,title
https://findtheinvisiblecow.com,Find the Invisible Cow
https://cat-bounce.com,Cat Bounce
```

== REST API ==

`GET /wp-json/distractinator/v1/random`

Returns JSON:
```json
{ "id": 42, "title": "Cat Bounce", "url": "https://cat-bounce.com" }
```

== Frequently Asked Questions ==

= How do I add new sites? =

Go to **Distractinator → Add New Site** in your admin menu, enter a title and URL, and publish it.

= Can I disable community submissions? =

Yes — go to **Distractinator → Settings** and uncheck "Allow Public Submissions".

= How does the dead-link checker work? =

Every week WordPress runs a background task that sends a HEAD request to each URL. Any URL returning a 4xx/5xx or timing out is flagged. Flagged URLs are excluded from the random pool. You can also clear the flag manually by editing the site and saving.

= Visitors can report dead links? =

Yes. After clicking the button, a small "Report last link as dead" link appears. After 3 unique reports from different IPs, the site is auto-flagged as dead.

== Screenshots ==

1. Frontend — the one-click random redirect page
2. Admin — Sites list with click counts and dead-link flags
3. Admin — Pending submissions queue
4. Admin — Settings page
5. WordPress Dashboard widget

== Changelog ==

= 1.0.0 =
* Initial release
* 50 seed sites
* Admin UI, submissions queue, CSV import
* Dead-link detection (cron + crowdsourced)
* Customisable shortcode
* Dashboard widget
* REST API endpoint
