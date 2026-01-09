# Historie změn

Všechny významné změny v tomto projektu budou zaznamenány v tomto souboru.

## [2.3.1] - 2026-01-09

### 🐛 Opravy chyb

#### Bug #1: Chybějící `opening_quote` v AI promptu (KRITICKÁ)
- **Problém:** Pole `opening_quote` nebylo zahrnuto v JSON schématu VisionOcrService
- **Důsledek:** AI nikdy neextrahovalo úvodní citáty, protože nebylo v promptu požadováno
- **Oprava:** Přidáno `opening_quote` do JSON schématu (VisionOcrService:998-1011)
- **Oprava:** Přidána extrakční pravidla pro opening_quote (VisionOcrService:1039-1049)
- **Dopad:** Příkaz `php artisan parte:extract-opening-quotes` nyní funguje správně

#### Bug #2: Nečištěné polské prefixy "śp." ve jménech
- **Problém:** Jména jako "śp. Stanislav Raszka" obsahovala polský prefix zemřelé osoby
- **Očekávané:** "Stanislav Raszka" (bez prefixu)
- **Oprava:** Přidána metoda `cleanFullName()` do VisionOcrService a AbacusAiVisionService
- **Varianty:** Odstraňuje 'śp.', 'sp.', 'ś.p.', 'Śp.', 'Sp.', 'Ś.p.'
- **Dopad:** Všechna nově extrahovaná jména budou bez prefixů

#### Bug #3: Kontaktní informace pohřební služby v textu oznámení
- **Problém:** Text oznámení obsahoval podpisy pohřebních služeb (např. "Jan Sadový Pohřební služba Bystřice tel. 558352208 mobil: 602539388")
- **Očekávané:** Text by měl končit rodinným podpisem ("Zasmucona rodzina")
- **Oprava:** Přidána metoda `removeFuneralServiceSignature()` s regex vzory pro:
  - České pohřební služby: "Pohřební služba..." + telefon
  - Polské služby: "Zakład pogrzebowy..." + telefon
  - Obecné firmy: "s.r.o., ul. ..." + telefon
  - Samostatné telefony: "tel:", "mobil:"
- **Dopad:** Čistý text oznámení bez obchodních kontaktů

### Změněno

#### VisionOcrService (app/Services/VisionOcrService.php)
- **JSON schéma:** Přidáno pole `opening_quote` (řádek 1000)
- **Extrakční pravidla:** Přidána sekce 1.5 pro opening_quote (řádky 1039-1049)
- **Announcement pravidla:** Aktualizována pro vyloučení opening_quote (řádky 1066-1086)
- **Nové metody:**
  - `cleanFullName()` - Odstraňuje polské prefixy "śp." (řádek 1242)
  - `removeFuneralServiceSignature()` - Čistí kontakty pohřební služby (řádek 1265)
- **cleanExtractionResult():** Aktualizováno pro volání obou čisticích metod (řádky 1120, 1152)

#### AbacusAiVisionService (app/Services/AbacusAiVisionService.php)
- **Nová metoda:** `cleanFullName()` - Odstraňuje polské prefixy "śp." (řádek 323)
- **parseTextExtraction():** Aktualizováno pro volání cleanFullName() (řádek 292)

#### ProcessExistingPartesCommand (app/Console/Commands/ProcessExistingPartesCommand.php)
- **Nová volba:** `--missing-opening-quote` pro cílenou re-extrakci (řádek 15)
- **Logika dotazů:** Aktualizována pro podporu opening_quote filtru (řádky 38-61)
- **Výchozí chování:** Nyní zahrnuje opening_quote v chybějících polích (řádek 60)

### Přidáno

#### Unit testy (tests/Unit/OpeningQuoteValidationTest.php)
- `cleanFullName removes śp. prefix variants` - Testy odstranění všech variant prefixů
- `cleanFullName handles names without prefix` - Testy jmen bez prefixů
- `cleanFullName only removes first prefix` - Test edge case vícenásobných prefixů
- **Výsledek:** Všech 13 testů prošlo (42 assertions)

### Technické poznámky

#### Regex vzory pro pohřební služby
- **Unicode-aware:** Všechny vzory používají flag `/u` pro správné zpracování českých/polských znaků
- **Case-insensitive:** Flag `/i` pro zachycení různých variant psaní
- **Patterns:**
  - České služby: `/pohřební služba.*?(?:tel\.?|mobil).*?[\d\s\-]{7,}/iu`
  - Polské služby: `/zakład pogrzebowy.*?(?:tel\.?|mobil).*?[\d\s\-]{7,}/iu`
  - Firmy s adresou: `/s\.r\.o\.,?\s+ul\.\s+[^,]+,\s+[^,]+,\s+tel\.?:?\s*[\d\s\-]{7,}/iu`

