<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP = 'http://localhost:3000';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-secret',
            'app.storefront_url' => 'https://shop.mirai.test',
            'cors.allowed_origins' => [self::SHOP, 'https://mirai.kandycode.com'],
        ]);
    }

    /** Google answers the callback with this account (no network). */
    private function googleReturns(array $profile = [], bool $verified = true): void
    {
        $user = (new GoogleUser)->setRaw(['email_verified' => $verified])->map(array_merge([
            'id' => 'google-123',
            'name' => 'Sokha Chan',
            'email' => 'sokha@example.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/test',
        ], $profile));

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function returnFromGoogle(string $query = 'code=test&state=test')
    {
        return $this->withSession(['signin.return_to' => self::SHOP.'/shop?product=2'])
            ->get("/auth/google/callback?{$query}");
    }

    public function test_redirect_remembers_the_storefront_page_and_goes_to_google(): void
    {
        $response = $this->withHeader('Referer', self::SHOP.'/shop')
            ->get('/auth/google/redirect?return='.urlencode('/shop?product=2'));

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $response->headers->get('Location'));
        // Callback on the host the request came in on (localhost:8000 or mirai-api.kandycode.com).
        $this->assertStringContainsString(urlencode(url('/auth/google/callback')), $response->headers->get('Location'));
        $response->assertSessionHas('signin.return_to', self::SHOP.'/shop?product=2');
    }

    public function test_return_paths_to_other_sites_fall_back_to_the_storefront_home(): void
    {
        foreach (['//evil.com', 'https://evil.com', '/\\evil.com', ''] as $return) {
            $this->withHeader('Referer', self::SHOP.'/')
                ->get('/auth/google/redirect?return='.urlencode($return))
                ->assertSessionHas('signin.return_to', self::SHOP.'/');
        }
    }

    public function test_an_unknown_referer_returns_to_the_configured_storefront(): void
    {
        $this->withHeader('Referer', 'https://evil.com/page')
            ->get('/auth/google/redirect?return=/shop')
            ->assertSessionHas('signin.return_to', 'https://shop.mirai.test/shop');
    }

    public function test_first_sign_in_creates_the_account_and_returns_to_the_shop(): void
    {
        $this->googleReturns();

        $this->returnFromGoogle()->assertRedirect(self::SHOP.'/shop?product=2');

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Sokha Chan', $user->name);
        $this->assertNull($user->password);
        $this->assertNull($user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_signing_in_again_reuses_the_account(): void
    {
        $this->googleReturns();
        $this->returnFromGoogle();
        auth()->logout();

        $this->returnFromGoogle()->assertRedirect(self::SHOP.'/shop?product=2');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_existing_staff_email_is_linked_and_keeps_its_role(): void
    {
        $staff = User::factory()->create(['email' => 'sokha@example.com']);
        $staff->forceFill(['role' => UserRole::Employee])->save();
        $this->googleReturns();

        $this->returnFromGoogle();

        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($staff);
        $this->assertSame('google-123', $staff->fresh()->google_id);
        $this->assertSame(UserRole::Employee, $staff->fresh()->role);
    }

    public function test_an_unverified_google_email_is_refused(): void
    {
        $this->googleReturns(verified: false);

        $this->returnFromGoogle()->assertRedirect(self::SHOP.'/shop?product=2&signin=unverified');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_cancelling_on_google_returns_with_a_notice(): void
    {
        $this->returnFromGoogle('error=access_denied&state=test')
            ->assertRedirect(self::SHOP.'/shop?product=2&signin=cancelled');

        $this->assertGuest();
    }

    public function test_a_bad_state_returns_with_a_notice(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->returnFromGoogle()->assertRedirect(self::SHOP.'/shop?product=2&signin=failed');

        $this->assertGuest();
    }

    public function test_me_shows_the_signed_in_customer_without_private_fields(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();

        $user = User::factory()->create(['email' => 'sokha@example.com']);
        $user->forceFill(['role' => UserRole::Admin, 'google_id' => 'google-123'])->save();

        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'sokha@example.com')
            ->assertJsonMissingPath('data.role')
            ->assertJsonMissingPath('data.google_id');
    }

    public function test_the_storefront_gets_a_csrf_token(): void
    {
        $this->getJson('/api/csrf', ['Origin' => self::SHOP])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_sign_out_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/logout', [], ['Origin' => self::SHOP])
            ->assertNoContent();

        $this->assertGuest('web');
    }
}
