<?php

namespace App\Services;

use App\Models\CanonicalLabel;
use App\Models\CanonicalLabelAlias;

class CanonicalLabelResolver
{
    public function resolve(?string $value, ?string $kind = null): ?CanonicalLabel
    {
        $normalized = trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string) $value)) ?? '');
        if ($normalized === '') {
            return null;
        }

        return CanonicalLabelAlias::query()
            ->where('normalized_alias', $normalized)
            ->whereHas('label', fn ($query) => $query
                ->where('active', true)
                ->when($kind, fn ($q) => $q->where('kind', $kind)))
            ->with('label')
            ->first()
            ?->label;
    }
}
