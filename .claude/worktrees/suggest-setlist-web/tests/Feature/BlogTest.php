<?php

declare(strict_types=1);

use App\Support\BlogArticleCatalog;

describe('Blog Index', function () {
    it('displays the blog index page successfully', function () {
        $this->get(route('blog.index'))
            ->assertSuccessful()
            ->assertSee('Blog')
            ->assertSee('Tips and strategies for live musicians');
    });

    it('lists all blog articles', function () {
        $articles = app(BlogArticleCatalog::class)->all();

        $response = $this->get(route('blog.index'));

        foreach ($articles as $article) {
            $response->assertSee($article['title']);
        }
    });

    it('has a free trial CTA with correct trial days', function () {
        $this->get(route('blog.index'))
            ->assertSuccessful()
            ->assertSee('free for '.config('billing.trial_days').' days');
    });

    it('has navigation back to home', function () {
        $this->get(route('blog.index'))
            ->assertSee(config('app.name'));
    });
});

describe('Blog Article', function () {
    it('displays an individual blog article', function () {
        $article = app(BlogArticleCatalog::class)->all()->first();

        $this->get(route('blog.show', $article['slug']))
            ->assertSuccessful()
            ->assertSee($article['title']);
    });

    it('returns 404 for non-existent slug', function () {
        $this->get(route('blog.show', 'non-existent-article'))
            ->assertNotFound();
    });

    it('has correct SEO meta title for each article', function () {
        $article = app(BlogArticleCatalog::class)->all()->first();

        $this->get(route('blog.show', $article['slug']))
            ->assertSee('<title>'.$article['title'].' - '.config('app.name').'</title>', false);
    });

    it('has a back to blog link', function () {
        $article = app(BlogArticleCatalog::class)->all()->first();

        $this->get(route('blog.show', $article['slug']))
            ->assertSee('Back to Blog');
    });

    it('has a free trial CTA', function () {
        $article = app(BlogArticleCatalog::class)->all()->first();

        $this->get(route('blog.show', $article['slug']))
            ->assertSee('free for '.config('billing.trial_days').' days');
    });
});

describe('Home Page Blog Section', function () {
    it('shows latest blog articles on the home page', function () {
        $latestArticles = app(BlogArticleCatalog::class)->latest(3);

        $response = $this->get(route('home'));

        $response->assertSee('Latest from the Blog');

        foreach ($latestArticles as $article) {
            $response->assertSee($article['title']);
        }
    });
});
