=== Give CiviCRM Integration BETA VERSION ===
Contributers: dandoran-wp, joliverwestbrook
Tags: give donation, give donations, wordpress give, wp give, givewp, give, wp donation, wp donations, civicrm
Requires at least: 4.9
Tested up to: 5.2.3
Requires PHP: 5.6
Stable tag: TBD
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Automatically or manually synchronize Give payments with CiviCRM contributions.

== Description ==

**[Give](http://bit.ly/WPORGGive "Visit the Give website")** is the highest rated, most downloaded, and best supported donation plugin for WordPress. **[CiviCRM](https://civicrm.org "Visit the CiviCRM website")** is the powerful opensource CRM used by more than 10,000 non-profits. Many small- to midsize organizational users of WordPress rely on both. Now CiviVIP's **[Give CiviCRM Integration](https://civivip.com/#plugins "Shop for CiviVIP plug-ins") saves you time and headache by automating the entry of Give payments into CiviCRM as contributions.

* Every time a donor makes a payment, you'll find the data in CiviCRM too.
* If you already have Give payments in your system, you can use the manual sync to automate that data into CiviCRM.
* Give's Recurring Donations add-on is now supported too!

= About the CiviVIP team =

We are a group of experienced developers, implementers, and designers focused on CiviCRM integrations. Each of our teams has a unique focus. We currently have teams with decades of experience in WordPress and Drupal integrations. CiviVIP is your first and last stop in the search for CiviCRM support.

= Connect with CiviVIP =

You will have a dedicated service manager and your service plan will offer the convenience of a fixed monthly cost. We’ll take care of hosting and maintaining your website and CiviCRM as well as supporting you and your entire team.

* **[Request an Invitation](https://civivip.com/#plugins "Request a CiviVIP invitation")**
* **[Shop CiviVIP Plugins](https://civivip.com/#plugins "Shop for CiviVIP plugins")**
* **[CiviVIP Website](https://civivip.com "Visit the CiviVIP website")**

== Version Notice ==

Give CiviCRM Integration is currently in BETA. Please make sure to back up your data before using, and report unexpected behavior or results to your representative at CiviVIP.

== Installation ==

= Minimum Requirements =

* WordPress 4.9 or higher
* PHP version 5.6 or higher
* Give version 2.0 or higher
* CiviCRM version 4.6.38 (stable legacy patch-level) or version 5.4 or higher
* For manual sync, webserver timeout of 5 minutes (300 seconds) (e.g. Apache: TimeOut 300) or greater

= Automatic Installation =

Automatic installation is not supported at this time.

= Manual Installation =

Obtain a .zip of the Give CiviCRM Integration and your license number from your CiviVIP representative. Then, use WordPress's Plugins > Add New > Upload Plugin functionality to install the plugin, and use Give's Donations > Settings > Licenses page to activate it.

== Frequently-Asked Questions ==

Coming soon.

== Changelog ==

= 0.3.0-beta: October 1, 2019 =
* Bugfix: Fix naming and schema incompatibilities
* Improve source tracking

= 0.3.0-beta: September 20, 2018 =
* New: Automated updates.
* Tweak: Enhanced CiviCRM contact lookup to capture more records that may be Give donors and assign to them the integration ID.
* Tweak: Implemented custom CiviCRM "transaction type" for use by Give donation - related CiviCRM contributions.
* Tweak: Reverted functionality that checked php.ini max_execution_time and emitted a nag notice to administrators.
