# Právní aspekty služby Parte - Analýza GDPR a dalších právních otázek

**Datum vypracování:** 2. ledna 2026  
**Účel dokumentu:** Právní posouzení souladu se zákonem o ochraně osobních údajů (GDPR) a dalšími právními předpisy

---

## Shrnutí pro management

**Právní status:** Služba operuje v šedé zóně s významnými právními riziky, ale s možností legálního provozu při splnění konkrétních podmínek.

**Klíčová zjištění:**

- ✅ Zpracování údajů o zemřelých osobách primárně NEPODLÉHÁ GDPR
- ⚠️ Vyžaduje však respektování práv pozůstalých a dobrých mravů
- ⚠️ Autorská práva k parte představují právní riziko
- ✅ Při správném nastavení je služba legální a v souladu s českým právem

---

## 1. GDPR a ochrana osobních údajů

### 1.1 Vztahuje se GDPR na zemřelé osoby?

**ODPOVĚĎ: NE, s výjimkami**

Podle čl. 27 Preambule GDPR:

> _"Toto nařízení se nevztahuje na osobní údaje zemřelých osob. Členské státy mohou stanovit pravidla pro zpracování osobních údajů zemřelých osob."_

**Český zákon o zpracování osobních údajů (ZZOOÚ) § 1 odst. 4:**

> _"Tento zákon se nevztahuje na zpracování osobních údajů zemřelých."_

### 1.2 Jaká data služba zpracovává?

| Datový prvek   | Typ                 | GDPR aplikace             |
| -------------- | ------------------- | ------------------------- |
| `full_name`    | Jméno zemřelé osoby | ❌ NE (zemřelá osoba)     |
| `death_date`   | Datum úmrtí         | ❌ NE (veřejná informace) |
| `funeral_date` | Datum pohřbu        | ❌ NE (veřejná informace) |
| `source_url`   | URL zdroje          | ❌ NE (veřejný zdroj)     |
| PDF parte      | Sken/kopie parte    | ⚠️ AUTORSKÉ PRÁVO         |

### 1.3 Pozůstalí a jejich práva

**Ačkoliv GDPR neplatí, pozůstalí mají stále práva:**

1. **Ochrana osobnosti zemřelého** (§ 13 a násl. občanského zákoníku)
    - Pozůstalí mohou bránit neoprávněným zásahům do osobnosti zemřelého
    - Právo na ochranu dobrého jména zemřelého

2. **Právo na zapomenutí (analogické)**
    - Pozůstalí mohou požadovat odstranění údajů z důvodu ochrany důstojnosti zemřelého
    - **DOPORUČENÍ:** Implementovat mechanismus pro žádosti o odstranění

### 1.4 Právní základ zpracování

**Legitimní základ pro provoz služby:**

✅ **Čl. 6 odst. 1 písm. f) GDPR - Oprávněný zájem** (pro případné související údaje):

- Archivace veřejně přístupných informací
- Poskytování vyhledávací služby pro pozůstalé
- Historický a genealogický výzkum

✅ **Veřejný zájem:**

- Parte jsou tradičně veřejné dokumenty
- Služba pouze agreguje již veřejně dostupné informace
- Podobné principu novinových archivů

---

## 2. Autorská práva k parte

### 2.1 Problematika

⚠️ **VÝZNAMNÉ RIZIKO:** Parte jsou chráněny autorským zákonem

**Zákon č. 121/2000 Sb., autorský zákon:**

1. **Parte jako autorské dílo:**
    - Design, grafika, fotografie zemřelého = autorské dílo
    - Autor: pohřební služba nebo rodina zemřelého
    - Ochrana: 70 let po smrti autora (u fotografií)

### 2.1a VAROVÁNÍ: Extrakce fotografií

⚠️ **NOVÉ RIZIKO:** Systém nyní automaticky extrahuje fotografie zemřelých z parte dokumentů

**PRÁVNÍ RIZIKA:**

- Fotografie jsou autorská díla (§ 2 autorského zákona)
- Fotografové mají autorská práva (pokud nejsou převedena na pohřební službu)
- Pozůstalí mají osobnostní práva k podobizně zemřelého (§ 84 občanského zákoníku)
- Veřejné zobrazení fotografií může vyžadovat souhlas pozůstalých nebo autora fotografie
- Ořezání a úprava fotografie = vytvoření odvozených děl (§ 2 odst. 5)

**DOPORUČENÍ:**

- ⚠️ **KRITICKÉ:** Konzultovat s právníkem před veřejným zobrazením fotografií
- Zvážit pouze archivační účely bez veřejného URL přístupu
- Omezit přístup pouze na autorizované uživatele
- Implementovat mechanismus pro žádosti pozůstalých o odstranění fotografií
- Zvážit watermark "Pro archivační účely" na extraovaných fotografiích

