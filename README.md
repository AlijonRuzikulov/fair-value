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
| **Baholash hisoboti** | Ko‘chmas mulk bo‘yicha to‘liq hisobot (muqova, mundarija, ilova xat, vazifa, bozor tahlili, tuzatishlar jadvali va sharhi, muvofiqlashtirish, ilovalar) — kirill yoki lotinda, PDF qilib olinadi |
| **Qiyosiy yondashuv — analoglar** | Ko‘chmas mulk sahifasida: OLX.uz, Uybor.uz va boshqa saytlardan o‘xshash e’lonlarni topish (sun’iy intellekt) yoki link orqali qo‘shish, tuzatishlar, vaznlar va qiymat hisobi |
| **Saytdan arizalar** | Saytdan kelgan arizalar, holatini belgilash; xohlasangiz Telegram’ga xabar |
| **Xodimlar** | Rollar: administrator, baholovchi, operator |
| **Viloyat va tumanlar** | O‘zbekistonning 14 ta hududi va 200+ tumani (uch tilda) |
| **Audit jurnali** | Kim, qachon, qaysi IP va qurilmadan: tizimga kirdi / chiqdi / kira olmadi, nimani kiritdi, o‘zgartirdi, o‘chirdi, ko‘rdi, yuklab oldi, chop etdi, Excel’ga chiqardi. Yozuvlar zanjir bilan himoyalangan — o‘zgartirilsa darhol bilinadi |
| **Tizim holati** | Server, baza, disk, rejali vazifalar, xatolar, xavfsizlik sozlamalari tekshiruvi; hozir tizimda kimlar borligi (seansni masofadan yopish mumkin) |
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

**Kirishda “E-mail yoki parol noto‘g‘ri” chiqsa:**

- `admin12345` faqat birinchi kirish uchun. Parol almashtirilgach u ishlamaydi — kirish sahifasida
  login ko‘rinmay qolgani shuni bildiradi.
- Klaviatura lotinda va Caps Lock o‘chiq bo‘lsin. Nofaol qilingan xodim ham shu xabarni ko‘radi.
- Yangi parol qo‘yish (Codespace yoki server terminalida):

  ```bash
  php artisan user:password                      # administrator (ADMIN_EMAIL)
  php artisan user:password xodim@fairvalue.uz   # boshqa xodim
  ```

  Buyruq yangi parolni ikki marta so‘raydi, hisobni faol qiladi va bloklashni ochadi. E-mail
  topilmasa, barcha xodimlar ro‘yxatini ko‘rsatadi. Parol o‘zgargani audit jurnaliga yoziladi.
- “Urinishlar juda ko‘p” chiqsa — bir daqiqa kuting. “419 Page Expired” chiqsa — sahifani yangilab,
  qayta kiriting.

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

### Cron (valyuta kurslari, audit tekshiruvi)

Kurslar har 3 soatda yangilanishi, audit jurnali har kecha tekshirilishi va eski yozuvlar
tozalanishi uchun cron qo‘shing (hosting panelidagi “Cron” bo‘limi):

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
xodimlarning harakatlari **Audit jurnali**ga yoziladi (pastga qarang).

### Audit jurnali va tizim monitoringi

**Audit jurnali** (admin panel → Audit jurnali, faqat administrator uchun) har bir yozuvda:
vaqt, xodim, IP manzil, brauzer/qurilma, sahifa manzili va nima o‘zgargani (eski → yangi qiymat).
Yozuvlar to‘rt guruhga bo‘lingan:

| Guruh | Nima yoziladi |
|---|---|
| **Kirish va chiqish** | Tizimga kirish (“eslab qolish” bilan yoki yo‘q), chiqish, noto‘g‘ri parol yoki noma’lum login bilan urinish, ko‘p urinishdan keyin bloklash, seansning majburan yopilishi (xodim nofaol qilinganda, parol almashtirilganda, administrator yopganda), parol almashtirish |
| **Kiritilgan ma’lumotlar** | Yaratish, o‘zgartirish (har bir maydonning eski va yangi qiymati), o‘chirish, Excel/CSV’dan import, hujjat yuklash va o‘chirish, sozlamalar, analoglar qidiruvi |
| **Olingan ma’lumotlar** | Obyekt, mijoz yoki ariza kartasini ko‘rish, ma’lumotnomani chop etish, hujjatni yuklab olish, Excel’ga eksport (qaysi filtr bilan, nechta qator), saytda hisobotni tekshirish |
| **Tizim** | Valyuta kurslari xatolari, jurnal tekshiruvi, eski yozuvlarni tozalash |

Parollar jurnalga hech qachon yozilmaydi. Jurnalni filtrlash (guruh, xodim, hodisa, IP, sana)
va Excel’ga yuklab olish mumkin.

