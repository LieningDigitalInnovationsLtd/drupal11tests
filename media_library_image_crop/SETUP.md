# Media Library Image Crop — setup

## Composer packages

Required for this module:

```bash
composer require drupal/image_widget_crop cweagans/composer-patches
```

`drupal/image_widget_crop` pulls in `drupal/crop` automatically.

`drupal/media_library` is a Drupal core module — enable it in the UI or with Drush; no Composer package.

**Not required:** `drupal/contextual_image_widget_crop`. This module does not use it.

### Cropper.js library

`image_widget_crop` expects Cropper at `web/libraries/cropper`. Add the custom package repository and require the library:

```bash
composer require cropper/cropper:4.0.0
```

If `cropper/cropper` is not on Packagist in your project, add this under `repositories` in `composer.json` first:

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

## composer.json snippets (copy/paste)

### Allow the patches plugin

Under `config.allow-plugins`:

```json
"cweagans/composer-patches": true
```

### jQuery 4 patch for image_widget_crop

Under `extra.patches` (Drupal 11 / jQuery 4 — without this, inline cropping can fail):

```json
"patches": {
    "drupal/image_widget_crop": {
        "3526211: jQuery 4 isFunction polyfill for Cropper": "https://www.drupal.org/files/issues/2026-02-09/image_widget_crop-jquery-isfunction.patch"
    }
}
```

### Minimal `require` entries (crop stack only)

Add alongside your existing requirements:

```json
"cweagans/composer-patches": "^1.7",
"cropper/cropper": "4.0.0",
"drupal/crop": "^2.6",
"drupal/image_widget_crop": "^3.0"
```

Do **not** add `drupal/contextual_image_widget_crop` for this module.

### Example merged `composer.json` (crop-related parts only)

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

After editing `composer.json`, run:

```bash
composer update drupal/image_widget_crop cropper/cropper --with-dependencies
```

## Enable modules

```bash
drush en crop image_widget_crop media_library media_library_image_crop -y
drush cr
```

## Widget configuration

1. Create crop types (Structure → Crop types).
2. On the entity form display, set the media reference field widget to **Media Library Image Crop**.
3. Configure **Bildstil** (image style with a manual crop effect) and **Zuschnittformate** (crop types) on the widget.
