<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REFERENCE_UNIQUE = 'pa_ref_uq';
    private const DOCUMENT_UUID_UNIQUE = 'pad_uuid_uq';
    private const DOCUMENT_APPLICATION_FOREIGN = 'pad_app_fk';
    private const DOCUMENT_TYPE_INDEX = 'pa_docs_app_type_idx';

    public function up(): void
    {
        $this->addApplicationColumns();
        $this->ensureIndex('partner_applications', self::REFERENCE_UNIQUE, ['reference'], function (Blueprint $table): void {
            $table->unique('reference', self::REFERENCE_UNIQUE);
        });

        if (!Schema::hasTable('partner_application_documents')) {
            Schema::create('partner_application_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid');
                $table->foreignId('partner_application_id');
                $table->string('document_type', 60);
                $table->string('disk', 40)->default('local');
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();
                $table->unique('uuid', self::DOCUMENT_UUID_UNIQUE);
                $table->foreign('partner_application_id', self::DOCUMENT_APPLICATION_FOREIGN)
                    ->references('id')->on('partner_applications')->cascadeOnDelete();
                $table->index(['partner_application_id', 'document_type'], self::DOCUMENT_TYPE_INDEX);
            });
            return;
        }

        // The original release could create this table before failing on its
        // composite index. Finish only the missing non-destructive objects.
        $this->ensureIndex('partner_application_documents', self::DOCUMENT_UUID_UNIQUE, ['uuid'], function (Blueprint $table): void {
            $table->unique('uuid', self::DOCUMENT_UUID_UNIQUE);
        });
        $this->ensureDocumentForeignKey();
        $this->ensureIndex('partner_application_documents', self::DOCUMENT_TYPE_INDEX, ['partner_application_id', 'document_type'], function (Blueprint $table): void {
            $table->index(['partner_application_id', 'document_type'], self::DOCUMENT_TYPE_INDEX);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_application_documents');

        if (Schema::hasTable('partner_applications')) {
            $this->dropIndexIfPresent('partner_applications', self::REFERENCE_UNIQUE);
            Schema::table('partner_applications', function (Blueprint $table): void {
                foreach (['reference', 'country', 'national_id', 'business_data', 'sales_data', 'terms_accepted_at', 'terms_version', 'approved_at', 'rejected_at', 'rejection_reason'] as $column) {
                    if (Schema::hasColumn('partner_applications', $column)) $table->dropColumn($column);
                }
            });
        }
    }

    private function addApplicationColumns(): void
    {
        if (!Schema::hasTable('partner_applications')) return;

        $columns = [
            'reference' => fn (Blueprint $table) => $table->string('reference', 40)->nullable()->after('id'),
            'country' => fn (Blueprint $table) => $table->string('country', 100)->nullable()->after('province'),
            'national_id' => fn (Blueprint $table) => $table->text('national_id')->nullable()->after('country'),
            'business_data' => fn (Blueprint $table) => $table->json('business_data')->nullable()->after('reason'),
            'sales_data' => fn (Blueprint $table) => $table->json('sales_data')->nullable()->after('business_data'),
            'terms_accepted_at' => fn (Blueprint $table) => $table->timestamp('terms_accepted_at')->nullable()->after('submitted_at'),
            'terms_version' => fn (Blueprint $table) => $table->string('terms_version', 30)->nullable()->after('terms_accepted_at'),
            'approved_at' => fn (Blueprint $table) => $table->timestamp('approved_at')->nullable()->after('reviewed_at'),
            'rejected_at' => fn (Blueprint $table) => $table->timestamp('rejected_at')->nullable()->after('approved_at'),
            'rejection_reason' => fn (Blueprint $table) => $table->text('rejection_reason')->nullable()->after('admin_notes'),
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('partner_applications', $name)) {
                Schema::table('partner_applications', $definition);
            }
        }
    }

    private function ensureIndex(string $tableName, string $indexName, array $columns, callable $definition): void
    {
        if (!$this->indexExists($tableName, $indexName) && !$this->hasEquivalentUniqueIndex($tableName, $columns)) {
            Schema::table($tableName, $definition);
        }
    }

    private function ensureDocumentForeignKey(): void
    {
        if ($this->foreignKeyExists('partner_application_documents', 'partner_application_id')) return;

        Schema::table('partner_application_documents', function (Blueprint $table): void {
            $table->foreign('partner_application_id', self::DOCUMENT_APPLICATION_FOREIGN)
                ->references('id')->on('partner_applications')->cascadeOnDelete();
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return (bool) DB::selectOne('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1', [$tableName, $indexName]);
        }

        return collect(DB::select("PRAGMA index_list('{$tableName}')"))->contains(fn ($index) => ($index->name ?? null) === $indexName);
    }

    private function hasEquivalentUniqueIndex(string $tableName, array $columns): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') return false;

        $rows = DB::select('SELECT index_name, non_unique, seq_in_index, column_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? ORDER BY index_name, seq_in_index', [$tableName]);
        $indexes = [];
        foreach ($rows as $row) {
            if ((int) $row->non_unique !== 0 || $row->index_name === 'PRIMARY') continue;
            $indexes[$row->index_name][] = $row->column_name;
        }

        foreach ($indexes as $indexColumns) {
            if ($indexColumns === $columns) return true;
        }

        return false;
    }

    private function foreignKeyExists(string $tableName, string $column): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return (bool) DB::selectOne('SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL LIMIT 1', [$tableName, $column]);
        }

        // The only existing SQLite table case is a table created by the
        // original migration, which already created this relationship.
        return true;
    }

    private function dropIndexIfPresent(string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
        }
    }
};
