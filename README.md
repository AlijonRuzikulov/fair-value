# Fair Value — baholash firmasi sayti va boshqaruv tizimi

**Fair Value** baholash firmasi uchun ikki qismdan iborat tizim:

1. **Ochiq sayt** (`/`) — mijozlar uchun: xizmatlar, ariza qoldirish formasi,
   hisobotni QR-kod orqali tekshirish, Markaziy bank kurslari.
2. **Admin panel** (`/admin`) — xodimlar uchun: mijozlar, baholash obyektlari,
   zarar hisob-kitobi, hisobotlar, arizalar va sozlamalar.

Interfeys uch tilda ishlaydi: **o‘zbekcha (kirill)**, **o‘zbekcha (lotin)** va **ruscha**.

---

## Imkoniyatlar

### Admin panel

| Bo‘lim | Nima qiladi |
|---|---|
| **Bosh sahifa** | Jarayondagi obyektlar, shu oyda yakunlanganlar, tushum, yangi arizalar, 12 oylik grafik, baholovchilar bandligi, valyuta kurslari |
| **Mijozlar** | Jismoniy shaxslar (F.I.Sh., pasport, JShShIR) va yuridik shaxslar (STIR, rahbar, bank rekvizitlari); har bir mijozning barcha obyektlari bir joyda |
| **Ko‘chmas mulk** | Noturar va turar joylar: kadastr raqami, maydonlar, qavat, alohida qulayliklar |
| **Mol-mulklar** | Asbob-uskunalar, dastgohlar, texnika, mebel, tovarlar |
| **Avtotransport** | Marka, model, VIN, davlat raqami, yurgan masofasi va h.k. |
| **Yetkazilgan zarar** | Zararni baholash kalkulyatori: ehtiyot qismlar − eskirish + kichik qismlar + ta’mir ishlari (normo-soat × narx) + bo‘yoq + boshqa + tovar qiymatining yo‘qotilishi. Kataloglar: avtolar ro‘yxati, avto ehtiyot qismlar, kichik ehtiyot qismlar, ta’mir muddati normalari (Excel/CSV’dan import) |
| **Boshqa mol-mulk** | Biznes, ulushlar, nomoddiy aktivlar, yer huquqi va boshqalar |
| **Baholash plyus** | Qiyosiy (analoglar bilan), xarajat va daromad yondashuvlari kalkulyatorlari, natijalarni kelishtirish, valyuta konvertori |
| **Saytdan arizalar** | Saytdan kelgan arizalar, holatini belgilash; xohlasangiz Telegram’ga xabar |
| **Xodimlar** | Rollar: administrator, baholovchi, operator |
| **Viloyat va tumanlar** | O‘zbekistonning 14 ta hududi va 200+ tumani (uch tilda) |
| **Faoliyat jurnali** | Kim, qachon, nimani yaratdi / o‘zgartirdi / o‘chirdi |
| **Sozlamalar** | Kompaniya ma’lumotlari, kontaktlar, litsenziya, bosh sahifadagi raqamlar |

Har bir baholash obyekti uchun:

- **Shaxslar** — mulkdor, buyurtmachi, vakil, garovga oluvchi bank;
- **Yondashuvlar** — xarajat, qiyosiy, daromad va har biri bo‘yicha qiymat, yakuniy bozor qiymati (USD ekvivalenti bilan);
- **Ilova hujjatlari** — PDF, Word, Excel, rasmlar (sudrab tashlash bilan);
- **Shartnoma va hisobot** — raqam, sana, xizmat narxi; yakunlanganda hisobot raqami avtomatik beriladi (`2026/0001`);
- **Chop etish** — QR-kodli “Baholash natijalari to‘g‘risida ma’lumotnoma”;
- **Excel’ga eksport** — har bir ro‘yxat filtrlar bilan birga.

### Hisobotni tekshirish

Har bir obyektga noyob kod beriladi (masalan `FV-7K3M-9Q2X`) va u chop etiladigan
ma’lumotnomaga QR-kod sifatida tushadi. Bank, notarius yoki sud QR-kodni skanerlasa,
saytda hisobot raqami, sanasi, obyekt turi va qiymati ko‘rinadi. Mijozlarning
shaxsiy ma’lumotlari bu sahifada **hech qachon** ko‘rsatilmaydi.

---

## Texnologiyalar

- **PHP 8.3+**, **Laravel 13**
- Ma’lumotlar bazasi: **MySQL / MariaDB**, PostgreSQL yoki SQLite
- Frontend yig‘ishga (Node.js, npm) **ehtiyoj yo‘q** — CSS va JavaScript tayyor holda `public/` papkasida.
  Shriftlar (Manrope, Lora) va ikonkalar (Lucide) ham saytning o‘zida, tashqi CDN’larga bog‘liq emas.

