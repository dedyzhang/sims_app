/**
 * Helper lokasi absensi QR — warm GPS via watchPosition, ambil sample terbaik.
 * Dipakai di halaman Absen QR, izin pulang QR, dan pengaturan pin sekolah.
 *
 * Soft geofence memakai toleransi SERVER tetap (softToleranceM) — bukan nilai
 * accuracy dari perangkat — agar klien tidak bisa melebarkan radius.
 */
(function (global) {
    'use strict';

    var DEFAULTS = {
        watchMs: 10000,
        targetAccuracy: 40,
        highTimeout: 20000,
        lowTimeout: 15000,
        maxAccuracySubmit: 150,
        softToleranceM: 50, // selaras App\Support\Geofence::SOFT_TOLERANCE_M
    };

    function pesanGagal(err) {
        if (typeof window.isSecureContext !== 'undefined' && !window.isSecureContext) {
            return 'Lokasi hanya bisa dibaca lewat alamat aman (diawali https://). Buka aplikasi memakai alamat https, bukan http.';
        }
        if (err && err.code === 1) {
            return 'Izin lokasi ditolak. Buka pengaturan izin — ikon gembok di address bar (di HP: izin Lokasi untuk aplikasi/browser ini) — aktifkan Lokasi, lalu tekan Perbarui.';
        }
        if (err && err.code === 2) {
            return 'Lokasi belum ditemukan. Pastikan GPS/Layanan Lokasi menyala, lalu tekan Perbarui.';
        }
        if (err && err.code === 3) {
            return 'Membaca lokasi terlalu lama. Pindah ke tempat lebih terbuka, lalu tekan Perbarui.';
        }
        return 'Lokasi gagal dibaca. Tekan Perbarui untuk mencoba lagi.';
    }

    /**
     * Ambil posisi GPS terbaik dalam jendela waktu singkat.
     * @param {object} [opts]
     * @param {number} [opts.watchMs]
     * @param {number} [opts.targetAccuracy]
     * @param {function(string):void} [opts.onProgress]
     * @returns {Promise<{lat:number,lng:number,accuracy:number}>}
     */
    function getBestLocation(opts) {
        opts = opts || {};
        var watchMs = opts.watchMs != null ? opts.watchMs : DEFAULTS.watchMs;
        var targetAccuracy = opts.targetAccuracy != null ? opts.targetAccuracy : DEFAULTS.targetAccuracy;
        var onProgress = typeof opts.onProgress === 'function' ? opts.onProgress : null;

        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject({ code: 0, message: 'Perangkat ini tidak mendukung deteksi lokasi. Coba buka lewat HP atau browser lain.' });
                return;
            }
            if (typeof window.isSecureContext !== 'undefined' && !window.isSecureContext) {
                reject({ code: 1, message: pesanGagal(null) });
                return;
            }

            var best = null;
            var watchId = null;
            var settled = false;
            var timer = null;

            function finishOk() {
                if (settled) return;
                settled = true;
                cleanup();
                if (!best) {
                    reject({ code: 2, message: pesanGagal({ code: 2 }) });
                    return;
                }
                resolve({
                    lat: best.lat,
                    lng: best.lng,
                    accuracy: best.accuracy,
                });
            }

            function finishErr(err) {
                if (settled) return;
                if (best) {
                    finishOk();
                    return;
                }
                settled = true;
                cleanup();
                if (err && err.message) {
                    reject(err);
                    return;
                }
                reject({ code: err && err.code, message: pesanGagal(err) });
            }

            function cleanup() {
                if (watchId != null && navigator.geolocation.clearWatch) {
                    try { navigator.geolocation.clearWatch(watchId); } catch (e) {}
                }
                watchId = null;
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
            }

            function consider(pos) {
                var c = pos.coords;
                var acc = (typeof c.accuracy === 'number' && isFinite(c.accuracy)) ? c.accuracy : 9999;
                if (!best || acc < best.accuracy) {
                    best = { lat: c.latitude, lng: c.longitude, accuracy: acc };
                }
                if (onProgress) {
                    onProgress('Menyempurnakan GPS… akurasi ~' + Math.round(best.accuracy) + ' m');
                }
                if (best.accuracy <= targetAccuracy) {
                    finishOk();
                }
            }

            if (onProgress) onProgress('Sedang membaca lokasi (GPS sedang dipanaskan)…');

            var highOpts = { enableHighAccuracy: true, timeout: DEFAULTS.highTimeout, maximumAge: 0 };

            try {
                watchId = navigator.geolocation.watchPosition(consider, function (err) {
                    if (!best && err && err.code === 1) {
                        finishErr(err);
                    }
                }, highOpts);
            } catch (e) {
                watchId = null;
            }

            navigator.geolocation.getCurrentPosition(consider, function (err) {
                if (best) return;
                if (err && err.code === 3) {
                    navigator.geolocation.getCurrentPosition(consider, function (err2) {
                        if (!best) finishErr(err2 || err);
                    }, { enableHighAccuracy: false, timeout: DEFAULTS.lowTimeout, maximumAge: 0 });
                    return;
                }
                if (!best) finishErr(err);
            }, highOpts);

            timer = setTimeout(function () {
                if (best) finishOk();
                else finishErr({ code: 3, message: pesanGagal({ code: 3 }) });
            }, watchMs);
        });
    }

    function accuracyAcceptable(accuracy, maxM) {
        maxM = maxM != null ? maxM : DEFAULTS.maxAccuracySubmit;
        if (accuracy == null || !isFinite(accuracy) || accuracy < 0) return false;
        return accuracy <= maxM;
    }

    /** Soft geofence: jarak ≤ radius + toleransi server tetap. */
    function withinRadius(dist, radius, softTolerance) {
        softTolerance = softTolerance != null ? softTolerance : DEFAULTS.softToleranceM;
        return dist <= (radius + softTolerance);
    }

    function effectiveRadius(radius, softTolerance) {
        softTolerance = softTolerance != null ? softTolerance : DEFAULTS.softToleranceM;
        return radius + softTolerance;
    }

    global.SimsGeo = {
        defaults: DEFAULTS,
        pesanGagal: pesanGagal,
        getBestLocation: getBestLocation,
        accuracyAcceptable: accuracyAcceptable,
        withinRadius: withinRadius,
        effectiveRadius: effectiveRadius,
    };
})(typeof window !== 'undefined' ? window : this);
