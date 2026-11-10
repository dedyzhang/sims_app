<?php

namespace App\Demo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DemoCatalogController extends Controller
{
    /**
     * Halaman showcase/katalog rahasia untuk FL atau tim sales.
     * Berisi daftar seluruh fitur, link promosi, dan QR Code interaktif
     * yang siap diunduh (SVG/PNG) atau disalin ke landing page marketing.
     */
    public function index(Request $request)
    {
        // 1. Cek apakah demo aktif
        if (!config('demo.enabled', false)) {
            abort(404);
        }

        // 2. Cek restriksi subdomain jika ada
        $allowedDomain = config('demo.allowed_domain');
        if (!empty($allowedDomain) && strcasecmp($request->getHost(), $allowedDomain) !== 0) {
            abort(404);
        }

        // 3. Proteksi akses: Wajib kunci rahasia atau login sebagai superadmin/admin
        $secretKey = config('demo.secret_key');
        $providedKey = (string) $request->query('key', '');
        $isAuthorized = ($secretKey !== '' && hash_equals($secretKey, $providedKey))
            || (auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()));

        if (!$isAuthorized) {
            abort(403, 'Akses terbatas untuk pengelola demo. Sertakan parameter ?key=... yang valid.');
        }

        // 4. Bangun data katalog fitur beserta QR Code SVG
        $catalog = [];
        $features = config('demo.features', []);

        foreach ($features as $slug => $data) {
            $directUrl = \Illuminate\Support\Facades\Route::has('demo.enter')
                ? route('demo.enter', ['slug' => $slug])
                : url("/demo-access/go/{$slug}");

            // SimpleSoftwareIO QrCode mengembalikan objek/string SVG
            $qrSvg = QrCode::size(200)
                ->margin(1)
                ->errorCorrection('M')
                ->generate($directUrl);

            $catalog[] = [
                'slug'        => $slug,
                'title'       => $data['title'],
                'badge'       => $data['badge'] ?? ucfirst($data['role']),
                'description' => $data['description'],
                'role'        => $data['role'],
                'target_url'  => $data['target_url'],
                'direct_url'  => $directUrl,
                'qr_svg'      => $qrSvg,
                'icon'        => $data['icon'] ?? 'star',
            ];
        }

        return response()
            ->view('demo.showcase', [
                'catalog'   => $catalog,
                'secretKey' => $secretKey,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
