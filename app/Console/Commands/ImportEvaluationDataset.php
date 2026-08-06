<?php

namespace App\Console\Commands;

use App\Models\CanonicalLabel;
use App\Models\EvaluationDataset;
use App\Models\EvaluationDatasetItem;
use App\Models\User;
use App\Services\CanonicalLabelResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportEvaluationDataset extends Command
{
    protected $signature = 'agroaide:evaluation:import
        {csv : CSV with external_id,image,crop,disease,provenance}
        {images : Private image directory}
        {--name=} {--version=} {--source=} {--license=} {--staff=}';

    protected $description = 'Import and lock a checksum-addressed private evaluation dataset';

    public function handle(CanonicalLabelResolver $resolver): int
    {
        foreach (['name', 'version', 'source', 'license', 'staff'] as $option) {
            if (! filled($this->option($option))) {
                $this->error("--{$option} is required.");

                return self::FAILURE;
            }
        }
        $staff = User::where('email', $this->option('staff'))->first();
        if (! $staff?->isAdmin()) {
            $this->error('The importing account must be an admin.');

            return self::FAILURE;
        }
        $csv = realpath($this->argument('csv'));
        $directory = realpath($this->argument('images'));
        if (! $csv || ! is_file($csv) || ! $directory || ! is_dir($directory)) {
            $this->error('CSV or image directory is invalid.');

            return self::FAILURE;
        }

        try {
            $count = DB::transaction(function () use ($resolver, $staff, $csv, $directory) {
                $dataset = EvaluationDataset::create([
                    'name' => $this->option('name'), 'version' => $this->option('version'),
                    'source' => $this->option('source'), 'license' => $this->option('license'),
                    'checksum' => hash_file('sha256', $csv),
                    'created_by' => $staff->id,
                ]);
                $handle = fopen($csv, 'rb');
                $headers = fgetcsv($handle);
                $required = ['external_id', 'image', 'crop', 'disease', 'provenance'];
                if (! is_array($headers) || array_diff($required, $headers)) {
                    throw new RuntimeException('CSV headers must include '.implode(', ', $required).'.');
                }
                $count = 0;
                while (($values = fgetcsv($handle)) !== false) {
                    if (count($values) !== count($headers)) {
                        throw new RuntimeException('CSV row has the wrong number of columns.');
                    }
                    $row = array_combine($headers, $values);
                    $sourcePath = realpath($directory.DIRECTORY_SEPARATOR.$row['image']);
                    if (! $sourcePath || ! str_starts_with($sourcePath, $directory.DIRECTORY_SEPARATOR) || ! is_file($sourcePath)) {
                        throw new RuntimeException("Image {$row['image']} is missing or outside the private directory.");
                    }
                    $mime = mime_content_type($sourcePath);
                    if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                        throw new RuntimeException("Image {$row['image']} has an unsupported type.");
                    }
                    $crop = $resolver->resolve($row['crop'], 'crop');
                    $disease = filled($row['disease']) ? $resolver->resolve($row['disease']) : CanonicalLabel::where('slug', 'healthy')->first();
                    if (! $crop || ! $disease || ! filled($row['provenance'])) {
                        throw new RuntimeException("Row {$row['external_id']} has unresolved labels or provenance.");
                    }
                    $checksum = hash_file('sha256', $sourcePath);
                    $extension = match ($mime) {
                        'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
                    };
                    $stored = "evaluation/{$dataset->id}/{$checksum}.{$extension}";
                    Storage::disk('local')->put($stored, file_get_contents($sourcePath));
                    EvaluationDatasetItem::create([
                        'evaluation_dataset_id' => $dataset->id,
                        'external_id' => $row['external_id'], 'image_path' => $stored,
                        'image_checksum' => $checksum, 'crop_label_id' => $crop->id,
                        'disease_label_id' => $disease->id,
                        'ground_truth_provenance' => $row['provenance'],
                        'metadata' => ['original_filename' => basename($sourcePath)],
                    ]);
                    $count++;
                }
                fclose($handle);
                if ($count === 0) {
                    throw new RuntimeException('Dataset contains no items.');
                }
                $dataset->update(['locked_at' => now()]);

                return $count;
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Imported and locked {$count} items.");

        return self::SUCCESS;
    }
}
