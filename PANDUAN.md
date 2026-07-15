# Panduan LapanginAja

Panduan lengkap untuk **menjalankan** aplikasi dan **mengintegrasikan** layanan pihak ketiga (Midtrans dan Email).

Struktur project:

```
LapanginAja-main/
├── api/   # Backend  -> Laravel 12 + Sanctum + PostgreSQL/SQLite
└── web/   # Frontend -> React + Vite + TypeScript
```

---

## 1. Prasyarat

| Tool        | Versi minimal | Keterangan                          |
|-------------|---------------|-------------------------------------|
| PHP         | 8.2+          | ekstensi: mbstring, pdo, sqlite, gd, intl, curl, zip, bcmath |
| Composer    | 2.x           | package manager PHP                 |
| Node.js     | 20+           | untuk frontend Vite                 |
| npm         | 10+           |                                     |
| PostgreSQL  | 14+ (opsional)| bisa pakai SQLite untuk dev cepat   |

---

## 2. Cara Menjalankan (Development)

### 2.1 Backend (folder `api/`)

```bash
cd api

# 1. Install dependency PHP
composer install

# 2. Siapkan file konfigurasi
cp .env.example .env

# 3. Generate application key
php artisan key:generate

# 4. Pilih database (lihat bagian 2.3), lalu migrate + seed data demo
php artisan migrate:fresh --seed

# 5. Jalankan server API
php artisan serve
# API aktif di http://localhost:8000
```

> **Cara cepat (all-in-one):** setelah `composer install`, jalankan `composer run dev`.
> Perintah ini menjalankan **server + queue worker + log viewer + Vite** sekaligus.

### 2.2 Frontend (folder `web/`)

```bash
cd web

# 1. Install dependency Node
npm install

# 2. Siapkan env frontend
cp .env.example .env

# 3. Jalankan dev server
npm run dev
# Buka http://localhost:5173
```

Isi `web/.env`:

```env
VITE_API_URL=http://localhost:8000/api/v1
VITE_MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxx   # client key sandbox Midtrans
```

### 2.3 Pilihan Database

**Opsi A — SQLite (paling cepat untuk dev):**

```env
# di api/.env
DB_CONNECTION=sqlite
```

```bash
touch api/database/database.sqlite
php artisan migrate:fresh --seed
```

**Opsi B — PostgreSQL (mirip produksi):**

```env
# di api/.env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lapanginaja
DB_USERNAME=postgres
DB_PASSWORD=rahasia
```

### 2.4 Queue & Scheduler (WAJIB agar notifikasi & housekeeping jalan)

Aplikasi memakai **queued jobs** (kirim e-ticket, invoice, notifikasi) dan **scheduled commands**
(rilis booking kedaluwarsa, reminder harian, retry notifikasi gagal).

```bash
# Terminal terpisah: proses antrian job
php artisan queue:work

# Untuk scheduler di server produksi, tambahkan 1 cron ini:
# * * * * * cd /path/ke/api && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal yang sudah terdaftar (di `bootstrap/app.php`):

| Command                       | Frekuensi        | Fungsi                                   |
|-------------------------------|------------------|------------------------------------------|
| `bookings:release-expired`    | tiap menit       | membatalkan booking pending yang lewat hold time |
| `bookings:send-reminders`     | tiap hari 08:00  | mengirim pengingat H-jadwal              |
| `notifications:retry`         | tiap 5 menit     | mengulang notifikasi yang gagal terkirim |

### 2.5 Akun Demo

Setelah `migrate:fresh --seed`, gunakan akun hasil seeder (cek `database/seeders/DatabaseSeeder.php`
untuk email & password default). Registrasi akun baru butuh verifikasi lewat tautan email (lihat bagian 4).

---

## 3. Integrasi Midtrans (Pembayaran)

LapanginAja memakai **Midtrans Snap** (QRIS, GoPay, ShopeePay, transfer bank, dll).

### 3.1 Ambil API Key

1. Daftar / masuk ke **https://dashboard.sandbox.midtrans.com** (mode Sandbox untuk testing).
2. Menu **Settings → Access Keys**. Salin:
   - **Server Key** (contoh: `SB-Mid-server-xxxxx`)
   - **Client Key** (contoh: `SB-Mid-client-xxxxx`)

### 3.2 Set di `.env`

```env
# api/.env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false                # true saat live
MIDTRANS_SNAP_URL=https://app.sandbox.midtrans.com/snap/snap.js
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true
```

```env
# web/.env  (client key HARUS sama dengan yang di backend)
VITE_MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
```

> Saat naik ke produksi: ganti key ke versi production, set `MIDTRANS_IS_PRODUCTION=true`,
> dan ubah `MIDTRANS_SNAP_URL` menjadi `https://app.midtrans.com/snap/snap.js` (juga di frontend
> `BookingCheckout.tsx`).

