<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCurrentConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $current = $user->consents()
            ->where('terms_version', config('legal.terms.version'))
            ->where('privacy_version', config('legal.privacy.version'))
            ->exists();

        if (! $current) {
            return response()->json([
                'message' => 'Current terms and privacy consent is required.',
                'consentRequired' => true,
                'legal' => [
                    'termsVersion' => config('legal.terms.version'),
                    'privacyVersion' => config('legal.privacy.version'),
                    'researchVersion' => config('legal.research_consent.version'),
                ],
            ], 428);
        }

        return $next($request);
    }
}
