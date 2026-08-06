<?php

namespace Database\Seeders;

use App\Models\CanonicalLabel;
use App\Models\ConfidencePolicy;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiagnosisDomainSeeder extends Seeder
{
    public function run(): void
    {
        $crops = [];
        foreach (config('diagnosis.labels.crops') as $slug => $names) {
            $label = CanonicalLabel::firstOrCreate(
                ['slug' => $slug],
                ['kind' => 'crop', 'name' => $names[0], 'is_diseased' => false, 'active' => true],
            );
            $crops[$slug] = $label;
            foreach (array_unique([$slug, ...$names]) as $alias) {
                $label->aliases()->firstOrCreate(['normalized_alias' => $this->normalize($alias)]);
            }
        }

        $cropByDisease = [
            'maize-leaf-blight' => 'maize', 'maize-rust' => 'maize',
            'cassava-mosaic-disease' => 'cassava', 'cassava-brown-streak-disease' => 'cassava',
            'rice-blast' => 'rice', 'yam-anthracnose' => 'yam',
            'tomato-late-blight' => 'tomato', 'tomato-early-blight' => 'tomato',
            'pepper-leaf-spot' => 'pepper', 'cowpea-mosaic-virus' => 'cowpea',
            'groundnut-rosette' => 'groundnut', 'black-sigatoka' => 'plantain',
        ];
        foreach (config('diagnosis.labels.diseases') as $slug => $names) {
            $label = CanonicalLabel::firstOrCreate(
                ['slug' => $slug],
                [
                    'kind' => $slug === 'healthy' ? 'condition' : 'disease',
                    'name' => $names[0],
                    'crop_label_id' => isset($cropByDisease[$slug]) ? $crops[$cropByDisease[$slug]]->id : null,
                    'is_diseased' => $slug !== 'healthy',
                    'active' => true,
                ],
            );
            foreach (array_unique([$slug, ...$names]) as $alias) {
                $label->aliases()->firstOrCreate(['normalized_alias' => $this->normalize($alias)]);
            }
        }

        // Model/prompt/policy rows are immutable after creation — never Eloquent-update them.
        // Create missing versions, then flip `active` via query builder.
        $model = config('diagnosis.model');
        $modelVersion = ModelVersion::query()->firstOrCreate(
            [
                'provider' => $model['provider'],
                'model_identifier' => $model['identifier'],
                'version' => $model['version'],
            ],
            [
                'parameters' => $model['parameters'],
                'checksum' => hash('sha256', json_encode($model)),
                'active' => false,
            ],
        );
        DB::table('model_versions')->update(['active' => false, 'updated_at' => now()]);
        DB::table('model_versions')->where('id', $modelVersion->id)->update(['active' => true, 'updated_at' => now()]);

        $prompt = config('diagnosis.prompt');
        $promptVersion = PromptVersion::query()->firstOrCreate(
            ['name' => $prompt['name'], 'version' => $prompt['version']],
            [
                'system_prompt' => $prompt['system'],
                'user_prompt' => $prompt['user'],
                'checksum' => hash('sha256', $prompt['system']."\n".$prompt['user']),
                'active' => false,
            ],
        );
        DB::table('prompt_versions')->update(['active' => false, 'updated_at' => now()]);
        DB::table('prompt_versions')->where('id', $promptVersion->id)->update(['active' => true, 'updated_at' => now()]);

        $policy = config('diagnosis.confidence_policy');
        $policyVersion = ConfidencePolicy::query()->firstOrCreate(
            ['name' => $policy['name'], 'version' => $policy['version']],
            [
                'retake_below' => $policy['retake_below'],
                'review_below' => $policy['review_below'],
                'require_canonical' => $policy['require_canonical'],
                'checksum' => hash('sha256', json_encode($policy)),
                'active' => false,
            ],
        );
        DB::table('confidence_policies')->update(['active' => false, 'updated_at' => now()]);
        DB::table('confidence_policies')->where('id', $policyVersion->id)->update(['active' => true, 'updated_at' => now()]);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($value)) ?? '');
    }
}
