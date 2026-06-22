<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class RajaOngkirSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apiKey = config('services.rajaongkir.api_key');
        $packageType = config('services.rajaongkir.package_type', 'starter');
        $jsonPath = database_path('data/rajaongkir_locations.json');

        $provincesData = [];
        $citiesData = [];
        $fetchedFromApi = false;

        $apiKeyStr = is_string($apiKey) ? $apiKey : '';
        $packageTypeStr = is_string($packageType) ? $packageType : 'starter';

        if ($apiKeyStr !== '' && ! str_starts_with($apiKeyStr, 'AIzaSy')) {
            try {
                // Fetch Provinces
                $responseProvinces = Http::withHeaders(['key' => $apiKeyStr])
                    ->get("https://api.rajaongkir.com/{$packageTypeStr}/province");

                if ($responseProvinces->successful()) {
                    $resArr = $responseProvinces->json();
                    $provincesData = $resArr['rajaongkir']['results'] ?? [];
                }

                // Fetch Cities
                $responseCities = Http::withHeaders(['key' => $apiKeyStr])
                    ->get("https://api.rajaongkir.com/{$packageTypeStr}/city");

                if ($responseCities->successful()) {
                    $resArr = $responseCities->json();
                    $citiesData = $resArr['rajaongkir']['results'] ?? [];
                }

                if (! empty($provincesData) && ! empty($citiesData)) {
                    $fetchedFromApi = true;
                    // Save to JSON for future fallback
                    File::ensureDirectoryExists(database_path('data'));
                    $jsonContent = json_encode([
                        'provinces' => $provincesData,
                        'cities' => $citiesData,
                    ], JSON_PRETTY_PRINT);
                    if (is_string($jsonContent)) {
                        File::put($jsonPath, $jsonContent);
                    }
                }
            } catch (\Exception $e) {
                // Ignore and fall back
            }
        }

        if (! $fetchedFromApi) {
            // Try loading from local JSON
            if (File::exists($jsonPath)) {
                $jsonData = json_decode(File::get($jsonPath), true);
                $provincesData = $jsonData['provinces'] ?? [];
                $citiesData = $jsonData['cities'] ?? [];
            } else {
                // Hardcoded fallback subset (containing our default Jakarta Barat store origin)
                $provincesData = [
                    ['province_id' => '6', 'province' => 'DKI Jakarta'],
                    ['province_id' => '9', 'province' => 'Jawa Barat'],
                    ['province_id' => '11', 'province' => 'Jawa Timur'],
                    ['province_id' => '39', 'province' => 'DI Yogyakarta'],
                    ['province_id' => '17', 'province' => 'Kepulauan Riau'],
                ];
                $citiesData = [
                    [
                        'city_id' => '151',
                        'province_id' => '6',
                        'province' => 'DKI Jakarta',
                        'type' => 'Kota',
                        'city_name' => 'Jakarta Barat',
                        'postal_code' => '11830',
                    ],
                    [
                        'city_id' => '23',
                        'province_id' => '9',
                        'province' => 'Jawa Barat',
                        'type' => 'Kota',
                        'city_name' => 'Bandung',
                        'postal_code' => '40111',
                    ],
                    [
                        'city_id' => '444',
                        'province_id' => '11',
                        'province' => 'Jawa Timur',
                        'type' => 'Kota',
                        'city_name' => 'Surabaya',
                        'postal_code' => '60111',
                    ],
                    [
                        'city_id' => '501',
                        'province_id' => '39',
                        'province' => 'DI Yogyakarta',
                        'type' => 'Kota',
                        'city_name' => 'Yogyakarta',
                        'postal_code' => '55111',
                    ],
                    [
                        'city_id' => '48',
                        'province_id' => '17',
                        'province' => 'Kepulauan Riau',
                        'type' => 'Kota',
                        'city_name' => 'Batam',
                        'postal_code' => '29400',
                    ],
                ];
            }
        }

        // Insert Provinces
        foreach ($provincesData as $prov) {
            Province::updateOrCreate(
                ['id' => (int) $prov['province_id']],
                ['name' => $prov['province']]
            );
        }

        // Insert Cities
        foreach ($citiesData as $city) {
            City::updateOrCreate(
                ['id' => (int) $city['city_id']],
                [
                    'province_id' => (int) $city['province_id'],
                    'name' => $city['city_name'],
                    'type' => $city['type'],
                    'postal_code' => $city['postal_code'],
                ]
            );
        }
    }
}