2. **Co aplikace dělá:**
    - Stahuje PDF parte (= rozmnožování díla)
    - Ukládá a archivuje (= rozmnožování a sdělování veřejnosti)
    - **POTENCIÁLNĚ porušuje autorská práva**

### 2.2 Právní obrana - výjimky z autorského práva

✅ **§ 30 Autorského zákona - Právo citace:**

> _"Do práva autorského nezasahuje ten, kdo užije zkráceně nebo formou výtahu zpráv nebo článků v souhrnu přehledů tisku."_

⚠️ **PROBLÉM:** Parte nejsou "zprávy" v užším smyslu

✅ **§ 37 Autorského zákona - Veřejně přístupné databáze:**

> _"Za předpokladu uvedení zdroje je dovoleno bez svolení autora užít pro vlastní vnitřní potřebu jednotlivé dílo."_

⚠️ **PROBLÉM:** Služba poskytuje údaje veřejně, ne jen pro "vnitřní potřebu"

✅ **§ 38c Autorského zákona - Vytěžování databází:**

> _"Pořizovatel databáze má právo zakázat vytěžování nebo znovuvyužití celého obsahu databáze nebo její podstatné části."_

### 2.3 Řešení autorských práv

**DOPORUČENÉ KROKY:**

1. ✅ **Získat licenci od pohřebních služeb**
    - Uzavřít smlouvu o sdružování obsahu
    - Odstranit údaje od služeb, které nesouhlasí

2. ✅ **Implementovat robot.txt respekt**
    - Respektovat `robots.txt` pohřebních služeb
    - Přidat `User-Agent` identifikaci služby
    - Vytvořit opt-out mechanismus

3. ✅ **Transformativní použití**
    - Extrahovat pouze strukturovaná data (jméno, data)
    - Negenerovat 1:1 kopie parte
    - Odkazovat na originální zdroj

4. ✅ **Fair use / oprávněné užití**
    - Archivační účel
    - Veřejný zájem (vyhledávání zesnulých)
    - Nepřekáží normálnímu využití díla

---

## 3. Ochrana osobních údajů provozovatelů

### 3.1 Jaká data o živých osobách služba zpracovává?

**VAROVÁNÍ:** Parte MOHOU obsahat údaje živých osob:

- Jména pozůstalých (manžel/ka, děti)
- Kontaktní informace na organizátory pohřbu
- Podpisy autorů parte

**Tato data PODLÉHAJÍ GDPR!**

### 3.2 Minimalizace rizika

✅ **OCR extrakce pouze relevantních dat:**

```php
// Extrahujeme pouze:
- full_name (zemřelého)
- death_date
- funeral_date

// NEEXTRAHUJEME:
- Jména pozůstalých
- Telefonní čísla
- E-maily
```

✅ **Aktuální implementace JE v souladu:**

- `GeminiService` extrahuje pouze jméno zemřelého a data
- Neukládá plný OCR text
- PDF jsou uložena jako archiv, ne pro zpracování živých osob

---

## 4. Transparentnost a informační povinnost

### 4.1 Co musí služba zveřejnit?

**DOPORUČENÉ DOKUMENTY:**

1. ✅ **Podmínky použití (Terms of Service)**
    - Účel služby: archivace veřejně dostupných parte
    - Zdroje dat: pohřební služby (seznam)
    - Práva pozůstalých

2. ✅ **Zásady ochrany soukromí (Privacy Policy)**
    - I když GDPR neplatí na zemřelé, je to etické
    - Vysvětlit zpracování dat
    - Kontakt pro žádosti o odstranění

3. ✅ **Žádost o odstranění údajů**
    - Formulář pro pozůstalé
    - Proces ověření (ochrana proti zneužití)
    - Lhůta: do 30 dnů

### 4.2 Kontaktní informace

**PRÁVNÍ POŽADAVEK (§ 435 zákona č. 89/2012 Sb., občanský zákoník):**

Služba musí uvádět:

- Název/jméno provozovatele
- Sídlo/adresa
- IČO (pokud podnikatel)
- Kontaktní e-mail
- Telefonní číslo (doporučeno)

---

## 5. Odpovědnost za obsah

### 5.1 Nepřesné nebo zastaralé údaje

**Problém:** OCR může chybně přečíst jméno nebo datum

**Právní riziko:**

- Zásah do osobnostních práv (§ 11 a násl. OZ)
- Šíření nepravdivých informací

**Řešení:**

```php
// Disclaimer v UI:
"Údaje jsou extrahovány automaticky pomocí OCR technologie
a mohou obsahovat chyby. Vždy ověřujte na původním zdroji."

// Odkaz na původní parte od pohřební služby
```

