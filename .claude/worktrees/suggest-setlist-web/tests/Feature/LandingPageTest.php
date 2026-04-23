<?php

declare(strict_types=1);

use App\Mail\ContactFormSubmission;
use App\Models\Project;
use App\Support\BillingPlanCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

describe('Home Page', function () {
    it('displays the landing page successfully', function () {
        $this->get(route('home'))
            ->assertSuccessful()
            ->assertSee('Get Paid More to Play')
            ->assertSee('Simple, Transparent Pricing')
            ->assertSee('Find a Performer')
            ->assertSee('images/song_tipper_logo_light.png', false)
            ->assertSee('images/song_tipper_logo_dark.png', false)
            ->assertSee('favicon-light-32x32.png', false)
            ->assertSee('favicon-dark-32x32.png', false)
            ->assertSee('prefers-color-scheme: light', false)
            ->assertSee('content="'.config('songtipper_theme.meta.light').'"', false)
            ->assertSee('content="'.config('songtipper_theme.meta.dark').'"', false)
            ->assertSee('bg-gradient-to-br from-white/30 via-surface to-accent-50/50', false)
            ->assertSee('dark:from-canvas-dark dark:via-surface-inverse dark:to-brand-900', false)
            ->assertSee('var(--st-success-container)', false)
            ->assertSee('var(--st-on-success-container)', false)
            ->assertSee('bg-surface-muted', false);
    });

    it('shows pricing section with the current billing catalog', function () {
        $response = $this->get(route('home'));

        $response->assertSuccessful()
            ->assertSee('Simple, Transparent Pricing')
            ->assertSee('We don\'t get paid until after you get paid')
            ->assertSee('We collect no fees for tips you earn through this platform. All money goes directly to you.')
            ->assertSee('No credit card at signup. Billing only starts after you', false);

        foreach (app(BillingPlanCatalog::class)->allTierGroups() as $group) {
            $response
                ->assertSee($group['label'])
                ->assertSee($group['description']);

            // The landing page renders one pricing card per tier group,
            // showing only the first plan in the group. Interval suffixes
            // are stripped and the interval label is shown separately.
            $headlinePlan = $group['plans'][0];
            $displayPrice = str_replace(['/mo', '/year'], '', $headlinePlan['price_label']);
            $response
                ->assertSee($displayPrice)
                ->assertSee($headlinePlan['interval_label']);
        }
    });

    it('shows features section', function () {
        $this->get(route('home'))
            ->assertSuccessful()
            ->assertSee('Accept Requests')
            ->assertSee('Real-time Queue')
            ->assertSee('Chart Library')
            ->assertSee('Gamified Tipping')
            ->assertDontSee('Competitive Tipping');
    });

    it('shows about the creator section', function () {
        $this->get(route('home'))
            ->assertSuccessful()
            ->assertSee('About the Creator')
            ->assertSee('Built by Grayson Erhard')
            ->assertSee('Song Tipper is the tool I wish');
    });

    it('shows contact form section', function () {
        $this->get(route('home'))
            ->assertSuccessful()
            ->assertSee('Questions? Get in Touch');
    });

    it('has navigation links for guests', function () {
        $this->get(route('home'))
            ->assertSuccessful()
            ->assertSee('Login')
            ->assertSee('Get Started');
    });

    it('has footer with legal links', function () {
        $this->get(route('home'))
            ->assertSuccessful()
            ->assertSee('Terms of Service')
            ->assertSee('Privacy Policy')
            ->assertSee('EULA');
    });
});

describe('Legal Pages', function () {
    it('displays the terms of service page', function () {
        $this->get(route('terms'))
            ->assertSuccessful()
            ->assertSee('Terms of Service')
            ->assertSee('Acceptance of Terms');
    });

    it('displays the privacy policy page', function () {
        $this->get(route('privacy'))
            ->assertSuccessful()
            ->assertSee('Privacy Policy')
            ->assertSee('Information We Collect');
    });

    it('displays the EULA page', function () {
        $this->get(route('eula'))
            ->assertSuccessful()
            ->assertSee('End User License Agreement')
            ->assertSee('License Grant');
    });

    it('legal pages have navigation back to home', function () {
        $this->get(route('terms'))->assertSee(config('app.name'));
        $this->get(route('privacy'))->assertSee(config('app.name'));
        $this->get(route('eula'))->assertSee(config('app.name'));
    });
});