**Himoya.** Har bir yozuv oldingisining SHA-256 xeshiga bog‘langan (zanjir). Bazada biror
yozuv o‘zgartirilsa yoki o‘rtadan o‘chirilsa, tekshiruv buni aniq qaysi yozuvdan buzilganini
ko‘rsatadi. Tekshirish: jurnal sahifasidagi **Butunlikni tekshirish** tugmasi yoki

```bash
php artisan audit:verify     # har kecha 03:10 da avtomatik ham ishlaydi
php artisan audit:prune      # AUDIT_RETENTION_DAYS kundan eski yozuvlarni o‘chiradi (03:20 da avtomatik)
```

Jurnal saqlanish muddati `.env` dagi `AUDIT_RETENTION_DAYS` (standart 1095 kun = 3 yil, kamida 30).

**Tizim holati** sahifasi: baza, disk, `storage/` yozish huquqi, cron ishlayaptimi, valyuta
kurslari, so‘nggi 24 soatdagi xatolar, jurnal butunligi va xavfsizlik sozlamalari
(`APP_DEBUG`, `APP_ENV`, https, cookie, `DEMO_MODE`, boshlang‘ich parol) — har biri yashil /
sariq / qizil. Shu yerda hozir tizimda kimlar borligi (qurilma, IP, oxirgi faollik) ko‘rinadi va
istalgan seansni masofadan yopish mumkin; so‘nggi xatolar va xavfsizlik hodisalari ham shu yerda.

**Telegram ogohlantirishlari** (`TELEGRAM_BOT_TOKEN` va `TELEGRAM_CHAT_ID` kiritilgan bo‘lsa):
hisob bloklanganda, 15 daqiqada 10 tadan ko‘p noto‘g‘ri parol kiritilganda, 10 daqiqada
10 tadan ko‘p server xatosi bo‘lganda va jurnal butunligi buzilganda darhol xabar keladi.

Cheklov: zanjir yozuvlar o‘zgartirilganini yoki o‘rtadan o‘chirilganini aniqlaydi, lekin
serverga to‘liq kirgan odam oxirgi yozuvlarni butunlay o‘chirib yuborsa, buni faqat zaxira
nusxa bilan solishtirib bilish mumkin. Shuning uchun bazaning kunlik zaxira nusxasini boshqa
joyda saqlang.

### Qiyosiy yondashuv: analoglarni avtomatik topish

Ko‘chmas mulk obyektida **“Qiyosiy”** yondashuv belgilanib saqlansa, obyekt sahifasidagi
**“Qiyosiy yondashuv — analoglar”** bo‘limi o‘xshash e’lonlarni o‘zi qidiradi:

1. **Topish.** Sun’iy intellekt (Claude) OLX.uz, Uybor.uz, Realt24.uz, Domtut.uz saytlarida obyektga
   o‘xshash (turi, maydoni, xonalari, tumani) sotuv e’lonlarini qidiradi. Yoki baholovchi o‘zi
   e’lon linklarini yoki saytda filtrlar bilan ochilgan qidiruv sahifasi linkini qo‘yadi.
2. **O‘qish.** Server har bir e’lon sahifasini ochadi va narx, valyuta, maydon, xonalar, qavatni ajratib
   oladi. Dollardagi narxlar Markaziy bank kursi bo‘yicha so‘mga o‘tkaziladi.
3. **Tuzatishlar** (10-son MBS taqqoslash elementlari tartibida, **ketma-ket** qo‘llaniladi):
   huquqlar, moliyalashtirish, savdolashish (−5 % taklif qilinadi), **bozor shartlari** (dollardagi narx uchun
   taklif sanasi va baholash sanasidagi Markaziy bank kurslari farqidan avtomatik), joylashuv, maydon,
   qavat (1-qavat / oxirgi qavat), devor materiali, ta’mir holati (KMK 2.01.16-97 eskirish darajalari bo‘yicha
   taklif qilinadi), qulayliklar va boshqa. Narxi boshqalardan ±35 % dan ortiq farq qiladigan e’lon chetga olinadi.
   Har bir analogga taklif sanasi, ta’mir holati, devor materiali va rasm (e’londan avtomatik yoki qo‘lda) kiritiladi.
4. **Natija.** Tuzatilgan 1 m² narxlari vaznlar bilan o‘rtacha olinadi — vazn **tuzatishlar soniga** (an’anaviy usul)
   yoki **tuzatishlar miqdoriga** ko‘ra; obyekt maydoniga ko‘paytiriladi. Tarqoqlik 33 % dan oshsa, ogohlantiriladi.
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

### To‘liq baholash hisoboti (ko‘chmas mulk)

