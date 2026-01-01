# Progress - Služba pro stahování oznámení úmrtí

## Implementované kroky

1. Nainstalovány balíčky Spatie Media Library, Browsershot, Guzzle a Symfony DOM Crawler.
2. Publikována migrace pro Media Library a vytvořena migrace pro tabulku death_notices.
3. Vytvořen model DeathNotice s implementací HasMedia interface a konfigurací media collections.
4. Vytvořena abstraktní třída AbstractScraper pro základní scraper funkcionalitu.
5. Implementován SadovyJanScraper pro stahování oznámení z www.sadovyjan.cz.
6. Implementován PSHajdukovaScraper pro stahování oznámení z pshajdukova.cz.
7. Implementován PSBKScraper pro stahování oznámení z psbk.cz s podporou obrázků.
8. Vytvořena služba DeathNoticeService pro správu scraperů a generování PDF.
9. Vytvořen Blade template pro generování HTML-based PDF oznámení.
10. Vytvořen artisan příkaz parte:download s možností výběru zdrojů.
11. Nakonfigurován scheduler pro denní stahování v 16:00.
12. Vytvořen tento Progress.md soubor pro sledování postupu.
13. Napsány Pest testy pro DeathNotice model a artisan příkaz.

## Další kroky

- Nakonfigurovat databázi (MariaDB nebo PostgreSQL) v produkčním prostředí
- Spustit migrace pomocí `php artisan migrate`
- Nainstalovat Node.js dependencies pro Browsershot
- Otestovat stahování z jednotlivých zdrojů pomocí `php artisan parte:download --source=sadovy-jan`
- Spustit scheduler pomocí `php artisan schedule:work` nebo nastavit cron job

## Poznámky

- Hash je generován z kombinace jména, příjmení, data pohřbu a URL zdroje
- První 12 znaků SHA-256 hashe se ukládá do databáze
- PDF soubory jsou připojeny k záznamu pomocí Spatie Media Library
- Browsershot vyžaduje Node.js a Puppeteer pro konverzi obrázků do PDF
- Pro PS BK jsou obrázky stahovány a konvertovány do PDF formátu
