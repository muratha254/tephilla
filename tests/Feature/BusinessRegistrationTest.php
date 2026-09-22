<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\SubscriptionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BusinessRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_screen_is_available_from_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Create account');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Enter your business details');
    }

    public function test_signup_wizard_submits_pending_request_and_does_not_login(): void
    {
        $plan = $this->basicPlan();

        $this->post(route('register.business'), [
            'business_name' => 'Sunrise Mart',
            'owner_name' => 'Jane Owner',
            'phone' => '0700111222',
            'address' => 'Nairobi',
        ])->assertRedirect(route('register.account'));

        $this->post(route('register.account.store'), [
            'email' => 'jane@sunrise.test',
            'username' => 'sunrisejane',
            'password' => '1234',
            'password_confirmation' => '1234',
        ])->assertRedirect(route('register.plans'));

        $this->post(route('register.plans.store'), [
            'plan_id' => $plan->id,
        ])->assertRedirect(route('register.review'));

        $this->get(route('register.review'))
            ->assertOk()
            ->assertSee('Sunrise Mart')
            ->assertSee('Basic');

        $this->post(route('register.submit'))
            ->assertRedirect(route('register.submitted'));

        $this->assertGuest();

        $user = User::query()->where('email', 'jane@sunrise.test')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->isSystemOwner());

        $company = Company::query()->find($user->company_id);
        $this->assertSame('Sunrise Mart', $company->name);

        $subscription = Subscription::query()->where('company_id', $company->id)->first();
        $this->assertSame(SubscriptionCatalog::STATUS_PENDING_APPROVAL, $subscription->status);
        $this->assertFalse($subscription->allowsAccess());
        $this->assertSame($plan->id, (int) $subscription->subscription_plan_id);
    }

    public function test_pending_client_cannot_open_dashboard(): void
    {
        $this->completePendingSignup();
        $user = User::query()->where('email', 'jane@sunrise.test')->first();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.blocked', [
                'reason' => SubscriptionCatalog::STATUS_PENDING_APPROVAL,
            ]));

        $this->actingAs($user)
            ->get(route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_PENDING_APPROVAL]))
            ->assertOk()
            ->assertSee('Pending approval');
    }

    public function test_owner_can_approve_and_then_client_reaches_dashboard(): void
    {
        $this->completePendingSignup();
        $company = Company::query()->where('name', 'Sunrise Mart')->first();
        $owner = $this->makeSystemOwner();

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('Pending Approval')
            ->assertSee('Sunrise Mart');

        $this->actingAs($owner)
            ->post(route('owner.businesses.approve', $company), ['notes' => 'Paid'])
            ->assertRedirect();

        $subscription = $company->fresh()->subscription;
        $this->assertSame(SubscriptionCatalog::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->allowsAccess());

        $client = User::query()->where('email', 'jane@sunrise.test')->first();
        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_owner_can_reject_pending_request(): void
    {
        $this->completePendingSignup();
        $company = Company::query()->where('name', 'Sunrise Mart')->first();
        $owner = $this->makeSystemOwner();

        $this->actingAs($owner)
            ->post(route('owner.businesses.reject', $company), ['notes' => 'Incomplete'])
            ->assertRedirect();

        $this->assertSame(
            SubscriptionCatalog::STATUS_REJECTED,
            $company->fresh()->subscription->status
        );

        $client = User::query()->where('email', 'jane@sunrise.test')->first();
        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.blocked', [
                'reason' => SubscriptionCatalog::STATUS_REJECTED,
            ]));
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->completePendingSignup();

        $this->post(route('register.business'), [
            'business_name' => 'Second Shop',
            'owner_name' => 'Ann Two',
        ])->assertRedirect(route('register.account'));

        $this->from(route('register.account'))
            ->post(route('register.account.store'), [
                'email' => 'jane@sunrise.test',
                'username' => 'anntwo',
                'password' => '1234',
                'password_confirmation' => '1234',
            ])
            ->assertRedirect(route('register.account'))
            ->assertSessionHasErrors('email');
    }

    protected function completePendingSignup(): void
    {
        $plan = $this->basicPlan();

        $this->post(route('register.business'), [
            'business_name' => 'Sunrise Mart',
            'owner_name' => 'Jane Owner',
            'phone' => '0700111222',
            'address' => 'Nairobi',
        ]);
        $this->post(route('register.account.store'), [
            'email' => 'jane@sunrise.test',
            'username' => 'sunrisejane',
            'password' => '1234',
            'password_confirmation' => '1234',
        ]);
        $this->post(route('register.plans.store'), ['plan_id' => $plan->id]);
        $this->post(route('register.submit'));
        $this->assertGuest();
    }

    protected function basicPlan(): SubscriptionPlan
    {
        app(\App\Services\SystemOwnerBootstrapper::class)->ensurePlans();

        return SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
    }

    protected function makeSystemOwner(): User
    {
        $role = Role::query()->withoutGlobalScope('company')->firstOrCreate(
            ['name' => PermissionCatalog::SYSTEM_OWNER, 'company_id' => null],
            [
                'display_name' => 'System Owner',
                'description' => 'Platform',
                'is_system' => true,
            ]
        );

        return User::query()->create([
            'company_id' => null,
            'branch_id' => null,
            'role_id' => $role->id,
            'name' => 'Owner',
            'email' => 'owner-reg@test.local',
            'username' => 'ownerreg',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);
    }
}
