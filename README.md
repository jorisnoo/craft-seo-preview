# SEO Preview for Craft CMS

A Craft CMS plugin that adds an SEO preview target to element live previews. See how your entries will look in Google search results while editing.

## Requirements

- PHP 8.4+
- Craft CMS 5.0+

## Installation

```bash
composer require jorisnoo/craft-seo-preview
```

Then install the plugin from the Craft control panel or by running:

```bash
php craft plugin/install seo-preview
```

## How it works

The plugin adds an “SEO Preview” target to elements with a URL. It shows Google, Facebook and X cards using the title, description and social tags in the preview page’s head. The target uses the edited site’s domain, language and URL. Craft preview links work without a site-domain login and grant access only to the element and site Craft selected. Direct visits require a login, access to the site and permission to view the element.

## Template requirements

For template-driven metadata, create `_helpers/seo.twig` in your site templates. It receives `entry` (including the preview draft) and should output the same `<title>` and `<meta>` tags as your public layout. The preview does not render that layout.

For SEOmatic or other head integrations, the controller sets Craft’s matched element before rendering and includes Craft’s `head()`. If your integration needs per-page Twig setup, put that setup in the partial; installing an SEO plugin alone does not guarantee it will emit metadata for a custom route. Without a partial the preview shows a notice and reads any metadata registered in the head.

The preview uses local JavaScript and CSS assets, with no inline handlers, script evaluation, or external JavaScript dependencies. Your CSP must permit Craft’s asset origin and any preview image origins.

## License

MIT

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
