# TODO - Implementační úkoly pro projekt Parte

**Datum vytvoření:** 2. ledna 2026  
**Status dokumentu:** Aktivní pracovní seznam

---

## 🔴 KRITICKÁ PRIORITA (okamžitě)

### 0. Základní webový frontend (CHYBÍ!)

**STAV:** Aplikace zatím nemá žádný web frontend pro zobrazování parte. Je to pouze backend scraping systém.

- [ ] **Základní layout (app.blade.php)**
  - Header s logem a navigací
  - Footer s odkazy na Privacy Policy, Terms, Contact
  - Tailwind CSS styling (již nainstalováno)
  - Responsivní design (mobile-first)

- [ ] **Homepage Controller**
  - Route: `GET /`
  - Controller: `HomeController@index`
  - Query: `DeathNotice::with('media')->latest()->paginate(20)`
  - Cache (5 minut): `Cache::remember('homepage_notices', 300, ...)`

- [ ] **Homepage view (resources/views/home.blade.php)**
  - Hero sekce:
    - Nadpis: "Archiv parte a úmrtních oznámení"
    - Popisek služby (1-2 věty)
    - Vyhledávací pole (jméno zemřelého)
  - Grid/seznam nejnovějších parte:
    - Karta pro každé parte
    - Thumbnail PDF nebo placeholder ikona
    - Jméno zemřelého (bold)
    - Datum úmrtí (pokud známo)
    - Datum pohřbu
    - Zdroj (pohřební služba)
    - Odkaz "Zobrazit detail"
  - Pagination (Laravel default)
  - Jednoduchý, minimalistický design

- [ ] **Parte detail view (resources/views/parte/show.blade.php)**
  - Route: `GET /parte/{hash}`
  - Controller: `ParteController@show`
  - Layout:
    - Levý sloupec (nebo horní část na mobilu):
      - PDF viewer (browser native `<embed>` nebo `<iframe>`)
      - Fallback: "Stáhnout PDF" tlačítko
    - Pravý sloupec (nebo dolní část na mobilu):
      - Metadata v kartě:
        - Jméno zemřelého
        - Datum úmrtí
        - Datum pohřbu
        - Zdroj (odkaz na pohřební službu)
        - Datum archivace
      - Akční tlačítka:
        - Stáhnout PDF
        - Zpět na seznam
  - Open Graph meta tags pro social sharing

- [ ] **Routes definice (routes/web.php)**
  ```php
  Route::get('/', [HomeController::class, 'index'])->name('home');
  Route::get('/parte/{hash}', [ParteController::class, 'show'])->name('parte.show');
  ```

- [ ] **PDF serving route**
  - Route: `GET /parte/{hash}/pdf`
  - Controller: `ParteController@pdf`
  - Headers: `Content-Type: application/pdf`, `inline` disposition
  - Spatie Media Library: `$deathNotice->getFirstMedia('pdf')`

**Odhadovaný čas:** 6-8 hodin  
**Priorita:** KRITICKÁ - bez toho aplikace nemá uživatelské rozhraní  
**Blokuje:** Body 4 (Disclaimer), 10 (Vyhledávání), 11 (Detail - již částečně zde)

**Design rozhodnutí:**
- Minimalistický design (černá/šedá/bílá paleta, respektující téma úmrtních oznámení)
- Žádné fancy animace nebo barvy
- Focus na čitelnost a přístupnost
- Mobile-first responsivní design
- Browser native PDF viewer (ne PDF.js - jednodušší)

---

### 1. Respektování robots.txt
- [ ] **Vytvořit RobotsTxtParser service**
  - Stahování a parsování robots.txt z pohřebních služeb
  - Cache mechanismus (neověřovat při každém requestu)
  - Implementace: `app/Services/RobotsTxtParser.php`
  
- [ ] **Integrovat do scraperů**
  - Kontrola před každým scrapováním
  - Log odmítnutých requestů
  - Implementace v `AbstractScraper::fetchContent()`

- [ ] **Upravit User-Agent**
  - Změnit z generického Chrome UA na identifikovatelný
  - Format: `ParteArchiveBot/1.0 (+https://parte.cz/about)`
  - Update v `.env`: `SCRAPER_USER_AGENT`

**Odhadovaný čas:** 4 hodiny  
**Právní riziko bez implementace:** VYSOKÉ

---

## 🟡 VYSOKÁ PRIORITA (do 1 měsíce)

### 2. Formulář pro žádost o odstranění údajů

