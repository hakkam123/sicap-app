# DATABASE MAINTENANCE & BLOAT PREVENTION GUIDE (SICAP / COPA)

Dokumen ini merupakan panduan operasional pemeliharaan database **Microsoft SQL Server** untuk aplikasi **SICAP / Consume Part (COPA)** guna mencegah terjadinya lonjakan kapasitas disk (*storage bloat*) dan kehabisan memori *tempdb*.

---

## 1. Daftar Pembersihan Otomatis (Automated Pruning Schedule)

Aplikasi telah dikonfigurasi untuk menjalankan pembersihan data kadaluarsa secara berkala melalui Laravel Task Scheduler (`routes/console.php`):

| Target Data / Tabel | Masa Retensi | Frekuensi Eksekusi | Perintah Artisan Terjadwal | Dampak Jika Tidak Dijalankan |
| :--- | :--- | :--- | :--- | :--- |
| **`failed_jobs`** | 7 Hari (168 Jam) | Harian (02:00 WIB) | `php artisan queue:prune-failed --hours=168` | Ribuan antrean gagal menumpuk payload JSON. |
| **`job_batches`** | 48 Jam | Harian (02:30 WIB) | `php artisan queue:prune-batches --hours=48` | Serialisasi metadata batch memenuhi storage. |
| **`personal_access_tokens`** | 30 Hari (720 Jam) | Mingguan (Minggu 03:00) | `php artisan sanctum:prune-expired --hours=720` | Token API kadaluarsa menumpuk terus menerus. |
| **`system_error_logs`** | • 30 Hari (Status *Resolved*)<br>• 90 Hari (Semua status) | Harian (03:30 WIB) | `php artisan copa:cleanup-logs` | *Exception storm* membuat LOB/text membengkak puluhan GB. |
| **`import_logs`** | 30 Hari | Harian (03:30 WIB) | `php artisan copa:cleanup-logs` | Riwayat dan JSON error upload Excel menumpuk. |
| **`laravel.log` (Disk Server)** | 14 Hari | Otomatis Harian | Monolog Daily Handler (`LOG_CHANNEL=daily`) | File log tunggal membesar hingga puluhan GB. |

---

## 2. Perintah Pembersihan Manual (Manual Cleanup Commands)

Jika scheduler server sempat mati atau database perlu dibersihkan secara instan:

```bash
# 1. Bersihkan log error sistem dan log import (dieksekusi secara chunked 500 baris)
php artisan copa:cleanup-logs

# 2. Bersihkan antrean failed queue yang lebih lama dari 7 hari
php artisan queue:prune-failed --hours=168

# 3. Bersihkan metadata antrean batch yang lebih lama dari 48 jam
php artisan queue:prune-batches --hours=48

# 4. Bersihkan token Sanctum kadaluarsa
php artisan sanctum:prune-expired --hours=720

# 5. Bersihkan cache aplikasi yang tersimpan di database
php artisan cache:clear
```

---

## 3. Query Monitoring Rutin (T-SQL untuk SSMS)

Jalankan query diagnostik berikut secara berkala (misal: setiap awal bulan) di database `sicap_app`.

### 3.1. Cek Top 10 Tabel Konsumen Storage Terbesar
```sql
USE [sicap_app];
GO

SELECT TOP 10
    t.NAME AS TableName,
    MAX(p.rows) AS ApproxRows,
    CAST(ROUND(SUM(a.total_pages) * 8 / 1024.00, 2) AS NUMERIC(36, 2)) AS TotalMB,
    CAST(ROUND(SUM(a.data_pages) * 8 / 1024.00, 2) AS NUMERIC(36, 2)) AS DataMB,
    CAST(ROUND((SUM(a.used_pages) - SUM(a.data_pages)) * 8 / 1024.00, 2) AS NUMERIC(36, 2)) AS IndexMB
FROM sys.tables t
INNER JOIN sys.indexes i ON t.OBJECT_ID = i.object_id
INNER JOIN sys.partitions p ON i.object_id = p.OBJECT_ID AND i.index_id = p.index_id
INNER JOIN sys.allocation_units a ON p.partition_id = a.container_id
WHERE t.is_ms_shipped = 0
GROUP BY t.Name
ORDER BY TotalMB DESC;
GO
```

### 3.2. Cek Pertumbuhan Baris 30 Hari Terakhir (Daily Ingestion)
```sql
USE [sicap_app];
GO

SELECT 'consumes' AS TableName, CAST(created_at AS DATE) AS LogDate, COUNT(*) AS RecordsAdded
FROM consumes WHERE created_at >= DATEADD(DAY, -30, GETDATE()) GROUP BY CAST(created_at AS DATE)
UNION ALL
SELECT 'system_error_logs', CAST(created_at AS DATE), COUNT(*)
FROM system_error_logs WHERE created_at >= DATEADD(DAY, -30, GETDATE()) GROUP BY CAST(created_at AS DATE)
UNION ALL
SELECT 'import_logs', CAST(created_at AS DATE), COUNT(*)
FROM import_logs WHERE created_at >= DATEADD(DAY, -30, GETDATE()) GROUP BY CAST(created_at AS DATE)
ORDER BY LogDate DESC, TableName ASC;
GO
```

### 3.3. Cek Penggunaan Ruang & File Tempdb
```sql
USE tempdb;
GO

SELECT 
    SUM(user_object_reserved_page_count) * 8 / 1024.0 AS UserObjectsMB,
    SUM(internal_object_reserved_page_count) * 8 / 1024.0 AS InternalObjectsMB,
    SUM(version_store_reserved_page_count) * 8 / 1024.0 AS VersionStoreMB,
    SUM(unallocated_extent_page_count) * 8 / 1024.0 AS FreeSpaceMB,
    SUM(total_page_count) * 8 / 1024.0 AS TotalTempdbSizeMB
FROM sys.dm_db_file_space_usage;
GO
```

---

## 4. Indikator Bahaya (Red Flags)

Segera lakukan investigasi teknis jika menemui salah satu tanda berikut:
1. **`system_error_logs` masuk ke Top 3 tabel terbesar**: Menandakan terjadi loop error exception pada scheduler atau API pihak ketiga.
2. **Ukuran tabel `failed_jobs` > 100 MB**: Menandakan worker queue mengalami kegagalan massal.
3. **File `tempdb.mdf` atau `templog.ldf` melonjak > 10 GB**: Menandakan ada query agregasi SQL yang tidak memakai index sehingga tumpah (*spill*) ke disk.
4. **Ukuran `storage/logs/laravel.log` > 500 MB**: Menandakan environment `.env` belum beralih ke `LOG_CHANNEL=daily` atau log level masih diatur ke `debug`.

