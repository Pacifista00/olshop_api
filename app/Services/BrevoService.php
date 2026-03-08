<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoService
{
    public static function sendEmail($to, $name, $subject, $html)
    {
        return Http::withHeaders([
            'accept' => 'application/json',
            'api-key' => config('services.brevo.key'),
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
                    "sender" => [
                        "name" => "WawaNet",
                        "email" => "noreply@wawanet.com"
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
    }
}