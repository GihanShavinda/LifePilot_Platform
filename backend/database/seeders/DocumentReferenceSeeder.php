<?php

namespace Database\Seeders;

use App\Domain\Documents\Models\DocumentCategory;
use App\Domain\Documents\Models\DocumentSource;
use Illuminate\Database\Seeder;

class DocumentReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'bill', 'receipt', 'warranty', 'insurance', 'subscription', 'appointment',
            'education', 'employment', 'banking', 'vehicle', 'property', 'medical-admin',
            'government', 'other',
        ];

        foreach ($categories as $slug) {
            DocumentCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('-', ' ', $slug)), 'is_system' => true]
            );
        }

        foreach (['upload' => 'Manual Upload', 'email' => 'Email', 'integration' => 'Integration', 'manual' => 'Manual Entry'] as $slug => $name) {
            DocumentSource::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