- [ ] **Backend: Removal Request Model & Migration**
  ```bash
  php artisan make:model RemovalRequest -m
  ```
  - Pole: `full_name`, `death_date`, `email`, `reason`, `status`, `token`
  - Status: `pending`, `verified`, `approved`, `rejected`, `completed`

- [ ] **Backend: RemovalRequestController**
  - `POST /api/removal-requests` - vytvoření žádosti
  - `GET /api/removal-requests/verify/{token}` - ověření emailu
  - `GET /admin/removal-requests` - správa žádostí (admin only)
  - `PATCH /admin/removal-requests/{id}` - schválení/zamítnutí

- [ ] **Email notifikace**
  - Ověřovací email s tokenem
  - Potvrzení o přijetí žádosti
  - Notifikace o vyřízení (schváleno/zamítnuto)

- [ ] **Frontend: Removal Request Form**
  - Route: `/removal-request`
  - Formulář: jméno zemřelého, datum úmrtí, email žadatele, důvod
  - Validace: required fields, email format, date format
  - CAPTCHA/honeypot (ochrana proti spamu)

- [ ] **Admin panel pro správu žádostí**
  - Seznam pending žádostí
  - Detail žádosti s náhledem na parte
  - Tlačítka: Schválit / Zamítnout
  - Log akcí administrátorů

**Odhadovaný čas:** 12 hodin  
**Právní riziko bez implementace:** STŘEDNÍ až VYSOKÉ

---

### 3. Právní dokumenty (Privacy Policy, Terms of Service, About)

- [ ] **Privacy Policy** (`/privacy-policy`)
  - Jaká data sbíráme (pouze údaje zemřelých)
  - Proč data zpracovávám (archivace, vyhledávání)
  - Jak dlouho data uchováváme (neomezeně s opt-out)
  - Práva pozůstalých (žádost o odstranění)
  - Cookies policy (pokud používáme analytics)
  - Kontaktní údaje provozovatele

- [ ] **Terms of Service** (`/terms-of-service`)
  - Účel služby (nekomerční archivace)
  - Omezení odpovědnosti (data z OCR mohou být nepřesná)
  - Práva pozůstalých
  - Zákaz zneužití služby
  - Změny podmínek

- [ ] **About stránka** (`/about`)
  - Poslání projektu (genealogický výzkum, archivace)
  - Jak služba funguje (agregace z pohřebních služeb)
  - Seznam zdrojů (pohřební služby)
  - Kontakt
  - FAQ

- [ ] **Contact stránka** (`/contact`)
  - Kontaktní formulář
  - Email provozovatele
  - Odkaz na removal request

- [ ] **Footer s odkazy**
  - Privacy Policy
  - Terms of Service
  - About
  - Contact
  - Removal Request

**Odhadovaný čas:** 8 hodin (psaní textů + implementace views)  
**Právní riziko bez implementace:** VYSOKÉ

---

### 4. Disclaimer na stránkách s parte

- [ ] **Disclaimer component**
  - "Údaje extrahovány automaticky pomocí OCR, mohou obsahovat chyby"
  - "Ověřte prosím na původním zdroji: [odkaz na pohřební službu]"
  - Ikona varování pro vizuální zdůraznění

- [ ] **Integrovat do parte detail view**
  - Zobrazit nad/pod parte
  - Odkaz na source_url (pohřební služba)

- [ ] **Report error feature**
  - Tlačítko "Nahlásit chybu v údajích"
  - Formulář: co je špatně, správné údaje
  - Email administrátorovi

**Odhadovaný čas:** 3 hodiny  
**Právní riziko bez implementace:** STŘEDNÍ

---

## 🟢 STŘEDNÍ PRIORITA (do 3 měsíců)

### 5. Kontakt s pohřebními službami (licence)

- [ ] **Připravit email template pro pohřební služby**
  - Představení projektu
  - Vysvětlení účelu (archivace, genealogie, veřejný zájem)
  - Žádost o formální souhlas
  - Nabídka backlinku / propagace
  - Opt-out možnost

- [ ] **Kontaktní seznam**
  - Sadový Jan: info@sadovyjan.cz
  - PS Hajduková: info@pshajdukova.cz
  - PS BK Ostrava: info@psbk.cz

- [ ] **Tracking odpovědí**
  - Tabulka v DB: `funeral_service_licenses`
  - Pole: `service_name`, `contact_email`, `status`, `response_date`, `license_type`
  - Status: `pending`, `approved`, `rejected`, `no_response`

