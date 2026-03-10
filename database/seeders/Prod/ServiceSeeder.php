<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceVariant;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Service Categories
        $categories = [
            [
                'name' => 'Massage & Body Treatments',
                'description' => 'Traditonal and modern massage treatments',
                'color' => '#8B5CF6', // Purple
                'is_global' => true
            ],
            [
                'name' => 'Facial Treatments',
                'description' => 'Skin care and facial revitalization',
                'color' => '#EC4899', // Pink
                'is_global' => true
            ],
            [
                'name' => 'Hair Care',
                'description' => 'Hair treatments and styling',
                'color' => '#F59E0B', // Amber
                'is_global' => true
            ],
            [
                'name' => 'Pedi & Mani',
                'description' => 'Nail care and treatments',
                'color' => '#10B981', // Emerald
                'is_global' => true
            ]
        ];

        foreach ($categories as $cat) {
            ServiceCategory::updateOrCreate(
                ['name' => $cat['name']],
                $cat
            );
        }

        // 2. Define Services
        $services = [
            // Massage
            [
                'category_name' => 'Massage & Body Treatments',
                'name' => 'Traditional Javanese Massage',
                'code' => 'SVC-MSG-001',
                'duration' => 60,
                'price' => 150000,
                'description' => 'Authentic Javanese massage using traditional techniques.',
                'requires_room' => true,
                'variants' => [
                    ['name' => '60 Minutes', 'duration' => 60, 'price' => 150000],
                    ['name' => '90 Minutes', 'duration' => 90, 'price' => 210000],
                    ['name' => '120 Minutes', 'duration' => 120, 'price' => 275000],
                ]
            ],
            [
                'category_name' => 'Massage & Body Treatments',
                'name' => 'Aromatherapy Reflexology',
                'code' => 'SVC-MSG-002',
                'duration' => 90,
                'price' => 220000,
                'description' => 'Relaxing massage with essential oils.',
                'requires_room' => true,
                'variants' => [
                    ['name' => 'Full Body 90m', 'duration' => 90, 'price' => 220000],
                    ['name' => 'Premium Oil 90m', 'duration' => 90, 'price' => 260000],
                ]
            ],
            [
                'category_name' => 'Massage & Body Treatments',
                'name' => 'Organic Coffee Scrub',
                'code' => 'SVC-MSG-003',
                'duration' => 45,
                'price' => 180000,
                'description' => 'Body scrub using organic coffee grounds.',
                'requires_room' => true,
            ],

            // Facial
            [
                'category_name' => 'Facial Treatments',
                'name' => 'Brightening Facial',
                'code' => 'SVC-FCL-001',
                'duration' => 60,
                'price' => 250000,
                'description' => 'Treatment to brighten and glow your skin.',
                'requires_room' => true,
                'variants' => [
                    ['name' => 'Express 30m', 'duration' => 30, 'price' => 150000],
                    ['name' => 'Signature 60m', 'duration' => 60, 'price' => 250000],
                ]
            ],
            [
                'category_name' => 'Facial Treatments',
                'name' => 'Anti-Aging Therapy',
                'code' => 'SVC-FCL-002',
                'duration' => 90,
                'price' => 450000,
                'description' => 'Advanced treatment to reduce wrinkles.',
                'requires_room' => true,
            ],

            // Hair
            [
                'category_name' => 'Hair Care',
                'name' => 'Hair Spa & Vitamin',
                'code' => 'SVC-HAR-001',
                'duration' => 60,
                'price' => 120000,
                'description' => 'Deep conditioning for healthy hair.',
                'requires_room' => false,
            ],
            [
                'category_name' => 'Hair Care',
                'name' => 'Scalp Treatment',
                'code' => 'SVC-HAR-002',
                'duration' => 45,
                'price' => 100000,
                'description' => 'Healthy scalp, healthy hair.',
                'requires_room' => false,
            ],

            // Pedi Mani
            [
                'category_name' => 'Pedi & Mani',
                'name' => 'Classic Manicure',
                'code' => 'SVC-PCM-001',
                'duration' => 45,
                'price' => 85000,
                'description' => 'Standard hand and nail care.',
                'requires_room' => false,
            ],
            [
                'category_name' => 'Pedi & Mani',
                'name' => 'Deluxe Pedicure',
                'code' => 'SVC-PCM-002',
                'duration' => 60,
                'price' => 110000,
                'description' => 'Premium foot and nail treatment.',
                'requires_room' => false,
            ],
        ];

        $branches = Branch::all();

        $categoryMapping = [
            'Massage & Body Treatments' => 'Massage',
            'Facial Treatments' => 'Face Treatment',
            'Hair Care' => 'Hair Treatment',
            'Pedi & Mani' => 'Packages', // Fallback to Packages for Mani/Pedi in this enum
        ];

        foreach ($services as $srv) {
            $cat = ServiceCategory::where('name', $srv['category_name'])->first();

            $srv['category'] = $categoryMapping[$srv['category_name']] ?? 'Packages';
            unset($srv['category_name']);
            $srv['service_category_id'] = $cat->id;
            $srv['is_active'] = true;
            $srv['is_global'] = true;
            $srv['is_booking_online_enabled'] = true;

            $variants = $srv['variants'] ?? null;
            unset($srv['variants']);

            $service = Service::updateOrCreate(
                ['code' => $srv['code']],
                $srv
            );

            // Link to all branches
            $service->branches()->sync($branches->pluck('id'));

            // 3. Create variants if defined
            if ($variants) {
                foreach ($variants as $v) {
                    ServiceVariant::updateOrCreate(
                        ['service_id' => $service->id, 'name' => $v['name']],
                        [
                            'duration' => $v['duration'],
                            'price' => $v['price'],
                            'is_active' => true,
                            'all_branches_same_price' => true
                        ]
                    );
                }
            }
        }
    }
}
