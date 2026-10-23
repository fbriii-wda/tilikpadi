# Tilik Padi

Portal web administrator Tilik Padi berbasis PHP, MySQL, dan Firebase untuk memantau pengguna serta riwayat deteksi penyakit padi.

## Menjalankan di Laragon

1. Letakkan folder proyek di `C:\laragon\www\Tilik_Padi`.
2. Jalankan Laragon, lalu aktifkan Apache dan MySQL.
3. Pastikan database `tilikpadi` dan tabel `user` sudah tersedia di Laragon.
4. Pengaturan koneksi ada di [`config/database.php`](./config/database.php). Secara default, aplikasi memakai `127.0.0.1:3306`, username `root`, tanpa kata sandi, dan database `tilikpadi`.
5. Buka `http://localhost/Tilik_Padi/`.
6. Pilih **Register Now** untuk membuat akun pertama. Setelah berhasil, akun tersebut langsung masuk ke dashboard.

Untuk pengaturan koneksi lain, atur environment variables `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, dan `DB_NAME`.

PHP harus memiliki ekstensi `mysqli` aktif. Dashboard dan halaman komponen meminta login; gunakan **Logout** untuk mengakhiri sesi.

### Statistik dashboard

Jalankan migrasi [`database/migrations/20261022_add_last_login_at.sql`](./database/migrations/20261022_add_last_login_at.sql) satu kali pada database `tilikpadi` sebelum login atau registrasi berikutnya (misalnya lewat tab **SQL** di phpMyAdmin). Kartu total pendaftar dan pengguna aktif di dashboard menggunakan Firebase Authentication: total akun Auth dan jumlah yang terakhir login hari ini menurut zona waktu `Asia/Jakarta`. Daftar akun portal web pada Manajemen Pengguna tetap bersumber dari tabel MySQL `user`.

Kartu jenis penyakit membaca nama unik dari Firestore `detections.nama_penyakit` dan memperbarui statistik otomatis setiap 60 detik. Koleksi belum perlu dibuat sekarang; nilainya 0 sampai ada dokumen deteksi. Untuk mengisi data nanti, setiap dokumen di koleksi `detections` harus memiliki field string `nama_penyakit`.

Grafik aktivitas pemindaian, riwayat terbaru, dan distribusi tingkat keparahan memakai tabel `detection` dan `tingkat_keparahan` di MySQL. Grafik dapat difilter berdasarkan tahun melalui pemilih tanggal. Nilai ditampilkan dari data yang benar-benar tercatat; bila belum ada hasil scan, ringkasan akan kosong/bernilai 0 dan tidak menggunakan data contoh.

Halaman `riwayat_pemantauan.php` menampilkan 100 hasil scan terbaru dari MySQL, termasuk waktu, pengguna, klasifikasi penyakit, skor confidence, dan label keparahan. Path citra pada `detection.image_path` dipakai untuk membuat URL bertanda tangan Firebase Storage. Jalankan migrasi [`database/migrations/20261022_add_infected_ratio_to_detection.sql`](./database/migrations/20261022_add_infected_ratio_to_detection.sql) agar tersedia kolom opsional `infected_ratio_percent`; nilainya tetap “belum tercatat” sampai proses aplikasi pemindai mengisinya. Set `FIREBASE_STORAGE_BUCKET` agar gambar dari Storage dapat ditampilkan.

Dashboard web ini adalah portal administrasi dan analitik sesuai proposal. Pemindaian kamera/galeri, inferensi model CNN, rekomendasi perawatan, dan aplikasi mobile merupakan bagian dari sistem yang lebih luas dan tidak ditampilkan sebagai fitur aktif pada portal ini sebelum implementasinya terhubung.

### Katalog edukasi penyakit

Halaman `management_penyakit.php` menyediakan CRUD katalog penyakit. Nama penyakit, penyebab, rasio area terinfeksi, kategori keparahan, dan rekomendasi disimpan pada tabel MySQL `disease_catalog`. Jalankan migrasi [`database/migrations/20261022_create_disease_catalog.sql`](./database/migrations/20261022_create_disease_catalog.sql) satu kali pada database `tilikpadi` melalui phpMyAdmin.

Kategori dihitung dari rasio area daun terinfeksi: Ringan 0-25%, Sedang >25-50%, dan Parah >50-100%. Gambar JPG, PNG, atau WEBP (maksimal 5 MB) disimpan di Firebase Storage; aplikasi menyimpan lokasi objeknya di MySQL dan membuat URL bertanda tangan yang berlaku satu jam saat gambar ditampilkan.

Atur environment variable `FIREBASE_STORAGE_BUCKET` dengan nama bucket dari Firebase Console, misalnya lewat PowerShell `setx FIREBASE_STORAGE_BUCKET "nama-bucket-anda"`, lalu mulai ulang Apache/Laragon. Akun layanan pada `FIREBASE_CREDENTIALS` (atau `config/firebase_key.json`) harus memiliki izin mengunggah, membaca, dan menghapus objek pada bucket Storage. Jangan memasukkan kredensial Firebase ke source control.

### Manajemen akun pengguna

Halaman `management_pengguna.php` mengelola akun portal pada MySQL dan menampilkan akun aplikasi dari Firebase Authentication pada daftar terpisah. Daftar Firebase menampilkan UID, email, nama, status akun, waktu pendaftaran/login, serta kecocokan email dengan akun portal; akun Firebase tidak otomatis dibuat atau diubah di MySQL. Firebase Auth hanya dibaca (maksimal 1.000 akun per tampilan), dan kata sandi maupun hash tidak pernah ditampilkan. Pastikan ekstensi PHP OpenSSL aktif dan akun layanan Firebase memiliki izin membaca pengguna Authentication. Peran portal yang tersedia adalah Administrator dan Petani. Untuk membersihkan peran Penyuluh Pertanian yang mungkin tertinggal pada database lama, jalankan migrasi [`database/migrations/20261022_remove_counselor_role.sql`](./database/migrations/20261022_remove_counselor_role.sql); migrasi hanya menghapus peran jika tidak sedang dipakai akun mana pun. Akun portal baru harus memiliki kata sandi minimal 8 karakter; kata sandi selalu di-hash oleh PHP dan tidak pernah ditampilkan atau disinkronkan.

Setelah perubahan akun berhasil di MySQL, profil non-rahasia (ID MySQL, nama, email, dan peran) disinkronkan ke dokumen Firestore `users/{id_user}`. Hapus akun akan menghapus dokumen pasangan. Kegagalan Firebase ditampilkan sebagai peringatan dan dicatat pada log PHP; MySQL tetap menjadi sumber data utama. Akun layanan memerlukan izin baca/tulis Firestore.

Halaman ini khusus Administrator. Jika akun pertama dibuat melalui registrasi umum dan belum memiliki peran, tetapkan peran Administrator pada akun tepercaya melalui phpMyAdmin sebelum membuka manajemen pengguna:
`UPDATE \`user\` SET id_role = (SELECT id_role FROM role WHERE nama_role = 'Administrator' LIMIT 1) WHERE id_user = ID_AKUN;`

