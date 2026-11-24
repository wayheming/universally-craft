# Craft Plugin Store listing

## Name and category

- **Name:** Universally
- **Handle:** `universally`
- **Composer package:** `universally/craft-universally`
- **Category:** Localization
- **Keywords:** translation, localization, multilingual, language switcher
- **Icon:** [Universally logo](src/icon.svg)
- **Website:** [Universally](https://universally.com/)
- **Documentation:** [Setup and troubleshooting](https://github.com/wayheming/universally-craft#readme)
- **Support:** [Universally Support](https://universally.com/docs/contact-support/)
- **Privacy:** [Universally Privacy Policy](https://universally.com/privacy-policy/)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)

## Short description

Translate public Craft CMS pages on the server and give each language its own URL.

## Long description

Universally translates the public pages of your Craft site. Connect a Universally project, select the languages you want to offer, and let visitors switch between them.

The plugin is free to install. A [Universally](https://universally.com/) account is required to translate your site. The [Free plan](https://universally.com/pricing/) includes up to 2,000 translated words for one website and one target language.

- **Translated HTML at its own URL.** Craft serves the translated page at a path such as `/fr/about`. The translated text is present when the page loads, including when JavaScript is disabled.
- **Keep your Twig templates and domain.** The plugin renders your source templates in Craft and serves translations on the same domain.
- **Let visitors switch languages.** The Universally language switcher opens the translation of the page the visitor is viewing.

Requires PHP 8.2 or later, Craft CMS 5.8 or later in the 5.x series, and a Universally project. This version supports the primary Craft site at the root of an origin. Translation usage follows your Universally plan.

After the site owner authorizes the connection, the plugin sends rendered public HTML, the source page URL, and the selected target language to Universally to translate the page. The Documentation link explains how to enable the connection and which requests are eligible.

[Source code on GitHub](https://github.com/wayheming/universally-craft)

## Screenshot captions

1. Universally installed in the Craft control panel.
2. Connect a Craft project to Universally and choose its target languages.
3. A French page at its own URL with the Universally language switcher.