### 5.2 Omezení odpovědnosti

**Doporučený text:**

> _"Služba Parte funguje jako agregátor veřejně dostupných informací. Neposkytujeme záruku za úplnost, přesnost nebo aktuálnost údajů. Primárním zdrojem informací jsou vždy webové stránky pohřebních služeb."_

---

## 6. FAQ - Právní otázky a odpovědi

### Q1: Je služba legální podle GDPR?

**A:** Ano, primárně ANO. GDPR se nevztahuje na údaje zemřelých osob. Služba však musí:

- Respektovat práva pozůstalých
- Implementovat mechanismus pro odstranění údajů na žádost
- Nezpracovávat údaje živých osob z parte (jména pozůstalých)

### Q2: Potřebujeme souhlas pozůstalých?

**A:** Ne, ze zákona ne. Parte jsou veřejné dokumenty. Ale:

- **DOPORUČENO:** Poskytnout opt-out mechanismus
- Etický přístup: respektovat žádosti o odstranění
- Analogie: novinové archivy také nepotřebují souhlas

### Q3: Porušujeme autorská práva stahováním PDF?

**A:** Potenciálně ano, ale:

- **ŘEŠENÍ 1:** Získat licenci od pohřebních služeb
- **ŘEŠENÍ 2:** Ukládat pouze URL + metadata (ne PDF)
- **ŘEŠENÍ 3:** Transformativní použití (jen data, ne design)
- **AKTUÁLNÍ STAV:** Riziko existuje, doporučeno ošetřit

### Q4: Co když někdo chce odstranit parte svého příbuzného?

**A:** MUSÍTE vyhovět, i když zákon nepřikazuje:

- Ochrana osobnosti zemřelého (§ 13 OZ)
- Oprávněné osoby: manžel/ka, děti, rodiče, sourozenci
- Proces:
    1. Ověření identity žadatele
    2. Ověření vztahu k zemřelému
    3. Odstranění do 30 dnů
    4. Potvrzení o odstranění

### Q5: Musíme mít pověřence pro ochranu osobních údajů (DPO)?

**A:** Pravděpodobně NE:

- Služba nezpracovává citlivé údaje živých osob ve velkém rozsahu
- Není veřejný orgán
- **VÝJIMKA:** Pokud byste začali zpracovávat údaje pozůstalých (registrace uživatelů), pak zvážit

### Q6: Co když pohřební služba zakáže scrapování (robots.txt)?

**A:** MUSÍTE respektovat:

- `robots.txt` je standard, porušení = možný právní postih
- Nerespektování = nelegální přístup k systému (§ 230 trestního zákoníku)
- **IMPLEMENTACE:**
    ```php
    // Před scrapováním:
    if (!$this->isAllowedByRobotsTxt($url)) {
        return; // Přeskočit
    }
    ```

### Q7: Můžeme parte archivovat navždy?

**A:** Ano, ale s výhradou:

- Archivace je legitimní zájem
- Pozůstalí mají právo požádat o odstranění
- **DOPORUČENÍ:** Retention policy (např. 10 let od úmrtí)

### Q8: Co když extrahujeme omylem jméno živé osoby?

**A:** GDPR riziko:

- Pokud je osoba žijící → GDPR platí plně
- **ŘEŠENÍ:** Filtrace (pouze jména s datumem úmrtí)
- Mechanismus pro nahlášení chyby

### Q9: Musíme platit daň z digitální služby?

**A:** Závisí na business modelu:

- **Pokud ZDARMA:** Ne
- **Pokud REKLAMY:** Ano, při překročení limitu (≥ 750 mil. € globální tržby / 50 mil. € v EU)
- **Pokud PŘEDPLATNÉ:** Běžná DPH (21% v ČR)

### Q10: Co s parte z Polska?

**A:** Stejná pravidla:

- GDPR platí v celé EU stejně
- Polský autorský zákon podobný českému
- **DOPORUČENÍ:** Konzultace s polským právníkem pro jistotu

---

## 7. Rizikové oblasti a doporučení

### 7.1 Rizikové oblasti (seřazeno podle závažnosti)

| Riziko                              | Závažnost  | Pravděpodobnost | Dopad                                |
| ----------------------------------- | ---------- | --------------- | ------------------------------------ |
| **Autorská práva k PDF parte**      | 🔴 VYSOKÁ  | Střední         | Soudní spor, náhrada škody           |
| **Žádost pozůstalých o odstranění** | 🟡 STŘEDNÍ | Vysoká          | Reputační riziko                     |
| **Nerespektování robots.txt**       | 🟡 STŘEDNÍ | Nízká           | Zákaz přístupu, možný trestní postih |
| **Extrakce dat živých osob**        | 🟢 NÍZKÁ   | Velmi nízká     | GDPR pokuta (aktuálně nerelevantní)  |