#### Testovací data
- **Raszka record (hash: b8b1aab1fc52):**
  - **Před:** `full_name: "śp. Stanislav Raszka"`, `opening_quote: null`
  - **Po:** `full_name: "Stanislav Raszka"`, `opening_quote: "Będę żyć dalej..."`
  - **Announcement:** Končí "Zasmucona rodzina" (bez pohřební služby)

### Aktualizace

Pro aplikaci oprav na existující záznamy spusťte:

```bash
# Re-extrahovat všechna chybějící opening_quote
php artisan parte:extract-opening-quotes --force

# Nebo použijte novou volbu v parte:process-existing
php artisan parte:process-existing --missing-opening-quote

# Pro kompletní re-extrakci všech dat (death_date, announcement, opening_quote)
php artisan parte:process-existing --force
```

---

## [2.3.0] - 2026-01-09

### ⚠️ BREAKING CHANGES

#### Změna syntaxe konfigurace
Konfigurace Vision služby byla aktualizována pro podporu oddělených providerů pro text a foto extrakci.

**Stará syntaxe (DEPRECATED):**
```env
VISION_PROVIDER=gemini
VISION_FALLBACK_PROVIDER=zhipuai
```

**Nová syntaxe (POVINNÁ):**
```env
# Text extraction provider (doporučen rychlý model)
VISION_TEXT_PROVIDER=abacusai/gemini-3-flash
VISION_TEXT_FALLBACK=zhipuai

# Photo detection provider (doporučen přesný model)
VISION_PHOTO_PROVIDER=abacusai/claude-sonnet-4.5
VISION_PHOTO_FALLBACK=anthropic

# Abacus.AI API klíč (pokud používáte abacusai provider)
ABACUSAI_API_KEY=your_api_key_here
ABACUSAI_BASE_URL=https://routellm.abacus.ai
```

**Migrace:**
- **Production:** Aplikace vyhodí exception při detekci staré syntaxe
- **Local/Testing:** Stará syntaxe se automaticky konvertuje s varováním
- **Akce:** Aktualizujte `.env` soubor před nasazením do produkce

### Přidáno

#### Abacus.AI Integrace
- Přidána `AbacusAiVisionService` - Unified API pro více vision modelů (Gemini, Claude, GPT)
- Podpora 4 modelů přes jedno API:
  - `gemini-3-flash` - Rychlý, neomezený (9-13s)
  - `claude-sonnet-4.5` - Nejvyšší kvalita (12-16s)
  - `gemini-2.5-pro` - Prémiová kvalita, neomezený
  - `gpt-5.2` - Střední kvalita
- Auto-normalizace pixelových souřadnic na procenta
- Systém doporučení modelů podle případu použití

#### Pole Opening Quote
- Přidáno pole `opening_quote` do tabulky `death_notices`
- Extrahuje poetické/památné citáty odděleně od hlavního oznámení
- Validace: Varování pokud citát > 500 znaků
- Příklady: "Będę żyć dalej w sercach tych, którzy mnie kochali"
- Databázová migrace: `2026_01_09_103109_add_opening_quote_to_death_notices_table.php`

#### Oddělená architektura providerů
- Text extrakce a foto detekce nyní používají nezávislé providery
- Umožňuje optimalizaci: rychlý model pro text, přesný model pro foto
- Každý má samostatný fallback řetězec
- Konfigurace: `VISION_TEXT_PROVIDER` a `VISION_PHOTO_PROVIDER`

#### Nové příkazy
- `php artisan parte:extract-opening-quotes` - Dávková extrakce citátů z existujících PDF
  - Volby: `--limit=N` (výchozí 10), `--force` (re-extrakce všech)
  - Funkce: PDF→JPG konverze, progress bar, zpracování chyb

#### Testy
- Unit testy:
  - `AbacusAiCoordinateNormalizationTest` - Testy pixel→procenta konverze
  - `VisionProviderParserTest` - Testy parsování provider/model
  - `OpeningQuoteValidationTest` - Testy validace opening_quote
- Feature testy:
  - `AbacusAiVisionServiceTest` - Integrační testy s HTTP mockingem

### Změněno
- **VisionOcrService** kompletně refaktorován:
  - Nové metody: `extractTextFromImage()`, `extractPhotoFromImage()`
  - Oddělená konfigurace providerů pro text vs foto
  - Vylepšená validace a zpracování chyb
  - Stará `extractFromImage()` deprecated (backward compatible wrapper)
- **AbacusAiVisionService** prompt aktualizován pro extrakci pole `opening_quote`
- **DeathNoticeFactory** nyní generuje `opening_quote` (70% šance)
- Čištění announcement textu nyní vylučuje opening quote

