<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customer_wallet_withdrawal_requests')) {
            Schema::table('customer_wallet_withdrawal_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_wallet_withdrawal_requests', 'held_amount')) $table->decimal('held_amount', 18, 2)->default(0)->after('amount');
                if (!Schema::hasColumn('customer_wallet_withdrawal_requests', 'idempotency_key')) $table->string('idempotency_key')->nullable()->unique()->after('reference');
                if (!Schema::hasColumn('customer_wallet_withdrawal_requests', 'payment_reference')) $table->string('payment_reference')->nullable()->after('admin_note');
                if (!Schema::hasColumn('customer_wallet_withdrawal_requests', 'paid_at')) $table->timestamp('paid_at')->nullable()->after('reviewed_at');
                if (!Schema::hasColumn('customer_wallet_withdrawal_requests', 'released_at')) $table->timestamp('released_at')->nullable()->after('paid_at');
            });
        }
        foreach (['wallet_withdrawal_min_amount' => '0', 'wallet_withdrawal_max_amount' => '0', 'wallet_withdrawal_daily_limit' => '0'] as $type => $value) {
            DB::table('business_settings')->updateOrInsert(['type' => $type], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        if (Schema::hasTable('withdrawal_methods')) {
            $methods = [
                'Bank Transfer' => [
                    ['input_type'=>'string','input_name'=>'account_holder_name','placeholder'=>'Account holder name','is_required'=>1],
                    ['input_type'=>'string','input_name'=>'bank_name','placeholder'=>'Bank name','is_required'=>1],
                    ['input_type'=>'string','input_name'=>'account_number_iban','placeholder'=>'Account number / IBAN','is_required'=>1],
                    ['input_type'=>'string','input_name'=>'country','placeholder'=>'Country','is_required'=>1],
                    ['input_type'=>'string','input_name'=>'swift_bic','placeholder'=>'SWIFT / BIC (optional)','is_required'=>0],
                ],
                'JazzCash' => [
                    ['input_type'=>'string','input_name'=>'account_holder_name','placeholder'=>'Account holder name','is_required'=>1],
                    ['input_type'=>'phone','input_name'=>'mobile_number','placeholder'=>'03XXXXXXXXX','is_required'=>1],
                ],
                'Easypaisa' => [
                    ['input_type'=>'string','input_name'=>'account_holder_name','placeholder'=>'Account holder name','is_required'=>1],
                    ['input_type'=>'phone','input_name'=>'mobile_number','placeholder'=>'03XXXXXXXXX','is_required'=>1],
                ],
            ];
            foreach ($methods as $name => $fields) {
                if (!DB::table('withdrawal_methods')->where('method_name', $name)->exists()) DB::table('withdrawal_methods')->insert(['method_name'=>$name,'method_fields'=>json_encode($fields),'is_default'=>0,'is_active'=>1,'vendor_status'=>0,'customer_status'=>1,'created_at'=>now(),'updated_at'=>now()]);
            }
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('customer_wallet_withdrawal_requests')) Schema::table('customer_wallet_withdrawal_requests', function (Blueprint $table) { foreach (['held_amount','idempotency_key','payment_reference','paid_at','released_at'] as $column) if (Schema::hasColumn('customer_wallet_withdrawal_requests',$column)) $table->dropColumn($column); });
    }
};
