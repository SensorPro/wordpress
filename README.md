# SensorPro Signup Forms for WordPress

A WordPress plugin that adds a GDPR-friendly signup form to your site and sends new contacts straight into your [SensorPro](https://sensorpro.net/) account.

## Features

- `[sensorpro_form]` shortcode, a **SensorPro Signup Form** block and a classic widget
- Email (required), optional first and last name
- Consent checkbox with your own wording, plus an optional consent record on the contact
- Default tags for every signup, with extra tags per form
- Resubscribes people who unsubscribed earlier, but only when they tick the consent box
- Optional SensorPro welcome email (off by default)
- Submits without a page reload and still works without JavaScript
- Nonce, honeypot and per-IP rate limiting
- API credentials stay on the server and are never sent to the browser

## Requirements

- WordPress 6.0 or later, PHP 7.4 or later
- A SensorPro account with an API user and API key

## Install

1. Download the latest zip from [Releases](https://github.com/SensorPro/wordpress/releases), or clone this repository into `wp-content/plugins/sensorpro`.
2. Activate **SensorPro Signup Forms**.
3. Go to **Settings > SensorPro**, enter your API key, organization, API user and password, save, then click **Test connection**.
4. Add the form with the shortcode, the block or the widget.

```
[sensorpro_form title="Join our newsletter" tags="Website, Footer" button="Sign me up"]
```

## How it talks to SensorPro

The plugin signs in at `POST /auth/sys/signin` (header `x-apikey`, body with organization, user and password) and adds or updates contacts with `POST /api/Contact/UpdateAdd/{token}`, matched by email. For consented re-signups of unsubscribed contacts it uses `GetContacts` and `ChangeOptOutStatus`. See `readme.txt` for the full list of data sent. API reference: https://sensorpro.net/api/

## Hooks

- `sensorpro_contact` (filter): change the contact record before it is sent
- `sensorpro_upsert_body` (filter): change the UpdateAdd request body
- `sensorpro_rate_limit` (filter): attempts per IP per 10 minutes (default 5)
- `sensorpro_resubscribe_on_consent` (filter): return false to never resubscribe
- `sensorpro_client_ip` (filter): visitor IP used for rate limiting
- `sensorpro_signup_success`, `sensorpro_signup_failed`, `sensorpro_resubscribe_result` (actions)

## License

GPLv2 or later. See [LICENSE](LICENSE).
