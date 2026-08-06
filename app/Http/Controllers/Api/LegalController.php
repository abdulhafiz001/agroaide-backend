<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalController extends Controller
{
    public function metadata(): JsonResponse
    {
        return response()->json([
            'project' => config('legal.project'),
            'terms' => [
                'version' => config('legal.terms.version'),
                'effectiveDate' => config('legal.terms.effective_date'),
                'url' => route('legal.terms'),
            ],
            'privacy' => [
                'version' => config('legal.privacy.version'),
                'effectiveDate' => config('legal.privacy.effective_date'),
                'url' => route('legal.privacy'),
            ],
            'researchConsent' => config('legal.research_consent'),
        ]);
    }

    public function consent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'termsVersion' => ['required', 'in:'.config('legal.terms.version')],
            'privacyVersion' => ['required', 'in:'.config('legal.privacy.version')],
            'researchConsent' => ['sometimes', 'boolean'],
        ]);

        UserConsent::create([
            'user_id' => $request->user()->id,
            'terms_version' => $validated['termsVersion'],
            'privacy_version' => $validated['privacyVersion'],
            'research_version' => config('legal.research_consent.version'),
            'research_consent' => $validated['researchConsent'] ?? false,
            'consented_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return response()->json(['message' => 'Consent recorded.', 'consentRequired' => false]);
    }
}
