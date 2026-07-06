import re

path = r'c:\Users\Lenovo\Documents\main_app\school_app\smp_v6\routes\web.php'
with open(path, 'r', encoding='utf-8') as f:
    c = f.read()

# 1. Master Data Group
c = c.replace(
    "    Route::middleware('role:admin')->group(function () {\n\n        // Guru",
    "    Route::middleware('permission:manage_users')->group(function () {\n        // Guru"
)

# 2. Jadwal Group
c = c.replace(
    "        // Jadwal Pelajaran — editor grid per hari + generate + master jam",
    "    });\n\n    Route::middleware('permission:manage_jadwal')->group(function () {\n        // Jadwal Pelajaran — editor grid per hari + generate + master jam"
)

# 3. Absensi Group
c = c.replace(
    "        // Absensi wajah (face recognition)",
    "    });\n\n    Route::middleware('permission:manage_absensi')->group(function () {\n        // Absensi wajah (face recognition)"
)

# 4. Settings Group
c = c.replace(
    "        // Setting",
    "    });\n\n    Route::middleware('permission:manage_settings')->group(function () {\n        // Setting"
)

# 5. Keuangan
c = c.replace(
    "Route::middleware('role:admin,bendahara')->prefix('keuangan')->name('keuangan.')->group(function () {",
    "Route::middleware('permission:manage_keuangan')->prefix('keuangan')->name('keuangan.')->group(function () {"
)

# 6. Absensi Index
c = c.replace(
    "// ─── Absensi Siswa: admin (semua kelas) + wali kelas (kelasnya saja, disaring di controller) ───\n    Route::middleware('role:admin,walikelas')->group(function () {",
    "// ─── Absensi Siswa: admin (semua kelas) + wali kelas (kelasnya saja, disaring di controller) ───\n    Route::middleware('permission:manage_absensi')->group(function () {"
)

# 7. Sarpras - wait, Sarpras uses Policy (`can:`) as mentioned in app.php, but let's make sure if there is any role check for sarpras in routes
# Sarpras uses standard Resource and Policy, so no need to change here.

with open(path, 'w', encoding='utf-8') as f:
    f.write(c)

print('Success replacing middlewares in web.php')
