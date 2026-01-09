# Cross-Document Model Comparison

Comparison of 4 Abacus.AI models tested on 2 death notice documents.

---

## Overall Performance Matrix

| Model | Raszka Score | Wilhelm Score | Avg Score | Avg Speed | Best For |
|-------|--------------|---------------|-----------|-----------|----------|
| **Gemini 3 Flash** | 75/100 ⚠️ | 95/100 ✅ | 85/100 | 13.8s | Fast, unlimited Czech |
| **Claude Sonnet 4.5** | 45/100 ❌ | 95/100 ✅ | 70/100 | 12.7s | Reliable coordinates |
| **Gemini 2.5 Pro** | 75/100 ⚠️ | 95/100 ✅ | 85/100 | 31.0s | Validation checks |
| **GPT-5.2** | 45/100 ❌ | 95/100 ✅ | 70/100 | 11.4s | NOT RECOMMENDED |

---

## Field-by-Field Accuracy

### Full Name Extraction

| Model | Raszka (Polish) | Wilhelm (Czech) | Notes |
|-------|-----------------|-----------------|-------|
| Gemini 3 Flash | ✅ Stanislav Raszka | ✅ Jindřich Wilhelm | Preserved original spelling |
| Claude Sonnet 4.5 | ❌ Stanisław Raszka | ✅ Jindřich Wilhelm | Normalized to Polish ł |
| Gemini 2.5 Pro | ✅ Stanislav Raszka | ✅ Jindřich Wilhelm | Preserved original spelling |
| GPT-5.2 | ❌ Stanisław Raszka | ✅ Jindřich Wilhelm | Normalized to Polish ł |

**Winner:** Gemini models (preserve original name as written in document)

---

### Death Date Extraction

| Model | Raszka (Polish) | Wilhelm (Czech) | Accuracy |
|-------|-----------------|-----------------|----------|
| Gemini 3 Flash | ❌ null | ✅ 2026-01-04 | 50% |
| Claude Sonnet 4.5 | ❌ null | ✅ 2026-01-04 | 50% |
| Gemini 2.5 Pro | ❌ null | ✅ 2026-01-04 | 50% |
| GPT-5.2 | ❌ null | ✅ 2026-01-04 | 50% |

**Why Raszka failed:** No explicit "Zmarł dnia X" date - only indirect clue "w wieku 66 lat"  
**Database value:** 2026-01-06 (calculated from announcement or external source)

---

### Funeral Date Extraction

| Model | Raszka (Polish) | Wilhelm (Czech) | Accuracy |
|-------|-----------------|-----------------|----------|
| Gemini 3 Flash | ✅ 2026-01-12 | ✅ 2026-01-09 | 100% |
| Claude Sonnet 4.5 | ✅ 2026-01-12 | ✅ 2026-01-09 | 100% |
| Gemini 2.5 Pro | ✅ 2026-01-12 | ✅ 2026-01-09 | 100% |
| GPT-5.2 | ✅ 2026-01-12 | ✅ 2026-01-09 | 100% |

**Winner:** ALL models (perfect extraction)

---

### Opening Quote (Not in DB)

| Model | Raszka | Wilhelm | Bonus Points |
|-------|--------|---------|--------------|
| Gemini 3 Flash | ✅ "Będę żyć dalej..." | ✅ "Czas rozstania..." | +5 each |
| Claude Sonnet 4.5 | ✅ "Będę żyć dalej..." | ✅ "Czas rozstania..." | +5 each |
| Gemini 2.5 Pro | ✅ "Będę żyć dalej..." | ✅ "Czas rozstania..." | +5 each |
| GPT-5.2 | ✅ "Będę żyć dalej..." | ✅ "Czas rozstania..." | +5 each |

**Winner:** ALL models (database is missing these valuable quotes!)

---

## Portrait Detection & Extraction

### Coordinate System Reliability

| Model | Raszka System | Wilhelm System | Consistency |
|-------|---------------|----------------|-------------|
| Gemini 3 Flash | ❌ Pixels (422, 100) | ✅ Percentage (40.8%, 10.8%) | ⚠️ INCONSISTENT |
| Claude Sonnet 4.5 | ✅ Percentage (39%, 10%) | ✅ Percentage (45%, 14%) | ✅ RELIABLE |
| Gemini 2.5 Pro | ✅ Percentage (42.1%, 9.7%) | ✅ Percentage (41.4%, 11.2%) | ✅ RELIABLE |
| GPT-5.2 | ✅ Percentage (43.9%, 8.0%) | ✅ Percentage (34.9%, 7.7%) | ✅ RELIABLE |

**Critical Issue:** Gemini 3 Flash returned pixels for Raszka but percentages for Wilhelm

---

### Portrait Quality (File Size)

| Model | Raszka Portrait | Wilhelm Portrait | Quality |
|-------|-----------------|------------------|---------|
| Gemini 3 Flash | ❌ 1.8 KB (FAILED) | ✅ 38 KB | Inconsistent |
| Claude Sonnet 4.5 | ✅ 33 KB | ✅ 17 KB | Good |
| Gemini 2.5 Pro | ✅ 23 KB | ✅ 23 KB | Good |
| GPT-5.2 | ✅ 24 KB | ✅ 19 KB | Good |

**Winner:** Claude Sonnet 4.5 (most reliable portrait extraction)

---

## Speed Analysis

### Text Extraction Speed

