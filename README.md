# SICAP (Sistem Informasi Consume Sparepart)

<p align="center">
  <strong>Aplikasi Manajemen Pemakaian dan Pemetaan Sparepart Mesin</strong><br>
  PT Astra Visteon Indonesia
</p>

---

## 📌 Tentang Proyek

**SICAP (Sistem Informasi Consume Sparepart)** adalah aplikasi berbasis web yang dirancang untuk mengelola, memonitor, dan merekapitulasi penggunaan (*consume*) sparepart mesin pada setiap area produksi secara akurat dan terintegrasi. 

Aplikasi ini memudahkan tim operasional dan maintenance dalam mencatat transaksi pemakaian sparepart, memetakan part number ke area/mesin tertentu, serta menyajikan data analitik biaya pemakaian sparepart secara real-time.

---

## 🚀 Tech Stack

### Backend
- **Framework**: [Laravel 11](https://laravel.com/) (PHP 8.3+)
- **Database**: Microsoft SQL Server / MySQL
- **Authentication & RBAC**: Laravel Breeze + [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- **Excel Processing**: [Maatwebsite Excel 4.0](https://laravel-excel.com/)
- **API Glue**: [Inertia.js v2 (Laravel Adapter)](https://inertiajs.com/)

### Frontend
- **Framework**: [Vue 3](https://vuejs.org/) (Composition API with `<script setup>`)
- **Styling**: [Tailwind CSS](https://tailwindcss.com/)
- **Icons**: [Lucide Vue Next](https://lucide.dev/)
- **Charts / Visualisasi**: [Chart.js](https://www.chartjs.org/) & [vue-chartjs](https://vue-chartjs.org/)
- **Build Tool**: [Vite](https://vitejs.dev/)

---

## ✨ Fitur Utama

### 1. 📊 Dashboard & Monitoring
- Ringkasan metrik utama: total transaksi consume, total biaya (amount IDR), dan part teraktif.
- Grafik visualisasi tren pemakaian sparepart berkala.

### 2. ⚙️ Master Data Management
- **Area Management**: CRUD data area/lini produksi, kode area, dan rekapitulasi jumlah mesin terkait.
- **Machine Management**: CRUD data mesin operasional terikat ke masing-masing area.
- **Part Number Management**: CRUD katalog part number (PN BAAN), deskripsi, dan harga per unit (IDR) dengan counter relasi area/mesin.

### 3. 🔗 Mapping Part Number
- **Tampilan Dual-Tab**:
  - *Data Mapping*: Tabel relasi pivot aktual antara part, area, dan mesin operasional.
  - *Daftar Part*: Manajemen assignment langsung part number ke banyak area dan mesin sekaligus via modal interaktif.
- **Import & Template Excel**: Fasilitas unduh template dan import massal mapping part via berkas spreadsheet.

### 4. 📦 Consume Sparepart (Transaksi Pemakaian)
- **Pencatatan Transaksi**: Input pemakaian sparepart harian secara manual atau melalui batch import file Excel.
- **Kalkulasi Otomatis**: Perhitungan otomatis nilai biaya (*amount*) berdasarkan kuantitas dan harga satuan part.
- **Filter Komprehensif**: Filter pencarian teks (PN/nama part), dropdown area, dropdown mesin dinamis berbasis area terpilih, dan rentang tanggal (*Date From - Date To*).
- **Import Logs**: Riwayat dan audit log proses import file transaksi consume.

### 5. 👥 User Management & Autentikasi
- Pengelolaan pengguna sistem, pergantian password, dan penetapan role (*Admin* vs *User*).
- Hak akses granular untuk operasi CRUD dan import data.

### 6. 🛠️ Komponen UI & Navigasi Terstandarisasi
- Komponen `DataTable` reusable dengan integrasi pagination bawaan Laravel.
- **Dynamic Pagination**: Pilihan jumlah baris per halaman (`5`, `10`, `25`, `100`) di dalam *filter card* pada semua modul.
- Desain antarmuka konsisten, responsif, dan rapi menggunakan standar card container Tailwind CSS.

---

## 💻 Panduan Instalasi & Menjalankan Aplikasi

### Prasyarat
- PHP >= 8.3 dengan ekstensi yang diperlukan (`pdo_sqlsrv` atau `pdo_mysql`, `mbstring`, `openssl`, dll.)
- Composer >= 2.x
- Node.js >= 18.x & NPM
- Database server (SQL Server atau MySQL)

### Langkah-langkah

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/hakkam123/sicap-app.git
   cd sicap-app
   ```

2. **Instal Dependensi Backend (PHP)**:
   ```bash
   composer install
   ```

3. **Konfigurasi Environment**:
   Salin berkas `.env.example` menjadi `.env`:
   ```bash
   cp .env.example .env
   ```
   Sesuaikan konfigurasi koneksi database pada file `.env`:
   ```env
   DB_CONNECTION=sqlsrv
   DB_HOST=127.0.0.1
   DB_PORT=1433
   DB_DATABASE=sicap_db
   DB_USERNAME=sa
   DB_PASSWORD=your_password
   ```

4. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

5. **Jalankan Migrasi & Database Seeder**:
   ```bash
   php artisan migrate --seed
   ```

6. **Instal Dependensi Frontend (Node.js)**:
   ```bash
   npm install
   ```

7. **Jalankan Server Pengembangan**:
   Jalankan Vite development server:
   ```bash
   npm run dev
   ```
   Di terminal terpisah, jalankan Laravel development server:
   ```bash
   php artisan serve
   ```
   Aplikasi dapat diakses melalui browser di `http://127.0.0.1:8000`.

8. **Build untuk Production**:
   ```bash
   npm run build
   ```

---

## 📁 Struktur Direktori Utama

```
sicap-app/
├── app/
│   ├── Exports/            # File Export Excel (MappingTemplateExport, dll)
│   ├── Http/Controllers/  # Controller Laravel (Area, Machine, PartNumber, Consume, Mapping, dll)
│   ├── Imports/            # File Import Excel (MappingImport, ConsumeImport, dll)
│   └── Models/             # Model Eloquent (Area, Machine, PartNumber, Consume, ImportLog, dll)
├── database/
│   ├── migrations/         # Skema tabel database
│   └── seeders/            # Seeder data awal (User, Role, dll)
├── resources/
│   ├── js/
│   │   ├── Components/     # Komponen Vue reusable (DataTable, Modal, UI Atoms)
│   │   ├── Layouts/        # Layout utama (AppLayout, GuestLayout)
│   │   └── Pages/          # Halaman Inertia.js (Area, Machine, PartNumber, Mapping, Consume, Dashboard)
│   └── css/                # Konfigurasi Tailwind CSS
└── routes/
    ├── web.php             # Rute aplikasi web
    └── auth.php            # Rute autentikasi
```

---

## 📄 Lisensi

Hak Cipta © 2026 PT Astra Visteon Indonesia. Seluruh hak cipta dilindungi undang-undang.
