---
title: Documentation
navigation:
  label: Documentation
---

## LaraPaper (PHP/Laravel)

LaraPaper is a self-hostable implementation of a TRMNL server (BYOS), built with Laravel.
It allows you to manage TRMNL devices, generate screens using **native plugins** (Screens API, Markup), **recipes** (130+ from the [OSS community catalog](https://bnussbau.github.io/trmnl-recipe-catalog/), 700+ from the [TRMNL catalog](https://trmnl.com/recipes), or your own), or the **API**, and can also act as a **proxy** for the native cloud service (Core). With over 50k downloads and 200+ stars, it’s the most popular community-driven BYOS.

### Key Features

* 📡 Device Information – Display battery status, WiFi strength, firmware version, and more.
* 🔍 Auto-Join – Automatically detects and adds devices from your local network.
* 🖥️ Screen Generation – Supports Plugins (including Mashups), Recipes, API, Markup, or updates via Code.
  * Support for TRMNL [Design Framework](https://trmnl.com/framework)
  * Compatible open-source recipes are available in the [community catalog](https://bnussbau.github.io/trmnl-recipe-catalog/)
  * Import from the [TRMNL community recipe catalog](https://trmnl.com/recipes)
  * Supported Devices
    * TRMNL OG (1-bit & 2-bit)
    * SeeedStudio TRMNL 7,5" (OG) DIY Kit
    * Seeed Studio (XIAO 7.5" ePaper Panel)
    * reTerminal E1001 Monochrome ePaper Display
    * Custom ESP32 with TRMNL firmware
    * E-Reader Devices
      * KOReader ([trmnl-koreader](https://github.com/usetrmnl/trmnl-koreader))
      * Kindle ([trmnl-kindle](https://github.com/usetrmnl/larapaper/pull/27))
      * Nook ([trmnl-nook](https://github.com/usetrmnl/trmnl-nook))
      * Kobo ([trmnl-kobo](https://github.com/usetrmnl/trmnl-kobo))
    * Android Devices with [trmnl-android](https://github.com/usetrmnl/trmnl-android)
    * Raspberry Pi (HDMI output) [trmnl-display](https://github.com/usetrmnl/trmnl-display)
* 🔄 TRMNL API Proxy – Can act as a proxy for the native cloud service (requires TRMNL Developer Edition).
    * This enables a hybrid setup – for example, you can update your custom Train Monitor every 5 minutes in the morning, while displaying native TRMNL plugins throughout the day.
* 🌙 Dark Mode – Switch between light and dark mode.
* 🐳 Deployment – Dockerized setup for easier hosting (Dockerfile, docker-compose).
* 💾 Flexible Database configuration – uses SQLite by default, also compatible with MySQL or PostgreSQL 
* 🛠️ Devcontainer support for easier development.

### Support ❤️
This repo is maintained voluntarily by [@bnussbau](https://github.com/bnussbau).

Support the development of this package by purchasing a TRMNL device through the referral link: https://trmnl.com/?ref=laravel-trmnl. At checkout, use the code `laravel-trmnl` to receive a $15 discount on your purchase.

or

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/bnussbau)

[GitHub Sponsors](https://github.com/sponsors/bnussbau/)

### Releated Work
* [bnussbau/laravel-trmnl-blade](https://github.com/bnussbau/laravel-trmnl-blade) – Blade Components on top of the TRMNL Design System
* [bnussbau/trmnl-pipeline-php](https://github.com/bnussbau/trmnl-pipeline-php) – Browser Rendering and Image Conversion Pipeline with support for TRMNL Models API
* [bnussbau/trmnl-recipe-catalog](https://github.com/bnussbau/trmnl-recipe-catalog) – A community-driven catalog of public repositories containing trmnlp-compatible recipes.

### License
[MIT](LICENSE.md)