- [ ] **Disable scrapingu pro služby bez licence** (po X měsících)
  - Config flag: `license_required_after` (datum)
  - Auto-disable scraperů bez odpovědi

**Odhadovaný čas:** 4 hodiny + čekání na odpovědi  
**Právní riziko bez implementace:** STŘEDNÍ (autorská práva)

---

### 6. Rate limiting a etické scrapování

- [ ] **Implementovat rate limiting v scraperech**
  - Sleep 2-5 sekund mezi požadavky
  - Randomizace pro přirozenější chování
  - Implementace v `AbstractScraper`

- [ ] **Respect-Crawl-Delay z robots.txt**
  - Parsovat `Crawl-delay` direktivu
  - Použít jako minimum sleep time

- [ ] **Monitoring a alerting**
  - Log počet requestů na službu/den
  - Alert při abnormálně vysokém počtu (ochrana před buggy loop)

**Odhadovaný čas:** 2 hodiny  
**Právní riziko bez implementace:** NÍZKÉ

---

## 🔵 NÍZKÁ PRIORITA / NICE-TO-HAVE

### 7. Audit extrahovaných dat

- [ ] **Admin dashboard pro kontrolu kvality OCR**
  - Seznam parte s missing `death_date`
  - Možnost manuální editace
  - Statistiky: úspěšnost OCR extrakce

- [ ] **Confidence score pro OCR**
  - Uložit confidence level z Tesseract/Gemini
  - Prioritizovat low-confidence parte pro manual review

**Odhadovaný čas:** 6 hodin  
**Přínos:** Zvýšení kvality dat

---

### 8. Retention policy

- [ ] **Konfigurace retention policy**
  - Config: `parte.retention_years` (default: neomezeno)
  - Artisan command: `php artisan parte:cleanup-old`
  - Soft delete starších parte (>X let)

- [ ] **User preferences** (pokud bude registrace)
  - Uživatelé mohou sledovat konkrétní parte
  - Notifikace před smazáním

**Odhadovaný čas:** 4 hodiny  
**Přínos:** Compliance, úspora storage

---

## 📊 FUNKČNÍ ROZŠÍŘENÍ APLIKACE

### 9. Homepage - Zobrazení nejnovějších parte

- [ ] **Design homepage**
  - Hero sekce s popisem služby
  - Vyhledávací pole (jméno, datum)
  - Grid/list nejnovějších parte (10-20 položek)

- [ ] **Backend: Homepage Controller**
  - `GET /` - homepage
  - Query: `DeathNotice::latest()->take(20)->get()`
  - Cache (5 minut)

- [ ] **Parte card component**
  - Thumbnail PDF (pokud možné) nebo placeholder
  - Jméno zemřelého
  - Datum úmrtí (pokud známé)
  - Datum pohřbu
  - Zdroj (pohřební služba)
  - Odkaz na detail

**Odhadovaný čas:** 6 hodin  
**Priorita:** VYSOKÁ (základ UI)

---

### 10. Vyhledávání parte

- [ ] **Search form na homepage**
  - Input: jméno (fulltext)
  - Datum úmrtí (od-do range)
  - Datum pohřbu (od-do range)
  - Pohřební služba (select)

- [ ] **Backend: SearchController**
  - `GET /search?q=jmeno&death_from=&death_to=&source=`
  - Fulltext search v `full_name` (použít DB fulltext index)
  - Filtry na data
  - Stránkování (20/stránka)

- [ ] **Search results view**
  - Seznam nalezených parte
  - Highlight hledaného výrazu v jméně
  - Počet výsledků
  - Prázdný stav: "Nenalezeny žádné parte"

**Odhadovaný čas:** 8 hodin  
**Priorita:** VYSOKÁ

---

### 11. Detail parte

- [ ] **Parte detail view** (`/parte/{hash}`)
  - PDF viewer (embed nebo link ke stažení)
  - Metadata:
    - Jméno zemřelého
    - Datum úmrtí
    - Datum pohřbu
    - Zdroj (odkaz na pohřební službu)
    - Datum archivace (created_at)
  - Disclaimer (viz bod 4)
  - Tlačítka:
    - Stáhnout PDF
    - Nahlásit chybu
    - Požádat o odstranění

- [ ] **Open Graph meta tags**
  - og:title: "Parte - {full_name}"
  - og:description: "Datum úmrtí: {death_date}, Pohřeb: {funeral_date}"
  - og:image: Náhled PDF (pokud možné)

**Odhadovaný čas:** 4 hodiny  
**Priorita:** VYSOKÁ

---

