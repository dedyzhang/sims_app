<?php

namespace App\Exceptions;

/**
 * Kuota HARIAN free tier provider AI sudah habis.
 * Pemanggil boleh menyimpan progres dan menjadwalkan lanjutan setelah reset (tengah malam).
 * Bukan untuk 429 per-menit — itu {@see AiRateLimitedException}.
 */
class AiDailyQuotaExhaustedException extends AiProviderUnavailableException {}
