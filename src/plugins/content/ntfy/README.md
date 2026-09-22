**Location:** `src/plugins/content/ntfy`
**Type:** Joomla Content plugin (`plg_content_ntfy`), namespace `Alikonweb\Plugin\Content\Ntfy`

## What it does

Sends a push notification via [ntfy.sh](https://ntfy.sh) (or a self-hosted/custom ntfy server) whenever a Joomla article is published.

## Trigger events

The plugin subscribes to two Joomla content events:

- **`onContentAfterSave`** — fires when a *new* article is saved directly in a *published* state (`state == 1`).
- **`onContentChangeState`** — fires when one or more existing articles transition *to* published state (e.g. draft → published, batch state change). It queries the `#__content` table for the affected articles and notifies for each one.

Both paths funnel into a shared private method, `sendNtfyNotification()`.

## Notification logic (`sendNtfyNotification`)

1. Reads plugin params: `ntfy_server`, `ntfy_topic`, `ntfy_token`, `ntfy_priority`. If no topic is configured, it silently does nothing.
2. Builds the article's front-end URL via `RouteHelper::getArticleRoute()`.
3. Composes HTTP headers for the ntfy request:
   - `Title` → "New Article: " + article title
   - `Priority` → configured priority (1–5)
   - `Tags` → `newspaper,joomla`
   - `Click` → article URL
   - `Authorization: Bearer <token>` if an access token is set
4. Uses the article's `introtext` (stripped of HTML, truncated to 250 chars) as the notification body, falling back to a default message if empty.
5. POSTs the body to `{server}/{topic}` using Joomla's `HttpFactory`, with a 20s timeout.
6. On failure (non-2xx response or exception) it logs the error and shows an admin error message.

## Configuration fields (`ntfy.xml`)

| Field | Type | Purpose |
|---|---|---|
| `ntfy_server` | url | ntfy server, defaults to `https://ntfy.sh` |
| `ntfy_topic` | text | the topic/channel to publish to (required) |
| `ntfy_token` | password | optional Bearer token for protected topics |
| `ntfy_priority` | list | 1 (Low) to 5 (Max), default 3 (Normal) |

It also includes two custom decorative form fields (`field/links.php`, `field/version.php`) that render a resource-links toolbar (manual, Twitter/X, issue tracker, sponsor) and a version badge in the plugin's config screen — purely UI sugar, no functional impact.

## Supporting files

- **`services/provider.php`** — DI container registration, wires up database/user-factory dependencies for the plugin.
- **`updateserver.xml` / `changelog.xml`** — Joomla update-server manifest for self-updating the extension (points to a GitHub release download and this repo's raw changelog).
- **`language/en-GB/`** — UI strings (labels, descriptions, error/success messages).

**In short:** it's a "publish → notify" integration — every time an article goes live, subscribers of the configured ntfy topic get a push notification with the article title, a snippet, and a direct link.
