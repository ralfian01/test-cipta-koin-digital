<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse; // Import kelas BinaryFileResponse

class OwnCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization, App-Key'
        ];
        if ($request->getMethod() == "OPTIONS") {
            return response('OK')
                ->withHeaders($headers);
        }

        $response = $next($request);

        // --- PERBAIKAN KRUSIAL DI SINI ---
        // Cek apakah respons adalah BinaryFileResponse (seperti dari download file)
        if ($response instanceof BinaryFileResponse) {
            // Untuk BinaryFileResponse, kita tidak bisa menggunakan method header().
            // Namun, header CORS biasanya tidak terlalu krusial untuk download file langsung.
            // Kita bisa langsung mengembalikan responsnya apa adanya.
            // Jika Anda benar-benar perlu menambahkan header, caranya lebih kompleks:
            // $response->headers->set('X-Custom-Header', 'value');
            // Tapi untuk CORS, seringkali lebih aman untuk membiarkannya.
            return $response;
        }

        // Jika ini adalah respons normal (JSON, HTML, dll.), terapkan header CORS.
        // Pastikan juga method header() ada sebelum memanggilnya.
        if (method_exists($response, 'header')) {
            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }
        }
        // ------------------------------------

        return $response;
    }
}