### 7.2 Prioritní akční kroky

**MUSÍ být implementováno (právně nutné):**

1. ✅ **Respektování robots.txt**
    - Priority: KRITICKÁ
    - Deadline: Okamžitě
2. ✅ **Kontaktní formulář pro odstranění údajů**
    - Priority: VYSOKÁ
    - Deadline: Do 1 měsíce

3. ✅ **Zásady ochrany soukromí + podmínky použití**
    - Priority: VYSOKÁ
    - Deadline: Do 1 měsíce

**DOPORUČENO (best practice):**

4. ✅ **Získání licence od pohřebních služeb**
    - Priority: STŘEDNÍ
    - Deadline: Do 3 měsíců

5. ✅ **Pravidelný audit extrahovaných dat**
    - Priority: NÍZKÁ
    - Deadline: Kontinuální

---

## 8. Implementační checklist

### Technické implementace

- [ ] **robots.txt parser**

    ```php
    public function isAllowedByRobotsTxt(string $url): bool
    {
        // Implementace kontroly robots.txt
    }
    ```

- [ ] **Žádost o odstranění údajů**

    ```php
    // Route: POST /api/removal-request
    // Parametry: name, death_date, email, reason
    // Proces: email verification → manual review → removal
    ```

- [ ] **Disclaimer v PDF view**

    ```blade
    <div class="disclaimer">
      Údaje extrahované automaticky.
      <a href="{{ $source_url }}">Ověřte na zdroji</a>
    </div>
    ```

- [ ] **Rate limiting scrapingu**
    ```php
    // Aby pohřební služby nebyly přetížené
    sleep(rand(2, 5)); // mezi požadavky
    ```

### Dokumentace

- [ ] **Zásady ochrany soukromí** (`/privacy-policy`)
- [ ] **Podmínky použití** (`/terms-of-service`)
- [ ] **O projektu** (`/about`) - vysvětlit účel
- [ ] **Žádost o odstranění** (`/removal-request`)
- [ ] **Kontakt** (`/contact`)

### Právní konzultace

- [ ] Konzultace s advokátem (autorská práva)
- [ ] Registrace u ÚOOÚ? (pravděpodobně ne)
- [ ] Pojištění odpovědnosti? (doporučeno)

---

## 9. Závěr

### 9.1 Je služba legální?

**ANO**, za podmínek:

✅ Údaje zemřelých nejsou chráněny GDPR  
✅ Parte jsou veřejné dokumenty  
✅ Archivace je v legitimním zájmu  
✅ Služba poskytuje společenský přínos

**ALE:**

⚠️ Musí být implementovány ochranné mechanismy  
⚠️ Musí být respektována autorská práva  
⚠️ Musí být respektována práva pozůstalých

### 9.2 Celkové hodnocení

| Aspekt                    | Hodnocení    | Poznámka                     |
| ------------------------- | ------------ | ---------------------------- |
| **GDPR compliance**       | ✅ VYHOVUJE  | Zemřelí nejsou subjekty GDPR |
| **Autorská práva**        | ⚠️ RIZIKO    | Doporučeno řešit licencí     |
| **Práva pozůstalých**     | ✅ VYHOVUJE  | S opt-out mechanismem        |
| **Transparentnost**       | ⚠️ NEÚPLNÉ   | Chybí Privacy Policy         |
| **Technické zabezpečení** | ✅ ADEKVÁTNÍ | Redis queue, retry, hash     |

### 9.3 Doporučení

**Pro bezpečný a legální provoz:**

1. Doplnit chybějící dokumenty (Privacy Policy, ToS)
2. Implementovat removal request proces
3. Kontaktovat pohřební služby pro licenci
4. Zvážit konzultaci s advokátem
5. Monitorovat změny v legislativě

---

## 10. Právní disclaimer

Tento dokument byl vypracován na základě analýzy zdrojového kódu aplikace a aktuální právní úpravy v České republice k datu 2. ledna 2026.

**Upozornění:**

- Tento dokument není právní poradnou
- Nenahrazuje konzultaci s advokátem
- Právní úprava se může změnit
- Doporučeno ověření u specializovaného právníka na IT právo a GDPR

---

**Kontakty pro konzultace:**

- **Úřad pro ochranu osobních údajů (ÚOOÚ)**: https://www.uoou.cz
- **Specializace:** IT právo, GDPR, autorské právo
- **Doporučená právní kancelář:** Havel & Partners, PRK Partners, Glatzová & Co.

---

_Dokument připraven: 2. ledna 2026_  
_Verze: 1.0_  
_Další revize: Leden 2027 nebo při změně legislativy_
