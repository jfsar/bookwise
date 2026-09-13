<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $currentSessionId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($currentSessionId) {
                return [
                    'id' => base64_encode($session->id),
                    'device' => $this->describeDevice($session->user_agent),
                    'browser' => $this->describeBrowser($session->user_agent),
                    'ip_address' => $session->ip_address,
                    'is_current' => $session->id === $currentSessionId,
                    'last_active_at' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            })
            ->values();

        return Inertia::render('Settings/Sessions', [
            'sessions' => $sessions,
        ]);
    }

    public function destroy(Request $request, string $session): RedirectResponse
    {
        $sessionId = base64_decode($session);

        if (! $sessionId) {
            return back()->with('error', 'Invalid session identifier.');
        }

        if ($sessionId === $request->session()->getId()) {
            return back()->with('error', 'You cannot sign out your current session here.');
        }

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with(
            $deleted ? 'success' : 'error',
            $deleted ? 'Session signed out.' : 'That session no longer exists.'
        );
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $currentSessionId = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return back()->with('success', 'All other sessions have been signed out.');
    }

    private function describeDevice(?string $userAgent): string
    {
        $userAgent = $userAgent ?? '';

        $patterns = [
            '/iPhone/i' => 'iPhone',
            '/iPad/i' => 'iPad',
            '/Android/i' => 'Android',
            '/Windows/i' => 'Windows',
            '/Macintosh|Mac OS X/i' => 'Mac',
            '/Linux/i' => 'Linux',
        ];

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $userAgent)) {
                return $label;
            }
        }

        return 'Unknown device';
    }

    private function describeBrowser(?string $userAgent): string
    {
        $userAgent = $userAgent ?? '';

        $patterns = [
            '/Edg\//i' => 'Edge',
            '/Chrome\//i' => 'Chrome',
            '/Firefox\//i' => 'Firefox',
            '/Safari\//i' => 'Safari',
            '/OPR\//i' => 'Opera',
        ];

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $userAgent)) {
                return $label;
            }
        }

        return 'Unknown';
    }
}