### 3.3 Konfigurasi Webhook (Payment Notification)

Agar status pembayaran otomatis terupdate, daftarkan URL webhook di dashboard Midtrans:

- Menu **Settings → Configuration → Payment Notification URL**
- Isi dengan: `https://domain-anda.com/api/v1/webhooks/midtrans`

Saat development di localhost, ekspos dulu pakai tunnel:

```bash
ngrok http 8000
# lalu pakai URL ngrok, contoh:
# https://xxxx.ngrok-free.app/api/v1/webhooks/midtrans
```

Webhook sudah aman: memverifikasi **signature key** (SHA512), mencocokkan **gross amount**
(anti-manipulasi harga), dan bersifat **idempoten** (aman jika Midtrans mengirim notifikasi berulang).

### 3.4 Kartu / pembayaran uji coba (Sandbox)

Gunakan data test dari dokumentasi Midtrans, contoh kartu sukses:
`4811 1111 1111 1114`, CVV `123`, exp bebas di masa depan, OTP/3DS `112233`.
Untuk QRIS/e-wallet sandbox, ikuti simulator di dashboard Midtrans.

### 3.5 Mode Demo (tanpa key Midtrans)

Jika `MIDTRANS_SERVER_KEY` kosong / masih placeholder, sistem masuk **DEMO MODE**:
membuat `snap_token` palsu berawalan `mock-`. Di halaman checkout akan muncul tombol
**"Simulasi Pembayaran"** untuk menandai booking lunas tanpa bayar.

> ⚠️ **Penting (keamanan):** endpoint simulasi (`/simulate-payment`) sekarang **otomatis dinonaktifkan
> di environment `production`** (mengembalikan 404). Jadi lubang "bayar gratis" tidak akan aktif saat live.
> Pastikan `APP_ENV=production` di server produksi.

---

## 4. Integrasi Email (Verifikasi Akun, E-Ticket, Invoice, Reset Password)

Seluruh notifikasi aplikasi dikirim **hanya lewat email**. Integrasi WhatsApp sudah dihapus sepenuhnya.

Email dipakai untuk: **tautan verifikasi akun** saat registrasi, **e-ticket + invoice PDF** setelah
pembayaran sukses, **pengingat H-1** jadwal main, dan **kode reset password**.

### 4.1 Konfigurasi SMTP

Default `MAIL_MAILER=log` berarti email **tidak benar-benar dikirim**, hanya ditulis ke
`storage/logs/laravel.log`. Untuk mengirim email sungguhan, set `MAIL_MAILER=smtp` dan isi kredensial SMTP.

Contoh untuk **Mailtrap Sandbox** (Email Testing, menangkap email di inbox virtual, tidak sampai ke Gmail asli):

```env
# api/.env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=xxxxxxxxxxxxxx        # Username dari Mailtrap > My Sandbox > SMTP Settings
MAIL_PASSWORD=xxxxxxxxxxxxxx        # Password lengkap (bukan yang tersamar ****)
MAIL_FROM_ADDRESS="no-reply@lapanginaja.test"
MAIL_FROM_NAME="LapanginAja"
```

