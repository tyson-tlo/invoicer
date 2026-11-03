<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('total');
        });

        $paidInvoices = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->get();

        foreach ($paidInvoices as $invoice) {
            DB::table('payments')->insert([
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total,
                'paid_at' => Carbon::parse($invoice->paid_at)->toDateString(),
                'method' => $invoice->payment_method ?? 'bank_transfer',
                'reference' => $invoice->payment_reference,
                'note' => $invoice->payment_note,
                'proof_path' => null,
                'proof_original_name' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('invoices')->where('id', $invoice->id)->update([
                'amount_paid' => $invoice->total,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });
    }
};
