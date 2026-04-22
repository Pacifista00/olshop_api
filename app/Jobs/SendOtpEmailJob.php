<?php

namespace App\Jobs;

use App\Services\BrevoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOtpEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $name;
    protected $otp;

    public function __construct($email, $name, $otp)
    {
        $this->email = $email;
        $this->name = $name;
        $this->otp = $otp;
    }

    public function handle()
    {
        $logo = config('app.url') . '/image/logo.png';

        $html = "
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>OTP Verification</title>
</head>

<body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f8;padding:40px 0'>
<tr>
<td align='center'>

<table width='500' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;padding:40px'>

<tr>
<td align='center' style='padding-bottom:20px'>
<img src='{$logo}' alt='Logo' style='height:40px'>
</td>
</tr>

<tr>
<td style='text-align:center'>
<h2 style='margin:0;color:#333'>Verifikasi Akun</h2>
<p style='color:#666;font-size:14px'>
Halo <b>{$this->name}</b>, gunakan kode OTP berikut untuk menyelesaikan pendaftaran akun Anda.
</p>
</td>
</tr>

<tr>
<td align='center' style='padding:30px 0'>

<div style='
display:inline-block;
padding:15px 25px;
font-size:32px;
letter-spacing:8px;
font-weight:bold;
background:#f2f4f6;
border-radius:6px;
color:#333;
'>
{$this->otp}
</div>

</td>
</tr>

<tr>
<td style='text-align:center;font-size:13px;color:#888'>
Kode OTP ini berlaku selama <b>5 menit</b>.
</td>
</tr>

<tr>
<td style='padding-top:30px;text-align:center;font-size:12px;color:#aaa'>
Jika Anda tidak melakukan pendaftaran, abaikan email ini.
</td>
</tr>

</table>

<tr>
<td style='text-align:center;padding-top:20px;font-size:12px;color:#aaa'>
© " . date('Y') . " WawaNet
</td>
</tr>

</td>
</tr>
</table>

</body>
</html>
";

        BrevoService::sendEmail(
            $this->email,
            $this->name,
            'Kode OTP Registrasi',
            $html
        );
    }
    public function failed(\Throwable $exception)
    {
        Log::error('Gagal kirim OTP Email', [
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
