# Joomla Contact Plugin — Custom Reply (`plg_contact_customreply`)

**Location:** `src/plugins/contact/customreply`
**Type:** Joomla Contact plugin, namespace `Alikonweb\Plugin\Contact\CustomReply`

## What it does

Hooks into the site's **contact form submission** flow (`com_contact`) to send the standard contact email, and — if enabled — automatically send an **auto-responder acknowledgment email** back to the person who submitted the form, then optionally redirect them to a custom "thank you" page.

## Trigger event

Subscribes to a single event:

- **`onSubmitContact`** — fires when a visitor submits a site contact form. The plugin bails out immediately if triggered from the administrator client.

## Flow on submission

1. Checks the `com_contact` component's global `custom_reply` setting — if it's off, the plugin does nothing.
2. **Sends the primary contact email** (`_sendEmail`) to the contact's recipient address (looked up from the contact record, or from the linked Joomla user if no explicit `email_to` is set), including any custom fields rendered via `FieldsHelper`, and optionally CCs a copy to the sender if `show_email_copy` is enabled.
3. If the sender provided a **valid email address**, sends a separate **auto-response email** (`sendAutoresponse`) using a dedicated mail template (`plg_contact_customreply.autoresponder`), populated with site name, sender name, subject, and message.
4. Shows a success message (`"Thank you for your email."`) if the primary email went through; logs a warning if the auto-response specifically failed.
5. On the front end, **redirects** the visitor:
   - By default, back to the contact page itself.
   - Or, if a **`redirect_url`** menu item is configured in the plugin settings, to that menu item instead (respecting multilingual URLs).
   - Clears the submitted form data from the session before redirecting.

## Configuration

- **`redirect_url`** — a menu-item picker (`modal_menu` field) letting the admin choose a custom "thank you" landing page instead of the default contact page.
- Standard decorative fields: license note, resource links, version badge (same pattern as the other Alikon plugins).

## Installation logic (`script.php`)

On **install**, it inserts a new row into `#__mail_templates` for `plg_contact_customreply.autoresponder`, registering the subject/body/HTML-body language strings as the editable auto-responder email template (visible/editable under Joomla's Mail Templates manager). On **uninstall**, it removes that row again. Requires Joomla ≥ 5.0 and PHP ≥ 8.1, and auto-publishes itself right after installation.

## In short

It's an **auto-responder add-on for Joomla's contact form**: when someone submits an inquiry, the site owner still gets the normal contact email, but the sender additionally gets an instant "we received your message" confirmation email (editable template), and can be routed to a custom thank-you page instead of the default one.
