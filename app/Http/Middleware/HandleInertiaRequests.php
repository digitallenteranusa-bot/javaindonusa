<?php

namespace App\Http\Middleware;

use App\Models\IspInfo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone,
                    'role' => $request->user()->role,
                    'profile_photo' => $request->user()->profile_photo,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'isp' => fn () => $this->getIspInfo(),
        ]);
    }

    /**
     * Get ISP information for branding
     */
    protected function getIspInfo(): array
    {
        try {
            $isp = IspInfo::getCached();
            if ($isp) {
                // Generate initials from company name (e.g., "Java Indonusa" -> "JI")
                $words = explode(' ', $isp->company_name ?? 'ISP');
                $initials = '';
                foreach (array_slice($words, 0, 2) as $word) {
                    $initials .= strtoupper(substr($word, 0, 1));
                }

                return [
                    'name' => $isp->company_name,
                    'tagline' => $isp->tagline,
                    'logo' => $isp->logo_url,
                    'favicon' => $isp->favicon_url,
                    'initials' => $initials,
                    'phone' => $isp->phone_primary,
                    'email' => $isp->email,
                ];
            }

            return [
                'name' => config('app.name'),
                'tagline' => 'ISP Billing System',
                'logo' => null,
                'favicon' => null,
                'initials' => 'ISP',
                'phone' => null,
                'email' => null,
            ];
        } catch (\Exception $e) {
            return [
                'name' => config('app.name'),
                'tagline' => 'ISP Billing System',
                'logo' => null,
                'favicon' => null,
                'initials' => 'ISP',
                'phone' => null,
                'email' => null,
            ];
        }
    }
}
