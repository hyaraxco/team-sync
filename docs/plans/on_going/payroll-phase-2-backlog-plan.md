# Payroll Phase 2 Backlog Plan

## Ringkasan
- Dokumen ini menjaga backlog lanjutan setelah payroll v1 selesai untuk semua role `manager`, `hr`, `finance`, dan `employee`.
- Fokus fase berikutnya bukan memperlebar payroll tanpa arah, tetapi menutup kebutuhan operasional yang mulai terlihat dari QA, flow real user, dan praktik HR/Finance nyata.
- Prioritas default yang dipakai:
  1. `Payroll Settings`
  2. `Payroll Audit Trail`
  3. `Resend Notification Manual`
  4. `Advanced Payroll Report`
  5. `Employee Payroll UX Improvements`
  6. `Approval Layer`
  7. `Advanced Period Rules`

## Status Saat Ini
- Selesai:
  - `Payroll Settings`
  - `Payroll Audit Trail`
  - `Resend Notification Manual`
  - `Advanced Payroll Report`
  - `Employee Payroll UX Improvements`
  - `Approval Layer`
  - `Advanced Period Rules`
- Prioritas aktif berikutnya:
  - Tidak ada prioritas aktif payroll phase 2 yang tersisa pada batch saat ini

## Backlog Prioritas

### 1. Payroll Settings
- Tambahkan halaman atau config panel payroll untuk aturan global perusahaan.
- Scope v1.1:
  - payday default
  - cut-off attendance
  - default working days
  - formula potongan dasar
  - rounding policy
  - template note atau slip dasar
- Kontrak penting:
  - setting baru hanya memengaruhi payroll yang dibuat setelah perubahan
  - payroll lama tetap immutable dari sisi hasil kalkulasi
- Role default:
  - `finance` bisa lihat dan ubah
  - `hr` boleh lihat, edit hanya jika memang disepakati kemudian
- Acceptance:
  - user authorized bisa menyimpan setting
  - payroll baru membaca setting yang aktif
  - perubahan setting tercatat timestamp dan actor

### 2. Payroll Audit Trail
- Tambahkan histori aktivitas payroll untuk aksi sensitif.
- Event minimum:
  - payroll generated
  - payroll detail edited
  - payroll marked as paid
  - payroll report exported
  - notification resent nanti jika fitur itu ada
- Kontrak penting:
  - audit log tidak boleh bisa diubah dari UI
  - setiap event menyimpan actor, waktu, dan konteks minimum
- Acceptance:
  - detail payroll menampilkan activity timeline ringkas
  - backend menyimpan event penting secara konsisten
  - investigasi perubahan payroll bisa ditelusuri

### 3. Resend Notification Manual
- Tambahkan aksi manual resend notifikasi setelah payroll `paid`.
- Scope:
  - hanya untuk `finance`
  - resend level payroll period, bukan per employee dulu
  - tidak mengubah status payroll
- Kontrak penting:
  - notifikasi otomatis saat `Mark as Paid` tetap source of truth
  - resend adalah fallback operasional, bukan alur utama
- Acceptance:
  - finance bisa resend notifikasi dari payroll detail
  - ada success atau error feedback
  - action tercatat di audit trail

### 4. Advanced Payroll Report
- Perluas report finance dari ringkasan period menjadi report yang lebih berguna operasional.
- Scope fase awal:
  - report summary lintas periode tetap dipertahankan
  - tambah opsi detail per employee untuk periode tertentu
  - filter by status, month or year, dan team bila data siap
- Kontrak penting:
  - summary report dan detail report dipisah jelas
  - permission tetap mengikuti `payroll-list` sampai ada kebutuhan split permission
- Acceptance:
  - finance bisa export report summary dan detail
  - format file stabil dan mudah dipakai rekonsiliasi
  - report tidak membuka data di luar scope permission role

