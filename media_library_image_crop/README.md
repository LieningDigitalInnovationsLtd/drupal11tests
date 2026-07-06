# Media Library Image Crop — setup

## Composer packages

Required for this module:

```bash
composer require drupal/image_widget_crop cweagans/composer-patches
```

`drupal/image_widget_crop` pulls in `drupal/crop` automatically.

`drupal/media_library` is a Drupal core module — enable it in the UI or with Drush; no Composer package.

**Not required:** `drupal/contextual_image_widget_crop`. This module does not use it.

## Cropper.js library

`image_widget_crop` needs the [Cropper](https://github.com/fengyuanchen/cropper) JS library. **`cropper/cropper` is not on Packagist** — you cannot `composer require` it unless you add a custom repository entry first (see option 3 below).

Pick one of these options.

### Option 1 — CDN (easiest, no Cropper install)

`image_widget_crop` loads Cropper from cdnjs by default when no local copy is found. **You can skip installing Cropper entirely.**

After enabling modules, cropping should work without any extra steps. Confirm at `/admin/config/media/crop-widget` that the library/CSS URL fields are **empty** (CDN fallback).

### Option 2 — Manual install (recommended for local/offline)

From the project root:

```bash
mkdir -p web/libraries/cropper
curl -L https://github.com/fengyuanchen/cropper/archive/refs/tags/v4.0.0.tar.gz \
  | tar xz -C web/libraries/cropper --strip-components=1
```

You should have:

```
web/libraries/cropper/dist/cropper.min.js
web/libraries/cropper/dist/cropper.min.css
```

Verify in the browser (replace with your domain):

```
https://YOUR-SITE/libraries/cropper/dist/cropper.min.js
```

That URL must return **200**, not 404.

Then point `image_widget_crop` at the local files.

**Configuration → Media → Crop widget** (`/admin/config/media/crop-widget`):

| Field | Value |
|---|---|
| Custom Cropper library | `libraries/cropper/dist/cropper.min.js` |
| Custom Cropper CSS file | `libraries/cropper/dist/cropper.min.css` |

Or with Drush:

```bash
drush cset image_widget_crop.settings settings.library_url 'libraries/cropper/dist/cropper.min.js' -y
drush cset image_widget_crop.settings settings.css_url 'libraries/cropper/dist/cropper.min.css' -y
drush cr
```

### Option 3 — Composer custom package

**Step 1:** Add this to the `repositories` array in `composer.json` (must be done **before** `composer require`):

```json
{
    "type": "package",
    "package": {
        "name": "cropper/cropper",
        "version": "4.0.0",
        "type": "drupal-library",
        "dist": {
            "url": "https://github.com/fengyuanchen/cropper/archive/refs/tags/v4.0.0.zip",
            "type": "zip"
        },
        "require": {
            "composer/installers": "^2.3"
        }
    }
}
```

**Step 2:** Require the package:

```bash
composer require cropper/cropper:4.0.0
```

Composer installs it to `web/libraries/cropper/` via `composer/installers`. Then set the Crop widget paths as in option 2.

## composer.json snippets (copy/paste)

### Allow the patches plugin

Under `config.allow-plugins`:

```json
"cweagans/composer-patches": true
```

### jQuery 4 patch for image_widget_crop

Under `extra.patches` (Drupal 11 / jQuery 4 — **required**; without this, inline cropping fails with `cropper is not a function`):

```json
"patches": {
    "drupal/image_widget_crop": {
        "3526211: jQuery 4 isFunction polyfill for Cropper": "https://www.drupal.org/files/issues/2026-02-09/image_widget_crop-jquery-isfunction.patch"
    }
}
```

Then run:

```bash
composer update drupal/image_widget_crop
drush cr
```

### Minimal `require` entries (crop stack only)

Add alongside your existing requirements (Cropper optional — see options above):

```json
"cweagans/composer-patches": "^1.7",
"drupal/crop": "^2.6",
"drupal/image_widget_crop": "^3.0"
```

If using Composer for Cropper (option 3), also add:

```json
"cropper/cropper": "4.0.0"
```

Do **not** add `drupal/contextual_image_widget_crop` for this module.

### Example merged `composer.json` (crop-related parts only)

With local Cropper via Composer (option 3):

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.drupal.org/8"
        },
        {
            "type": "package",
            "package": {
                "name": "cropper/cropper",
                "version": "4.0.0",
                "type": "drupal-library",
                "dist": {
                    "url": "https://github.com/fengyuanchen/cropper/archive/refs/tags/v4.0.0.zip",
                    "type": "zip"
                },
                "require": {
                    "composer/installers": "^2.3"
                }
            }
        }
    ],
    "require": {
        "cweagans/composer-patches": "^1.7",
        "composer/installers": "^2.3",
        "cropper/cropper": "4.0.0",
        "drupal/crop": "^2.6",
        "drupal/image_widget_crop": "^3.0"
    },
    "config": {
        "allow-plugins": {
            "cweagans/composer-patches": true,
            "composer/installers": true
        }
    },
    "extra": {
        "patches": {
            "drupal/image_widget_crop": {
                "3526211: jQuery 4 isFunction polyfill for Cropper": "https://www.drupal.org/files/issues/2026-02-09/image_widget_crop-jquery-isfunction.patch"
            }
        }
    }
}
```

After editing `composer.json`:

```bash
composer update drupal/image_widget_crop --with-dependencies
```

Add `cropper/cropper` to the update command only if you use option 3.

## Enable modules

```bash
drush en crop image_widget_crop media_library media_library_image_crop -y
drush cr
```

## Widget configuration

1. Create crop types (Structure → Crop types).
2. On the entity form display, set the media reference field widget to **Media Library Image Crop**.
3. Configure **Bildstil** (image style with a manual crop effect) and **Zuschnittformate** (crop types) on the widget.

## Troubleshooting: `cropper is not a function`

This means Cropper.js never registered as a jQuery plugin (`$(img).cropper(...)`). Common causes:

1. **Cropper JS not installed or 404** — install option 2 above and confirm `https://YOUR-SITE/libraries/cropper/dist/cropper.min.js` returns 200.
2. **Wrong Crop widget paths** — set `library_url` and `css_url` as in option 2, or leave both empty for CDN.
3. **Missing jQuery 4 patch** — apply the patch in `extra.patches` and run `composer update drupal/image_widget_crop`.

### Browser checks

On a page with the crop widget, open DevTools → **Network** and confirm:

- `cropper.min.js` loads (200)
- `jquery-isfunction-polyfill.js` loads (from `image_widget_crop`)
- No JS error before `ImageWidgetCropType.js` runs

If `cropper.min.js` is 404, the library is missing from `web/libraries/cropper/dist/`. If it loads but the error persists, the jQuery 4 patch is almost certainly missing.