#### Produksi: kirim email sungguhan lewat Gmail (gratis, tanpa domain)

Untuk deploy portfolio, cara termudah dan gratis adalah Gmail SMTP. Email akan benar-benar masuk
ke kotak masuk Gmail penerima (bukan inbox virtual). Midtrans tetap sandbox dan tidak terpengaruh.

Langkah:

1. Aktifkan **Verifikasi 2 Langkah** di Akun Google (wajib, kalau belum aktif App Password tidak muncul):
   Google Account > Security > 2-Step Verification.
2. Buat **App Password**: Google Account > Security > App passwords. Pilih app "Mail", lalu salin
   sandi 16 karakter yang muncul (contoh: `abcd efgh ijkl mnop`). Hapus spasinya saat dipakai.
3. Isi `api/.env` seperti berikut:

```env
# api/.env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=emailkamu@gmail.com
MAIL_PASSWORD=abcdefghijklmnop        # App Password 16 karakter, tanpa spasi (BUKAN password login biasa)
MAIL_FROM_ADDRESS="emailkamu@gmail.com"  # harus sama dengan akun Gmail di atas
MAIL_FROM_NAME="LapanginAja"
```

4. Bersihkan cache config lalu pastikan queue worker jalan (lihat bagian 4.3):

```bash
php artisan config:clear
php artisan queue:work
```

Catatan:
- `MAIL_FROM_ADDRESS` harus sama dengan `MAIL_USERNAME`; Gmail menolak mengirim atas nama alamat lain.
- Batas kirim Gmail sekitar 500 email/hari, lebih dari cukup untuk demo/portfolio.
- App Password hanya bisa dibuat kalau Verifikasi 2 Langkah sudah aktif.

Alternatif gratis lain: Brevo (300 email/hari) atau Resend, keduanya butuh verifikasi alamat/domain
pengirim lebih dulu, jadi setup-nya sedikit lebih panjang dibanding Gmail.

Catatan Midtrans: bagian ini hanya soal email. Konfigurasi Midtrans tetap sandbox
(`MIDTRANS_IS_PRODUCTION=false`) dan tidak perlu diubah saat mengaktifkan email produksi.

### 4.2 Alur Verifikasi Akun (tautan email)

- Saat registrasi (`POST /auth/register`), akun dibuat dengan status **belum terverifikasi** dan sebuah
  **token verifikasi** yang berlaku **24 jam**. Sistem mengirim email berisi tombol/tautan:
  `FRONTEND_URL/verify-email?token=...`
- User klik tautan tersebut, frontend memanggil `POST /auth/verify-email` dengan token, akun diaktifkan,
  dan user langsung login.
- Login sebelum verifikasi akan ditolak (`403`) dengan pesan agar user mengecek email.
- Jika email tidak diterima, user bisa minta kirim ulang lewat `POST /auth/resend-verification`.
- Registrasi **tidak lagi** langsung memberi token login; token hanya diterbitkan setelah verifikasi
  (kecuali di environment `testing` yang melewati verifikasi agar test tetap jalan).
- **Mode dev tanpa SMTP:** jika email gagal/belum dikonfigurasi, tautan verifikasi tetap dicatat ke log
  **hanya di environment non-production** (`[DEV] Tautan verifikasi untuk ...`) agar mudah dites.
- Endpoint terkait: `POST /auth/register`, `/auth/verify-email`, `/auth/resend-verification`,
  `/auth/forgot-password`, `/auth/reset-password`.
- Reset password tetap memakai **kode 6 digit** yang dikirim ke email (berlaku 15 menit).

### 4.3 WAJIB: agar email benar-benar terkirim

Banyak kasus "email tidak masuk" disebabkan tiga hal berikut. Pastikan semuanya:

1. **`.env` benar-benar ada dan MAIL_MAILER=smtp.** Project ini hanya berisi `.env.example`.
   Jalankan `cp .env.example .env` lalu isi kredensial SMTP. Selama masih `log`, email tidak terkirim.
