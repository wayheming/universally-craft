# Universally for Craft CMS

The Craft plugin translates public Twig pages on the server. Your source pages keep their URLs, and enabled languages appear under their configured prefixes, for example `/fr/about`. The package supports Craft CMS 5.8 and newer 5.x releases and requires PHP 8.2 or newer.

The plugin is free to install. A [Universally](https://universally.com/) account is required to translate your site. The [Free plan](https://universally.com/pricing/) includes up to 2,000 translated words for one website and one target language.

## Install the plugin

**Craft Plugin Store.** Open the [Universally listing](https://plugins.craftcms.com/universally?craft5=) or search for **Universally** in the Craft control panel’s **Plugin Store** and select **Install**.

**Composer.** Run these commands from your Craft project root:

```sh
composer config repositories.universally-craft vcs https://github.com/wayheming/universally-craft
composer require universally/craft-universally:^0.1
php craft plugin/install universally
```

## Connect your site and translate a page

1. Create a **Craft CMS** project in Universally using the public hostname of your Craft site. Select the source language and add the languages you want to offer. During preview, the Craft CMS project type is available to preview accounts.
2. Review [what the plugin sends](#content-transfer-and-privacy). With the site owner’s express permission, set the content transfer option and the unified API key from Universally Setup in the Craft server environment:

   ```dotenv
   UNIVERSALLY_ALLOW_CONTENT_TRANSFER=true
   UNIVERSALLY_API_KEY=your_unified_project_key
   ```

   Use your production host’s secret manager for the key. Keep the key out of Twig, client JavaScript, and version control.
3. Open the public site in a new browser session. Choose a language in the switcher or visit a translated path directly, such as `/fr/about`.

## Content transfer and privacy

The plugin stays inactive until the site owner authorizes the connection and sets `UNIVERSALLY_ALLOW_CONTENT_TRANSFER=true` with a valid project key on the server. Once enabled, the Craft server sends rendered HTML, the source URL, and the selected target language to Universally when serving an eligible translated page. It also retrieves the project's enabled languages, and the visitor’s browser can load the language switcher from Universally.

To withdraw permission, remove `UNIVERSALLY_ALLOW_CONTENT_TRANSFER` or set it to `false`. Purge any reverse-proxy or full-page caches that may still serve pages containing the switcher.

See the [Universally Privacy Policy](https://universally.com/privacy-policy/) for information about how Universally handles website content.

## Supported sites

Use one primary Craft site with an absolute, origin-level base URL and pretty URLs. The host and protocol visitors use must match the configured Craft base URL. Reserve the enabled Universally language prefixes at the root of that site.

The plugin handles anonymous GETs for public HTML Twig pages. Administrative, authenticated, preview, action, and query-string requests stay in Craft. Pages marked private, non-cacheable, or setting response cookies stay in Craft. For any custom template that renders personalized data for anonymous visitors, set private or no-cache headers before rendering the response.

If the translation service is temporarily unavailable after a language route has been resolved, Craft serves the source document with no-cache and noindex headers. Allow up to one minute for changes to enabled languages. If a reverse proxy or cache sits in front of Craft, forward language paths to Craft and honor the response's caching directives.

The Craft CMS project type in Universally is currently in private preview. The Composer package can be downloaded from the public repository.

## Local verification

Copy `tests/fixtures/*.twig` into the `templates/` folder of a disposable Craft 5 installation. Connect that Craft site to a Universally project with French enabled and German disabled. The translation service or test model must return the French headings in `tests/smoke.mjs`. Then run the HTTP smoke test:

```sh
CRAFT_URL=http://craft.localhost:18182 node --test tests/smoke.mjs
```

To check the opt-in separately, keep the valid API key, remove `UNIVERSALLY_ALLOW_CONTENT_TRANSFER`, and run `node --test tests/consent.mjs` against the same fixture.

Local integration testing also supports `UNIVERSALLY_API_URL`, `UNIVERSALLY_TRANSLATOR_URL`, and `UNIVERSALLY_SCRIPTS_URL` as server-side overrides.
