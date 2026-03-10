<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run()
    {
        $defaults = SubscriptionPlan::getDefaultPlans();

        foreach ($defaults as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['plan_key' => $planData['plan_key']],
                $planData
            );
        }

        echo "Subscription plans seeded: " . SubscriptionPlan::count() . " plans\n";
    }
}