2. **Queue worker berjalan.** Email invoice/e-ticket dikirim lewat **antrean** (`QUEUE_CONNECTION=database`).
   Tanpa worker, job hanya menumpuk di tabel `jobs` dan email tidak pernah dikirim:
   ```bash
   php artisan queue:work
   ```
   (atau cukup `composer run dev` yang sudah menyertakan queue worker).
3. **Bersihkan cache config jika pernah `config:cache`.** Nilai `.env` lama bisa nyangkut:
   ```bash
   php artisan config:clear
   ```

Kalau memakai **Mailtrap Sandbox**, email tidak sampai ke Gmail asli melainkan **muncul di inbox virtual
Mailtrap** (menu **Email Testing > Inboxes > My Sandbox**). Jadi walau Data Pemesan memakai
`itsmepblank@gmail.com`, cek emailnya di Mailtrap. Sebaliknya, kalau memakai **Gmail SMTP (produksi)**,
email benar-benar dikirim ke kotak masuk Gmail penerima.

Catatan penting soal e-ticket/invoice: email invoice **baru dikirim setelah pembayaran benar-benar lunas**
(webhook Midtrans `settlement` atau tombol Simulasi Pembayaran di mode demo). Jika pembayaran sandbox tidak
pernah selesai, job notifikasi tidak berjalan sehingga tidak ada email invoice, terlepas dari konfigurasi SMTP.

---

## 6. Ringkasan Variabel `.env`

### Backend (`api/.env`)

```env
APP_NAME=LapanginAja
APP_ENV=local                 # WAJIB 'production' saat live
APP_DEBUG=true                # WAJIB false saat live
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite          # atau pgsql

QUEUE_CONNECTION=database

# Email
MAIL_MAILER=log               # ganti ke smtp untuk email nyata

# Midtrans
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SNAP_URL=https://app.sandbox.midtrans.com/snap/snap.js
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true

# Frontend (untuk tautan verifikasi email)
FRONTEND_URL=http://localhost:5173
```

### Frontend (`web/.env`)

```env
VITE_API_URL=http://localhost:8000/api/v1
VITE_MIDTRANS_CLIENT_KEY=
```

---

## 7. Checklist Sebelum Produksi

- [ ] `APP_ENV=production` dan `APP_DEBUG=false`.
- [ ] `APP_KEY` sudah di-generate.
- [ ] Database PostgreSQL siap, jalankan `php artisan migrate --force`.
- [ ] Key Midtrans **production** terpasang + `MIDTRANS_IS_PRODUCTION=true` + Snap URL production (backend & frontend).
- [ ] Webhook Midtrans diarahkan ke domain produksi (`/api/v1/webhooks/midtrans`).
- [ ] SMTP email nyata terkonfigurasi (`MAIL_MAILER=smtp`) dan `FRONTEND_URL` diarahkan ke domain frontend produksi.
- [ ] `queue:work` berjalan sebagai daemon (mis. Supervisor) dan cron `schedule:run` aktif.
- [ ] Batasi **CORS** ke domain frontend (buat `config/cors.php`, set `allowed_origins`).
- [ ] Aktifkan cache konfigurasi: `php artisan config:cache && php artisan route:cache`.
- [ ] Pastikan endpoint `/simulate-payment` tidak aktif (otomatis 404 di production).
- [ ] Frontend di-build: `npm run build` (folder `web/dist`).

---

## 8. Menjalankan Test & Kualitas Kode

```bash
cd api
composer test        # PHPUnit feature/unit tests
composer lint        # Laravel Pint (code style)
composer analyse     # PHPStan static analysis
```

Frontend:

```bash
cd web
npm run build        # type-check + build
```

CI (GitHub Actions) di `.github/workflows/ci.yml` sudah menjalankan lint, analisis statis,
test backend, dan build frontend otomatis pada setiap push/PR.
