<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get collectors
        $collectors = User::where('role', 'penagih')->get();
        $routers = Router::all();

        // Parent areas (main regions)
        $mainAreas = [
            [
                'name' => 'Area Timur',
                'code' => 'TIMUR',
                'description' => 'Wilayah Jakarta Timur dan sekitarnya',
                'router_id' => $routers->where('name', 'Router Area Timur')->first()?->id,
                'latitude' => -6.2250,
                'longitude' => 106.9004,
            ],
            [
                'name' => 'Area Barat',
                'code' => 'BARAT',
                'description' => 'Wilayah Jakarta Barat dan sekitarnya',
                'router_id' => $routers->where('name', 'Router Area Barat')->first()?->id,
                'latitude' => -6.1675,
                'longitude' => 106.7589,
            ],
            [
                'name' => 'Area Utara',
                'code' => 'UTARA',
                'description' => 'Wilayah Jakarta Utara dan sekitarnya',
                'router_id' => $routers->where('name', 'Router Area Utara')->first()?->id,
                'latitude' => -6.1214,
                'longitude' => 106.9025,
            ],
            [
                'name' => 'Area Selatan',
                'code' => 'SELATAN',
                'description' => 'Wilayah Jakarta Selatan dan sekitarnya',
                'router_id' => $routers->where('name', 'Router Area Selatan')->first()?->id,
                'latitude' => -6.2615,
                'longitude' => 106.8106,
            ],
        ];

        $createdMainAreas = [];
        foreach ($mainAreas as $area) {
            $createdMainAreas[$area['code']] = Area::create(array_merge($area, ['is_active' => true]));
        }

        // Sub-areas (kelurahan/kampung)
        $subAreas = [
            // Area Timur
            [
                'name' => 'Cakung',
                'code' => 'TIMUR-CKG',
                'parent' => 'TIMUR',
                'collector_index' => 0,
            ],
            [
                'name' => 'Pulo Gadung',
                'code' => 'TIMUR-PLG',
                'parent' => 'TIMUR',
                'collector_index' => 0,
            ],
            [
                'name' => 'Duren Sawit',
                'code' => 'TIMUR-DRS',
                'parent' => 'TIMUR',
                'collector_index' => 1,
            ],
            [
                'name' => 'Jatinegara',
                'code' => 'TIMUR-JTN',
                'parent' => 'TIMUR',
                'collector_index' => 1,
            ],
            // Area Barat
            [
                'name' => 'Cengkareng',
                'code' => 'BARAT-CKR',
                'parent' => 'BARAT',
                'collector_index' => 2,
            ],
            [
                'name' => 'Kalideres',
                'code' => 'BARAT-KLD',
                'parent' => 'BARAT',
                'collector_index' => 2,
            ],
            [
                'name' => 'Grogol',
                'code' => 'BARAT-GRG',
                'parent' => 'BARAT',
                'collector_index' => 2,
            ],
            // Area Utara
            [
                'name' => 'Tanjung Priok',
                'code' => 'UTARA-TJP',
                'parent' => 'UTARA',
                'collector_index' => 3,
            ],
            [
                'name' => 'Kelapa Gading',
                'code' => 'UTARA-KLG',
                'parent' => 'UTARA',
                'collector_index' => 3,
            ],
            [
                'name' => 'Cilincing',
                'code' => 'UTARA-CLN',
                'parent' => 'UTARA',
                'collector_index' => 3,
            ],
            // Area Selatan
            [
                'name' => 'Pasar Minggu',
                'code' => 'SELATAN-PSM',
                'parent' => 'SELATAN',
                'collector_index' => 4,
            ],
            [
                'name' => 'Jagakarsa',
                'code' => 'SELATAN-JGK',
                'parent' => 'SELATAN',
                'collector_index' => 4,
            ],
            [
                'name' => 'Cilandak',
                'code' => 'SELATAN-CLD',
                'parent' => 'SELATAN',
                'collector_index' => 4,
            ],
        ];

        foreach ($subAreas as $subArea) {
            $parent = $createdMainAreas[$subArea['parent']];
            $collector = $collectors[$subArea['collector_index']] ?? null;

            Area::create([
                'name' => $subArea['name'],
                'code' => $subArea['code'],
                'description' => "Kelurahan {$subArea['name']}",
                'parent_id' => $parent->id,
                'router_id' => $parent->router_id,
                'collector_id' => $collector?->id,
                'is_active' => true,
            ]);
        }

        // Update collectors' area_id
        $areaTimur = $createdMainAreas['TIMUR'];
        if ($collectors->count() >= 2) {
            $collectors[0]->update(['area_id' => $areaTimur->id]);
            $collectors[1]->update(['area_id' => $areaTimur->id]);
        }

        $areaBarat = $createdMainAreas['BARAT'];
        if ($collectors->count() >= 3) {
            $collectors[2]->update(['area_id' => $areaBarat->id]);
        }

        $areaUtara = $createdMainAreas['UTARA'];
        if ($collectors->count() >= 4) {
            $collectors[3]->update(['area_id' => $areaUtara->id]);
        }

        $areaSelatan = $createdMainAreas['SELATAN'];
        if ($collectors->count() >= 5) {
            $collectors[4]->update(['area_id' => $areaSelatan->id]);
        }
    }
}