### Deprecated
- `VISION_PROVIDER` proměnná prostředí (použijte `VISION_TEXT_PROVIDER`)
- `VISION_FALLBACK_PROVIDER` proměnná prostředí (použijte `VISION_TEXT_FALLBACK`)
- `VisionOcrService::extractFromImage()` metoda (použijte `extractTextFromImage()`)

### Dokumentace
- Přesunutá testovací dokumentace do `docs/abacusai_testy/`
  - `integration_summary.md` - Shrnutí Abacus.AI integrace
  - `raszka_tests/` - Výsledky testů polského parte (11 testů)
  - `wilhelm_tests/` - Výsledky testů českého parte (8 testů)
- Aktualizován `AGENTS.md` s v2.3 konfiguračními instrukcemi
- Aktualizován `.env.example` s novou syntaxí konfigurace

### Technické detaily
- PHP 8.4+ s plnými type hints
- Laravel 12.x architektura
- Pest 4 testing framework
- Formátováno pomocí Laravel Pint

### Migrační návod

#### Krok 1: Aktualizace proměnných prostředí
```bash
# Zkopírovat novou syntaxi z .env.example
# Přidat Abacus.AI credentials
ABACUSAI_API_KEY=your_api_key_here

# Konfigurovat providery
VISION_TEXT_PROVIDER=abacusai/gemini-3-flash
VISION_PHOTO_PROVIDER=abacusai/claude-sonnet-4.5

# Volitelné fallbacky
VISION_TEXT_FALLBACK=zhipuai
VISION_PHOTO_FALLBACK=anthropic
```

#### Krok 2: Spuštění databázové migrace
```bash
php artisan migrate
```

#### Krok 3: Extrakce citátů (volitelné)
```bash
# Extrahovat z 10 záznamů bez opening_quote
php artisan parte:extract-opening-quotes --limit=10

# Vynutit re-extrakci všech záznamů
php artisan parte:extract-opening-quotes --limit=100 --force
```

#### Krok 4: Testování
```bash
# Spustit testy
./vendor/bin/pest

# Testovat specifické funkce
./vendor/bin/pest --filter=AbacusAi
```

### Kompatibilita
- Zpětně kompatibilní v local/testing prostředích (auto-konverze)
- Breaking change v produkci (vyžaduje manuální migraci)
- Veškerý existující kód pokračuje v práci s aktualizovanou konfigurací

---

## [2.2.0] - 2026-01-06

### Změněno
- **BREAKING:** Nahrazena Browsershot (Chrome headless) knihovna za Imagick + DomPDF
- Konverze obrázků na PDF nyní používá Imagick (300 DPI kvalita, JPEG komprese 85)
- Konverze HTML na PDF nyní používá DomPDF (A4 formát, konfigurovatelné okraje)

### Přidáno
- Nová služba `PdfGeneratorService` pro centralizovanou správu PDF generování
- 19 komplexních Pest testů s >90% pokrytím kódu
- Metoda pro stahování obrázků z URL s retry logikou (3 pokusy, exponential backoff)
- Automatické vytváření výstupních adresářů

### Vylepšeno
- **6-10× rychlejší** generování PDF (~0.3s vs ~3-5s)
- Typické PDF soubory <600KB (target ~1MB)
- Odstranění externích závislostí (Chrome/Node.js/Puppeteer)
- Vyřešeny production sandbox errors na Ubuntu 24.04+
- Garantované čištění dočasných souborů

### Odstraněno
- Balíček `spatie/browsershot` a všechny jeho závislosti
- Chrome/Puppeteer systémové závislosti

**Commits:**
- TBD - Replace Browsershot with Imagick + DomPDF for PDF generation

## [2.1.0] - 2026-01-06

### Přidáno
- Dvou-fázová detekce fotografií (hlavní + photo-only fallback režim)
- Google Gemini 2.0 Flash jako primární vision provider
- Download retry mechanismus s 3 pokusy a exponential backoff (2s, 4s, 6s)
- Konfigurovatelný fallback chain: Gemini → ZhipuAI → Anthropic
- Automatické odstranění černých okrajů z portrétů (padding removal)

### Vylepšeno
- Detection rate portrétů zvýšena z ~66% na >95%
- Rychlejší zpracování parte (~10-14s per parte)
- Lepší handling network errors při stahování

### Změněno
- Gemini temperature konfigurace (main=0.3, photo-only=0.5)
- Omezení automatického stahování pouze na pracovní dny (weekdays)
- Photo-only režim s high-sensitivity prompt pro všechny providery

**Commits:**
- `ea75890` - Improve portrait photo detection with two-phase extraction and auto-padding
- `b6988f4` - Add Gemini API support and implement download retry mechanism
- `c363906` - Limit parte download schedule to weekdays only
