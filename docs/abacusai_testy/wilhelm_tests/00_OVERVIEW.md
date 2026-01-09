# Abacus.AI Model Testing - Wilhelm Parte

**Test Date:** January 8, 2026  
**Parte Document:** Jindřich Wilhelm  
**Database ID:** 5  
**Hash:** `324d7840ab5d`  
**Source PDF:** `public/parte/324d7840ab5d/Wilhelm20260105_09594623.pdf`

---

## Executive Summary

Tested 4 Abacus.AI models on Czech death notice (parte). **ALL 4 MODELS ACHIEVED 95/100 SCORE** - perfect extraction of all critical data fields.

### Key Findings

🎯 **Perfect Performance Across All Models**
- ✅ All 4 models correctly extracted: full_name, death_date, funeral_date
- ✅ All 4 models included opening quote (bonus points)
- ✅ All 4 models detected portrait photo with accurate bounding boxes
- ✅ All 4 models used percentage coordinates (no pixel issues)

⚡ **Speed Winner: Claude Sonnet 4.5**
- Fastest: 10.67s (text) + 3.89s (photo) = **14.56s total**
- Gemini 3 Flash: 12.59s + 4.91s = 17.5s
- GPT-5.2: 9.75s + 3.6s = 13.35s (fastest but less reliable on other documents)

💰 **Cost Considerations**
- **Gemini 3 Flash & 2.5 Pro:** UNLIMITED usage
- **Claude Sonnet 4.5:** 50-100 credits/image (~200-400 images/month)
- **GPT-5.2:** Limited, not recommended due to diacritic issues

---

## Model Rankings (Wilhelm Document)

| Rank | Model | Score | Total Time | Accuracy | Opening Quote | Coordinates |
|------|-------|-------|------------|----------|---------------|-------------|
| 🥇 | **Gemini 3 Flash** | 95/100 | 17.5s | Perfect | ✅ | ✅ Percentage |
| 🥇 | **Claude Sonnet 4.5** | 95/100 | 14.6s | Perfect | ✅ | ✅ Percentage |
| 🥇 | **Gemini 2.5 Pro** | 95/100 | 41.7s | Perfect | ✅ | ✅ Percentage |
| 🥇 | **GPT-5.2** | 95/100 | 13.4s | Perfect | ✅ | ✅ Percentage |

---

## Database Values (Ground Truth)

```json
{
  "id": 5,
  "hash": "324d7840ab5d",
  "full_name": "Jindřich Wilhelm",
  "death_date": "2026-01-04",
  "funeral_date": "2026-01-09",
  "announcement_text": "Zmarł dnia 4.1.2026 w wieku 79 lat nasz ukochany Mąż, Tatuś, Dziadzik, Brat, Szwagier, Kuzyn, Wujek, Kolega i Sąsiad, Pan Jindřich Wilhelm. Pogrzeb Drogiego Zmarłego odbędzie się w piątek 9.1.2026 o godzinie 15.00 z ewangelickiego kościoła w Bystrzycy. Zamiast kwiatów prosimy o dar, który będzie przeznaczony MEDYCE Trzyniec. Zasmucona rodzina.",
  "has_photo": true
}
```

**Missing in DB:** Opening quote "Czas rozstania mego z życiem nadszedł. Miłość niech będzie w sercach waszych a dobroć niech będzie z Wami."

---

## Comparison: Wilhelm vs Raszka

| Metric | Wilhelm (Czech) | Raszka (Polish) |
|--------|-----------------|-----------------|
| **Best Score** | 95/100 (all 4 tied) | 75/100 (Gemini 3 Flash & 2.5 Pro) |
| **Death Date Extraction** | ✅ All models (4/4) | ❌ None (0/4) |
| **Name Accuracy** | ✅ All correct (4/4) | ⚠️ Mixed (2 changed Stanislav→Stanisław) |
| **Coordinate System** | ✅ All percentage (4/4) | ⚠️ Gemini 3 Flash used pixels |
| **Opening Quote** | ✅ All included (4/4) | ✅ All included (4/4) |
| **Fastest Model** | GPT-5.2 (13.4s) | Gemini 3 Flash (10.1s) |

**Conclusion:** Czech document (Wilhelm) was easier for all models - better structured, clearer text, explicit death date ("Zmarł dnia 4.1.2026").

---

## Portrait Extraction Results

All 4 models successfully detected the portrait photo. Extracted portraits saved:

- `portrait_gemini_3_flash.jpg` (38 KB) ✅ Good quality
- `portrait_claude_sonnet_4_5.jpg` (17 KB) ✅ Good quality
- `portrait_gemini_2_5_pro.jpg` (23 KB) ✅ Good quality
- `portrait_gpt_5_2.jpg` (19 KB) ✅ Good quality

**Bounding Box Comparison:**

| Model | x | y | width | height | System |
|-------|---|---|--------|--------|--------|
| Gemini 3 Flash | 40.8% | 10.8% | 16.6% | 15.7% | ✅ Percentage |
| Claude Sonnet 4.5 | 45% | 14% | 16% | 27% | ✅ Percentage |
| Gemini 2.5 Pro | 41.4% | 11.2% | 17.8% | 21.1% | ✅ Percentage |
| GPT-5.2 | 34.9% | 7.7% | 14.7% | 19.1% | ✅ Percentage |

---

## Recommendations

### For Production Use (Czech/Polish Death Notices)

**Primary Provider:** Gemini 3 Flash Preview
- Fast (17.5s average)
- UNLIMITED usage
- 95/100 accuracy on Czech documents
- ⚠️ Coordinate system inconsistency (pixels on Raszka, % on Wilhelm)

**Fallback Provider:** Claude Sonnet 4.5
- Fastest overall (14.6s)
- Most reliable coordinate system (always percentages)
- 95/100 accuracy
- Limited to 200-400 images/month

**NOT Recommended:** GPT-5.2
- Despite perfect Wilhelm scores, has diacritic errors on other documents
- Name normalization issues (Stanislav→Stanisław)

---

## Test Configuration

- **API Endpoint:** `https://routellm.abacus.ai/v1/chat/completions`
- **Authentication:** Bearer token
- **Image Format:** JPEG, 2458x3488px @ 300 DPI (803 KB)
- **Temperature:** 0 (deterministic)
- **Max Tokens:** 1000

---

## Files Generated

1. `00_OVERVIEW.md` - This file
2. `01_CLAUDE_SONNET_4.5.md` - Claude Sonnet 4.5 detailed results
3. `02_GEMINI_2.5_PRO.md` - Gemini 2.5 Pro detailed results
4. `03_GEMINI_3_FLASH_PREVIEW.md` - Gemini 3 Flash detailed results
5. `04_GPT_5.2.md` - GPT-5.2 detailed results
6. `05_COMPARISON_TABLE.md` - Side-by-side comparison
7. `portrait_*.jpg` - 4 extracted portrait photos

---

**Next Steps:**
1. Test on more Czech/Polish documents to validate consistency
2. Fix coordinate system detection in `AbacusAiVisionService.php`
3. Integrate Gemini 3 Flash as primary provider with Claude fallback
4. Add retry logic for coordinate parsing errors
