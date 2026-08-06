<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 20)->default('farmer')->index();
            });
        }

        if (! Schema::hasTable('canonical_labels')) {
            Schema::create('canonical_labels', function (Blueprint $table): void {
                $table->id();
                $table->string('kind', 20);
                $table->string('slug')->unique();
                $table->string('name');
                $table->foreignId('crop_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete();
                $table->boolean('is_diseased')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index(['kind', 'active']);
            });
        }

        if (! Schema::hasTable('canonical_label_aliases')) {
            Schema::create('canonical_label_aliases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('canonical_label_id')->constrained()->cascadeOnDelete();
                $table->string('normalized_alias');
                $table->timestamps();
                $table->unique(['canonical_label_id', 'normalized_alias'], 'label_alias_unique');
                $table->index('normalized_alias');
            });
        }

        if (! Schema::hasTable('model_versions')) {
            Schema::create('model_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('provider');
                $table->string('model_identifier');
                $table->string('version');
                $table->json('parameters');
                $table->string('checksum', 64);
                $table->boolean('active')->default(false)->index();
                $table->timestamps();
                $table->unique(['provider', 'model_identifier', 'version'], 'model_version_unique');
            });
        }

        if (! Schema::hasTable('prompt_versions')) {
            Schema::create('prompt_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('version');
                $table->longText('system_prompt');
                $table->longText('user_prompt');
                $table->string('checksum', 64);
                $table->boolean('active')->default(false)->index();
                $table->timestamps();
                $table->unique(['name', 'version'], 'prompt_version_unique');
            });
        }

        if (! Schema::hasTable('confidence_policies')) {
            Schema::create('confidence_policies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('version');
                $table->decimal('retake_below', 5, 4);
                $table->decimal('review_below', 5, 4);
                $table->boolean('require_canonical')->default(true);
                $table->string('checksum', 64);
                $table->boolean('active')->default(false)->index();
                $table->timestamps();
                $table->unique(['name', 'version'], 'confidence_policy_unique');
            });
        }

        $scanColumns = [
            'model_version_id' => fn (Blueprint $table) => $table->foreignId('model_version_id')->nullable()->constrained()->restrictOnDelete(),
            'prompt_version_id' => fn (Blueprint $table) => $table->foreignId('prompt_version_id')->nullable()->constrained()->restrictOnDelete(),
            'confidence_policy_id' => fn (Blueprint $table) => $table->foreignId('confidence_policy_id')->nullable()->constrained()->restrictOnDelete(),
            'predicted_crop_label_id' => fn (Blueprint $table) => $table->foreignId('predicted_crop_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete(),
            'predicted_disease_label_id' => fn (Blueprint $table) => $table->foreignId('predicted_disease_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete(),
            'effective_crop_label_id' => fn (Blueprint $table) => $table->foreignId('effective_crop_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete(),
            'effective_disease_label_id' => fn (Blueprint $table) => $table->foreignId('effective_disease_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete(),
            'processing_state' => fn (Blueprint $table) => $table->string('processing_state', 20)->default('legacy')->index(),
            'verification_state' => fn (Blueprint $table) => $table->string('verification_state', 24)->default('legacy_ineligible')->index(),
            'normalized_confidence' => fn (Blueprint $table) => $table->decimal('normalized_confidence', 5, 4)->nullable(),
            'inference_latency_ms' => fn (Blueprint $table) => $table->unsignedInteger('inference_latency_ms')->nullable(),
            'raw_result' => fn (Blueprint $table) => $table->longText('raw_result')->nullable(),
            'raw_result_checksum' => fn (Blueprint $table) => $table->string('raw_result_checksum', 64)->nullable(),
            'outbreak_eligible' => fn (Blueprint $table) => $table->boolean('outbreak_eligible')->default(false)->index(),
            'processing_started_at' => fn (Blueprint $table) => $table->timestamp('processing_started_at')->nullable(),
            'processing_completed_at' => fn (Blueprint $table) => $table->timestamp('processing_completed_at')->nullable(),
            'safe_error_code' => fn (Blueprint $table) => $table->string('safe_error_code')->nullable(),
        ];

        $missingScanColumns = array_filter(
            array_keys($scanColumns),
            fn (string $column): bool => ! Schema::hasColumn('farm_image_analyses', $column),
        );

        if ($missingScanColumns !== []) {
            Schema::table('farm_image_analyses', function (Blueprint $table) use ($scanColumns, $missingScanColumns): void {
                foreach ($missingScanColumns as $column) {
                    $scanColumns[$column]($table);
                }
            });
        }

        if (
            Schema::hasColumn('farm_image_analyses', 'verification_state')
            && ! collect(Schema::getIndexes('farm_image_analyses'))->contains(
                fn (array $index): bool => ($index['name'] ?? null) === 'farm_image_analyses_verification_state_created_at_index',
            )
        ) {
            Schema::table('farm_image_analyses', function (Blueprint $table): void {
                $table->index(['verification_state', 'created_at']);
            });
        }

        if (
            Schema::hasColumn('farm_image_analyses', 'processing_state')
            && Schema::hasColumn('farm_image_analyses', 'verification_state')
            && Schema::hasColumn('farm_image_analyses', 'outbreak_eligible')
        ) {
            DB::table('farm_image_analyses')
                ->where('processing_state', 'legacy')
                ->update([
                    'processing_state' => 'completed',
                    'verification_state' => 'legacy_ineligible',
                    'outbreak_eligible' => false,
                ]);
        }

        if (! Schema::hasTable('scan_feedback')) {
            Schema::create('scan_feedback', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('farm_image_analysis_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('verdict', 16);
                $table->text('comment')->nullable();
                $table->timestamps();
                $table->index(['farm_image_analysis_id', 'created_at'], 'scan_feedback_scan_created_idx');
            });
        }

        if (! Schema::hasTable('scan_review_history')) {
            Schema::create('scan_review_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('farm_image_analysis_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('from_state', 24);
                $table->string('to_state', 24);
                $table->foreignId('effective_crop_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete();
                $table->foreignId('effective_disease_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete();
                $table->text('reason')->nullable();
                $table->timestamps();
                $table->index(['farm_image_analysis_id', 'created_at'], 'scan_review_scan_created_idx');
            });
        }

        if (! Schema::hasTable('evaluation_datasets')) {
            Schema::create('evaluation_datasets', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('version');
                $table->text('source');
                $table->text('license');
                $table->string('checksum', 64)->unique();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
                $table->unique(['name', 'version'], 'evaluation_dataset_version_unique');
            });
        }

        if (! Schema::hasTable('evaluation_dataset_items')) {
            Schema::create('evaluation_dataset_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('evaluation_dataset_id')->constrained()->cascadeOnDelete();
                $table->string('external_id');
                $table->string('image_path');
                $table->string('image_checksum', 64);
                $table->foreignId('crop_label_id')->constrained('canonical_labels')->restrictOnDelete();
                $table->foreignId('disease_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete();
                $table->text('ground_truth_provenance');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['evaluation_dataset_id', 'external_id'], 'eval_item_external_unique');
                $table->index(['evaluation_dataset_id', 'disease_label_id'], 'eval_item_disease_idx');
            });
        }

        if (! Schema::hasTable('evaluation_runs')) {
            Schema::create('evaluation_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('evaluation_dataset_id')->constrained()->restrictOnDelete();
                $table->foreignId('model_version_id')->constrained()->restrictOnDelete();
                $table->foreignId('prompt_version_id')->constrained()->restrictOnDelete();
                $table->foreignId('confidence_policy_id')->constrained()->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->string('status', 20)->default('queued')->index();
                $table->unsignedInteger('sample_count')->default(0);
                $table->json('metrics')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('safe_error_code')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('evaluation_predictions')) {
            Schema::create('evaluation_predictions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('evaluation_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('evaluation_dataset_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('predicted_crop_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete();
                $table->foreignId('predicted_disease_label_id')->nullable()->constrained('canonical_labels')->restrictOnDelete();
                $table->decimal('normalized_confidence', 5, 4)->nullable();
                $table->boolean('abstained')->default(false);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->longText('raw_result');
                $table->string('raw_result_checksum', 64);
                $table->timestamps();
                $table->unique(['evaluation_run_id', 'evaluation_dataset_item_id'], 'eval_prediction_item_unique');
            });
        }

        if (! Schema::hasTable('evaluation_class_metrics')) {
            Schema::create('evaluation_class_metrics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('evaluation_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('canonical_label_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('tp');
                $table->unsignedInteger('fp');
                $table->unsignedInteger('fn');
                $table->unsignedInteger('tn');
                $table->decimal('precision', 10, 8)->nullable();
                $table->decimal('recall', 10, 8)->nullable();
                $table->decimal('f1', 10, 8)->nullable();
                $table->decimal('fpr', 10, 8)->nullable();
                $table->timestamps();
                $table->unique(['evaluation_run_id', 'canonical_label_id'], 'eval_class_metric_unique');
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('subject_type');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('safe_context')->nullable();
                $table->string('request_fingerprint', 64)->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id', 'created_at'], 'audit_subject_created_idx');
            });
        }

        if (! Schema::hasTable('outbreak_events')) {
            Schema::create('outbreak_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('canonical_label_id')->constrained()->restrictOnDelete();
                $table->string('crop_key');
                $table->string('grid_key');
                $table->string('level', 16);
                $table->unsignedInteger('distinct_farmer_count');
                $table->unsignedInteger('eligible_scan_count');
                $table->date('period_start');
                $table->date('period_end');
                $table->timestamps();
                $table->unique(['canonical_label_id', 'crop_key', 'grid_key', 'period_start', 'level'], 'outbreak_events_aggregate_unique');
            });
        }

        if (! Schema::hasTable('system_job_runs')) {
            Schema::create('system_job_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('job_type')->index();
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('status', 20)->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('heartbeat_at')->nullable()->index();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('attempt')->default(1);
                $table->string('safe_error_code')->nullable();
                $table->json('safe_metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('provider_health_snapshots')) {
            Schema::create('provider_health_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->string('provider')->index();
                $table->string('status', 20);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->string('safe_error_code')->nullable();
                $table->timestamp('checked_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_health_snapshots');
        Schema::dropIfExists('system_job_runs');
        Schema::dropIfExists('outbreak_events');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('evaluation_class_metrics');
        Schema::dropIfExists('evaluation_predictions');
        Schema::dropIfExists('evaluation_runs');
        Schema::dropIfExists('evaluation_dataset_items');
        Schema::dropIfExists('evaluation_datasets');
        Schema::dropIfExists('scan_review_history');
        Schema::dropIfExists('scan_feedback');

        if (Schema::hasTable('farm_image_analyses')) {
            $foreignKeys = [
                'model_version_id', 'prompt_version_id', 'confidence_policy_id',
                'predicted_crop_label_id', 'predicted_disease_label_id',
                'effective_crop_label_id', 'effective_disease_label_id',
            ];
            $existingForeignKeys = array_values(array_filter(
                $foreignKeys,
                fn (string $column): bool => Schema::hasColumn('farm_image_analyses', $column),
            ));
            $columns = [
                ...$foreignKeys,
                'processing_state', 'verification_state', 'normalized_confidence',
                'inference_latency_ms', 'raw_result', 'raw_result_checksum',
                'outbreak_eligible', 'processing_started_at', 'processing_completed_at',
                'safe_error_code',
            ];
            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('farm_image_analyses', $column),
            ));

            if ($existingForeignKeys !== [] || $existingColumns !== []) {
                Schema::table('farm_image_analyses', function (Blueprint $table) use ($existingForeignKeys, $existingColumns): void {
                    foreach ($existingForeignKeys as $foreign) {
                        $table->dropForeign([$foreign]);
                    }
                    if ($existingColumns !== []) {
                        $table->dropColumn($existingColumns);
                    }
                });
            }
        }

        Schema::dropIfExists('confidence_policies');
        Schema::dropIfExists('prompt_versions');
        Schema::dropIfExists('model_versions');
        Schema::dropIfExists('canonical_label_aliases');
        Schema::dropIfExists('canonical_labels');

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        }
    }
};
