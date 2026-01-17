# Claude Sonnet 4.5 - Test Results

**Model:** `CLAUDE-SONNET-4-5-20250929`  
**Provider:** Abacus.AI  
**Status:** ✅ PASS  
**Test Date:** 2026-01-08

## Configuration

```json
{
    "model": "CLAUDE-SONNET-4-5-20250929",
    "temperature": 0.0,
    "response_format": { "type": "json" }
}
```

## Performance

- **Response Time:** ~13 seconds
- **Input Tokens:** 1,712 (~549KB image)
- **Output Tokens:** 326
- **Credits Used:** ~50-100 (estimated)

## Extracted Data

```json
{
    "full_name": "Stanislav Raszka",
    "death_date": null,
    "funeral_date": "2026-01-12",
    "announcement_text": "Bede żyć dalej w sercach tych, którzy mnie kochali.\n\nZ głębokim smutkiem i żalem zawiadamiamy rodzinę, przyjaciół i znajomych, że zmarł nasz Ukochany Mąż, Ojciec, Teść, Dziadek, Brat, Szwagier, Wujek, Zięć i Przyjaciel\nPan\n\nśp. Stanislav Raszka\n\nzamieszkały w Bystrzycy nr. 1169.\nZmarł w kręgu rodziny w wieku 66 lat.\n\nPogrzeb Drogiego Zmarłego odbędzie się w poniedziałek 12.1.2026 o godzinie 14.00 z ewangelickiego kościoła w Bystrzycy.\n\nZasmucona rodzina\n\nJan Sadový Pohřební služba Bystřice tel: 558352208 mobil: 602539388"
}
```

## Announcement Text (formatted)

```
Bede żyć dalej w sercach tych, którzy mnie kochali.

Z głębokim smutkiem i żalem zawiadamiamy rodzinę, przyjaciół i znajomych,
że zmarł nasz Ukochany Mąż, Ojciec, Teść, Dziadek, Brat, Szwagier, Wujek,
Zięć i Przyjaciel
Pan

śp. Stanislav Raszka

zamieszkały w Bystrzycy nr. 1169.
Zmarł w kręgu rodziny w wieku 66 lat.

Pogrzeb Drogiego Zmarłego odbędzie się w poniedziałek 12.1.2026 o godzinie
14.00 z ewangelickiego kościoła w Bystrzycy.

Zasmucona rodzina

Jan Sadový Pohřební služba Bystřice tel: 558352208 mobil: 602539388
```

**Length:** 470 characters

## Photo Detection

```json
{
    "has_photo": true,
    "photo_bounds": {
        "x": 39,
        "y": 10,
        "width": 30,
        "height": 28
    }
}
```

**Extracted Portrait:** `portrait_abacus_test.jpg` (33 KB)

## Quality Assessment

### ✅ Strengths

1. **Complete text extraction** - Includes opening quote
2. **Clean name** - Without "śp." prefix
3. **Perfect diacritics** - All Polish characters preserved (ż ł ś ę ą ó ć ń ź)
4. **Czech diacritics** - ř ý preserved in contact info
5. **Formatting preserved** - Line breaks maintained
6. **Photo detection** - Accurate bounding box
7. **Contact info** - Complete with phone numbers

### ❌ Weaknesses

1. **Missing death_date** - Not extracted (though info exists in text: "w wieku 66 lat")
2. **Slower response** - 13s vs faster models

### 🎯 Use Cases

- **Primary extraction** for highest accuracy
- **Fallback provider** when Gemini fails
- **Quality validation** - Compare other models against this

## Comparison with Database

| Field             | Claude Sonnet 4.5 | Database             | Match              |
| ----------------- | ----------------- | -------------------- | ------------------ |
| full_name         | Stanislav Raszka  | śp. Stanislav Raszka | ✅ (cleaner)       |
| death_date        | null              | 2026-01-06           | ❌                 |
| funeral_date      | 2026-01-12        | 2026-01-12           | ✅                 |
| announcement_text | 470 chars         | 425 chars            | ✅ (more complete) |
| has_photo         | true              | true                 | ✅                 |
| diacritics        | Perfect           | Perfect              | ✅                 |

## Recommendation

**Usage:** Fallback provider for highest accuracy  
**Position in chain:** #2 (after Gemini 3 Flash)  
**Cost:** ~50-100 credits per image (200-400 images/month on Basic plan)
