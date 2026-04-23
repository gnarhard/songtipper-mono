<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\BillingSetupController;
use App\Http\Controllers\DashboardBillingController;
use App\Http\Controllers\PayoutOnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\AudienceProjectController;
use App\Http\Controllers\Public\RequestConfirmationController;
use App\Http\Controllers\SetlistShareRedirectController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\AllowEmbedding;
use App\Models\Project;
use App\Support\BlogArticleCatalog;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/eula', 'pages.eula')->name('eula');

Route::get('/blog', function () {
    $articles = app(BlogArticleCatalog::class)->all();

    return view('pages.blog.index', ['articles' => $articles]);
})->name('blog.index');

Route::get('/blog/{slug}', function (string $slug) {
    $article = app(BlogArticleCatalog::class)->find($slug);

    abort_if($article === null, 404);

    return view('pages.blog.show', ['article' => $article]);
})->name('blog.show');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/shared-setlists/{shareLink:token}', [SetlistShareRedirectController::class, 'show'])
    ->name('shared-setlists.redirect');
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/setup/billing', [BillingSetupController::class, 'show'])->name('setup.billing.show');
    Route::post('/setup/billing', [BillingSetupController::class, 'store'])->name('setup.billing.store');
});

Route::middleware(['auth', 'verified', 'billing.setup', 'admin'])->group(function () {
    Route::get('/admin/songs', function () {
        return view('admin.songs');
    })->name('admin.songs');

    Route::get('/admin/access', function () {
        return view('admin.access');
    })->name('admin.access');

    Route::get('/admin/song-integrity', function () {
        return view('admin.song-integrity');
    })->name('admin.song-integrity');

    Route::get('/admin/test-checklist', function () {
        return view('admin.test-checklist');
    })->name('admin.test-checklist');
});

Route::middleware(['auth', 'verified', 'billing.setup'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/dashboard/payout-account/onboarding/refresh', [
        PayoutOnboardingController::class,
        'refresh',
    ])->name('payout-account.onboarding.refresh');
    Route::get('/dashboard/payout-account/onboarding/return', [
        PayoutOnboardingController::class,
        'returned',
    ])->name('payout-account.onboarding.return');

    Route::get('/dashboard/billing', [DashboardBillingController::class, 'show'])
        ->name('dashboard.billing.show');
    Route::post('/dashboard/billing/payment-method', [DashboardBillingController::class, 'updatePaymentMethod'])
        ->name('dashboard.billing.payment-method');
    Route::post('/dashboard/billing/plan', [DashboardBillingController::class, 'updatePlan'])
        ->name('dashboard.billing.plan');
    Route::post('/dashboard/billing/activate', [DashboardBillingController::class, 'activate'])
        ->name('dashboard.billing.activate');
    Route::get('/dashboard/billing/portal', [DashboardBillingController::class, 'portal'])
        ->name('dashboard.billing.portal');
});

Route::middleware(['auth', 'billing.setup'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/request/confirmation', RequestConfirmationController::class)->name('request.confirmation');

require __DIR__.'/auth.php';

Route::get('/project/{projectSlug}', function (string $projectSlug) {
    $projectName = Project::query()
        ->where('slug', $projectSlug)
        ->value('name');

    abort_if($projectName === null, 404);

    $trimmedProjectName = trim($projectName);

    $pageTitle = $trimmedProjectName === ''
        ? config('app.name', 'Song Tipper')
        : (
            str_ends_with(strtolower($trimmedProjectName), 's')
                ? "{$trimmedProjectName}' Song Tipper"
                : "{$trimmedProjectName}'s Song Tipper"
        );

    return view('pages.project', [
        'pageTitle' => $pageTitle,
        'projectSlug' => $projectSlug,
    ]);
})->name('project.page');

Route::get('/project/{projectSlug}/repertoire', function (string $projectSlug) {
    $projectName = Project::query()
        ->where('slug', $projectSlug)
        ->value('name');

    abort_if($projectName === null, 404);

    $trimmedProjectName = trim($projectName);

    $pageTitle = $trimmedProjectName === ''
        ? 'Repertoire — '.config('app.name', 'Song Tipper')
        : (
            str_ends_with(strtolower($trimmedProjectName), 's')
                ? "{$trimmedProjectName}' Repertoire"
                : "{$trimmedProjectName}'s Repertoire"
        );

    return view('pages.repertoire', [
        'pageTitle' => $pageTitle,
        'projectSlug' => $projectSlug,
    ]);
})->middleware(AllowEmbedding::class)->name('project.repertoire');

Route::get('/project/{projectSlug}/suggest-setlist', function (string $projectSlug) {
    $projectName = Project::query()
        ->where('slug', $projectSlug)
        ->value('name');

    abort_if($projectName === null, 404);

    $trimmedProjectName = trim($projectName);

    $pageTitle = $trimmedProjectName === ''
        ? 'Suggest a Setlist — '.config('app.name', 'Song Tipper')
        : (
            str_ends_with(strtolower($trimmedProjectName), 's')
                ? "{$trimmedProjectName}' Suggested Setlist"
                : "{$trimmedProjectName}'s Suggested Setlist"
        );

    return view('pages.suggest-setlist', [
        'pageTitle' => $pageTitle,
        'projectSlug' => $projectSlug,
    ]);
})->name('project.suggest-setlist');

Route::get('/project/{projectSlug}/learn-more', [
    AudienceProjectController::class,
    'learnMore',
])->name('project.learn-more');

Route::get('/project/{projectSlug}/request/{song}', function (string $projectSlug, int $song) {
    return view('pages.request', compact('projectSlug', 'song'));
})->name('request.page');