### 12. Statistiky

- [ ] **Statistics page** (`/statistics`)
  - Celkový počet archivovaných parte
  - Počet parte podle pohřební služby
  - Graf: parte v čase (denně/měsíčně)
  - Nejčastější jména (anonymizované statistiky)

- [ ] **Backend: StatsController**
  - Cache statistik (1 hodina)
  - Agregace v DB: `COUNT`, `GROUP BY source`

**Odhadovaný čas:** 4 hodiny  
**Priorita:** NÍZKÁ

---

### 13. RSS feed

- [ ] **RSS feed** (`/rss`)
  - Nejnovějších 50 parte
  - Format: RSS 2.0
  - Item: title = jméno, description = data, link = detail

**Odhadovaný čas:** 2 hodiny  
**Priorita:** NÍZKÁ  
**Přínos:** SEO, distribuční kanál

---

### 14. API pro třetí strany

- [ ] **Public API** (`/api/v1/`)
  - `GET /api/v1/death-notices` - list (paginace)
  - `GET /api/v1/death-notices/{hash}` - detail
  - `GET /api/v1/search?q=` - search
  - Rate limiting: 100 req/hodina/IP
  - API dokumentace (Swagger/OpenAPI)

**Odhadovaný čas:** 6 hodin  
**Priorita:** NÍZKÁ  
**Přínos:** Otevřená data, integrace s genealogickými nástroji

---

### 15. Registrace uživatelů a oblíbené parte

- [ ] **User authentication**
  - Laravel Breeze/Jetstream
  - Login, registrace, reset hesla

- [ ] **Watchlist feature**
  - Uživatel může označit parte jako "sledované"
  - Notifikace před smazáním (retention policy)

- [ ] **⚠️ POZOR: GDPR aplikace!**
  - Registrace = zpracování osobních údajů živých osob
  - Nutné:
    - Privacy Policy pro uživatelské účty
    - Souhlas se zpracováním
    - Možnost exportu dat (GDPR čl. 20)
    - Možnost smazání účtu (GDPR čl. 17)
    - Možná potřeba DPO (Data Protection Officer)

**Odhadovaný čas:** 16 hodin  
**Priorita:** VELMI NÍZKÁ  
**Právní komplexita:** VYSOKÁ

---

## 📝 POZNÁMKY

### Technologické úvahy

- **Frontend framework?**
  - Aktuálně: Blade templates
  - Zvážit: Inertia.js (React/Vue) pro lepší UX
  - Nebo: Livewire pro jednodušší real-time features

- **Full-text search**
  - Aktuálně: MySQL `LIKE` query
  - Lepší: Laravel Scout + Algolia/Meilisearch
  - Nebo: Elasticsearch pro velké objemy dat

- **PDF thumbnail generování**
  - Imagick (již používán)
  - Cache thumbnail obrázků
  - Storage: `storage/app/thumbnails/{hash}.jpg`

### Bezpečnostní úvahy

- **CAPTCHA na veřejné formuláře**
  - Removal request form
  - Contact form
  - Implementace: Google reCAPTCHA v3 nebo hCaptcha

- **Rate limiting**
  - API endpointy
  - Search (ochrana proti scraping)
  - Removal request (max 3/den/IP)

---

## 🎯 DOPORUČENÉ POŘADÍ IMPLEMENTACE

### Fáze 1: Právní compliance (1-2 týdny)
1. Respektování robots.txt (bod 1)
2. Právní dokumenty (bod 3)
3. Disclaimer (bod 4)

### Fáze 2: Core features (2-3 týdny)
4. Homepage (bod 9)
5. Vyhledávání (bod 10)
6. Detail parte (bod 11)

### Fáze 3: User engagement (1-2 týdny)
7. Formulář pro odstranění (bod 2)
8. Kontakt s pohřebními službami (bod 5)
9. Rate limiting (bod 6)

### Fáze 4: Rozšíření (volitelné)
10. Statistiky (bod 12)
11. RSS feed (bod 13)
12. API (bod 14)
13. Další features podle potřeby

---

## 📊 TRACKING PROGRESS

**Celkový progres:** 0 / 50 úkolů (0%)

### Hotové úkoly
- [x] Vytvoření TODO.md
- [x] Právní analýza (PRAVNI_ASPEKTY.md)

### V progress
- [ ] ...

### Blokované úkoly
- [ ] ...

---

*Dokument vytvořen: 2. ledna 2026*  
*Poslední update: 2. ledna 2026*  
*Spravuje: Development team*
