# AGENTS.md

Pokyny pro agentické nástroje (Claude, Cursor, Copilot, atd.) pracující v tomto repozitáři. 
Cíl: bezpečné, konzistentní a předvídatelné úpravy Laravel aplikace `parte`.

---

## 1. Build / test / lint příkazy

Vždy předpokládaj standardní Laravel prostředí (PHP 8.4+, Composer, Node, SQLite/MySQL test DB).

- `composer install` – nainstaluj PHP závislosti.
- `npm install` – nainstaluj JS/CSS závislosti (jen pokud pracuješ s frontendem nebo buildem).
- `php artisan key:generate` – jednorázově po vytvoření `.env`.
- `php artisan migrate` – spusť migrace (pro lokální/testovací DB).

**Assets (Vite):**
- `npm run dev` – vývojový server / watch pro frontend.
- `npm run build` – produkční build assetů (spouštěj jen, když to dává smysl pro úkol).

**Testy (Pest):**
- `./vendor/bin/pest` – spusť všechny testy.
- `./vendor/bin/pest tests/Feature/DeathNoticeServiceTest.php` – jediný testovací soubor.
- `./vendor/bin/pest --filter="death notice can be created with valid data"` – jediný test dle názvu.
- `php artisan test` – alternativa, preferuj ale Pest přímo.

**Lint / formátování:**
- Pokud je v projektu nainstalován Laravel Pint:
  - `./vendor/bin/pint` – automatický formátovač PHP kódu.
- Neinstaluj nové nástroje (PHP-CS-Fixer, ESLint, atd.) bez výslovného pokynu uživatele.

---

## 2. Struktura projektu a obecné zásady

- Framework: Laravel 12.x, testy: Pest (`tests/Feature`, `tests/Unit`).
- Doménové jádro této aplikace:
  - `app/Models/DeathNotice.php` – model parte.
  - `app/Services/DeathNoticeService.php` – orchestruje scrapování, ukládání a PDF.
  - `app/Services/Scrapers/*Scraper.php` – scrapery pro jednotlivé pohřební služby.
  - `app/Services/HashPathGenerator.php` – vlastní path generator pro Spatie Media Library.
  - `app/Console/Commands/DownloadDeathNotices.php` – artisan příkaz `parte:download`.
- Soubory v `ai/` obsahují obecné instrukce pro Laravel SaaS vývoj; respektuj je, ale tento `AGENTS.md` má pro tento repozitář prioritu.
- Nepřidávej nové subsystémy (front-end framework, API vrstvy) bez výslovného zadání.

---

## 3. PHP / Laravel kódový styl

### Imports (`use` blok)

- Vždy používej `use` direktivy na začátku souboru, nepoužívej zbytečně plně kvalifikované názvy v těle.
- Seskup a řaď `use` bloky logicky:
  - Nejprve `App\...` (vlastní třídy),
  - poté `Illuminate\...` (Laravel),
  - pak ostatní vendory (`Spatie\...`, `Symfony\...`, `Carbon\...`, atd.).
- Odstraň nepoužívané importy.

### Formátování

- Odsazení: 4 mezery, žádné tabulátory.
- Otevírací složená závorka třídy/metody na stejném řádku.
- Řádky ideálně do ~120 znaků; delší konstrukce (pole, řetězce) rozděluj na více řádků.
- Používej krátkou syntaxi polí `[]`.
- V poli preferuj jasnou strukturu:
  - jeden prvek na řádek u delších polí,
  - zarovnání klíčů pouze pokud zůstává čitelné.

### Typy a signatury

- Vždy, kde to dává smysl, přidej:
  - typy parametrů (`function downloadNotices(?array $sources = null): array`),
  - návratové typy (`: ?string`, `: int`, `: DeathNotice`).
- Deklaruj typy vlastností (`private array $scrapers = [];`).
- Používej `?Type` pro nullable hodnoty.
- Pokud je potřeba přesnější typ pro pole, uveď ho v PHPDoc (např. `@var array<string, class-string>`).

### Pojmenování

- Třídy: PascalCase (`DeathNoticeService`, `SadovyJanScraper`).
- Metody a proměnné: camelCase (`generatePdf`, `$availableSources`).
- Proměnné pojmenovávej sémanticky (`$funeralDate`, `$hashString`), ne genericky (`$foo`, `$bar`).
- Pro booleany používej názvy typu `is...`, `has...`, `should...`.

---

## 4. Doména: oznámení úmrtí a hash

- Každé oznámení má v DB jedno textové pole `full_name` (jméno a příjmení dohromady), nedělí se na `first_name` / `last_name`.
- Hash slouží jako unikátní identifikátor oznámení.
- Výpočet hashe vychází z kombinace:
  - `full_name`, datum pohřbu (pokud je k dispozici), zdrojová URL oznámení.
- Algoritmus: `sha256` a uložení prvních 12 znaků:
  - `substr(hash('sha256', $hashString), 0, 12)`.
