# ERP Manufaktur Air Mineral Dalam Kemasan – Backend API (Laravel)

Backend API sebuah ERP Manufaktur AMDK berbasis mobile. Backend ini berperan sebagai **REST API Server** yang menangani Modul Data MAster, Penjualan, Pembelian, Akutansi Sederhana

---

## Tech Stack

- **Framework** : Laravel 11
- **Database** : MySQL
- **Authentication** : Laravel Sanctum (Bearer Token)
- **API Type** : RESTful API

---

## Development Tools (Opsional)

- **Local Server**: Laragon
- **Database Management**: PHPMyAdmin
- **API Testing**: Postman

---

## Struktur Folder

app/
├── Http/
│ ├── Controllers/
│ │ ├── API <-- Logika utama ERP Semua Module
│ │ ├── Auth <-- Digunakan Untuk Login dan Log Out
├── Models/
├── Resource/
routes/
└── api.php <-- Endpoint untuk integrasi frontend

---

## Konfigurasi Proyek

1.  Clone Repositori

```
git clone https://github.com/RafelDream/erp-manufacturadkm.git
cd erp-manufacturadkm
```

2. Install Dependency

```
composer install
```

3. Setup Environment

```
cp .env.example .env
php artisan key:generate
```

Isi konfigurasi penting:

- Database MySQL

---

4. Migration Database Dan Seeder

```
php artisan migrate --seed
```

5. Jalankan Server

```
php artisan serve
```

Backend akan berjalan di:

```
http://127.0.0.1:8000
```

6. Jalankan Ngrok

```
ngrok http 8000
```

Akan membuat secure tunnel dari URL publik ke server lokal yang berjalan di port 8000

```
http://127.0.0.1:4040 <-- Web Interface dari ngrok untuk melihat respons nya
https://unplaying-hedwig-beautiful.ngrok-free.dev
```