## Koneksi Firebase Firestore

`firebase_connect.php` menggunakan akun layanan Firebase. Secara default, file kredensial harus berada di `config/firebase_key.json`; lokasi lain dapat ditentukan melalui environment variable `FIREBASE_CREDENTIALS`. Sebaiknya simpan file tersebut di luar folder web publik. Akses HTTP ke folder `config` juga diblokir oleh `config/.htaccess`.

Pastikan ekstensi PHP `openssl` dan `sodium` aktif, database Firestore sudah dibuat, akun layanan memiliki izin baca Firestore, dan dependensi Composer sudah terpasang (`composer install`). Buka `http://localhost/Tilik_Padi/firebase_connect.php` untuk menguji koneksi baca ke Firestore melalui REST API. Pesan sukses hanya ditampilkan setelah permintaan Firestore berhasil.

### API deteksi untuk aplikasi Flutter

`api/detections.php` menerima `POST` untuk menyimpan hasil deteksi dan `GET` untuk membaca riwayat milik pengguna yang terautentikasi. Setiap permintaan harus membawa Firebase ID token yang masih valid pada header `Authorization: Bearer <ID_TOKEN>`. API memverifikasi token terhadap project service account, lalu menggunakan UID token untuk menemukan akun MySQL; akun tidak dicocokkan otomatis hanya berdasarkan email.

Jalankan migrasi [`database/migrations/20261022_add_firebase_uid_to_user.sql`](./database/migrations/20261022_add_firebase_uid_to_user.sql) satu kali pada database `tilikpadi`. Administrator kemudian harus menautkan UID Firebase ke akun MySQL yang benar secara eksplisit, misalnya:

```sql
UPDATE `user`
SET `firebase_uid` = 'UID_FIREBASE_PENGGUNA'
WHERE `id_user` = ID_USER_YANG_SESUAI;
```

UID dapat dilihat di Firebase Console pada Authentication → Users. Kolom tersebut unik sehingga satu UID hanya dapat ditautkan ke satu akun web. Jangan menautkan akun berdasarkan email tanpa memverifikasi pemiliknya.

Contoh body `POST` (confidence berupa angka 0–1; `image_path` opsional dan, bila disertakan, harus menunjuk objek Firebase Storage yang dapat dibaca web):

```json
{
  "nama_penyakit": "Blas Padi",
  "confidence": 0.92,
  "severity": "Parah",
  "infected_ratio_percent": 68.5,
  "image_path": "disease-catalog/contoh.jpg"
}
```

`severity` opsional dan harus sama dengan label pada tabel `tingkat_keparahan`. `infected_ratio_percent` opsional serta harus berada pada rentang 0–100. Riwayat dapat dibaca dengan `GET /Tilik_Padi/api/detections.php?limit=20&offset=0`; batas `limit` adalah 100. Flutter mengambil token dengan `FirebaseAuth.instance.currentUser?.getIdToken()`, lalu mengirimkannya sebagai bearer token. Saat uji dari Android Emulator, gunakan host `10.0.2.2` untuk mengakses Laragon pada laptop, misalnya `http://10.0.2.2/Tilik_Padi/api/detections.php`. Ponsel fisik perlu menggunakan alamat IP lokal laptop dan berada di jaringan yang sama. Untuk akses di luar jaringan lokal, pasang web/API pada server HTTPS.