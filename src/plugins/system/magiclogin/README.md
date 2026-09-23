# Joomla System Plugin — Magic Login (`plg_system_magiclogin`)

**Location:** `src/plugins/system/magiclogin`
**Type:** Joomla System plugin, namespace `Alikonweb\Plugin\System\MagicLogin`

## What it does

Adds **passwordless "magic link" login** to the Joomla site login form: if someone tries to log in using their **email address** as the username, instead of failing, the plugin emails them a one-click login link. Clicking it logs them straight in — no password needed.

## Trigger event

Subscribes to a single event, checked on every front-end request:

- **`onAfterInitialise`** — skipped entirely for the admin client. Two things can happen here:
  1. A `magic_token` GET parameter is present → validate and process it (`processMagicToken`).
  2. A POST login attempt is detected where the submitted `username` is a valid email address → send a magic link instead (`sendMagicLink`), then redirect back with an info message.

## Sending the magic link

1. Checks **rate limiting** first (see below) — bails out silently if exceeded.
2. Looks up an active, non-blocked user by that email; if none exists, does nothing (deliberately doesn't reveal whether the address is registered).
3. Generates a cryptographically secure 32-byte token (`random_bytes`), hashes it with **Argon2id**, and stores it in a dedicated `#__magiclogin_tokens` table along with the user ID, expiry timestamp, requester IP, and user-agent.
4. Sends an email via Joomla's `MailTemplate` (`plg_system_magiclogin.magiclink`) containing the magic link (`?magic_token=...`) and the configured expiry time.

## Consuming the magic link

When a `magic_token` shows up in a request:

1. Sets `X-Frame-Options: DENY` and `X-Content-Type-Options: nosniff` headers.
2. Purges expired tokens from the table.
3. Looks up candidate tokens matching the **current IP + user-agent**, then verifies the token against the stored Argon2id hash with `password_verify` — binding the token to the original browser/IP, not just its value.
4. If valid: loads the user, sets them into the session (logged in), updates their last-visit date, writes an entry to Joomla's Action Log (`com_actionlogs`), deletes the used token, shows a success message, and redirects — either to the site root or to a configured menu item.
5. If invalid/expired: shows an "invalid or expired" error message.

## Rate limiting

Configurable (on by default): counts how many tokens have been issued to a user within a rolling time window and blocks new magic-link requests once the max is reached (defaults: 3 attempts / 5 minutes), cleaning out old rows as it goes.

## Configuration

| Setting | Purpose |
|---|---|
| `token_expiry` | Minutes a magic link stays valid (default 15, range 5–1440) |
| `rate_limit_enabled` | Toggle rate limiting on/off |
| `rate_limit_max_attempts` | Max requests per window (default 3) |
| `rate_limit_window` | Window length in minutes (default 5) |
| `login` | Menu item to redirect to after a successful magic login |

## Installation (`script.php`)

On install, it creates the `#__magiclogin_tokens` table (with MySQL/PostgreSQL-specific DDL, indexed on `user_id`, `expires`, `ip_address`, unique on `token`) and registers the `plg_system_magiclogin.magiclink` row in `#__mail_templates` so the email text is editable from Joomla's Mail Templates manager. On uninstall, the table is dropped. Requires Joomla ≥ 5.0 and PHP ≥ 8.1, and auto-publishes on install.

## In short

It's a **passwordless-login fallback**: type your email into the normal login form, and instead of an error, you get an emailed one-time link (IP/user-agent-bound, Argon2id-hashed, rate-limited, expiring) that logs you in with a single click.
