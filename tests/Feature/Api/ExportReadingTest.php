<?php

use App\Models\Anemometer;

it('exports nested readings as JSON', function (): void {
    actingAsUser();
    $number = 3;
    $anemometer = Anemometer::factory()->withReadings($number)->create();

    $response = $this->getJson("/api/anemometers/{$anemometer->id}/readings/export?format=json");

    $response->assertOk();
    expect($response->json())->toHaveCount($number);
});

it('exports nested readings as CSV', function (): void {
    actingAsUser();
    $number = 3;
    $anemometer = Anemometer::factory()->withReadings($number)->create();

    $response = $this->get("/api/anemometers/{$anemometer->id}/readings/export?format=csv");

    $response->assertOk();
    $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    $response->assertHeader('Content-Disposition');
});

it('rejects invalid export format', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create();

    $response = $this->getJson("/api/anemometers/{$anemometer->id}/readings/export?format=pdf");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['format']);
});

it('rejects unauthenticated export request', function (): void {
    $anemometer = Anemometer::factory()->create();

    $response = $this->getJson("/api/anemometers/{$anemometer->id}/readings/export?format=json");

    $response->assertStatus(401);
});