Ko‘chmas mulk sahifasidagi **“Baholash hisoboti”** tugmasi tizimdagi ma’lumotlardan to‘liq hisobotni
(A4, sahifalarga bo‘lingan, 25–40 bet) yaratadi — **“Chop etish / PDF saqlash”** bilan PDF qilib olinadi.
Hisobot standart holatda **lotin** yozuvida chiqadi. Hisobot sahifasidagi tugma yoki tayyorlash sahifasining
6-qadami bilan **kirill**ga almashtiriladi; kompaniya uchun standart yozuv: Sozlamalar → “Hisobot yozuvi”. Tarkibi:

- muqova (rekvizitlar, logotip, asosiy ma’lumotlar, QR-kod) va **sahifa raqamlari bilan mundarija**;
- I. Ilova xat — qiymat raqamda va **so‘z bilan** (“besh yuz o‘n to‘rt million…”), farazlar, imzolar;
- II. Asosiy atamalar; III. Baholash vazifasi jadvali (mijoz va baholovchi rekvizitlari,
  **baholash sanasidagi** USD, EUR, RUB kurslari), standartlarga muvofiqlik bayonoti, manbalar;
- IV. Makroiqtisodiy ko‘rsatkichlar, bozor tahlili va **topilgan e’lonlar bo‘yicha segment statistikasi**;
- V. Obyekt tavsifi, yer uchastkasi, konstruktiv elementlar; VI. Eng samarali foydalanish tahlili;
- VII. Yondashuvlar va ularni tanlash asosi; **tuzatishlar jadvali** (har bir taqqoslash elementi va har bir
  tuzatishdan keyingi 1 m² narxi) va har bir tuzatishga **avtomatik sharh**;
- VIII. Natijalarni muvofiqlashtirish (mezonlar bo‘yicha ballar, vaznlar, formula), yakuniy qiymat;
- IX. Sifat to‘g‘risida bayonot; X. Ilovalar: ko‘zdan kechirish dalolatnomasi, analoglar kartochkalari
  (rasm bilan), obyekt rasmlari (sahifaga 4 tadan), hujjatlar skanerlari va kompaniya hujjatlari.

Har bir sahifada sarlavha, sahifa raqami va hisobotni tekshirish QR-kodi bor.

- **Hisobotni tayyorlash** (obyekt sahifasidagi tugma) — 6 qadam, har birida nima bajarilgani ko‘rinadi:
  1. **Hujjatlar** — pasport (ID-karta) va kadastr ko‘chirmasi skanerini yoki rasmini yuklaysiz,
     **“Hujjatlarni o‘qish”** bosilsa sun’iy intellekt mulk egasini (F.I.Sh., JShShIR, pasport, berilgan sana)
     va obyektni (kadastr raqami, maydonlar, manzil, viloyat/tuman, huquq va uni belgilovchi hujjat, yuklamalar)
     o‘qiydi. Tekshirish sahifasida “hozir / hujjatda” yonma-yon ko‘rinadi, faqat belgilanganlar saqlanadi;
     mulk egasi mavjud mijozlar orasidan JShShIR bo‘yicha topiladi yoki yangi mijoz yaratiladi;
  2. **Obyekt va huquqlar** — tayyor variantlardan tanlanadigan maydonlar;
  3. **Ko‘zdan kechirish** — sana, **bino turi bo‘yicha bir bosishda to‘ldirish** (panel, g‘isht, monolit,
     hovli, ofis, ombor), qurilish hajmini hisoblash (maydon × ship balandligi), kommunikatsiyalar, rasmlar;
  4. **Bozor va analoglar** — qiyosiy yondashuv natijasi;
  5. **Yondashuvlar va qiymat** — sabablar, mezonlar bo‘yicha ballar (vaznlar darhol hisoblanadi),
     yakuniy qiymatni muvofiqlashtirilgan qiymatdan bir tugma bilan qo‘yish;
  6. **Hisobot** — yozuv, sanalar, ilovalar va **“Tayyorlik”** ro‘yxati.
- **Hisobot matnlari** (Sozlamalar → Hisobot matnlari, administrator): atamalar, bayonotlar,
  makroiqtisodiy jadval, metodologiya va boshqa standart bo‘limlar — bir marta tahrirlanadi va barcha
  hisobotlarda ishlatiladi. Lotin yozuvida yoziladi, kirill hisobot avtomatik tayyorlanadi.
- **Kompaniya hujjatlari** (Sozlamalar): guvohnoma, a’zolik sertifikati, sug‘urta polisi, baholovchi
  sertifikati skanerlari — har bir hisobot oxiriga qo‘shiladi. Rus tilidagi nomi, IFUT (XXTUT) va a’zolik
  sertifikati matni ham shu yerda.
