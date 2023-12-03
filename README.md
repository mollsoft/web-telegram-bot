![Pest Laravel Expectations](https://banners.beyondco.de/Web%20Telegram%20Bot.png?theme=light&packageManager=composer+require&packageName=mollsoft%2Fweb-telegram-bot&pattern=architect&style=style_1&description=Make+Telegram+Bots+like+Website+using+Laravel&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg
)

<a href="https://packagist.org/packages/mollsoft/web-telegram-bot" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/v/mollsoft/web-telegram-bot.svg?style=flat&cacheSeconds=3600" alt="Latest Version on Packagist">
</a>

<a href="https://www.php.net">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/badge/php-%3E=8.2-brightgreen.svg?maxAge=2592000" alt="Php Version">
</a>

<a href="https://laravel.com/">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/badge/laravel-%3E=10-red.svg?maxAge=2592000" alt="Php Version">
</a>

<a href="https://packagist.org/packages/mollsoft/web-telegram-bot" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/dt/mollsoft/web-telegram-bot.svg?style=flat&cacheSeconds=3600" alt="Total Downloads">
</a>

<a href="https://mollsoft.com">
    <img alt="Website" src="https://img.shields.io/badge/Website-https://mollsoft.com-black">
</a>

<a href="https://t.me/mollsoft">
    <img alt="Telegram" src="https://img.shields.io/badge/Telegram-@mollsoft-blue">
</a>

---

**Web Telegram Bot** is a Laravel package for create Telegram Bots like Websites. Internal routing, sessions, forms, templates, layouts. 

This module allows you to create Telegram bots using Laravel and Telegraph package similar to creating a website.

You can contact me for help.

# Installation

You can install the package via composer:

```bash
composer require mollsoft/web-telegram-bot

php artisan web-telegram-bot:install

php artisan migrate
```

In file ```app/Providers/RouteServiceProvider.php``` add lines in ```$this->routes(function () {```:
```php
if( is_file(base_path('routes/telegraph.php')) ) {
    Route::middleware('telegraph')
        ->name('telegraph.')
        ->prefix('telegraph')
        ->group(base_path('routes/telegraph.php'));
}
```

After you need create Telegram Bot using Telegraph instruction.

# Commands
Add Telegram Bot

```bash
php artisan telegraph:new-bot
```

Manual polling updates from Telegram Bot:

```bash
php artisan telegraph:polling BOT_ID --debug
```

Manual live updates from Telegram Bot:

```bash
php artisan telegraph:live --debug
```

# Views examples

```html
<message>
    <video src="{{ resource_path("media/logo.mp4") }}" />
    <reply-keyboard>
        <row>
            <columm>Menu 1</columm>
            <column>Menu 2</column>
        </row>
        <row>
            <columm>Menu 3</columm>
            <column>Menu 4</column>
        </row>
    </reply-keyboard>
</message>
<message>
    <p>Hello! Choice you language</p>
    <keyboard>
        <row>
            <column>
                <button value="language:en">English</button>
            </column>
        </row>
        <row>
            <column>
                <button value="language:ru">Russian</button>
            </column>
        </row>
        <row>
            <column>
                <a href="{{ route('contacts') }}">Support</a>
            </column>
        </row>
    </keyboard>
</message>
```

## How make 404 page?

Create file `resources/views/telegraph/errors/404.blade.php`.
