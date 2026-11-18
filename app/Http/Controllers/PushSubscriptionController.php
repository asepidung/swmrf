<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menyimpan dan menghapus langganan Web Push milik pengguna yang sedang login.
 *
 * Satu pengguna bisa punya beberapa langganan sekaligus — satu per perangkat
 * dan per browser. Itu memang diinginkan: notifikasi sampai ke HP maupun ke
 * komputer kerjanya.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', $this->publicHttpsEndpoint()],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? 'aesgcm'
        );

        return response()->json(['status' => 'subscribed']);
    }

    /**
     * Alamat langganan harus https ke nama host publik. Server mengirim permintaan ke alamat ini tiap ada notifikasi,
     * jadi alamat jaringan dalam, IP, atau localhost ditolak (audit pra-rilis 14 November 2026, PS-01).
     */
    private function publicHttpsEndpoint(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $parts = parse_url((string) $value);
            $host = strtolower((string) ($parts['host'] ?? ''));

            $valid = ($parts['scheme'] ?? null) === 'https'
                && $host !== ''
                && str_contains($host, '.')
                && ! filter_var($host, FILTER_VALIDATE_IP)
                && ! preg_match('/(^|\.)(localhost|local|internal|lan|home|test)$/', $host);

            if (! $valid) {
                $fail(__('The push address is not valid.'));
            }
        };
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['status' => 'unsubscribed']);
    }
}
