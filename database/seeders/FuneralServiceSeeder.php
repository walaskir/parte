<?php

namespace Database\Seeders;

use App\Models\FuneralService;
use Illuminate\Database\Seeder;

class FuneralServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Sadový Jan',
                'slug' => 'sadovy-jan',
                'homepage_url' => 'https://www.sadovyjan.cz',
                'parte_url' => 'https://www.sadovyjan.cz/parte/',
                'active' => true,
            ],
            [
                'name' => 'PS Hajdukova',
                'slug' => 'pshajdukova',
                'homepage_url' => 'https://pshajdukova.cz',
                'parte_url' => 'https://pshajdukova.cz/smutecni-obrady-parte/',
                'active' => true,
            ],
            [
                'name' => 'PS BK',
                'slug' => 'psbk',
                'homepage_url' => 'https://psbk.cz',
                'parte_url' => 'https://psbk.cz/parte/',
                'active' => true,
            ],
        ];

        foreach ($services as $service) {
            FuneralService::updateOrCreate(
                ['slug' => $service['slug']],
                $service
            );
        }
    }
}
