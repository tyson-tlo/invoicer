<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function __invoke(Payment $payment): StreamedResponse
    {
        abort_unless(
            $payment->proof_path && Storage::disk('local')->exists($payment->proof_path),
            404,
        );

        return Storage::disk('local')->response(
            $payment->proof_path,
            $payment->proof_original_name ?? basename($payment->proof_path),
        );
    }
}
