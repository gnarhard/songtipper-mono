<?php

declare(strict_types=1);

use App\Models\Project;

describe('Sitemap', function () {
    it('returns valid XML with correct content type', function () {
        $this->get(route('sitemap'))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
    });

    it('contains all static page URLs', function () {
        $response = $this->get(route('sitemap'));

        $response
            ->assertSee('<loc>'.config('app.url').'</loc>', false)
            ->assertSee('<loc>'.config('app.url').'/terms</loc>', false)
            ->assertSee('<loc>'.config('app.url').'/privacy</loc>', false)
            ->assertSee('<loc>'.config('app.url').'/eula</loc>', false);
    });

    it('excludes blog URLs while content is under review', function () {
        $this->get(route('sitemap'))
            ->assertDontSee('/blog', false);
    });

    it('contains project page URLs', function () {
        $project = Project::factory()->create(['slug' => 'test-performer']);

        $this->get(route('sitemap'))
            ->assertSee('<loc>'.config('app.url').'/project/test-performer</loc>', false);
    });

    it('does not include lastmod when urls do not have it set', function () {
        $response = $this->get(route('sitemap'));

        $response->assertSuccessful()
            ->assertDontSee('<lastmod>', false);
    });
});
