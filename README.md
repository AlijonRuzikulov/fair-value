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
| **Qiyosiy yondashuv — analoglar** | Ko‘chmas mulk sahifasida: OLX.uz, Uybor.uz va boshqa saytlardan o‘xshash e’lonlarni topish (sun’iy intellekt) yoki link orqali qo‘shish, tuzatishlar, vaznlar va qiymat hisobi |
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

Tizim **bo‘sh** holda ishga tushadi: mijozlar, obyektlar, arizalar va xodimlar yo‘q —
faqat viloyat/tumanlar ro‘yxati va bitta administrator. Hammasini o‘zingiz kiritasiz.

### 1-usul: brauzerda, hech narsa o‘rnatmasdan (GitHub Codespaces)

1. GitHub’da repozitoriyni oching va **`claude/amazing-bohr-4qrtrr`** branchini tanlang.
2. Yashil **Code** tugmasi → **Codespaces** → **Create codespace on claude/amazing-bohr-4qrtrr**.
3. 3–5 daqiqa kuting: PHP, kutubxonalar va baza avtomatik o‘rnatiladi.
4. Pastdagi **Ports** bo‘limida **8000** portining yonidagi globus belgisini bosing — sayt yangi oynada ochiladi.
5. Admin panel: manzil oxiriga `/admin` qo‘shing. Birinchi kirish — `admin@fairvalue.uz` / `admin12345`
   (kirish sahifasida ham ko‘rsatib turiladi). Kirgach, o‘ng yuqoridagi ism → **Mening profilim** da
   parolni almashtiring — shundan keyin bu yozuv kirish sahifasidan yo‘qoladi.

**Linkni boshqa odamga yuborish:** standart holatda Codespaces linki faqat sizga ochiladi
(boshqalardan GitHub’ga kirishni so‘raydi). `serve.sh` uni avtomatik ochiq qiladi va terminalga
`Link for other people: ...` deb yozadi. Agar bunday yozuv chiqmasa: pastdagi **Ports** bo‘limida
8000 portini o‘ng tugma bilan bosing → **Port Visibility → Public**. Bu link faqat codespace
ishlab turganda ochiladi — doimiy link uchun pastdagi “Serverga joylash” bo‘limiga qarang.

Shaxsiy GitHub hisobida Codespaces oyiga ~60 soat bepul. Ishlatmaganda
*Codespaces → Stop codespace* qiling. Kiritilgan ma’lumotlar codespace o‘chirilmaguncha saqlanadi.

**“HTTP ERROR 502” chiqsa yoki yangi versiyani olish kerak bo‘lsa** — Codespace oynasida
terminalni oching (`Ctrl + \``) va bajaring:

```bash
git pull
bash .devcontainer/setup.sh
bash .devcontainer/serve.sh
```

`setup.sh` kiritilgan ma’lumotlarni o‘chirmaydi (faqat eski versiyadagi namuna
ma’lumotlarni bir marta tozalaydi). Terminalda `Server running on [http://0.0.0.0:8000]`
yozuvi chiqqach, sayt sahifasini yangilang.

### 2-usul: o‘z kompyuteringizda (Windows, macOS)