describe('Contact Form', function () {
    // The contact-form component has bot protection that discards submissions
    // made within 3 seconds of mount. This helper mounts the component, then
    // advances time past the threshold so tests behave like real users.
    function mountContactForm(): Testable
    {
        $component = Livewire::test('contact-form');
        test()->travel(5)->seconds();

        return $component;
    }

    it('validates required fields', function () {
        mountContactForm()
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'message']);
    });

    it('validates email format', function () {
        mountContactForm()
            ->set('name', 'John Doe')
            ->set('email', 'invalid-email')
            ->set('message', 'This is a test message that is long enough')
            ->call('submit')
            ->assertHasErrors(['email']);
    });

    it('validates minimum message length', function () {
        mountContactForm()
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Short')
            ->call('submit')
            ->assertHasErrors(['message']);
    });

    it('sends email on successful submission', function () {
        Mail::fake();

        mountContactForm()
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'This is a test message that is long enough to pass validation.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        Mail::assertSent(ContactFormSubmission::class, function ($mail) {
            return $mail->name === 'John Doe'
                && $mail->email === 'john@example.com'
                && $mail->messageContent === 'This is a test message that is long enough to pass validation.';
        });
    });

    it('shows success message after submission', function () {
        Mail::fake();

        mountContactForm()
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'This is a test message that is long enough to pass validation.')
            ->call('submit')
            ->assertSee('Message Sent!')
            ->assertSee('Thank you for reaching out');
    });

    it('resets form fields after submission', function () {
        Mail::fake();

        mountContactForm()
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'This is a test message that is long enough to pass validation.')
            ->call('submit')
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('message', '');
    });

    it('can send another message after first submission', function () {
        Mail::fake();

        mountContactForm()
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'This is a test message that is long enough to pass validation.')
            ->call('submit')
            ->assertSet('submitted', true)
            ->set('submitted', false)
            ->assertSet('submitted', false);
    });
});

describe('Performer Search', function () {
    it('shows featured projects for accepting performers', function () {
        Cache::flush();

        $featuredProject = Project::factory()->create([
            'name' => 'Featured Performer',
        ]);

        Project::factory()->notAcceptingRequests()->create([
            'name' => 'Hidden Performer',
        ]);

        Livewire::test('performer-search')
            ->assertSee('Featured Projects')
            ->assertSee($featuredProject->name)
            ->assertDontSee('Hidden Performer');
    });

    it('can search by performer name, slug, and owner name', function () {
        $ownerMatchedProject = Project::factory()->create([
            'name' => 'Downtown Lights',
            'slug' => 'downtown-lights',
        ]);

        $slugMatchedProject = Project::factory()->create([
            'name' => 'Blue Echo',
            'slug' => 'late-night-dj',
        ]);

        $ownerNameMatchedProject = Project::factory()->create([
            'name' => 'Sunset Riders',
            'slug' => 'sunset-riders',
        ]);

        $ownerNameMatchedProject->owner->forceFill([
            'name' => 'Casey Soundcheck',
        ])->save();

        Livewire::test('performer-search')
            ->set('search', 'Downtown')
            ->assertSee($ownerMatchedProject->name)
            ->assertDontSee($slugMatchedProject->name)
            ->assertDontSee($ownerNameMatchedProject->name)
            ->set('search', 'late-night')
            ->assertSee($slugMatchedProject->name)
            ->assertDontSee($ownerMatchedProject->name)
            ->assertDontSee($ownerNameMatchedProject->name)
            ->set('search', 'Casey')
            ->assertSee($ownerNameMatchedProject->name)
            ->assertDontSee($ownerMatchedProject->name)
            ->assertDontSee($slugMatchedProject->name);
    });
});
