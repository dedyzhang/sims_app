# Operasional TTS Asisten Guru

Di local Windows, TTS dapat berjalan sesudah response melalui connection `deferred` tanpa terminal worker. Di production, gunakan database queue khusus `tts` dan process monitor.

## Environment

```dotenv
QUEUE_CONNECTION=database
AI_TTS_DISPATCH=queue
AI_TTS_QUEUE_CONNECTION=tts
AI_TTS_QUEUE=tts
AI_TTS_QUEUE_RETRY_AFTER=1900
AI_TTS_MODEL=gemini-3.1-flash-tts-preview
AI_TTS_TRANSLATION_MODEL=gemini-3.7-flash
AI_TTS_TRANSLATION_TIMEOUT=120
AI_TTS_TRANSLATION_MAX_OUTPUT_TOKENS=8192
AI_TTS_OUTPUT_FORMAT=mp3
AI_TTS_CHUNK_CHARS=1000
AI_TTS_TIMEOUT=120
AI_TTS_RETRIES=2
```

Untuk local Windows tanpa worker, ubah hanya:

```dotenv
AI_TTS_DISPATCH=deferred
```

Bahasa Indonesia langsung masuk ke TTS tanpa tahap tambahan. Pilihan bahasa lain diterjemahkan terlebih dahulu oleh `AI_TTS_TRANSLATION_MODEL`, lalu hasil terjemahan dibacakan oleh model TTS. Dengan demikian satu audio non-Indonesia memakai minimal satu request model teks ditambah request TTS sesuai jumlah chunk.

Mode `deferred` tidak memerlukan `queue:work`, tetapi memakai proses web setelah response. Jangan gunakan mode ini di production karena dapat menahan PHP worker selama audio dibuat.

Jalankan migrasi sebelum worker pertama kali dinyalakan:

```bash
php artisan migrate --force
```

## Supervisor

```ini
[program:sims-queue]
command=php /var/www/sims/artisan queue:work tts --queue=tts --sleep=3 --tries=2 --timeout=1800 --max-time=3600
directory=/var/www/sims
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=1900
redirect_stderr=true
stdout_logfile=/var/www/sims/storage/logs/queue-worker.log
```

Setelah deploy atau perubahan kode:

```bash
php artisan queue:restart
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status sims-queue
```

## systemd

```ini
[Unit]
Description=SIMS Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/sims
ExecStart=/usr/bin/php artisan queue:work tts --queue=tts --sleep=3 --tries=2 --timeout=1800 --max-time=3600
Restart=always
RestartSec=5
TimeoutStopSec=1900

[Install]
WantedBy=multi-user.target
```

Aktifkan dengan `systemctl enable --now sims-queue` dan cek melalui `systemctl status sims-queue`.

## Pemeriksaan

```bash
php artisan queue:monitor tts:5
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

Status audio harus selalu berakhir sebagai `ready` atau `failed`. Error quota harian Free Tier tidak boleh dibiarkan `processing`.

`AI_TTS_QUEUE_RETRY_AFTER` harus lebih besar daripada timeout job 1.800 detik agar satu audio tidak diproses dua worker sekaligus.