- Migrace `death_notices` používá `string('hash', 12)->unique()->index()` – zachovej tuto délku a unikátnost.
- Před uložením nového záznamu vždy kontroluj existenci hashe, aby se zabránilo duplicitám.

---

## 5. Media, PDF a externí služby

- Pro práci se soubory používej Spatie Media Library:
  - Model implementuje `HasMedia` a používá trait `InteractsWithMedia`.
  - Kolekce `pdf` je `singleFile()` a přijímá pouze MIME `application/pdf`.
  - Kolekce používá disk `parte` (konfigurovaný v `config/filesystems.php`).
  - Custom path generator `HashPathGenerator` ukládá PDFs do `storage/app/parte/{hash}/`.
- PDF generuj přes Spatie Browsershot:
  - HTML → PDF: generuj Blade šablonou (`resources/views/pdf/death-notice.blade.php`) a pak `Browsershot::html($html)`.
  - Obrázek → PDF (např. PS BK): stáhni obrázek pomocí `Http`, vytvoř dočasný soubor v `storage/app/temp`, zabal do HTML s `<img>` a převeď na PDF.
- Dočasné soubory vždy po úspěšném zpracování smaž.
- Neprováděj síťová volání v testech bez mocků (`Http::fake()` apod.).
- Při stahování PDF ze zdrojových URL zachovej originální název souboru (např. `krzyžanková120251229_09470174.pdf`).

---

## 6. Parsování dat pomocí Carbon

- Pro parsování dat z textů VŽDY používej Carbon místo manuálního regex.
- Nastav českou lokalizaci: `Carbon::setLocale('cs')` pro podporu českých názvů měsíců.
- Pro české numerické datum (např. "2.1.2026", "31.12.2025") používej formát `j.n.Y` v `Carbon::createFromFormat()`.
- Vždy obaluj parsování do `try/catch` a loguj selhání pomocí `Log::warning()`.
- Příklad z `SadovyJanScraper::parseDate()`:
  ```php
  Carbon::setLocale('cs');
  try {
      return Carbon::createFromFormat('j.n.Y', $match[0])->format('Y-m-d');
  } catch (\Exception $e) {
      Log::warning("Failed to parse date: {$dateText}", ['error' => $e->getMessage()]);
      return null;
  }
  ```

---

## 7. Error handling a logování

- Všechny operace, které mohou selhat (HTTP requesty, práce s Browsershotem, DB transakce), obaluj do `try/catch`.
- Chyby loguj pomocí `Log::error()` nebo `Log::warning()` se srozumitelnou zprávou:
  - zahrň informaci o zdroji (`$this->source`, URL),
  - přidej text chyby (`{$e->getMessage()}`).
- Nepoužívaj prázdné `catch` bloky – vždy loguj.
- U artisan příkazů vracej smysluplné exit kódy (např. `Response::HTTP_OK`, `Response::HTTP_PARTIAL_CONTENT`, `Response::HTTP_UNPROCESSABLE_ENTITY`).

---

## 8. Testování a TDD

- Při přidávání nebo změně chování:
  - nejprve přidej/aktualizuj test v `tests/Feature` nebo `tests/Unit`,
  - používej Pest syntaxi (`test('description', function () { ... });`).
- Pro scrapery preferuj testy s mockovaným HTTP (`Http::fake()`) místo reálných requestů.
- Po větší změně spusť cílené testy (konkrétní soubor/`--filter`) a případně celý test suite.
- Používej `RefreshDatabase` trait pro testy, které potřebují čistou DB.

---

## 9. Git, nástroje a CI

- Neprováděj změny Git konfigurace (uživatel, e-mail, hooky) bez výslovného pokynu.
- Commity vytvářej pouze, pokud o to uživatel explicitně požádá.
- Nikdy nepoužívej `--no-verify` nebo podobné přepínače k obejití hooků bez svolení.
- Neprováděj force-push do sdílených větví, pokud o to uživatel výslovně neřekne.
- NIKDY nezmiňuj AI asistenci v commit zprávách (např. "generated by Claude", "AI-assisted").

---

## 10. Cursor / Copilot specifická pravidla

- V tomto repozitáři aktuálně **nejsou** `.cursor/rules/` ani `.cursorrules`, ani `.github/copilot-instructions.md`.
- Pokud budou v budoucnu přidány:
  - vždy je přečti a respektuj,
  - aktualizuj tento `AGENTS.md` tak, aby stručně shrnoval jejich pravidla (zejména commit message, styl kódu, zakázané patterny).

---

## 11. Obecná pravidla pro agenty

- Měň pouze to, co souvisí s aktuálním úkolem, nedělej plošné refaktoringy.
- Zachovej konvenci a styl kódu okolních souborů.
- Každou netriviální změnu popiš v závěrečném shrnutí pro uživatele.
- Při nejasnostech raději polož doplňující dotaz, než aby ses odchýlil od zamýšleného chování aplikace.

===
