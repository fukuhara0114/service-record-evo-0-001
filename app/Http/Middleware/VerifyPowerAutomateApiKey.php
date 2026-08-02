<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPowerAutomateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.power_automate.api_key', '');
        if ($configured === '') {
            return response()->json([
                'message' => 'Power Automate API key is not configured.',
            ], 503);
        }

        $provided = (string) (
            $request->header('X-API-Key')
            ?: $request->bearerToken()
            ?: ''
        );

        if ($provided === '' || !hash_equals($configured, $provided)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
