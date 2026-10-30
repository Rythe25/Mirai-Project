<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StorefrontUrl;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    /** Step 1: remember where the customer came from, then send them to Google. */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('signin.return_to', StorefrontUrl::to(
            $this->origin($request->headers->get('Referer')),
            $this->safePath($request->query('return')),
        ));

        return Socialite::driver('google')->redirect();
    }

    /** Step 2: Google sends the customer back; sign them in (first time = new account) and return to the storefront. */
    public function callback(Request $request): RedirectResponse
    {
        $returnTo = $request->session()->pull('signin.return_to', StorefrontUrl::to(null, '/'));

        if ($request->has('error')) { // "Cancel" on Google's screen
            return redirect()->away($this->withStatus($returnTo, 'cancelled'));
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException|GuzzleException $e) {
            Log::warning('Google sign-in failed', ['error' => $e->getMessage()]);

            return redirect()->away($this->withStatus($returnTo, 'failed'));
        }

        if (! ($google->getRaw()['email_verified'] ?? false)) {
            return redirect()->away($this->withStatus($returnTo, 'unverified'));
        }

        Auth::login($this->findOrCreateUser($google), remember: true);
        $request->session()->regenerate();

        return redirect()->away($returnTo);
    }

    private function findOrCreateUser(SocialiteUser $google): User
    {
        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $google->getEmail())->first() // same person: Google verified the email
            ?? new User(['name' => $google->getName() ?: $google->getEmail(), 'email' => $google->getEmail()]);

        // Not fillable on purpose; the backoffice role is never touched here.
        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar_url' => $google->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    /** A path on the storefront only; "//evil.com", "https://…" and "\…" fall back to "/". */
    private function safePath(mixed $path): string
    {
        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            ? $path
            : '/';
    }

    private function origin(?string $url): ?string
    {
        $parts = parse_url((string) $url);

        return isset($parts['scheme'], $parts['host'])
            ? "{$parts['scheme']}://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : '')
            : null;
    }

    private function withStatus(string $url, string $status): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?')."signin={$status}";
    }
}
