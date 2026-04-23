<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('reports queue stats as json for database queues', function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.driver', 'database');
    config()->set('charts.render_queue', 'renders');
    config()->set('charts.identification_queue', 'imports');

    $now = now()->timestamp;

    DB::table('jobs')->insert([
        [
            'queue' => 'renders',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\RenderChartPages']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now - 30,
            'created_at' => $now - 30,
        ],
        [
            'queue' => 'imports',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessImportedChart']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now - 5,
            'created_at' => $now - 5,
        ],
    ]);

    DB::table('failed_jobs')->insert([
        [
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'renders',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\RenderChartPages']),
            'exception' => 'RuntimeException: render failure',
            'failed_at' => now(),
        ],
        [
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'imports',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessImportedChart']),
            'exception' => 'RuntimeException: identify failure',
            'failed_at' => now(),
        ],
    ]);

    $this->artisan('charts:queue-stats --json')
        ->assertExitCode(0);
});

it('outputs text info for non-database driver without json flag', function () {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.driver', 'redis');
    config()->set('charts.render_queue', 'renders');
    config()->set('charts.identification_queue', 'imports');

    $this->artisan('charts:queue-stats')
        ->expectsOutput('charts:queue-stats currently supports detailed metrics for the database queue driver only.')
        ->expectsOutput('Active connection: redis (redis)')
        ->assertExitCode(0);
});

it('outputs json for non-database driver with json flag', function () {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.driver', 'redis');
    config()->set('charts.render_queue', 'renders');
    config()->set('charts.identification_queue', 'imports');

    $this->artisan('charts:queue-stats', ['--json' => true])
        ->assertExitCode(0);
});

it('outputs table for database driver without json flag', function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.driver', 'database');
    config()->set('charts.render_queue', 'renders');
    config()->set('charts.identification_queue', 'imports');

    $now = now()->timestamp;

    DB::table('jobs')->insert([
        'queue' => 'renders',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\RenderChartPages']),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => $now - 10,
        'created_at' => $now - 10,
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'renders',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\RenderChartPages']),
        'exception' => 'RuntimeException: render failure',
        'failed_at' => now(),
    ]);

    $this->artisan('charts:queue-stats')
        ->expectsOutput('Queue stats for database (database)')
        ->expectsOutput('Failed job breakdown:')
        ->assertExitCode(0);
});

it('returns failure when a database exception occurs', function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.driver', 'database');
    config()->set('charts.render_queue', 'renders');
    config()->set('charts.identification_queue', 'imports');

    DB::shouldReceive('table')
        ->andThrow(new RuntimeException('Connection refused'));

    $this->artisan('charts:queue-stats')
        ->expectsOutput('Unable to compute queue stats: Connection refused')
        ->assertExitCode(1);
});
