<?php

namespace App\Http\Middleware;

use App\Services\ThemeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the hotel's public theme for ordinary full-mode web routes and
 * rejects unknown or malformed definitions before the route's action. Individual
 * views are checked only when requested; deployment checks cover completeness.
 */
class SetThemeMiddleware
{
    /**
     * Core and integration routes that must keep working without a usable public
     * theme. They are matched on the route itself, never on request headers.
     * Public Livewire components check their theme in UsesPublicTheme instead.
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTES = [
        'installation.*',
        'logout',
        'paypal.successful-transaction',
        'paypal.cancelled-transaction',
        '*livewire.update',
        'livewire.upload-file',
        'livewire.preview-file',
        'sanctum.csrf-cookie',
        'filament.*',
    ];

    public function __construct(private ThemeService $themes) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('atom.mode') !== 'headless' && ! $request->routeIs(...self::EXEMPT_ROUTES)) {
            $this->themes->selected();
        }

        return $next($request);
    }
}
