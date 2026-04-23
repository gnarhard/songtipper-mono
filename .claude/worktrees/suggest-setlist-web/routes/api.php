<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountUsageController;
use App\Http\Controllers\Api\V1\AiSetController;
use App\Http\Controllers\Api\V1\AppVersionPolicyController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\UpdatePasswordController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BulkEnrichController;
use App\Http\Controllers\Api\V1\BulkImportConfirmController;
use App\Http\Controllers\Api\V1\BulkUploadController;
use App\Http\Controllers\Api\V1\CashTipController;
use App\Http\Controllers\Api\V1\ChartAdoptionController;
use App\Http\Controllers\Api\V1\ChartAnnotationController;
use App\Http\Controllers\Api\V1\ChartController;
use App\Http\Controllers\Api\V1\ChartDuplicateController;
use App\Http\Controllers\Api\V1\ChartPageViewportController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\ImageImportController;
use App\Http\Controllers\Api\V1\PayoutAccountController;
use App\Http\Controllers\Api\V1\PerformanceSessionController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\ProjectSongAudioFileController;
use App\Http\Controllers\Api\V1\ProjectSongController;
use App\Http\Controllers\Api\V1\ProjectSongMetadataController;
use App\Http\Controllers\Api\V1\ProjectStatsController;
use App\Http\Controllers\Api\V1\ProjectStatsHistoryController;
use App\Http\Controllers\Api\V1\PublicRepertoireController;
use App\Http\Controllers\Api\V1\PublicRequestController;
use App\Http\Controllers\Api\V1\QueueController;
use App\Http\Controllers\Api\V1\RequestController;
use App\Http\Controllers\Api\V1\RewardClaimController;
use App\Http\Controllers\Api\V1\RewardThresholdController;
use App\Http\Controllers\Api\V1\SetlistController;
use App\Http\Controllers\Api\V1\SetlistSetController;
use App\Http\Controllers\Api\V1\SetlistShareLinkController;
use App\Http\Controllers\Api\V1\SetlistSongController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Middleware\TrackAuthenticatedAccountActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', LoginController::class)
        ->middleware('throttle:5,1')
        ->name('api.v1.auth.login');
    Route::post('/auth/forgot-password', ForgotPasswordController::class)
        ->middleware('throttle:5,1')
        ->name('api.v1.auth.forgot-password');
    Route::post('/auth/reset-password', ResetPasswordController::class)
        ->middleware('throttle:5,1')
        ->name('api.v1.auth.reset-password');
    Route::get('/app/version-policy', AppVersionPolicyController::class)
        ->name('api.v1.app.version-policy');

    Route::prefix('public/projects/{projectSlug}')
        ->middleware('throttle:public-audience')
        ->group(function () {
            Route::get('repertoire', [PublicRepertoireController::class, 'index']);
            Route::post('requests', [PublicRequestController::class, 'store']);
        });

    Route::middleware(['auth:sanctum', TrackAuthenticatedAccountActivity::class])->group(function () {
        Route::post('/auth/logout', LogoutController::class)->name('api.v1.auth.logout');
        Route::put('/auth/password', UpdatePasswordController::class)->name('api.v1.auth.password.update');
        Route::get('me/profile', [ProfileController::class, 'show'])->name('api.v1.me.profile.show');
        Route::patch('me/profile', [ProfileController::class, 'update'])->name('api.v1.me.profile.update');
        Route::get('me/usage', AccountUsageController::class);
        Route::post('me/feedback', FeedbackController::class)
            ->middleware('throttle:5,1')
            ->name('api.v1.me.feedback');

        Route::get('me/billing', [BillingController::class, 'status']);
        Route::post('me/billing/activate', [BillingController::class, 'activate']);

        Route::get('me/projects', [ProjectController::class, 'index']);
        Route::post('me/projects', [ProjectController::class, 'store']);
        Route::put('me/projects/{project}', [ProjectController::class, 'update']);
        Route::patch('me/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('me/projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('me/projects/{project}/performer-image', [ProjectController::class, 'uploadPerformerImage']);
        Route::get('me/projects/{project}/members', [ProjectMemberController::class, 'index']);
        Route::post('me/projects/{project}/members', [ProjectMemberController::class, 'store']);
        Route::delete('me/projects/{project}/members/{projectMember}', [ProjectMemberController::class, 'destroy']);
        Route::get('me/payout-account', [PayoutAccountController::class, 'show']);
        Route::post('me/payout-account/onboarding-link', [PayoutAccountController::class, 'onboardingLink']);
        Route::post('me/payout-account/dashboard-link', [PayoutAccountController::class, 'dashboardLink']);
        Route::post('me/shared-setlists/{shareLink:token}/accept', [SetlistShareLinkController::class, 'accept']);
        Route::get('me/payouts', [WalletController::class, 'payouts']);
        Route::get('me/projects/{project}/wallet', [WalletController::class, 'show']);
        Route::get('me/projects/{project}/wallet/sessions', [WalletController::class, 'sessions']);
        Route::get('me/projects/{project}/stats', ProjectStatsController::class);
        Route::get('me/projects/{project}/stats/history', ProjectStatsHistoryController::class);

        Route::prefix('me/projects/{project}')->group(function () {
            Route::get('queue', [QueueController::class, 'index']);
            Route::post('queue', [QueueController::class, 'store']);
            Route::patch('queue/{queueRequest}', [QueueController::class, 'update']);
            Route::get('requests/history', [QueueController::class, 'history']);

            Route::get('cash-tips', [CashTipController::class, 'index']);
            Route::post('cash-tips', [CashTipController::class, 'store']);
            Route::patch('cash-tips/{cashTip}', [CashTipController::class, 'update']);
            Route::delete('cash-tips/{cashTip}', [CashTipController::class, 'destroy']);

            Route::get('repertoire', [ProjectSongController::class, 'index']);
            Route::post('repertoire', [ProjectSongController::class, 'store']);
            Route::get('repertoire/metadata', [ProjectSongMetadataController::class, 'show'])
                ->middleware('throttle:interactive-metadata');
            Route::post('repertoire/bulk-update', [ProjectSongController::class, 'bulkUpdate']);
            Route::put('repertoire/{projectSong}', [ProjectSongController::class, 'update']);
            Route::delete('repertoire/{projectSong}', [ProjectSongController::class, 'destroy']);
            Route::post('repertoire/{projectSong}/clone', [ProjectSongController::class, 'cloneVersion']);
            Route::post('repertoire/{projectSong}/pull-owner-copy', [ProjectSongController::class, 'pullOwnerCopy']);
            Route::post('repertoire/{projectSong}/performances', [ProjectSongController::class, 'storePerformance']);

            Route::post('repertoire/audio-files/batch', [ProjectSongAudioFileController::class, 'batchIndex']);
            Route::get('repertoire/audio-files/manifest', [ProjectSongAudioFileController::class, 'manifest']);
            Route::get('repertoire/{projectSong}/audio-files', [ProjectSongAudioFileController::class, 'index']);
            Route::post('repertoire/{projectSong}/audio-files', [ProjectSongAudioFileController::class, 'store'])
                ->middleware('throttle:chart-uploads');
            Route::get('repertoire/{projectSong}/audio-files/{audioFile}/signed-url', [ProjectSongAudioFileController::class, 'signedUrl']);
            Route::post('repertoire/{projectSong}/audio-files/{audioFile}/replace', [ProjectSongAudioFileController::class, 'replace'])
                ->middleware('throttle:chart-uploads');
            Route::put('repertoire/{projectSong}/audio-files/{audioFile}', [ProjectSongAudioFileController::class, 'update']);
            Route::delete('repertoire/{projectSong}/audio-files/{audioFile}', [ProjectSongAudioFileController::class, 'destroy']);

            Route::post('repertoire/bulk-enrich', [BulkEnrichController::class, 'store']);
            Route::post('repertoire/bulk-import/confirm', [BulkImportConfirmController::class, 'store'])
                ->middleware('throttle:chart-uploads');
            Route::post('repertoire/bulk-upload', [BulkUploadController::class, 'store'])
                ->middleware('throttle:chart-uploads');
            Route::get('charts/pending-review', [ChartDuplicateController::class, 'index']);
            Route::post('repertoire/import-from-image', [ImageImportController::class, 'store'])
                ->middleware('throttle:chart-uploads');
            Route::post('repertoire/copy-from', [ProjectSongController::class, 'copyFrom']);

            // Reward thresholds
            Route::get('reward-thresholds', [RewardThresholdController::class, 'index']);
            Route::post('reward-thresholds', [RewardThresholdController::class, 'store']);
            Route::put('reward-thresholds/reorder', [RewardThresholdController::class, 'reorder']);
            Route::put('reward-thresholds/{rewardThreshold}', [RewardThresholdController::class, 'update']);
            Route::delete('reward-thresholds/{rewardThreshold}', [RewardThresholdController::class, 'destroy']);

            Route::post('performances/start', [PerformanceSessionController::class, 'start']);
            Route::post('performances/stop', [PerformanceSessionController::class, 'stop']);
            Route::get('performances/current', [PerformanceSessionController::class, 'current']);
            Route::post('performances/current/complete', [PerformanceSessionController::class, 'complete']);
            Route::post('performances/current/skip', [PerformanceSessionController::class, 'skip']);
            Route::post('performances/current/random', [PerformanceSessionController::class, 'random']);

            // Setlists
            Route::post('setlists/extract-songs-from-image', [AiSetController::class, 'extractSongsFromImage']);
            Route::get('setlists', [SetlistController::class, 'index']);
            Route::post('setlists', [SetlistController::class, 'store']);
            Route::get('setlists/{setlist}', [SetlistController::class, 'show']);
            Route::post('setlists/{setlist}/share-link', [SetlistShareLinkController::class, 'store']);
            Route::put('setlists/{setlist}', [SetlistController::class, 'update']);
            Route::post('setlists/{setlist}/archive', [SetlistController::class, 'archive']);
            Route::post('setlists/{setlist}/restore', [SetlistController::class, 'restore']);
            Route::post('setlists/{setlist}/copy-to-project', [SetlistController::class, 'copyToProject']);
            Route::post('setlists/{setlist}/share-with-members', [SetlistController::class, 'shareWithMembers']);
            Route::delete('setlists/{setlist}', [SetlistController::class, 'destroy']);

            // Sets within setlist
            Route::post('setlists/{setlist}/sets/generate-ai', [AiSetController::class, 'generate']);
            Route::post('setlists/{setlist}/sets', [SetlistSetController::class, 'store']);
            Route::put('setlists/{setlist}/sets/{set}', [SetlistSetController::class, 'update']);
            Route::delete('setlists/{setlist}/sets/{set}', [SetlistSetController::class, 'destroy']);

            // Songs within set
            Route::post('setlists/{setlist}/sets/{set}/songs', [SetlistSongController::class, 'store']);
            Route::post('setlists/{setlist}/sets/{set}/songs/bulk', [SetlistSongController::class, 'bulkStore']);
            Route::post('setlists/{setlist}/sets/{set}/songs/import-text', [SetlistSongController::class, 'importText']);
            Route::put('setlists/{setlist}/sets/{set}/songs/reorder', [SetlistSongController::class, 'reorder']);
            Route::put('setlists/{setlist}/sets/{set}/songs/{song}', [SetlistSongController::class, 'update']);
            Route::delete('setlists/{setlist}/sets/{set}/songs/{song}', [SetlistSongController::class, 'destroy']);
        });

        Route::post('me/requests/{request}/played', [RequestController::class, 'markPlayed']);
        Route::post('me/reward-claims/{rewardClaim}/delivered', [RewardClaimController::class, 'markDelivered']);

        Route::post('me/charts/cache-manifest', [ChartController::class, 'cacheManifest'])
            ->middleware('throttle:2,5');
        Route::get('me/charts', [ChartController::class, 'index']);
        Route::post('me/charts/generate-lyrics', [ChartController::class, 'generateLyricSheet']);
        Route::post('me/charts', [ChartController::class, 'store'])
            ->middleware('throttle:chart-uploads');
        Route::get('me/charts/{chart}', [ChartController::class, 'show']);
        Route::get('me/charts/{chart}/render-status', [ChartController::class, 'renderStatus']);
        Route::get('me/charts/{chart}/signed-url', [ChartController::class, 'signedUrl']);
        Route::get('me/charts/{chart}/page', [ChartController::class, 'pageUrl']);
        Route::get('me/charts/{chart}/page-urls', [ChartController::class, 'pageUrls']);
        Route::post('me/charts/{chart}/render', [ChartController::class, 'render']);
        Route::get('me/charts/{chart}/pages/{page}/viewport', [ChartPageViewportController::class, 'show']);
        Route::put('me/charts/{chart}/pages/{page}/viewport', [ChartPageViewportController::class, 'update']);
        Route::get('me/charts/{chart}/pages/{page}/annotations/latest', [ChartAnnotationController::class, 'latest']);
        Route::post('me/charts/{chart}/pages/{page}/annotations', [ChartAnnotationController::class, 'store']);
        Route::post('me/charts/{chart}/resolve-duplicate', [ChartDuplicateController::class, 'resolve']);
        Route::post('me/charts/{chart}/adopt', [ChartAdoptionController::class, 'store']);
        Route::delete('me/charts/{chart}', [ChartController::class, 'destroy']);
    });
});
