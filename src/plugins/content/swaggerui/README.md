# Joomla Content Plugin — Swagger UI (`plg_content_swaggerui`)

**Location:** `src/plugins/content/swaggerui`
**Type:** Joomla Content plugin, namespace `Alikonweb\Plugin\Content\Swaggerui`

## What it does

Lets editors **embed an interactive Swagger UI (OpenAPI) explorer inside a Joomla article**, simply by adding a `{swaggerui}` shortcode/tag to the article body. When the article is rendered, the plugin replaces that tag with a fully working Swagger UI widget that loads and displays the given API spec.

## Trigger event

- **`onContentPrepare`** — runs whenever article content is being prepared for display. It short-circuits immediately if the article text doesn't contain `{swaggerui...}`.

## How the tag works

The plugin matches `{swaggerui ...}` with a regex and replaces every occurrence with a Swagger UI container `<div>`. Inside the tag you can pass optional attributes as `key="value"` pairs:

- **`url`** — the OpenAPI/Swagger spec URL to load. If omitted, it falls back to the demo spec `https://petstore.swagger.io/v2/swagger.json`.
- **`source`** — `local` or `cdn`, overriding the plugin's default asset source for that specific instance.

Example usage in an article: `{swaggerui url="https://api.example.com/openapi.json"}`

## Asset loading

For each tag found, it loads the Swagger UI JS/CSS via Joomla's WebAssetManager (registered in `media/joomla.asset.json`), with two selectable sources:

- **Local** (default) — bundled `swagger-ui-bundle.js`, `swagger-ui-standalone-preset.js`, and `swagger-ui.css` shipped with the plugin under `media/`.
- **CDN** — pulls the same Swagger UI v5.11.0 assets from `cdnjs.cloudflare.com` instead.

It then injects an inline initialization script that waits for `SwaggerUIBundle`/`SwaggerUIStandalonePreset` to be available (polling every 100ms if not yet loaded) and instantiates the Swagger UI widget bound to the `#swagger-ui-container` div, pointing at the given spec URL, with `deepLinking` enabled and the standard "StandaloneLayout".

## Configuration

- **`assets_source`** — global default (`local` or `cdn`) used when a tag doesn't specify its own `source` attribute.
- Standard decorative fields: license note, resource links, version badge.

## In short

It's a **"paste your API docs into an article" plugin**: drop a `{swaggerui url="..."}` tag anywhere in a Joomla article, and it renders a full interactive API explorer (try-it-out console included) right on the page, sourced either from bundled files or a CDN.
