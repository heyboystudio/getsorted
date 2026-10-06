<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Models\Pro;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function panelApprovedPro(?User $user = null): User
{
    $user ??= User::factory()->pro()->create(['first_name' => 'Thabo']);
    Pro::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

it('wraps customer pages in the Get Sorted shell with a labelled tab bar (spec 021, AC1)', function (string $route): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get(route($route))
        ->assertOk()
        ->assertSee('aria-label="Main"', false)
        ->assertSee(route('account.home'), false)
        ->assertSee(route('properties.index'), false)
        ->assertSee('Log out')
        ->assertSee('<title>', false)
        ->assertSee('· Get Sorted', false);
})->with(['account.home', 'properties.index']);

it('marks the current tab for assistive technology (spec 021, AC1)', function (): void {
    $customer = User::factory()->customer()->create();

    $html = $this->actingAs($customer)->get(route('account.home'))->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1);
    expect($html)->toMatch('/aria-current="page"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<span>Home<\/span>/s');
});

it('does not repeat the old per-page header on the customer home (spec 021, AC1)', function (): void {
    $customer = User::factory()->customer()->create();

    $html = $this->actingAs($customer)->get(route('account.home'))->getContent();

    expect(substr_count($html, 'name="_token"'))->toBe(1);
    expect($html)->not->toContain('>Sortd<');
});

it('gives an approved pro Today, Jobs and Application tabs (spec 021, AC2)', function (): void {
    $pro = panelApprovedPro();

    $this->actingAs($pro)->get(route('pros.welcome'))
        ->assertOk()
        ->assertSee('aria-label="Main"', false)
        ->assertSee('Today')
        ->assertSee(route('pros.jobs'), false)
        ->assertSee(route('pros.status'), false)
        ->assertDontSee('Go to my customer account');
});

it('keeps pros who are not approved yet on the plain welcome flow without tabs (spec 021, AC2)', function (): void {
    $applicant = User::factory()->pro()->create();

    $this->actingAs($applicant)->get(route('pros.welcome'))
        ->assertOk()
        ->assertSee('Start your application')
        ->assertDontSee('aria-label="Main"', false);

    Pro::factory()->create(['user_id' => $applicant->id, 'status' => 'submitted']);

    $this->actingAs($applicant)->get(route('pros.status'))
        ->assertOk()
        ->assertDontSee('aria-label="Main"', false);
});

it('hides the phone tab bar on the quote page that has its own bottom bar but keeps the desktop rail (spec 021, AC2)', function (): void {
    $user = panelApprovedPro();
    $pro = Pro::query()->where('user_id', $user->id)->firstOrFail();
    $navClasses = fn (string $html): string => preg_match('/<nav aria-label="Main" class="([^"]*)"/', $html, $m) === 1 ? $m[1] : '';

    $list = $navClasses($this->actingAs($user)->get(route('pros.jobs'))->getContent());
    expect($list)->toContain('lg:block')->not->toMatch('/(^|\s)hidden(\s|$)/');

    $invite = ServiceJobInvite::factory()->create(['pro_id' => $pro->id]);
    $quote = $navClasses($this->actingAs($user)->get(route('pros.jobs.show', $invite))->getContent());
    expect($quote)->toContain('lg:block')->toMatch('/(^|\s)hidden(\s|$)/');
});

it('offers a panel switch only to someone who is both customer and pro (spec 021, AC3)', function (): void {
    $both = User::factory()->customer()->create();
    $both->assignRole(Role::Pro->value);
    panelApprovedPro($both);

    $this->actingAs($both)->get(route('account.home'))->assertSee('Pro area')->assertSee(route('pros.welcome'), false);
    $this->actingAs($both)->get(route('pros.welcome'))->assertSee('Customer account')->assertSee(route('account.home'), false);

    $customerOnly = User::factory()->customer()->create();
    $this->actingAs($customerOnly)->get(route('account.home'))->assertDontSee('Pro area');

    $proOnly = panelApprovedPro();
    $this->actingAs($proOnly)->get(route('pros.welcome'))->assertDontSee('Customer account');
});

it('keeps every existing signed-in route working (spec 021, AC4)', function (): void {
    foreach (['account.home', 'properties.index', 'properties.create'] as $name) {
        $this->actingAs(User::factory()->customer()->create())->get(route($name))->assertOk();
    }

    $pro = panelApprovedPro();
    foreach (['pros.welcome', 'pros.jobs', 'pros.status'] as $name) {
        $this->actingAs($pro)->get(route($name))->assertOk();
    }
});

it('still sends guests to sign in for panel pages (spec 021, AC4)', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['account.home', 'properties.index', 'pros.jobs', 'pros.welcome']);