---

## Hozir sinab ko‘rish

### 1-usul: brauzerda, hech narsa o‘rnatmasdan (GitHub Codespaces)

1. GitHub’da repozitoriyni oching va **`claude/amazing-bohr-4qrtrr`** branchini tanlang.
2. Yashil **Code** tugmasi → **Codespaces** → **Create codespace on claude/amazing-bohr-4qrtrr**.
3. 3–5 daqiqa kuting: PHP, kutubxonalar va namuna ma’lumotlar avtomatik o‘rnatiladi.
4. Pastdagi **Ports** bo‘limida **8000** portining yonidagi globus belgisini bosing — sayt yangi oynada ochiladi.
5. Admin panel: manzil oxiriga `/admin` qo‘shing, kirish — `demo@fairvalue.uz` / `demo12345`.

Shaxsiy GitHub hisobida Codespaces oyiga ~60 soat bepul. Ishlatmaganda
*Codespaces → Stop codespace* qiling.

### 2-usul: o‘z kompyuteringizda (Windows, macOS)

1. [Laravel Herd](https://herd.laravel.com) ni o‘rnating — PHP va Composer u bilan birga keladi
   (yoki PHP 8.3+ va [Composer](https://getcomposer.org) ni alohida o‘rnating).
2. Loyihani yuklab oling: GitHub’da branchni tanlab **Code → Download ZIP**, yoki
   `git clone -b claude/amazing-bohr-4qrtrr https://github.com/AlijonRuzikulov/fair-value.git`.
3. Loyiha papkasida terminal (PowerShell) oching va bajaring:

```bash
composer install
composer demo          # .env, kalit va namuna ma’lumotlar bilan SQLite baza
php artisan serve
```

4. Brauzerda <http://localhost:8000> — sayt, <http://localhost:8000/admin> — admin panel
   (`demo@fairvalue.uz` / `demo12345`).

`composer demo` bazani tozalab, qaytadan namuna ma’lumotlar bilan to‘ldiradi —
**ishlab turgan saytda ishlatmang**. Katalogdagi narxlar ham faqat namuna.

### Haqiqiy ish uchun (namuna ma’lumotlarsiz)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # yoki .env da MySQL sozlang
php artisan migrate --seed               # hududlar va birinchi administrator
```

`db:seed` administratorning e-mail va parolini ekranga chiqaradi
(`.env` dagi `ADMIN_EMAIL` / `ADMIN_PASSWORD` orqali o‘zingiz belgilashingiz mumkin).

---

## Serverga joylash

### VPS (Ubuntu, nginx, PHP-FPM, MySQL)

```bash
cd /var/www/fair-value
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://fairvalue.uz, DB_* sozlamalari
php artisan migrate --force --seed
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

nginx’da sayt ildizi — **`public`** papkasi:

```nginx
server {
    server_name fairvalue.uz;
    root /var/www/fair-value/public;
    index index.php;
    client_max_body_size 25m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Valyuta kurslari har 3 soatda yangilanishi uchun cron qo‘shing:

```cron
* * * * * cd /var/www/fair-value && php artisan schedule:run >> /dev/null 2>&1
```

### Oddiy hosting (cPanel)

1. Fayllarni yuklang, domen ildizini (Document Root) **`public`** papkasiga yo‘naltiring.
2. cPanel’da MySQL baza yarating va `.env` ga yozing.
3. Terminal (yoki “Setup PHP / Cron” bo‘limi) orqali `composer install --no-dev`,
   `php artisan migrate --force --seed` buyruqlarini bajaring.
4. Cron: `php /home/USER/fair-value/artisan schedule:run` — har daqiqa.

### Cloudflare orqali

Saytni oddiy hosting yoki VPS’ga joylab, domenni Cloudflare’ga ulash mumkin
(bepul SSL, keshlash, hujumlardan himoya). Bunda `.env` ga qo‘shing:

```env
TRUSTED_PROXIES=*
```

Shunda sayt mijozlarning haqiqiy IP manzilini va `https` ni to‘g‘ri aniqlaydi.
Laravel PHP’da yozilgani uchun uni Cloudflare Workers / Pages’ning o‘zida yurgizib bo‘lmaydi.

### Telegram’ga ariza xabarlari

1. [@BotFather](https://t.me/BotFather) orqali bot yarating va tokenni oling.
2. Botni guruhga qo‘shing va guruh `chat_id` sini oling.
3. `.env` ga yozing:

```env
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_CHAT_ID=-1001234567890
```

Saytdan har bir yangi ariza shu guruhga keladi.

---

## Birinchi sozlashlar

Admin panel → **Sozlamalar**:

- kompaniyaning yuridik nomi, rahbari, litsenziya / sug‘urta polisi raqami;
- telefon, manzil, ish vaqti, Telegram, Instagram, xarita havolasi;
- bosh sahifadagi raqamlar (necha yil, nechta hisobot va h.k.);
- standart normo-soat narxi (zarar hisob-kitobi uchun).

Logotip `resources/views/components/logo-mark.blade.php` va `public/images/favicon.svg`
fayllarida — rasmiy logotip bilan almashtirish mumkin.

### Narxlar ro‘yxatini Excel’dan yuklash

“Avto ehtiyot qismlar”, “Kichik ehtiyot qismlar”, “Ta’mir muddati normalari” va
“Avtolar ro‘yxati” sahifalarida **Import** tugmasi bor. Excel’da jadvalni
*Saqlash → CSV* qiling, birinchi qator — sarlavha. Ustunlar tartibi:

| Katalog | Ustunlar |
|---|---|
| Avto ehtiyot qismlar | Nomi; Katalog raqami; O‘lchov birligi; Narxi; Avtomobil modeli |
| Kichik ehtiyot qismlar | Nomi; O‘lchov birligi; Narxi |
| Ta’mir muddati normalari | Qism / uzel; Operatsiya; Normo-soat; Avtomobil modeli |
| Avtolar ro‘yxati | Markasi; Modeli; Klassi; Kuzov turi; Ishlab chiqarilgan yillari; Normo-soat narxi |

Avtomobil modeli bo‘sh bo‘lsa — yozuv barcha modellarga tegishli bo‘ladi.
Mavjud ro‘yxatni **Excel** tugmasi bilan yuklab olib, tahrirlab, qayta import qilish ham mumkin.

---

## Rollar

| Rol | Huquqlar |
|---|---|
| Administrator | Hammasi, jumladan xodimlar, sozlamalar, ma’lumotnomalar, faoliyat jurnali |
| Baholovchi | Mijozlar, obyektlar, kataloglar; yozuvlarni o‘chira oladi |
| Operator | Arizalar, mijozlar va obyektlarni kiritadi; o‘chira olmaydi, kataloglarni o‘zgartira olmaydi |

O‘chirilgan obyektlar bazada saqlanib qoladi (soft delete), xodimlar esa o‘chirilmaydi — faolsizlantiriladi.

---

## Tarjimalar

| Fayl | Til |
|---|---|
| `lang/uz.json`, `lang/uz/*.php` | O‘zbekcha (lotin) — asosiy |
| `lang/oz.json`, `lang/oz/*.php` | O‘zbekcha (kirill) — **avtomatik** yaratiladi |
| `lang/ru.json`, `lang/ru/*.php` | Ruscha |

Lotin tarjimani o‘zgartirgandan keyin kirill variantini yangilang:

```bash
php artisan lang:cyrillic
```

Qoidaga to‘g‘ri kelmaydigan so‘zlarni `lang/oz.overrides.json` faylida
(`"inglizcha kalit": "кирилл матн"`) qo‘lda belgilash mumkin. Standart til `.env` dagi
`APP_LOCALE` bilan tanlanadi (`oz`, `uz` yoki `ru`).

---

## Testlar

```bash
php artisan test
```

100 dan ortiq test: barcha sahifalar uch tilda, ariza formasi, hisobotni tekshirish,
rollar, zarar hisob-kitobi, CSV import / eksport, fayllar, valyuta kurslari va boshqalar.

---

## Loyiha tuzilishi

```
app/
  Enums/                    holatlar, yondashuvlar, maqsadlar, rollar …
  Http/Controllers/Site/    ochiq sayt
  Http/Controllers/Admin/   admin panel
    ValuationController     barcha baholash obyektlari uchun umumiy mantiq
    CatalogController       zarar kataloglari uchun umumiy mantiq
  Models/                   Eloquent modellar (Concerns/ValuationObject — umumiy xatti-harakat)
  Services/                 Markaziy bank kurslari, Telegram xabarlari
  Support/                  formatlash, transliteratsiya, CSV, xizmatlar ro‘yxati
database/
  data/regions.php          viloyat va tumanlar
  seeders/DemoSeeder.php    namuna ma’lumotlar
resources/views/
  site/                     ochiq sayt sahifalari
  admin/                    admin panel sahifalari (objects/ — umumiy ro‘yxat, forma, kartochka, chop etish)
  components/               Blade komponentlar va layoutlar
public/css, public/js       stil va skriptlar (yig‘ishsiz)
```
