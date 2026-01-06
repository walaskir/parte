# Historie změn

Všechny významné změny v tomto projektu budou zaznamenány v tomto souboru.

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
