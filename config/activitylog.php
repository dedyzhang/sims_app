<?php

return [
    /*
     * Kalau false, activity() tak akan menyimpan Activity apa pun ke database.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * Kalau di-set, log lama otomatis dihapus setelah sekian hari (via perintah
     * `activitylog:clean`). Kosongkan (null) utk tak pernah menghapus otomatis.
     */
    'delete_records_older_than_days' => 365,

    /*
     * Nama log default kalau tidak disebutkan eksplisit (mis. activity()->log(...)
     * tanpa memanggil inLog() dulu).
     */
    'default_log_name' => 'default',

    /*
     * Guard auth default yg dipakai utk causedBy() kalau tidak disebutkan eksplisit.
     */
    'default_auth_driver' => null,

    /*
     * Kalau true, subject() masih boleh mengembalikan model yg sudah soft-deleted.
     */
    'subject_returns_soft_deleted_models' => false,

    /*
     * Model Eloquent yg dipakai utk menyimpan activity — WAJIB implements
     * Spatie\Activitylog\Contracts\Activity.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * Nama tabel penyimpanan activity log — dipakai jg oleh migration bawaan paket
     * (database/migrations/2026_07_23_111602_create_activity_log_table.php di repo
     * ini). WAJIB terisi (bukan kosong/null) — Schema::create() akan gagal dgn
     * "Incorrect table name ''" kalau file config ini tak ada / key ini kosong.
     */
    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    /*
     * Koneksi database yg dipakai utk activity log — null berarti ikut koneksi default.
     */
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
