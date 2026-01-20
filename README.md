# test-cipta-koin-digital

## 1. Apa itu REST API?

**API (Application Programming Interface)** adalah antarmuka yang memungkinkan dua aplikasi berbeda untuk saling berkomunikasi dan bertukar data. **REST (Representational State Transfer)** adalah arsitektur yang fungsinya seperti panduan untuk komunikasi data di internet. Jadi **REST API** adalah sebuah antarmuka yang mengadopsi aturan-aturan REST dengan ciri seperti berikut:

* Menggunakan protokol HTTP
* Menggunakan method-method tertentu untuk sebuah aksi, misalnya:
  * **GET**: Untuk mengambil data
  * **POST**: Untuk menambah data baru
  * **PUT**: Untuk mengubah data
  * **DELETE**: Untuk menghapus data

## 2. Apa itu CORS dan cara menanganinya di backend?

**CORS** adalah singkatan dari **Cross-Origin Resource Sharing**.
CORS adalah mekanisme keamanan pada browser yang memblokir suatu website untuk mengambil data dari website lain yang berbeda alamat (domain/origin). Aturan ini berlaku pada browser yang mengatur agar aplikasi dengan *domain-a.com* hanya bisa mengakses data dari server *domain-a.com*, tidak bisa mengakses data dari *domain-b.com*.

Pada pengembangan web modern, Frontend dan Backend sering dipisah seperti:

* **Frontend**: `https://websitesaya.com` menggunakan `react`
* **Backend**: `https://api.websitesaya.com` menggunakan `laravel`

Kondisi diatas akan menyebabkan error CORS ketika `https://websitesaya.com` mengakses `https://api.websitesaya.com`, sehingga perlu dibuat penanganannya dari sisi Backend. Untuk menangani hal tersebut dapat dilakukan dengan cara mengirimkan HTTP Headers dalam response server:

1. `Access-Control-Allow-Origin`
    * Berfungsi untuk menentukan domain mana yang boleh mengakses resource.
    * Dapat diisi dengan `*` untuk mengizinkan domain manapun untuk mengakses, atau
    * Diisi dengan domain spesifik seperti `https://websitesaya.com` untuk mengizinkan hanya domain `https://websitesaya.com` yang dapat mengakses API.
2. `Access-Control-Allow-Methods`
    * Berfugnsi untuk menentukan HTTP Method apa saja yang diizinkan untuk mengakses resource
    * Misalnya: `GET`, `POST`, `PATCH`, `PUT`, `DELETE`, `OPTIONS`
3. `Access-Control-Allow-Headers`
    * Berfungsi untuk menentukan jenis data yang diizinkan untuk dikirimkan ke resource
    * Misalnya: `Content-type`, `Authorization`, dll

Pada **PHP Native** dapat ditambahkan baris seperti dibawah ini untuk menangani CORS dari Backend:

```php
header("Access-Control-Allow-Origin: *"); // Izinkan semua domain
header("Access-Control-Allow-Origin: https://websitesaya.com"); // Izinkan spesifik domain
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
```

Pada **Laravel** dapat ditambahkan baris seperti berikut pada file `config/cors.php` baris `allowed_origins`:

```php
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Menentukan HTTP Method yang diizinkan
    'allowed_methods' => ['*'],

    // Menentukan origin yang diizinkan
    'allowed_origins' => [
        'https://websitesaya.com',
    ],

    // Menentukan jenis data yang diizinkan
    'allowed_headers' => ['*'],

    rest of code...
]
```

## 3. Perbedaan SQL dan No SQL?

**SQL (Structured Query Language)** adalah jenis database relasional yang menyimpan data dalam bentuk tabel terstruktur seperti sheet excel. Karakteristik utamanya adalah memiliki skema yang kaku yang mengharusnya user mendefinisikan tabel dan tipe data sebelum memasukan data. SQL mengandalkan relasi antar tabel sehingga cocok untuk aplikasi yang membutuhkan konsistensi data tinggi dan struktur data yang kompleks. Contohnya: MySQL, PostgreSQL, SQL Server, Oracle, dll.

**No SQL** adalah database non-relasional yang dirancang untuk menyimpan data yang tidak terstuktur atau yang formatnya sering berubah-ubah. Berbeda dengan SQL, NoSQL menyimpan data dalam format dokumen seperti key-value, atau grafik seperti JSON yang memungkinkan fleksibilitas tinggi. Data dapat disimpan dengan atribut yang berbeda-beda untuk setiap entitas tanpa perlu mengubah struktur database secara keseluruhan. NoSQL cocok untuk pengembangan aplikasi yang memiliki karakter pengembangan yang cepat, Big Data, atau sistem real-time seperti media sosial. Contohnya: MongoDB, Firebase, Redis.

## 4. Apa itu middleware?

Dalam konteks pengembangan backend, **Middleware** adalah layer yang bertindak sebagai jembatan antara *Request* dari pengguna dan *Controller*.

Secara teknis middleware berfungsi sebegai gerbang dengan tugas untuk memvalidasi status login pengguna, memeriksa hak akses, menangani masalah keamanan seperti CORS, atau mencatat logging. Jika hasil pemeriksaan berhasil, middleware akan meneruskan permintaan ke proses selanjutnya. Jika gagal, middleware akan langsung menghentikan proses dan mengirimkan pesan kesalahan kembali ke pengguna, sehingga kode utama server tetap bersih dan aman.

## 5. Project