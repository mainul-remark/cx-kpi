<?php

namespace App\Services\Attendance;

use App\Models\OfficeLocation;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Works out where a check in was made: at an office, somewhere else, or nowhere that can be told.
 */
class OfficeLocator
{
    public const OFFICE = 'office';
    public const FIELD = 'field';
    public const UNKNOWN = 'unknown';

    /**
     * @param  array{lat?: float|null, lng?: float|null}  $geo
     * @return array{place: string, office_id: int|null}
     */
    public function resolve(array $geo, ?string $ip): array
    {
        $offices = OfficeLocation::query()->active()->get();

        foreach ($offices as $office) {
            if ($this->atOffice($office, $geo, $ip)) {
                return ['place' => self::OFFICE, 'office_id' => $office->id];
            }
        }

        // with neither a position nor an office address on file to compare against, nothing can be said
        $located = isset($geo['lat'], $geo['lng']);

        return ['place' => $located ? self::FIELD : self::UNKNOWN, 'office_id' => null];
    }

    private function atOffice(OfficeLocation $office, array $geo, ?string $ip): bool
    {
        $ips = $office->ipList();

        if ($ip && $ips && IpUtils::checkIp($ip, $ips)) {
            return true;
        }

        return isset($geo['lat'], $geo['lng'], $office->lat, $office->lng)
            && $this->distanceInMetres((float) $geo['lat'], (float) $geo['lng'], $office->lat, $office->lng) <= $office->radius_m;
    }

    /** The great-circle distance between two points, by the haversine formula. */
    public function distanceInMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