1. [Laravel Herd](https://herd.laravel.com) ni o‘rnating — PHP va Composer u bilan birga keladi
   (yoki PHP 8.3+ va [Composer](https://getcomposer.org) ni alohida o‘rnating).
2. Loyihani yuklab oling: GitHub’da branchni tanlab **Code → Download ZIP**, yoki
   `git clone -b claude/amazing-bohr-4qrtrr https://github.com/AlijonRuzikulov/fair-value.git`.
3. Loyiha papkasida terminal (PowerShell) oching va bajaring:

```bash
composer setup         # kutubxonalar, .env, kalit, bo‘sh baza va administrator
php artisan serve
```

4. Terminalda `Administrator: admin@fairvalue.uz / ...` qatori chiqadi — shu login va parol bilan
   <http://localhost:8000/admin> ga kiring. Sayt: <http://localhost:8000>.
   Parolni oldindan o‘zingiz belgilash uchun `.env` dagi `ADMIN_PASSWORD` ni to‘ldiring.

### Namuna ma’lumotlar bilan ko‘rish (faqat sinov uchun)

```bash
composer demo
```

Bazani **butunlay o‘chirib**, o‘ylab topilgan mijozlar, obyektlar, arizalar va narxlar bilan
to‘ldiradi; kirish — `admin@fairvalue.uz` / `admin12345`. **Ishlab turgan saytda ishlatmang.**

### Bazani butunlay tozalash

```bash
php artisan migrate:fresh --seed --force
```

Barcha ma’lumotlar o‘chadi, faqat viloyatlar va administrator qayta yaratiladi.

### Haqiqiy ish uchun

```bash
composer install
cp .env.example .env                     # ADMIN_EMAIL / ADMIN_PASSWORD ni to‘ldiring
php artisan key:generate
touch database/database.sqlite          # yoki .env da MySQL sozlang
php artisan migrate --seed               # hududlar va birinchi administrator
```

`db:seed` administratorning e-mail va parolini ekranga chiqaradi. Haqiqiy saytda
`.env` da `DEMO_MODE=false` bo‘lsin — u yoqilganda kirish sahifasida birinchi login ko‘rinadi.
Bosh sahifadagi raqamlar (yillar, hisobotlar, mijozlar, banklar) **Sozlamalar** sahifasida
kiritiladi; bo‘sh qoldirilsa saytda ko‘rinmaydi.

---

## Serverga joylash — o‘z domeningizda (masalan, fairvalue.uz)

Mijozga oddiy link (`https://fairvalue.uz`) yuborish uchun sayt doimiy ishlaydigan serverda turishi kerak.
Odamlarning shaxsiy ma’lumotlari saqlangani uchun server **O‘zbekistonda** bo‘lsin
(“Shaxsga doir ma’lumotlar to‘g‘risida”gi qonun, 27-1-modda).

**Kerak bo‘ladi:**

1. **Domen** — masalan `fairvalue.uz` (.uz domenlarini O‘zbekistondagi registratorlar sotadi, yiliga bir marta to‘lanadi).
2. **Hosting** — O‘zbekistondagi hosting yoki VPS. Tarifda bular bo‘lishini so‘rang:
   PHP 8.3 yoki yangiroq, MySQL, **SSH kirish**, Composer, bepul SSL (https),
   domen papkasini (Document Root) o‘zgartirish imkoniyati.

**O‘rnatish** (hostingning SSH terminalida):

```bash
git clone -b claude/amazing-bohr-4qrtrr https://github.com/AlijonRuzikulov/fair-value.git
cd fair-value
bash deploy.sh          # 1-marta: .env faylini yaratadi
nano .env               # APP_URL=https://fairvalue.uz, DB_* (baza), ADMIN_EMAIL, ADMIN_PASSWORD
bash deploy.sh          # kutubxonalar, baza, administrator — tayyor
```

So‘ng hosting panelida domen papkasini (Document Root) **`fair-value/public`** ga yo‘naltiring
va SSL (https) ni yoqing. **Muhim:** butun loyihani `public_html` ga tashlamang — shunda `.env`
(parollar) brauzerdan ochilib qoladi; brauzerga faqat `public` papkasi ko‘rinishi kerak.

Yangilash: `git pull && bash deploy.sh` (sayt bir necha soniya “texnik ishlar” rejimida bo‘ladi).
`.env` o‘zgartirilgandan keyin ham `bash deploy.sh` ni qayta ishga tushiring.

`deploy.sh` `.env` da `APP_ENV=production`, `APP_DEBUG=false`, `DEMO_MODE=false`,
`SESSION_SECURE_COOKIE=true` ni o‘zi qo‘yadi. Sayt faqat `APP_URL` dagi domen (va uning `www.`)
orqali ochiladi.

### VPS (Ubuntu, nginx, PHP-FPM)

Yuqoridagi buyruqlar `/var/www/fair-value` da, keyin `chown -R www-data:www-data storage bootstrap/cache`.
nginx’da sayt ildizi — **`public`** papkasi:

```nginx
server {
    server_name fairvalue.uz www.fairvalue.uz;
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

SSL: `sudo certbot --nginx -d fairvalue.uz -d www.fairvalue.uz`.

### Cron (valyuta kurslari)

Kurslar har 3 soatda yangilanishi uchun cron qo‘shing (hosting panelidagi “Cron” bo‘limi):

```cron
* * * * * cd /home/USER/fair-value && php artisan schedule:run >> /dev/null 2>&1
```

### Cloudflare orqali

Saytni oddiy hosting yoki VPS’ga joylab, domenni Cloudflare’ga ulash mumkin
(bepul SSL, keshlash, hujumlardan himoya). Bunda `.env` ga qo‘shing:

```env
TRUSTED_PROXIES=*
```

Shunda sayt mijozlarning haqiqiy IP manzilini va `https` ni to‘g‘ri aniqlaydi.
Muhim: bunda serverga **faqat Cloudflare orqali** kirish mumkin bo‘lsin (server firewall’ida
80/443 portlarni faqat [Cloudflare IP manzillari](https://www.cloudflare.com/ips/) uchun oching) —
aks holda kirish urinishlari cheklovini soxta IP bilan aylanib o‘tish mumkin.
Laravel PHP’da yozilgani uchun uni Cloudflare Workers / Pages’ning o‘zida yurgizib bo‘lmaydi.

### Xavfsizlik — ishga tushirishdan oldin

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `DEMO_MODE=false`, `SESSION_SECURE_COOKIE=true`,
  `APP_URL` — saytning aniq manzili (boshqa domen nomi bilan kelgan so‘rovlarga sayt javob bermaydi).
- Administrator paroli kamida 12 belgi, boshqa joyda ishlatilmagan bo‘lsin (tizim 10 belgidan qisqa parolni qabul qilmaydi);
  har bir xodimga alohida hisob va kerakli rol bering (operator, baholovchi, administrator).
  Ishdan ketgan xodimni **Xodimlar** sahifasida o‘chirib qo‘ying (nofaol qiling).
- Sayt faqat **https** orqali ochilsin (Cloudflare yoki hostingning bepul SSL’i).
- Sayt ildizi — faqat `public` papkasi. `.env`, `database/` va `storage/` brauzerdan ochilmasligi kerak.
- Zaxira nusxa: har kuni bazani (`database/database.sqlite` yoki MySQL dump) va
  `storage/app` papkasini (yuklangan hujjatlar) boshqa joyga nusxalang.
- Vaqti-vaqti bilan yangilang: `composer update` va `composer audit` (ma’lum zaifliklarni tekshiradi).

Tizimda: parollar shifrlangan (bcrypt); kirish urinishlari bir hisob uchun daqiqasiga 5 ta,
soatiga 50 ta bilan cheklangan; parol almashtirilsa boshqa qurilmalardagi seanslar yopiladi;
barcha formalar CSRF tokeni bilan himoyalangan; brauzerga himoya sarlavhalari (CSP,
X-Frame-Options va boshqalar) yuboriladi; yuklangan hujjatlar faqat tizimga kirgan xodimlarga
ochiladi; yakunlangan hisobotni faqat baholovchi yoki administrator o‘zgartira oladi;
xodimlarning harakatlari **Faoliyat jurnali**ga yoziladi.

### Qiyosiy yondashuv: analoglarni avtomatik topish

Ko‘chmas mulk obyektida **“Qiyosiy”** yondashuv belgilanib saqlansa, obyekt sahifasidagi
**“Qiyosiy yondashuv — analoglar”** bo‘limi o‘xshash e’lonlarni o‘zi qidiradi:

1. **Topish.** Sun’iy intellekt (Claude) OLX.uz, Uybor.uz, Realt24.uz, Domtut.uz saytlarida obyektga
   o‘xshash (turi, maydoni, xonalari, tumani) sotuv e’lonlarini qidiradi. Yoki baholovchi o‘zi
   e’lon linklarini yoki saytda filtrlar bilan ochilgan qidiruv sahifasi linkini qo‘yadi.
2. **O‘qish.** Server har bir e’lon sahifasini ochadi va narx, valyuta, maydon, xonalar, qavatni ajratib
   oladi. Dollardagi narxlar Markaziy bank kursi bo‘yicha so‘mga o‘tkaziladi.
3. **Tuzatishlar.** Har bir analog uchun taklif qilinadi: savdolashish −5 %, maydon farqi,
   1-qavat / oxirgi qavat; joylashuv, holat va boshqa tuzatishlarni baholovchi kiritadi.
   Narxi boshqalardan ±35 % dan ortiq farq qiladigan e’lon avtomatik chetga olinadi.
4. **Natija.** Tuzatilgan 1 m² narxlari vaznlar bilan o‘rtacha olinadi (kam tuzatish talab qilgan analog
   ko‘proq hisobga olinadi), obyekt maydoniga ko‘paytiriladi. Tarqoqlik 33 % dan oshsa, ogohlantiriladi.
   **“Qiyosiy qiymat sifatida saqlash”** natijani obyektga yozadi.

Avtomatik qidiruv uchun `.env` ga Claude API kalitini yozing ([console.anthropic.com](https://console.anthropic.com)):

```env
ANTHROPIC_API_KEY=sk-ant-...
```

Kalitsiz ham ishlaydi: e’lon linklarini qo‘ysangiz, sahifadagi ma’lumotlar (narx, maydon va h.k.) o‘qiladi,
yoki e’lonni qo‘lda kiritasiz. Sun’iy intellektga **faqat ochiq e’lon sahifalari va obyekt tavsifi**
(maydon, xonalar, tuman) yuboriladi — mijozlarning shaxsiy ma’lumotlari yuborilmaydi.
Server faqat ro‘yxatdagi saytlarga murojaat qiladi (`ANALOG_DOMAINS`).

**Muhim:** e’lonlar — sotuvchining so‘ragan narxi; topilgan qiymatlar va tuzatishlarni har doim baholovchi
tekshiradi. Saytlar tuzilishini o‘zgartirsa, ba’zi maydonlarni qo‘lda to‘ldirishga to‘g‘ri kelishi mumkin.

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
