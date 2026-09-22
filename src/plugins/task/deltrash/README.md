# Joomla Task Plugin — Delete Trash (`plg_task_deltrash`)

**Location:** `src/plugins/task/deltrash`
**Type:** Joomla Task plugin (a "Scheduled Task" routine), namespace `Alikonweb\Plugin\Task\Deltrash`

## What it does

Registers a scheduled task that **permanently empties the trash** across several core Joomla item types, so trashed content doesn't just sit in "Trashed" state forever — it's actually purged from the database, including related rows in secondary tables.

## How it's wired up

The plugin subscribes to three events:

- **`onTaskOptionsList`** → advertises this routine (`deleteTrash`) as an available option in the Scheduled Tasks UI (`com_scheduler`).
- **`onExecuteTask`** → runs the routine when the scheduled task fires.
- **`onContentPrepareForm`** → injects its custom parameter form (`forms/deltrash_parameters.xml`) into the task edit screen.

Before doing any deletion, it calls `setGrant()`, which finds a Super User and loads their identity into the session — necessary because deleting some item types (e.g. categories, workflow-tracked articles) requires elevated ACL permissions when running from a CLI/cron context with no logged-in user.

## What it can delete (each toggle is independently configurable)

| Option | Behavior |
|---|---|
| **Articles** | Permanently deletes all trashed articles (state = -2) and cleans up related rows: `#__content_frontpage`, `#__contentitem_tag_map`, `#__ucm_content`, `#__ucm_base`, version history in `#__history`, workflow associations (`#__workflow_associations`), and cleans language associations. |
| **Categories** | Deletes trashed categories for one or more selected components (e.g. `com_content`, `com_contact`). Component selection is required and validated via a custom form rule (`JFormRuleConditionalcategory`) when this option is enabled. |
| **Contacts** | Deletes trashed contacts (`com_contact`). |
| **Menu Items** | Deletes trashed menu items, scoped to Site and/or Administrator menus. |
| **Modules** | Deletes trashed modules, scoped to Site and/or Administrator client. |
| **Redirects** | Deletes trashed redirect links (`com_redirect`); optionally can also *purge* all redirects first. |
| **Tags** | Deletes trashed tags (`com_tags`). |
| **Tasks** | Deletes trashed scheduled tasks (`com_scheduler`) — i.e. it can clean up trashed task definitions, including potentially itself if trashed. |

Each deletion pass logs a summary message to the task log (e.g. *"Articles deleted: 5"*, *"com_content Categories deleted: 2"*).

## Configuration

- **`forms/deltrash_parameters.xml`** — per-task form with a switch for each item type above, plus dependent fields (`components`, `menutype`, `moduletype`, `redirectspurge`) that appear via `showon` when their parent switch is enabled.
- **`rules/conditionalcategory.php`** — custom validation rule ensuring at least one component is selected when the "Categories" option is turned on.
- The plugin's own config screen (`deltrash.xml`) just shows license info and a resource-links/version widget (same decorative pattern as the ntfy plugin).

## In short

It's a **cron-driven "empty trash" housekeeping tool**: a site admin schedules it (e.g. nightly/weekly) and picks which content types should have their trashed items hard-deleted, with Joomla-specific cleanup of the secondary/orphaned database rows that a plain "delete" wouldn't otherwise touch.
