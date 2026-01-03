# Parte - Systém pro scrapování a správu parte

Laravel aplikace pro automatické stahování, zpracování a archivaci parte (úmrtních oznámení) z pohřebních služeb.

## Funkce

- 🔄 Automatické scrapování parte z pohřebních služeb
- 🤖 AI Vision extrakce dat (jméno, datum úmrtí, datum pohřbu) pomocí ZhipuAI GLM-4V + Anthropic Claude
- 📄 Generování a ukládání PDF
- ⚡ Asynchronní sekvenční zpracování přes Laravel Horizon
- 🔁 Automatické opakování při selhání (3× retry)
- 🗄️ Ukládání do databáze s deduplikací

## Pohřební služby

- [Pohřební služba Sadový Jan](https://www.sadovyjan.cz/parte/)
- [Pohřební služba Hajduková](https://pshajdukova.cz/smutecni-obrady-parte/)
- [PS BK Ostrava](https://psbk.cz/parte/)

## Instalace na server (Laravel Forge)

### 1. Požadavky na server

Vytvořte nový server v Laravel Forge s následující konfigurací:

- **PHP verze:** 8.4 nebo vyšší
- **Databáze:** MariaDB nebo MySQL
- **Node.js:** 20.x nebo vyšší (pro Vite build)

### 2. Systémové závislosti

Po vytvoření serveru se připojte přes SSH a nainstalujte požadované balíčky:

```bash
# ImageMagick pro konverzi PDF na obrázky
sudo apt-get update
sudo apt-get install -y imagemagick

# PHP rozšíření
sudo apt-get install -y php8.4-imagick php8.4-gd

# Ověření instalace
php -m | grep imagick   # Mělo by zobrazit: imagick
```

### 3. Konfigurace ImageMagick pro PDF

ImageMagick má ve výchozím stavu omezení pro práci s PDF soubory. Je potřeba upravit policy:

```bash
sudo nano /etc/ImageMagick-6/policy.xml
```

Najděte řádek s `<policy domain="coder" rights="none" pattern="PDF" />` a změňte na:

```xml
<policy domain="coder" rights="read|write" pattern="PDF" />
```

Uložte (Ctrl+O, Enter, Ctrl+X) a restartujte server:

```bash
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
```

### 4. Deployment v Laravel Forge

1. **Vytvořte nový site** v Laravel Forge s vaší doménou
2. **Nastavte Git repository:**
   - Repository: `vase-organizace/parte`
   - Branch: `main`
   - Deploy key: Zkopírujte a přidejte do GitHub/GitLab
3. **Nastavte Environment Variables** (`.env`):

```env
APP_NAME=Parte
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vase-domena.cz

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parte
DB_USERNAME=forge
DB_PASSWORD=vaše-db-heslo

QUEUE_CONNECTION=redis

# Scraper User-Agent (aktualizujte na nejnovější Chrome verzi)
SCRAPER_USER_AGENT="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"

# ZhipuAI GLM-4V API (primární OCR engine)
ZHIPUAI_API_KEY=your-zhipuai-api-key
ZHIPUAI_MODEL=glm-4.6v-flash
ZHIPUAI_BASE_URL=https://open.bigmodel.cn/api/paas/v4

# Anthropic Claude API (fallback OCR engine)
ANTHROPIC_API_KEY=your-anthropic-api-key
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
ANTHROPIC_MAX_TOKENS=2048

# Portrait extraction (set to false to disable)
PARTE_EXTRACT_PORTRAITS=true
```

4. **Upravte Deploy Script** v Forge:

```bash
cd /home/forge/vase-domena.cz

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan optimize
    
    # Restart queue workers
    $FORGE_PHP artisan queue:restart
fi

# Build frontend assets (pokud používáte Vite)
npm ci --prefer-offline --no-audit
npm run build
```

5. **Deploy aplikaci** kliknutím na "Deploy Now"

### 5. Nastavení Scheduleru (Cron)

V Forge přidejte nový **Scheduled Job**:

- **Command:** `php /home/forge/vase-domena.cz/artisan schedule:run`
- **User:** `forge`
- **Frequency:** `Every Minute` (* * * * *)

Laravel scheduler automaticky spustí naplánované úkoly definované v `routes/console.php`.

### 6. Nastavení Queue Workers (Horizon)

1. V Forge přidejte nový **Daemon**:
   - **Command:** `php /home/forge/vase-domena.cz/artisan horizon`
   - **User:** `forge`
   - **Directory:** `/home/forge/vase-domena.cz`
   - **Processes:** `1`

2. Po každém deploymentu Horizon automaticky restartuje díky `php artisan queue:restart` v deploy scriptu

### 7. Nastavení Horizon Dashboardu (volitelné)

Pro přístup k Horizon monitoringu upravte `app/Providers/HorizonServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user = null) {
        // V produkci: return in_array($user?->email, ['admin@example.com']);
        return app()->environment('local') || request()->ip() === 'vaše-ip-adresa';
    });
}
```

Horizon dashboard bude dostupný na: `https://vase-domena.cz/horizon`

### 8. První spuštění

Připojte se přes SSH a spusťte:

```bash
cd /home/forge/vase-domena.cz

# Spusťte migrace
php artisan migrate

# (Volitelně) Naplňte databázi seedem pohřebních služeb
php artisan db:seed

# Otestujte stahování parte
php artisan parte:download

# Zkontrolujte queue
php artisan horizon:list
```

### 9. Pravidelné stahování parte

Přidejte naplánovaný úkol do `routes/console.php` (pokud ještě není):

```php
Schedule::command('parte:download')->daily();
```

Nebo vytvořte vlastní frekvenci podle potřeby:
- `->hourly()` - každou hodinu
- `->dailyAt('09:00')` - denně v 9:00
- `->twiceDaily(9, 15)` - 2× denně (9:00 a 15:00)

## Použití

### Manuální stažení parte

```bash
# Stáhnout ze všech zdrojů
php artisan parte:download

# Stáhnout z konkrétního zdroje
php artisan parte:download --source=sadovy-jan
php artisan parte:download --source=pshajdukova
php artisan parte:download --source=psbk
```

### Zpracování existujících parte s OCR

```bash
# Zpracovat všechny parte s chybějícím death_date
php artisan parte:process-existing --missing-death-date

# Zpracovat konkrétní zdroj
php artisan parte:process-existing --source="Sadový Jan"
```

### Extrakce portrétů z existujících parte

```bash
# Extrahovat portréty z parte bez fotografií
php artisan parte:process-existing --extract-portraits

# Znovu extrahovat VŠECHNY portréty (včetně existujících)
php artisan parte:process-existing --extract-portraits --force
```

Tato volba extrahuje **pouze portréty** bez úpravy existujících textových dat.

### Monitoring queue jobů

```bash
# Zobrazit stav Horizon
php artisan horizon:status

# Vypsat failed jobs
php artisan queue:failed

# Opakovat failed jobs
php artisan queue:retry all
```

## Technologie

- **Laravel 12** - PHP framework
- **Laravel Horizon** - Queue management (sekvenční zpracování jobů)
- **ZhipuAI GLM-4V** - Primární AI Vision OCR (čeština, polština)
- **Anthropic Claude Vision** - Fallback AI Vision OCR
- **Spatie Media Library** - Správa souborů
- **Spatie Browsershot** - Generování PDF z HTML
- **Smalot PDF Parser** - Parsování PDF textu
- **Symfony DomCrawler** - Web scraping

## Struktura databáze

### Tabulka `death_notices`

- `hash` - Unikátní identifikátor (SHA-256, 12 znaků)
- `full_name` - Celé jméno
- `death_date` - Datum úmrtí (nullable)
- `funeral_date` - Datum pohřbu (nullable)
- `source` - Název pohřební služby
- `source_url` - URL zdroje
- PDFs uloženy v `storage/app/parte/{hash}/` přes Spatie Media Library

## AI Vision OCR

Aplikace používá **ZhipuAI GLM-4V** jako primární engine pro extrakci dat z parte obrázků.

### Konfigurace

```env
# Primární OCR engine
ZHIPUAI_API_KEY=your-api-key
ZHIPUAI_MODEL=glm-4.6v-flash

# Fallback OCR engine
ANTHROPIC_API_KEY=your-api-key
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
ANTHROPIC_MAX_TOKENS=2048
```

### Extrakční flow

1. **ZhipuAI GLM-4V** (primární, ~2-5s)
   - Podporuje PDF i JPG
   - Base64 encoding
   - Timeout 90s

2. **Anthropic Claude** (fallback, ~3-6s)
   - Pouze JPG
   - Vysoká přesnost
   - Timeout 90s

### Portrait Extraction (Extrakce fotografií)

Systém automaticky detekuje a extrahuje portréty zemřelých z parte dokumentů:

- **Detekce:** ZhipuAI GLM-4V identifikuje fotografie a jejich pozici (bounding box jako procenta)
- **Extrakce:** Automatické ořezání pomocí Imagick
- **Úložiště:** Samostatně uloženo jako JPEG (max 400x400px, kvalita 85)
- **Přístup:** `$deathNotice->getFirstMediaUrl('portrait')`
- **Non-Critical:** Selhání extrakce portrétu nezpůsobí selhání celého jobu (pouze varování v logu)

Portréty jsou uloženy v samostatné media collection `portrait` odděleně od PDF dokumentů.

### Sekvenční zpracování

Extrakční joby běží **postupně (jeden po druhém)** na dedikované `extraction` frontě s `maxJobs=1` konfigurací v Horizon. Toto zajišťuje stabilní zpracování a prevenci rate limitů.

### Ceny (orientační, 2026)

- **ZhipuAI:** ~$0.001-0.002 / parte obrázek
- **Anthropic:** ~$0.003-0.005 / parte obrázek
- **Denní náklady (10 parte):** ~$0.01-0.05

## Troubleshooting

### ImageMagick: "not authorized" error

Upravte ImageMagick policy (viz sekce Instalace, bod 3)

### Queue jobs nespadají

```bash
# Zkontrolujte Horizon status
php artisan horizon:status

# Restartujte Horizon daemon v Forge
# Nebo přes SSH:
sudo supervisorctl restart horizon
```

### User-Agent je zastaralý

Aktualizujte `SCRAPER_USER_AGENT` v `.env` souboru na nejnovější verzi Chrome z: https://www.whatismybrowser.com/guides/the-latest-user-agent/chrome

## Licence

Proprietární software.
