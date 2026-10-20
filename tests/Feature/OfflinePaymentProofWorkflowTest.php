<?php

namespace Tests\Feature;

use App\Http\Controllers\RestAPI\v1\FrontendOrderController;
use App\Models\OfflinePaymentMethod;
use App\Models\OfflinePaymentProof;
use App\Models\OfflinePaymentProofEvent;
use App\Models\Order;
use App\Services\OfflinePaymentProofReviewService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

/**
 * Focused payment lifecycle tests use a disposable SQLite schema assembled in
 * this test. The normal repository migrations cannot provide this schema
 * because the vendor checkout omitted the original 6Valley base migrations.
 */
class OfflinePaymentProofWorkflowTest extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'sqlite');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');
        $this->createDisposableSchema();
    }

    public function test_customer_can_submit_private_proof_and_admin_can_approve_once(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));
        $request = $this->proofRequest($order, 1, $method, 'BANK-001', UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'));

        $response = $controller->submitPaymentProof($request, $order->id);
        self::assertSame(201, $response->getStatusCode());

        $proof = OfflinePaymentProof::firstOrFail();
        Storage::disk('local')->assertExists($proof->proof_path);
        self::assertSame('pending', $proof->status);
        self::assertSame(1, OfflinePaymentProofEvent::where('event', 'submitted')->count());

        app(OfflinePaymentProofReviewService::class)->review($proof->id, 'approved', 99);

        self::assertSame('paid', Order::findOrFail($order->id)->payment_status);
        self::assertSame(1, DB::table('payment_transactions')->count());
        self::assertSame(1, OfflinePaymentProofEvent::where('event', 'approved')->count());
    }

    public function test_rejection_records_reason_and_allows_a_new_submission(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));
        $first = $this->proofRequest($order, 1, $method, 'BANK-002', UploadedFile::fake()->createWithContent('first.pdf', "%PDF-1.4\nfirst-proof"));
        $controller->submitPaymentProof($first, $order->id);
        $proof = OfflinePaymentProof::firstOrFail();

        app(OfflinePaymentProofReviewService::class)->review($proof->id, 'rejected', 99, 'Reference could not be verified.');

        self::assertSame('rejected', $proof->fresh()->status);
        self::assertSame('Reference could not be verified.', $proof->fresh()->rejection_reason);
        $second = $this->proofRequest($order, 1, $method, 'BANK-003', UploadedFile::fake()->createWithContent('second.pdf', "%PDF-1.4\nsecond-proof"));
        self::assertSame(201, $controller->submitPaymentProof($second, $order->id)->getStatusCode());
    }

    public function test_wrong_owner_amount_duplicate_proof_and_unsafe_files_are_rejected(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));

        $wrongOwner = $this->proofRequest($order, 2, $method, 'BANK-004', UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->submitPaymentProof($wrongOwner, $order->id);
    }

    public function test_wrong_amount_is_rejected_without_creating_a_proof(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));
        $request = $this->proofRequest($order, 1, $method, 'BANK-AMOUNT', UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'));
        $request->merge(['amount_claimed' => 1249]);

        $response = $controller->submitPaymentProof($request, $order->id);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, OfflinePaymentProof::count());
    }

    public function test_duplicate_file_hash_is_rejected(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));
        $first = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\nidentical-proof");
        self::assertSame(201, $controller->submitPaymentProof($this->proofRequest($order, 1, $method, 'BANK-DUP-1', $first), $order->id)->getStatusCode());

        $second = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\nidentical-proof");
        $response = $controller->submitPaymentProof($this->proofRequest($order, 1, $method, 'BANK-DUP-2', $second), $order->id);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(1, OfflinePaymentProof::count());
    }

    public function test_unsupported_and_oversized_files_are_rejected(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->submitPaymentProof($this->proofRequest($order, 1, $method, 'BANK-BAD-FILE', UploadedFile::fake()->create('script.php', 20, 'text/x-php')), $order->id);
    }

    public function test_file_over_five_megabytes_is_rejected(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->submitPaymentProof($this->proofRequest($order, 1, $method, 'BANK-LARGE', UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')), $order->id);
    }

    public function test_duplicate_review_cannot_create_second_transaction(): void
    {
        [$order, $method] = $this->fixture();
        $controller = new FrontendOrderController($this->createMock(\App\Services\OrderService::class));
        $controller->submitPaymentProof($this->proofRequest($order, 1, $method, 'BANK-005', UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf')), $order->id);
        $proof = OfflinePaymentProof::firstOrFail();
        $reviewer = app(OfflinePaymentProofReviewService::class);
        $reviewer->review($proof->id, 'approved', 99);
        self::assertSame(1, DB::table('payment_transactions')->count());

        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $reviewer->review($proof->id, 'approved', 99);
        self::assertSame(1, DB::table('payment_transactions')->count());
    }

    private function proofRequest(Order $order, int $customerId, OfflinePaymentMethod $method, string $reference, UploadedFile $file): Request
    {
        $request = Request::create('/api/v1/frontend/orders/' . $order->id . '/payment-proof', 'POST', [
            'method_id' => $method->id,
            'transaction_reference' => $reference,
            'amount_claimed' => $order->order_amount,
        ], [], ['proof' => $file]);
        $user = new \App\Models\User(['id' => $customerId]);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function fixture(): array
    {
        $methodId = DB::table('offline_payment_methods')->insertGetId([
            'method_name' => 'Test Bank Transfer',
            'method_fields' => json_encode([]),
            'method_informations' => json_encode([['customer_input' => 'reference', 'is_required' => 1]]),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'customer_id' => 1,
            'is_guest' => 0,
            'payment_method' => 'offline_payment',
            'payment_status' => 'unpaid',
            'order_amount' => 1250,
            'display_currency_code' => 'PKR',
            'base_currency_code' => 'PKR',
            'transaction_ref' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('offline_payments')->insert([
            'order_id' => $orderId,
            'payment_info' => json_encode(['method_id' => $methodId, 'method_name' => 'Test Bank Transfer']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_details')->insert(['order_id' => $orderId, 'payment_status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]);
        return [Order::findOrFail($orderId), OfflinePaymentMethod::findOrFail($methodId)];
    }

    private function createDisposableSchema(): void
    {
        $schema = Schema::connection('sqlite');
        foreach (['storages', 'offline_payment_proof_events', 'offline_payment_proofs', 'payment_transactions', 'offline_payments', 'offline_payment_methods', 'order_details', 'order_status_histories', 'orders'] as $table) {
            $schema->dropIfExists($table);
        }
        $schema->create('orders', function ($table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->boolean('is_guest')->default(false); $table->string('payment_method'); $table->string('payment_status'); $table->decimal('order_amount', 20, 4); $table->string('display_currency_code')->nullable(); $table->string('base_currency_code')->nullable(); $table->string('transaction_ref')->nullable(); $table->string('payment_by')->nullable(); $table->timestamps();
        });
        $schema->create('order_details', function ($table) { $table->id(); $table->unsignedBigInteger('order_id'); $table->string('payment_status')->nullable(); $table->timestamps(); });
        $schema->create('storages', function ($table) { $table->id(); $table->unsignedBigInteger('data_id'); $table->string('data_type'); $table->text('data')->nullable(); $table->timestamps(); });
        $schema->create('order_status_histories', function ($table) { $table->id(); $table->unsignedBigInteger('order_id'); $table->string('status')->nullable(); $table->string('cause')->nullable(); $table->timestamps(); });
        $schema->create('offline_payment_methods', function ($table) { $table->id(); $table->string('method_name'); $table->text('method_fields'); $table->text('method_informations'); $table->tinyInteger('status')->default(0); $table->timestamps(); });
        $schema->create('offline_payments', function ($table) { $table->id(); $table->unsignedBigInteger('order_id'); $table->text('payment_info'); $table->timestamps(); });
        $schema->create('payment_transactions', function ($table) { $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('order_id'); $table->string('provider'); $table->string('provider_reference')->nullable(); $table->string('provider_transaction_id')->nullable(); $table->decimal('amount', 20, 4); $table->string('currency'); $table->string('status'); $table->string('idempotency_key')->nullable(); $table->text('metadata')->nullable(); $table->timestamp('paid_at')->nullable(); $table->timestamp('failed_at')->nullable(); $table->timestamps(); $table->unique(['provider', 'provider_reference']); });
        $schema->create('offline_payment_proofs', function ($table) { $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('offline_method_id')->nullable(); $table->string('transaction_reference'); $table->decimal('amount_claimed', 20, 4); $table->string('currency'); $table->string('proof_disk'); $table->string('proof_path'); $table->string('proof_mime'); $table->unsignedBigInteger('proof_size'); $table->string('file_hash'); $table->text('note')->nullable(); $table->string('status'); $table->unsignedBigInteger('reviewed_by')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->text('rejection_reason')->nullable(); $table->timestamps(); $table->unique(['order_id', 'file_hash']); });
        $schema->create('offline_payment_proof_events', function ($table) { $table->id(); $table->unsignedBigInteger('proof_id'); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('actor_type'); $table->string('event'); $table->string('from_status')->nullable(); $table->string('to_status'); $table->text('note')->nullable(); $table->timestamps(); });
    }
}
