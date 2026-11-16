# CookieScript Cookie Banner for Magento 2

Adds a [Cookie-Script.com](https://cookie-script.com/) cookie consent banner to a Magento 2 / Adobe Commerce
storefront, with Google Consent Mode v2.

- **Module name:** `CookieScript_Banner`
- **Composer package:** `cookiescript/module-banner`
- **Requires:** Magento Open Source / Adobe Commerce 2.4.4 or newer, PHP 8.1 or newer

## What it does

| Feature | Where to find it |
|---|---|
| **Source link:** paste the banner link from your CookieScript account; choose where in the page the script goes (header or body, first or last) | *Banner* section |
| **Google Consent Mode v2:** default consent state for the 7 consent types plus "wait for update", globally and per region | *Google Consent Mode v2* section |
| **Per website / store view** settings, so every store can have its own banner | Scope switcher at the top of the configuration page |

## Install

### With Composer (recommended)

```bash
composer require cookiescript/module-banner
```

### By downloading the zip

1. Download the module zip from Cookie-Script.com.
2. Create the folder `app/code/CookieScript/Banner/` in your Magento installation.
3. Unzip the files into that folder, so that `registration.php` ends up at `app/code/CookieScript/Banner/registration.php`.

### Then, in both cases

```bash
bin/magento module:enable CookieScript_Banner
bin/magento setup:upgrade
bin/magento setup:di:compile            # production mode only
bin/magento setup:static-content:deploy -f   # production mode only
bin/magento cache:flush
```

## Set up

Open **Stores > Configuration > CookieScript > Cookie Banner**.

### 1. Banner

1. Paste the **CookieScript source link** from your account at Cookie-Script.com, under the *Installation* tab.
   It looks like `https://cdn.cookie-script.com/s/0123456789abcdef0123456789abcdef.js`. Anything else is rejected on save.
2. Choose the **script location** and **position**. To block third-party cookies until the visitor agrees,
   keep the script as high in the page as possible (*Header*, *First* is the default). *Header* means the area where
   Magento prints extra head content, which comes after Magento's own styles and core scripts; *Body*, *First* is the
   very start of the page body.
3. Set **Enable CookieScript banner** to *Yes* and save.

### 2. Google Consent Mode v2

Turn it on in the *Google Consent Mode v2* section. The defaults are printed before the banner script so the banner
can update them when the visitor makes a choice. Add **regional settings** to use different defaults for specific
regions (for example `US-CA`, or `DE, FR`). "Wait for update" is a number from 1 to 60000 ms; an empty regional value uses 500 ms.

The Consent Mode script is printed together with the banner, so it needs the banner to be enabled and have a source link.

### Several stores

Every setting can be changed per website or store view with the scope switcher.

## Good to know

- **Full Page Cache:** the banner is part of the cached page, so saving the settings empties the *Full Page Cache*
  automatically and the change shows on the next visit.
- **Content Security Policy:** the module whitelists `cookie-script.com` and `*.cookie-script.com` (scripts, connections,
  images) and prints inline scripts through Magento's secure renderer, so the banner is allowed by Magento's Content Security Policy.
- **Themes:** the banner is added to the standard `head.additional`, `after.body.start` and `before.body.end` layout
  elements, which the standard Magento themes provide.
- **Uninstall:** `bin/magento module:uninstall --remove-data CookieScript_Banner` deletes the saved settings.

## License

GPL-2.0-or-later. See `LICENSE`.

## Code map

```
etc/adminhtml/system.xml          Settings page (Stores > Configuration > CookieScript)
etc/csp_whitelist.xml             Content Security Policy hosts

Model/Config.php                  Reads the settings
Model/ConsentMode/Sanitizer.php   Validation rules for Google Consent Mode
Model/ConsentMode/ScriptBuilder.php  Builds the gtag("consent", "default", ...) script
Model/Config/Backend/*            Validation when the admin saves settings
Model/Config/Source/*             Dropdown options
Block/Adminhtml/Form/Field/*      "Regional settings" table

ViewModel/Banner.php              Decides what the storefront prints
view/frontend/layout/default.xml  Places the banner (head / body, first / last)
view/frontend/templates/banner.phtml
```
