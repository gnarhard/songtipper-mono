<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\BillingPlanCatalog;

beforeEach(function () {
    $this->catalog = new BillingPlanCatalog;
});

it('returns label for a valid billing plan', function () {
    expect($this->catalog->label(User::BILLING_PLAN_PRO_MONTHLY))->toBe('Pro Monthly');
    expect($this->catalog->label(User::BILLING_PLAN_PRO_YEARLY))->toBe('Pro Yearly');
});

it('returns Not selected for null billing plan label', function () {
    expect($this->catalog->label(null))->toBe('Not selected');
});

it('returns Not selected for unknown billing plan label', function () {
    expect($this->catalog->label('nonexistent_plan'))->toBe('Not selected');
});

it('returns price label for a valid billing plan', function () {
    expect($this->catalog->priceLabel(User::BILLING_PLAN_PRO_MONTHLY))->toBe('$19.99/mo');
    expect($this->catalog->priceLabel(User::BILLING_PLAN_PRO_YEARLY))->toBe('$199.99/year');
});

it('returns null price label for null billing plan', function () {
    expect($this->catalog->priceLabel(null))->toBeNull();
});

it('returns null price label for unknown billing plan', function () {
    expect($this->catalog->priceLabel('nonexistent_plan'))->toBeNull();
});

it('returns tier label for a valid billing plan', function () {
    expect($this->catalog->tierLabel(User::BILLING_PLAN_PRO_MONTHLY))->toBe('Pro');
    expect($this->catalog->tierLabel(User::BILLING_PLAN_PRO_YEARLY))->toBe('Pro');
});

it('returns Plan for null billing plan tier label', function () {
    expect($this->catalog->tierLabel(null))->toBe('Plan');
});

it('returns Plan for unknown billing plan tier label', function () {
    expect($this->catalog->tierLabel('nonexistent_plan'))->toBe('Plan');
});

it('resolves price id when config key is set', function () {
    config()->set('services.stripe.pro_monthly_price', 'price_abc123');

    expect($this->catalog->resolvePriceId(User::BILLING_PLAN_PRO_MONTHLY))->toBe('price_abc123');
});

it('returns null when config value is empty string', function () {
    config()->set('services.stripe.pro_monthly_price', '');

    expect($this->catalog->resolvePriceId(User::BILLING_PLAN_PRO_MONTHLY))->toBeNull();
});

it('returns null when config value is whitespace only', function () {
    config()->set('services.stripe.pro_monthly_price', '   ');

    expect($this->catalog->resolvePriceId(User::BILLING_PLAN_PRO_MONTHLY))->toBeNull();
});

it('returns null when billing plan has no config key', function () {
    expect($this->catalog->resolvePriceId('nonexistent_plan'))->toBeNull();
});
