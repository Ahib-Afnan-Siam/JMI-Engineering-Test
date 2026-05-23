<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ReadingDetailResource;
use App\Http\Resources\ReadingResource;
use App\Http\Responses\DrfPagination;
use App\Models\Reading;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Port of AnemometerReadingViewSet — readings nested under an anemometer.
 *
 * This lane exposes only list + retrieve (matches the router registration
 * and how the Django nested viewset is actually used for scoped fetches).
 */
class AnemometerReadingController extends Controller
{
    /**
     * GET /api/anemometers/{anemometer}/readings.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request, string $anemometerId): array
    {
        $readings = Reading::query()
            ->with(['anemometer', 'tags'])
            ->where('anemometer_id', $anemometerId)
            ->paginate();

        return DrfPagination::shape(
            $readings,
            fn (Reading $r) => (new ReadingResource($r))->resolve(),
        );
    }

    /**
     * GET /api/anemometers/{anemometer}/readings/{reading}.
     */
    public function show(string $anemometerId, string $readingId): ReadingDetailResource
    {
        $reading = Reading::query()
            ->with(['anemometer', 'tags'])
            ->where('anemometer_id', $anemometerId)
            ->where('id', $readingId)
            ->firstOrFail();

        return new ReadingDetailResource($reading);
    }

    /**
     * GET /api/anemometers/{anemometer}/readings/export.
     */
    public function export(Request $request, string $anemometerId)
    {
        $request->validate([
            'format' => 'required|in:csv,json',
        ]);

        $readings = Reading::query()
            ->with(['anemometer', 'tags'])
            ->where('anemometer_id', $anemometerId)
            ->get();

        $format = $request->input('format');

        if ($format === 'json') {
            return response()->json($readings->map(function ($reading) {
                return (new ReadingResource($reading))->resolve();
            }));
        }

        return response()->streamDownload(function () use ($readings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'speed', 'recorded_at', 'anemometer_id', 'tags']);

            foreach ($readings as $reading) {
                fputcsv($handle, [
                    $reading->id,
                    $reading->speed,
                    $reading->recorded_at->toISOString(),
                    $reading->anemometer_id,
                    implode(',', $reading->tags->pluck('name')->toArray()),
                ]);
            }

            fclose($handle);
        }, 'readings.csv');
    }
}