| Model | Raszka (549 KB) | Wilhelm (803 KB) | Average |
|-------|-----------------|------------------|---------|
| Gemini 3 Flash | 6.15s | 12.59s | 9.37s ⚡ |
| Claude Sonnet 4.5 | 12.92s | 10.67s | 11.80s |
| Gemini 2.5 Pro | 20.64s | 21.31s | 20.98s 🐌 |
| GPT-5.2 | 9.47s | 9.75s | 9.61s ⚡ |

---

### Photo Detection Speed

| Model | Raszka | Wilhelm | Average |
|-------|--------|---------|---------|
| Gemini 3 Flash | 3.94s | 4.91s | 4.43s |
| Claude Sonnet 4.5 | 3.93s | 3.89s | 3.91s ⚡ |
| Gemini 2.5 Pro | 24.00s | 20.39s | 22.20s 🐌 |
| GPT-5.2 | 3.90s | 3.60s | 3.75s ⚡ |

---

### Total Time (Text + Photo)

| Model | Raszka | Wilhelm | Average | Ranking |
|-------|--------|---------|---------|---------|
| GPT-5.2 | 13.37s | 13.35s | 13.36s | 🥇 Fastest |
| Claude Sonnet 4.5 | 16.85s | 14.56s | 15.71s | 🥈 |
| Gemini 3 Flash | 10.09s | 17.50s | 13.80s | 🥉 |
| Gemini 2.5 Pro | 44.64s | 41.70s | 43.17s | 🐌 Slowest |

**Note:** Gemini 3 Flash had unusual variance (10s vs 17.5s) - likely API caching

---

## Token Usage & Cost

### Average Tokens Per Document

| Model | Input Tokens | Output Tokens | Cost Implications |
|-------|--------------|---------------|-------------------|
| Gemini 3 Flash | 1,248 | 1,015 | 🚀 UNLIMITED |
| Claude Sonnet 4.5 | 1,743 | 325 | 💰 50-100 credits/image |
| Gemini 2.5 Pro | 3,510 | 1,481 | 🚀 UNLIMITED |
| GPT-5.2 | 1,922 | 268 | ⚠️ Limited quota |

**Monthly Capacity (assuming 1000 images/month):**
- Gemini 3 Flash: ✅ Unlimited
- Claude Sonnet 4.5: ⚠️ 200-400 images max
- Gemini 2.5 Pro: ✅ Unlimited (but slow)
- GPT-5.2: ❌ Unknown limit + diacritic errors

---

## Diacritic Accuracy

### Issue: Polish "Sadový" → "Sadowy"

| Model | Raszka | Wilhelm | Diacritic Errors |
|-------|--------|---------|------------------|
| Gemini 3 Flash | ✅ Sadový | ✅ Correct | 0 |
| Claude Sonnet 4.5 | ✅ Sadový | ✅ Correct | 0 |
| Gemini 2.5 Pro | ✅ Sadový | ✅ Correct | 0 |
| GPT-5.2 | ❌ Sadowy (missing ý) | ✅ Correct | 1 |

**Winner:** All Gemini & Claude models (perfect diacritic preservation)

---

## Final Recommendations

### 🏆 Production Configuration

**Primary Provider: Gemini 3 Flash Preview**
- ✅ Fast (average 13.8s)
- ✅ UNLIMITED usage
- ✅ Perfect diacritics
- ✅ Includes opening quotes
- ⚠️ Fix coordinate system detection bug

**Fallback Provider: Claude Sonnet 4.5**
- ✅ Most reliable coordinates
- ✅ Consistent performance
- ✅ Good portrait detection
- ⚠️ Limited to 200-400 images/month
- ⚠️ Name normalization issue (Stanislav→Stanisław)

**Validation Provider: Gemini 2.5 Pro**
- ✅ UNLIMITED usage
- ✅ Most accurate text extraction
- ❌ Too slow (43s average)
- Use only for: complex documents, quality checks, appeals

---

### ❌ NOT Recommended

**GPT-5.2**
- ❌ Diacritic corruption (Sadový→Sadowy)
- ❌ Name normalization (Stanislav→Stanisław)
- ✅ Fast but unreliable for Czech/Polish text

---

## Critical Issues to Fix

1. **Gemini 3 Flash Coordinate Bug**
   - Returns pixels on some images, percentages on others
   - Need detection logic: if `x > 100 || y > 100` → convert to percentage
   - Formula: `percentage = (pixels / image_dimension) * 100`

2. **Name Normalization**
   - Claude & GPT-5.2 change "Stanislav" → "Stanisław"
   - May indicate OCR correction, but should preserve original
   - Consider: use Gemini for name extraction, Claude for portraits

3. **Death Date Extraction**
   - All models missed Raszka death date (2026-01-06)
   - Document only says "w wieku 66 lat" (age 66)
   - May need: enhanced prompt or external calculation

4. **Opening Quotes Missing in DB**
   - All Abacus models capture opening quotes
   - Current DB extraction (Gemini/ZhipuAI/Claude) misses them
   - Consider: re-processing existing records

---

## Test Data Summary

### Raszka (Polish)
- **File:** 549 KB JPEG, 2458x3488px
- **Language:** Polish + Czech contact
- **Complexity:** No explicit death date
- **Best Model:** Gemini 3 Flash (75/100)

### Wilhelm (Czech)
- **File:** 803 KB JPEG, 2458x3488px
- **Language:** Czech
- **Complexity:** Clear date structure
- **Best Models:** ALL TIED (95/100)

---

**Test Date:** January 8, 2026  
**API:** Abacus.AI RouteLLM (https://routellm.abacus.ai)  
**Models Tested:** 4  
**Documents Tested:** 2  
**Total API Calls:** 16 (8 text + 8 photo)