### 5. Employee Payroll UX Improvements
- Rapikan area `My Payroll` supaya lebih nyaman untuk employee.
- Scope:
  - filter atau search by period
  - summary tahunan sederhana
  - status atau badge lebih jelas
  - detail slip lebih rapi
  - link email atau notifikasi diarahkan langsung ke detail payroll employee
- Kontrak penting:
  - route utama tetap `employee.payroll`
  - backend payslip endpoint existing tetap dipakai jika cukup
- Acceptance:
  - employee lebih mudah menemukan slip gaji tertentu
  - download PDF tetap berjalan
  - ownership tetap aman

### 6. Approval Layer
- Tambahkan approval step tambahan jika bisnis memang membutuhkannya.
- Scope default yang disiapkan:
  - HR generate draft
  - Finance review
  - optional approval sebelum final paid
- Kontrak penting:
  - ini hanya masuk jika ada kebutuhan nyata dari proses bisnis
  - tidak dipaksakan pada payroll v1.1 tanpa keputusan owner
- Acceptance:
  - state payroll punya transisi yang jelas
  - role approval tidak bentrok dengan finance finalization
  - route dan UI mengikuti state machine yang baru

### 7. Advanced Period Rules
- Tambahkan guard operasional yang lebih ketat pada periode payroll.
- Scope:
  - tidak bisa generate sebelum attendance cut-off selesai
  - tidak bisa generate terlalu jauh ke depan
  - tidak bisa reopen payroll `paid` tanpa workflow khusus
  - warning jika data attendance belum siap
- Kontrak penting:
  - semua rule harus konsisten antara FE dan backend
  - validasi backend tetap final authority
- Acceptance:
  - HR tidak bisa generate payroll pada periode yang tidak valid
  - pesan error atau guard lebih jelas dan operasional
  - QA bisa memverifikasi rule secara deterministik

## Perubahan Interface atau Kontrak yang Diprediksi
- `Payroll Settings`
  - butuh endpoint settings payroll baru atau reuse config service existing bila ada
  - FE perlu halaman settings khusus payroll
- `Audit Trail`
  - detail payroll akan memuat timeline aktivitas
- `Resend Notification`
  - payroll detail akan punya action resend terpisah setelah status `paid`
- `Advanced Payroll Report`
  - kemungkinan ada endpoint export summary dan export detail yang dibedakan
- `Employee UX`
  - route employee payroll tetap, tetapi query atau filter contract bisa bertambah
- `Advanced Period Rules`
  - backend validation message dan FE pre-check akan bertambah

## Test Plan
- `Payroll Settings`
  - authorized role bisa update
  - unauthorized role ditolak
  - payroll baru memakai setting terbaru, payroll lama tetap sama
- `Payroll Audit Trail`
  - setiap aksi sensitif membuat event audit
  - timeline tampil sesuai urutan
- `Resend Notification`
  - hanya finance yang bisa trigger
  - resend tidak mengubah status payroll
- `Advanced Payroll Report`
  - summary dan detail export menghasilkan file valid
  - filter month, year, dan status bekerja sesuai ekspektasi
- `Employee Payroll UX`
  - filter, detail, dan download tetap aman untuk ownership employee
- `Approval Layer`
  - transisi state dan guard role tervalidasi end-to-end
- `Advanced Period Rules`
  - invalid month, future month, atau incomplete attendance diblok di backend dan tercermin di UI

## Asumsi dan Default
- Payroll v1 yang sekarang dianggap stabil dan menjadi baseline untuk fase berikutnya.
- Prioritas paling masuk akal setelah v1 adalah `Payroll Settings`, lalu `Audit Trail`, karena keduanya paling kuat mendukung operasi payroll nyata.
- `Resend Notification Manual` belum dianggap blocker selama notifikasi otomatis tetap berjalan saat `Mark as Paid`.
- Report agregat finance yang sekarang tetap valid. Backlog report lanjutan bertujuan menambah kedalaman, bukan mengganti flow yang sudah ada.
- Jika nanti bisnis tidak membutuhkan approval tambahan, item `Approval Layer` boleh tetap parkir di backlog tanpa dieksekusi.
