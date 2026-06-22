<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RajaOngkirService
{
    protected string $apiKey;

    protected string $packageType;

    protected string $baseUrl;

    protected int $originCityId = 48; // Batam

    public function __construct()
    {
        $this->apiKey = (string) config('services.rajaongkir.api_key', '');
        $this->packageType = (string) config('services.rajaongkir.package_type', 'starter');
        $this->baseUrl = "https://api.rajaongkir.com/{$this->packageType}";
    }

    /**
     * Calculate shipping cost.
     *
     * @param  string  $courier  (jne, pos, tiki)
     * @return array<int, array{service: string, description: string, cost: int, etd: string}>
     */
    public function calculateCost(int $destinationCityId, int $weightGrams, string $courier): array
    {
        if (empty($this->apiKey) || str_starts_with($this->apiKey, 'AIzaSy')) {
            // Mock fallback offline rates if API key is not configured/placeholder
            return $this->getMockRates($courier, $destinationCityId, $weightGrams);
        }

        try {
            $response = Http::withHeaders([
                'key' => $this->apiKey,
            ])->post("{$this->baseUrl}/cost", [
                'origin' => $this->originCityId,
                'destination' => $destinationCityId,
                'weight' => $weightGrams,
                'courier' => strtolower($courier),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['rajaongkir']['results'] ?? [];

                // Return services array
                $services = [];
                if (! empty($results)) {
                    $costs = $results[0]['costs'] ?? [];
                    foreach ($costs as $costItem) {
                        $services[] = [
                            'service' => $costItem['service'],
                            'description' => $costItem['description'],
                            'cost' => $costItem['cost'][0]['value'] ?? 0,
                            'etd' => $costItem['cost'][0]['etd'] ?? '',
                        ];
                    }
                }

                return $services;
            }

            Log::error('RajaOngkir Cost API Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('RajaOngkir Exception', ['message' => $e->getMessage()]);
        }

        // Fallback to offline mock rates if API call fails
        return $this->getMockRates($courier, $destinationCityId, $weightGrams);
    }

    /**
     * Get mock fallback rates for starter destinations.
     *
     * @return array<int, array{service: string, description: string, cost: int, etd: string}>
     */
    protected function getMockRates(string $courier, int $cityId, int $weight): array
    {
        // Compute basic distance-based cost factor
        // Default base rate: JNE = 10k, POS = 8k, TIKI = 9k per kg.
        $baseMultiplier = match (strtolower($courier)) {
            'jne' => 12000,
            'pos' => 9000,
            'tiki' => 10000,
            default => 10000,
        };

        // If it's a further destination, add a multiplier
        // 48 (Batam) is close to Kepulauan Riau, other islands are further
        $distanceFactor = match ($cityId) {
            48 => 1.0,  // Batam (same city)
            151 => 2.5, // Jakarta Barat
            23 => 2.7,  // Bandung
            501 => 3.0, // Yogyakarta
            444 => 3.2, // Surabaya
            default => 3.5, // Outer Java/Remote
        };

        $weightKg = ceil($weight / 1000);
        if ($weightKg < 1) {
            $weightKg = 1;
        }

        $cost = (int) ($baseMultiplier * $distanceFactor * $weightKg);

        return [
            [
                'service' => 'REG',
                'description' => 'Regular Service',
                'cost' => $cost,
                'etd' => '2-3 HARI',
            ],
            [
                'service' => 'OKE',
                'description' => 'Economy Service',
                'cost' => (int) ($cost * 0.8),
                'etd' => '4-6 HARI',
            ],
        ];
    }
}
