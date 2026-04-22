<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoService
{
    public static function sendEmail($to, $name, $subject, $html)
    {
        $response = Http::timeout(10)->withHeaders([
            'accept' => 'application/json',
            'api-key' => config('services.brevo.key'),
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
                    "sender" => [
                        "name" => config('services.brevo.name'),
                        "email" => config('services.brevo.email'),
                    ],
                    "to" => [
                        [
                            "email" => $to,
                            "name" => $name
                        ]
                    ],
                    "subject" => $subject,
                    "htmlContent" => $html
                ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal kirim email: ' . $response->body());
        }

        return $response->json();

    }

}