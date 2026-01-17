# Abacus.AI Models Testing - Overview

**Test Date:** 2026-01-08  
**Test Image:** `public/parte/b8b1aab1fc52/test_full_page.jpg`  
**Death Notice:** Stanislav Raszka (Polish death notice)

## Models Tested

| #   | Model                      | Status       | Vision Support | Notes                     |
| --- | -------------------------- | ------------ | -------------- | ------------------------- |
| 1   | **Claude Sonnet 4.5**      | ✅ PASS      | ✅ Yes         | Baseline (tested earlier) |
| 2   | **GEMINI-2.5-PRO**         | ✅ PASS      | ✅ Yes         | UNLIMITED usage           |
| 3   | **GEMINI-3-FLASH-PREVIEW** | ✅ PASS      | ✅ Yes         | UNLIMITED usage           |
| 4   | **GPT-5.2**                | ✅ PASS      | ✅ Yes         | OpenAI latest             |
| 5   | GEMINI-2.5-FLASH-IMAGE     | ❌ FAIL      | -              | Invalid model name        |
| 6   | DeepSeek-V3.2              | ❌ FAIL      | -              | Invalid model name        |
| 7   | QWEN3-MAX                  | ❌ FAIL      | -              | Invalid model name        |
| 8   | GROK-4-0709                | ⏱️ TIMEOUT   | -              | >90s timeout              |
| 9   | DeepSeek-V3                | ⚠️ NO VISION | ❌ No          | Text-only model           |

## Key Findings

### ✅ Working Models with Vision

1. **Claude Sonnet 4.5** - Highest quality extraction
2. **GEMINI-2.5-PRO** - UNLIMITED, slower (24s)
3. **GEMINI-3-FLASH-PREVIEW** - UNLIMITED, fast (5s)
4. **GPT-5.2** - Good quality (9s)

### ❌ Failed Models

- `GEMINI-2.5-FLASH-IMAGE` - Wrong name, use `GEMINI-2.5-FLASH` instead
- `DEEPSEEK-V3.2` - Wrong name, use `DeepSeek-V3` (but no vision)
- `QWEN3-MAX` - Model not available in Abacus.AI
- `GROK-4-0709` - Timeout (>90s)

### 🎯 Recommended for Production

**Primary:** `GEMINI-3-FLASH-PREVIEW`

- UNLIMITED usage
- Fast (5s response)
- Good accuracy
- Free from hard limits

**Fallback 1:** `CLAUDE-SONNET-4-5-20250929`

- Highest accuracy
- Complete text extraction
- 50-100 credits/image

**Fallback 2:** `GPT-5.2`

- Good balance
- Preserves formatting
- Medium speed

**Fallback 3:** `GEMINI-2.5-PRO`

- UNLIMITED usage
- Highest quality (but slower)
- 2.5k output tokens

## Performance Comparison

| Model             | Response Time | Input Tokens | Output Tokens | Quality    |
| ----------------- | ------------- | ------------ | ------------- | ---------- |
| Claude Sonnet 4.5 | ~13s          | 1,712        | 326           | ⭐⭐⭐⭐⭐ |
| GEMINI-3-FLASH    | ~5s           | 1,226        | 259           | ⭐⭐⭐⭐   |
| GEMINI-2.5-PRO    | ~24s          | 3,488        | 2,550         | ⭐⭐⭐⭐⭐ |
| GPT-5.2           | ~9s           | 1,898        | 256           | ⭐⭐⭐⭐   |

## Next Steps

1. ✅ Implement Abacus.AI into `VisionOcrService.php`
2. ✅ Use `GEMINI-3-FLASH-PREVIEW` as primary (UNLIMITED)
3. ✅ Configure fallback chain: Gemini 3 → Claude 4.5 → GPT-5.2
4. ✅ Add tests for all working models
5. ✅ Update documentation