- **Xodimlar**: baholovchining malaka sertifikati raqami, sanasi va uni bergan organ.

Hisobot **muqovasi** obyektning birinchi rasmi bilan, undan keyin **“Asosiy xulosalar”** sahifasi (qiymat,
1 m² narxi, dollar ekvivalenti, obyekt bozordagi analoglar orasida qayerda turgani grafigi, yondashuvlar
vazni), tuzatishlar ta’siri grafigi va boshqa jadvallar bilan chiqadi.

Obyekt rasmlari va hujjatlar (pasport, kadastr ko‘chirmasi) **rasm** (JPG, PNG) sifatida yuklanadi;
PDF fayllar hujjatlarni o‘qishda ishlatiladi, lekin hisobot ilovasiga qo‘shilmaydi.

**Hujjatlarni o‘qish va maxfiylik.** “Hujjatlarni o‘qish” tugmasi bosilganda tanlangan skanerlar (pasport,
kadastr ko‘chirmasi — ya’ni shaxsiy ma’lumotlar) o‘qish uchun Claude (Anthropic) API’ga yuboriladi; rasmlar
oldin kichraytiriladi. Anthropic API orqali yuborilgan ma’lumotlar standart holatda model o‘qitish uchun
ishlatilmaydi. Bu imkoniyatni butunlay o‘chirish uchun `.env` ga `AI_DOCUMENTS=false` yozing — shunda
ma’lumotlar qo‘lda kiritiladi. Analoglar qidiruvida esa faqat ochiq e’lonlar va obyekt tavsifi yuboriladi. Valyuta kurslari kerakli sana uchun Markaziy bank
arxividan bir marta yuklab olinadi (`CBU_HISTORY=false` bilan o‘chiriladi).

### Shartnoma, dalolatnoma, hisob-faktura va vazifa

Har bir obyekt sahifasidagi **“Shartnoma va hisob-faktura”** tugmasi buyurtmachi bilan imzolanadigan
hujjatlarni tayyorlaydi (A4, “Chop etish / PDF saqlash”):

- **baholash xizmatini ko‘rsatish shartnomasi** — raqam, sana, taraflar, xizmat haqi raqam va so‘z bilan,
  ikkala tarafning rekvizitlari va imzolari;
- **ishni topshirish-qabul qilish dalolatnomasi** — hisobot raqami, manzil, baholash summasi (so‘z bilan);
- **hisob varaq-faktura** — QQS bilan yoki QQSsiz (Sozlamalar → “QQS stavkasi”; 0 — QQS to‘lovchisi emas);
- **baholash bo‘yicha vazifa** — shartnomaga ilova.

Kirill yoki lotinda, imzo chiziqlari yoki QR-kodlar bilan chiqadi (yuqoridagi tugmalar). Shartnoma shartlari
**Sozlamalar → Hisobot matnlari → “Xizmat ko‘rsatish shartnomasi”** da tahrirlanadi; `{object}`, `{fee}`,
`{customer}` kabi so‘zlar obyekt ma’lumotlari bilan almashtiriladi. Sug‘urta polisi va bosh hisobchi —
Sozlamalarda. Bitta hujjatni chiqarish: manzil oxiriga `?doc=contract` (`act`, `invoice`, `assignment`).

### Xarita, raqamlash va to‘lovlar

- **Xaritada joylashuv** (ko‘chmas mulk): tahrirlash sahifasida xaritani bosib yoki “Mening joylashuvim”
  (telefonda) bilan nuqta belgilanadi; Google/Yandex’dan nusxa olingan koordinatalarni “Kenglik”ka qo‘yish mumkin.
  Obyekt sahifasida va hisobotda (5.2-band) xarita chiqadi. Xarita OpenStreetMap’dan olinadi
  (`.env`: `MAP_TILES`, `MAP_ATTRIBUTION`).
- **Mo‘ljal (massiv, mahalla)**, **kadastr yoki balans qiymati**, **qurilish osti maydoni** maydonlari.
- **Avtomatik raqamlash:** yangi obyektga navbatdagi shartnoma raqami va bugungi sana beriladi; hisobot raqami
  yakunlanganda beriladi. Format — Sozlamalarda: `{n}-{yy}` → 668-26, `20/{n}-{yy}` → 20/551-26,
  `{yyyy}/{nnnn}` → 2026/0001. Oldingi tizimdagi oxirgi raqamni bitta obyektga qo‘lda yozsangiz, keyingilari
  undan davom etadi.
- **To‘lovlar:** xizmat narxi, to‘langan summa va qarzdorlik obyekt sahifasida ko‘rinadi.

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
