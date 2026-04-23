<?php

declare(strict_types=1);

use App\Models\AccountUsageAiOperationKey;
use App\Models\AccountUsageCounter;
use App\Models\AccountUsageDailyRollup;
use App\Models\AccountUsageFlag;
use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\SetlistShareAcceptance;
use App\Models\SetlistShareLink;
use App\Models\User;
use App\Models\UserPayoutAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

it('has many owned projects', function () {
    $model = new User;
    $relation = $model->ownedProjects();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('has many project memberships', function () {
    $model = new User;
    $relation = $model->projectMemberships();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ProjectMember::class);
});

it('has many created setlist share links', function () {
    $model = new User;
    $relation = $model->createdSetlistShareLinks();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistShareLink::class);
});

it('has many setlist share acceptances', function () {
    $model = new User;
    $relation = $model->setlistShareAcceptances();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistShareAcceptance::class);
});

it('has many charts', function () {
    $model = new User;
    $relation = $model->charts();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Chart::class);
});

it('has one payout account', function () {
    $model = new User;
    $relation = $model->payoutAccount();

    expect($relation)->toBeInstanceOf(HasOne::class);
    expect($relation->getRelated())->toBeInstanceOf(UserPayoutAccount::class);
});

it('has one account usage counter', function () {
    $model = new User;
    $relation = $model->accountUsageCounter();

    expect($relation)->toBeInstanceOf(HasOne::class);
    expect($relation->getRelated())->toBeInstanceOf(AccountUsageCounter::class);
});

it('has many account usage daily rollups', function () {
    $model = new User;
    $relation = $model->accountUsageDailyRollups();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(AccountUsageDailyRollup::class);
});

it('has many account usage flags', function () {
    $model = new User;
    $relation = $model->accountUsageFlags();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(AccountUsageFlag::class);
});

it('has many account usage ai operation keys', function () {
    $model = new User;
    $relation = $model->accountUsageAiOperationKeys();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(AccountUsageAiOperationKey::class);
});

it('identifies billing trialing status', function () {
    $user = new User;
    $user->billing_status = User::BILLING_STATUS_TRIALING;

    expect($user->isBillingTrialing())->toBeTrue();

    $user->billing_status = User::BILLING_STATUS_ACTIVE;
    expect($user->isBillingTrialing())->toBeFalse();
});

it('identifies billing active status', function () {
    $user = new User;
    $user->billing_status = User::BILLING_STATUS_ACTIVE;

    expect($user->isBillingActive())->toBeTrue();

    $user->billing_status = User::BILLING_STATUS_TRIALING;
    expect($user->isBillingActive())->toBeFalse();
});

it('identifies billing discounted status', function () {
    $user = new User;
    $user->billing_discount_type = User::BILLING_DISCOUNT_LIFETIME;

    expect($user->isBillingDiscounted())->toBeTrue();

    $user->billing_discount_type = null;
    $user->billing_status = User::BILLING_STATUS_DISCOUNTED;
    expect($user->isBillingDiscounted())->toBeTrue();

    $user->billing_status = User::BILLING_STATUS_ACTIVE;
    expect($user->isBillingDiscounted())->toBeFalse();
});

it('returns billing discount label for lifetime', function () {
    $user = new User;
    $user->billing_discount_type = User::BILLING_DISCOUNT_LIFETIME;

    expect($user->billingDiscountLabel())->toBe('Lifetime complimentary access');
});

it('returns billing discount label for free year without end date', function () {
    $user = new User;
    $user->billing_discount_type = User::BILLING_DISCOUNT_FREE_YEAR;
    $user->billing_discount_ends_at = now()->addYear();

    expect($user->billingDiscountLabel())->toContain('Complimentary access through');
});

it('returns billing discount label as complimentary access when end date is null but discount is active', function () {
    $user = new User;
    $user->billing_discount_type = User::BILLING_DISCOUNT_LIFETIME;
    $user->billing_discount_ends_at = null;

    // Lifetime discount is active regardless of end date
    expect($user->billingDiscountLabel())->toBe('Lifetime complimentary access');
});

it('returns null billing discount label when no active discount', function () {
    $user = new User;
    $user->billing_discount_type = null;
    $user->billing_status = User::BILLING_STATUS_ACTIVE;

    expect($user->billingDiscountLabel())->toBeNull();
});

it('checks accessible projects', function () {
    $user = User::factory()->create();
    $ownedProject = Project::factory()->create(['owner_user_id' => $user->id]);
    $memberProject = Project::factory()->create();
    $memberProject->addMember($user);

    $accessible = $user->accessibleProjects();

    expect($accessible)->toHaveCount(2);
    expect($accessible->pluck('id')->toArray())->toContain($ownedProject->id, $memberProject->id);
});

it('checks if user has access to a project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();

    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project->addMember($member);

    expect($owner->hasAccessToProject($project))->toBeTrue();
    expect($member->hasAccessToProject($project))->toBeTrue();
    expect($outsider->hasAccessToProject($project))->toBeFalse();
});

it('resolves billing plan', function () {
    $user = new User;
    $user->billing_plan = User::BILLING_PLAN_PRO_MONTHLY;

    expect($user->resolvedBillingPlan())->toBe(User::BILLING_PLAN_PRO_MONTHLY);
});

it('resolves default billing plan when plan is null', function () {
    $user = new User;
    $user->billing_plan = null;

    expect($user->resolvedBillingPlan())->toBe(User::BILLING_PLAN_PRO_YEARLY);
});

it('identifies pro billing plan', function () {
    $user = new User;

    $user->billing_plan = User::BILLING_PLAN_PRO_MONTHLY;
    expect($user->hasProBillingPlan())->toBeTrue();

    $user->billing_plan = User::BILLING_PLAN_PRO_YEARLY;
    expect($user->hasProBillingPlan())->toBeTrue();

    $user->billing_plan = User::BILLING_PLAN_VETERAN_MONTHLY;
    expect($user->hasProBillingPlan())->toBeTrue();

    $user->billing_plan = User::BILLING_PLAN_FREE;
    expect($user->hasProBillingPlan())->toBeFalse();
});

it('checks isBillingSetupComplete always returns true', function () {
    $user = new User;
    $user->billing_plan = null;
    expect($user->isBillingSetupComplete())->toBeTrue();

    $user->billing_plan = User::BILLING_PLAN_PRO_MONTHLY;
    $user->billing_discount_type = User::BILLING_DISCOUNT_LIFETIME;
    expect($user->isBillingSetupComplete())->toBeTrue();

    $user->billing_plan = User::BILLING_PLAN_PRO_MONTHLY;
    $user->billing_status = User::BILLING_STATUS_ACTIVE;
    expect($user->isBillingSetupComplete())->toBeTrue();
});
