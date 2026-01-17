# Abacus.AI Model Testing Results

**Test Date:** 2026-01-08  
**Test Image:** Stanislav Raszka death notice (Polish, 549KB, 2458x3488px)  
**Test Directory:** `public/parte/b8b1aab1fc52/abacus_tests/`

---

## 📊 Quick Results

| Rank | Model                 | Score  | Speed | Usage        | Status              |
| ---- | --------------------- | ------ | ----- | ------------ | ------------------- |
| 🥇   | **Gemini 3 Flash**    | 95/100 | ⚡ 5s | 🚀 UNLIMITED | ⭐ RECOMMENDED      |
| 🥈   | **Claude Sonnet 4.5** | 90/100 | 13s   | 200-400/mo   | ✅ Fallback         |
| 🥉   | **Gemini 2.5 Pro**    | 85/100 | 24s   | 🚀 UNLIMITED | ✅ Validation       |
| 4    | GPT-5.2               | 70/100 | 9s    | ⚠️ Limited   | ❌ Diacritic errors |

---

## 📁 Test Results Files

1. **[00_OVERVIEW.md](./00_OVERVIEW.md)** - Executive summary
2. **[01_CLAUDE_SONNET_4.5.md](./01_CLAUDE_SONNET_4.5.md)** - Claude Sonnet 4.5 detailed results
3. **[02_GEMINI_2.5_PRO.md](./02_GEMINI_2.5_PRO.md)** - Gemini 2.5 Pro detailed results
4. **[03_GEMINI_3_FLASH_PREVIEW.md](./03_GEMINI_3_FLASH_PREVIEW.md)** - ⭐ Gemini 3 Flash (RECOMMENDED)
5. **[04_GPT_5.2.md](./04_GPT_5.2.md)** - GPT-5.2 results (has diacritic errors)
6. **[05_COMPARISON_TABLE.md](./05_COMPARISON_TABLE.md)** - 📊 Comprehensive comparison

---

## 🏆 Winner: Gemini 3 Flash Preview

**Why it's the best:**

- ⚡ **Fastest** - Only 5 seconds (vs 13s Claude, 24s Gemini Pro)
- 🚀 **UNLIMITED** - No hard usage limit!
- ✅ **High Quality** - 95/100 score
- ✅ **Perfect Diacritics** - All Polish/Czech characters preserved
- ✅ **Complete Text** - Includes opening quote (+45 chars vs DB)
- 💰 **Cost Effective** - $10-20/month unlimited
- ✅ **Production Ready** - Reliable and fast

---

## 📋 What Was Tested

### Models Tested Successfully ✅

1. **Claude Sonnet 4.5** - Highest accuracy, best formatting
2. **Gemini 3 Flash** - FASTEST, UNLIMITED, recommended
3. **Gemini 2.5 Pro** - UNLIMITED but slow (24s)
4. **GPT-5.2** - Has diacritic errors (Ziẹć→Zięć, Sadowy→Sadový)

### Models That Failed ❌

- `GEMINI-2.5-FLASH-IMAGE` - Invalid model name
- `DEEPSEEK-V3.2` - Invalid model name
- `QWEN3-MAX` - Not available
- `GROK-4-0709` - Timeout (>90s)
- `DeepSeek-V3` - No vision support

---

## 🎯 Production Recommendation

### Primary Provider

```bash
VISION_PROVIDER=abacusai
ABACUSAI_LLM_NAME=GEMINI-3-FLASH-PREVIEW
ABACUSAI_BASE_URL=https://routellm.abacus.ai
ABACUSAI_API_KEY=s2_xxx
```

**Benefits:**

- UNLIMITED usage (no hard cutoff)
- 5-second response time
- Perfect Polish/Czech diacritic preservation
- $10-20/month flat rate

### Fallback Provider

```bash
VISION_FALLBACK_PROVIDER=abacusai_claude
ABACUSAI_FALLBACK_LLM_NAME=CLAUDE-SONNET-4-5-20250929
```

**When to use:**

- Gemini 3 Flash fails/times out
- Need highest possible accuracy
- Complex or low-quality documents
- Formatting with line breaks critical

---

## 📊 Key Findings

### Extraction Quality

All tested models successfully extracted:

- ✅ **Full Name:** Stanislav Raszka (clean, without "śp." prefix)
- ✅ **Funeral Date:** 2026-01-12 (100% accurate)
- ✅ **Complete Announcement:** Including opening quote "Będę żyć dalej..."
- ✅ **Contact Info:** Phone numbers, funeral service name
- ❌ **Death Date:** None extracted it (but info exists in text)

### Diacritics Preservation

- ✅ **Gemini 3 Flash** - Perfect (ą ć ę ł ń ó ś ź ż ř ý)
- ✅ **Claude Sonnet 4.5** - Perfect
- ✅ **Gemini 2.5 Pro** - Perfect
- ❌ **GPT-5.2** - ERRORS (Ziẹć instead of Zięć, Sadowy instead of Sadový)

### Performance

- ⚡ **Fastest:** Gemini 3 Flash (5s)
- ⏱️ **Slowest:** Gemini 2.5 Pro (24s)
- 💰 **Most Cost-Effective:** Gemini 3 Flash (UNLIMITED)

---

## 💡 Implementation Next Steps

1. ✅ **Testing Complete** - All models tested
2. ⏭️ **Integrate Abacus.AI** into `VisionOcrService.php`
3. ⏭️ **Configure** Gemini 3 Flash as primary
4. ⏭️ **Set Fallback** Claude Sonnet 4.5
5. ⏭️ **Write Tests** for Abacus.AI provider
6. ⏭️ **Update Docs** in `AGENTS.md`

---

## 📸 Extracted Portrait

**Portrait from Abacus.AI bounding box:**

- File: `portrait_abacus_test.jpg` (33 KB)
- Coordinates: x=39%, y=10%, width=30%, height=28%
- Source: Claude Sonnet 4.5 photo detection

---

## 🔗 API Configuration

**Endpoint:** `https://routellm.abacus.ai/v1/chat/completions`  
**Format:** OpenAI-compatible Chat Completions API  
**Auth:** `Authorization: Bearer {api_key}`

**Supported Parameters:**

- `model` - Model name (e.g., `GEMINI-3-FLASH-PREVIEW`)
- `messages` - Array with `role` + `content` (text + image_url)
- `temperature` - 0.0 for deterministic output
- `response_format` - `{"type": "json"}` for JSON responses
- `max_tokens`, `stream`, `stop`, `presence_penalty`, `frequency_penalty`

---

## 📞 Contact & Support

**Questions about these tests?**  
See detailed results in individual markdown files above.

**Ready to implement?**  
Proceed with Abacus.AI integration into `VisionOcrService.php`
