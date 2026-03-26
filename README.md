# SEO Preview for Craft CMS

A Craft CMS plugin that adds an SEO preview target to element live previews. See how your entries will look in Google search results while editing.

## Requirements

- PHP 8.2+
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

The plugin adds an "SEO Preview" tab to the live preview of any element that has a URL. It renders a Google-style search result preview using the element's meta tags (title, description, og:image, etc.), so you can see how the page will appear in search results before publishing.

The preview reads the actual meta tags from your templates, so it works with whatever SEO setup you already have (SEOmatic, custom meta tags, etc.).

## Template requirements

Your site templates need to include a partial at `_helpers/seo.twig` that outputs the relevant meta tags for the given entry. The plugin passes the entry to this partial and uses the rendered meta tags to build the preview.

## License

MIT
