<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BillingOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingOffer>
 */
class BillingOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
            'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
            'billing_discount_ends_at' => now()->addDays((int) config('billing.free_year_days', 365)),
            'sent_at' => null,
        ];
    }

    public function lifetime(): static
    {
        return $this->state(fn (): array => [
            'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
            'billing_discount_ends_at' => null,
        ]);
    }
}
