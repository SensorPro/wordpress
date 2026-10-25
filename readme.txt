=== SensorPro Signup Forms ===
Contributors: sensorpro
Tags: newsletter, signup form, email marketing, subscribe, gdpr
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a GDPR-friendly signup form to your site that adds contacts straight into your SensorPro email marketing account.

== Description ==

SensorPro Signup Forms connects WordPress to [SensorPro](https://sensorpro.net/), the email marketing platform. Visitors who sign up are added to your SensorPro contacts and tagged the way you choose.

* Shortcode `[sensorpro_form]`, a "SensorPro Signup Form" block and a classic widget.
* Email (required), optional first and last name.
* Consent checkbox with your own wording, plus an optional consent record saved to a contact field.
* Tags applied to every signup, with extra tags per form.
* Optional SensorPro welcome email (off by default).
* People who unsubscribed earlier are resubscribed when they sign up again and tick the consent box.
* Submits without reloading the page, and still works without JavaScript.
* Spam protection: nonce, hidden honeypot field and per-IP rate limit.
* Your API credentials stay on your server. They are never sent to the browser or shown in full in the admin.

You need a SensorPro account with an API user to use this plugin.

= Shortcode options =

`[sensorpro_form title="Join our newsletter" tags="Website, Footer" button="Sign me up"]`

* `title` – heading above the form.
* `tags` – extra SensorPro tags for this form (added to the default tags).
* `button` – button text.

== External services ==

This plugin connects to the SensorPro REST API, provided by SensorPro, to add the people who sign up through your form to your SensorPro account. It cannot work without this service.

The API address is set on the settings page and is https://apinie.sensorpro.net by default. The plugin calls it in these cases:

* **Test connection** (only when an administrator clicks the button on Settings > SensorPro) and **whenever no valid session token is stored** (about once an hour while the form is in use): it signs in at `/auth/sys/signin`, sending your SensorPro API key, organization, API user name and password. SensorPro returns a temporary session token, which is stored on your server.
* **When a visitor submits the form**: it sends the visitor's email address, first and last name (if those fields are shown), the tags you configured, the source name ("WordPress" by default) and, if enabled, a consent record (date, time and site address) to `/api/Contact/UpdateAdd`.
* **When a visitor who already exists in SensorPro submits the form with the consent box ticked**: it looks up that email address at `/api/Contact/GetContacts` and, only if the contact had unsubscribed, resubscribes them at `/api/Contact/ChangeOptOutStatus`.

Nothing is sent to SensorPro when a page with the form is simply viewed. The visitor's IP address is not sent by the plugin.

SensorPro privacy policy: https://sensorpro.net/privacy/
SensorPro acceptable use policy (terms of use): https://sensorpro.net/aup/
SensorPro data processing agreement: https://sensorpro.net/privacy/dpa.pdf

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. In SensorPro, create an API user and note your API key (API > API keys).
4. In WordPress go to Settings > SensorPro, enter the API key, organization, API user and password, save, and click Test connection.
5. Add the form with the shortcode, the block or the widget.

== Frequently Asked Questions ==

= Which list do contacts go into? =

SensorPro uses tags. Every signup gets the tags you set under Settings > SensorPro plus any tags set on the form. Tags that do not exist yet are created. The contact's source (ImportName) is set to "WordPress" by default.

= Does it send a welcome email? =

Only if you turn on "Welcome email" under Settings > SensorPro. It is off by default.

= What happens if the email is already in SensorPro? =

The contact is updated (matched by email) and the new tags are added. If they had unsubscribed and they tick the consent box, they are resubscribed. If the consent box is turned off, an unsubscribed contact stays unsubscribed.

= Test connection mentions my IP address =

Contact SensorPro support.

= I use page caching =

The form includes a security token that expires after about a day. If pages are cached longer than that, exclude pages with the form from the cache or lower the cache lifetime.

== Screenshots ==

1. The signup form.
2. Settings > SensorPro.

== Changelog ==

= 1.0.1 =
* Security hardening: every submitted form field is now sanitized as soon as it is read (no-JS and REST submissions), before validation or any filter runs.
* Settings input is sanitized field by field.

= 1.0.0 =
* First release: signup form (shortcode, block, widget), settings page with connection test, consent checkbox, resubscribe on consent, honeypot and rate limiting.

== Upgrade Notice ==

= 1.0.1 =
Security hardening of form input sanitization.

= 1.0.0 =
First release.
