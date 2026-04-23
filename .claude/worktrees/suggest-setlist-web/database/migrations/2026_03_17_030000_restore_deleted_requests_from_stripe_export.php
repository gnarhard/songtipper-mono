<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\AudienceProfile;
use App\Models\Song;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROJECT_ID = 1;

    private const USER_ID = 1;

    public function up(): void
    {
        if (! DB::table('projects')->where('id', self::PROJECT_ID)->exists()) {
            return;
        }

        $records = [
            ['pi' => 'pi_3TB4wUGpmpwjZSfi0Y5TqTH1', 'amount' => 1000, 'created' => '2026-03-15T03:02:14+00:00', 'ip' => '2600:100e:b08f:931e:f402:ceb9:5978:7a65', 'visitor' => 'd676059c-299e-48a2-b679-cf281b3c5d45', 'tip_only' => true, 'song_title' => null, 'artist' => null, 'fee' => 59, 'net' => 941],
            ['pi' => 'pi_3TB4NHGpmpwjZSfi1oJQteaX', 'amount' => 1000, 'created' => '2026-03-15T02:25:51+00:00', 'ip' => '2a09:bac3:6790:3d7::62:dd', 'visitor' => '9a5911c4-2e90-4470-8379-e18584a64605', 'tip_only' => false, 'song_title' => 'Smells Like Teen Spirit', 'artist' => 'Nirvana', 'fee' => 59, 'net' => 941],
            ['pi' => 'pi_3TB3rdGpmpwjZSfi0NbGyeVL', 'amount' => 700, 'created' => '2026-03-15T01:53:09+00:00', 'ip' => '2a09:bac2:6795:1be1::2c7:53', 'visitor' => '208f1248-0b42-45ab-ac6d-0076ae1d1d3c', 'tip_only' => false, 'song_title' => 'Stand By Me', 'artist' => 'Ben E. King', 'fee' => 50, 'net' => 650],
            ['pi' => 'pi_3TB3niGpmpwjZSfi0i4RxCDe', 'amount' => 500, 'created' => '2026-03-15T01:49:06+00:00', 'ip' => '2600:100e:b241:fcf3:f495:d7d9:741e:f9', 'visitor' => 'a4078b15-bd2e-4869-a487-e4a1677f2c9a', 'tip_only' => false, 'song_title' => 'Something', 'artist' => 'The Beatles', 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB3mqGpmpwjZSfi0UkrRome', 'amount' => 500, 'created' => '2026-03-15T01:48:12+00:00', 'ip' => '2600:100e:b25d:a07:7cca:c8e6:5100:446e', 'visitor' => '96113ad9-95e8-4fa7-8d43-eea9aa677d62', 'tip_only' => false, 'song_title' => 'Closing Time', 'artist' => 'Semisonic', 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB3mEGpmpwjZSfi1Lc2vSH9', 'amount' => 500, 'created' => '2026-03-15T01:47:34+00:00', 'ip' => '2600:100e:b25d:a07:7cca:c8e6:5100:446e', 'visitor' => '96113ad9-95e8-4fa7-8d43-eea9aa677d62', 'tip_only' => false, 'song_title' => 'Creep', 'artist' => 'Radiohead', 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB3hKGpmpwjZSfi18jmXiRr', 'amount' => 500, 'created' => '2026-03-15T01:42:30+00:00', 'ip' => '2600:100e:b241:fcf3:f495:d7d9:741e:f9', 'visitor' => 'a4078b15-bd2e-4869-a487-e4a1677f2c9a', 'tip_only' => false, 'song_title' => 'Country Roads', 'artist' => 'John Denver', 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB3biGpmpwjZSfi1OInxEHX', 'amount' => 500, 'created' => '2026-03-15T01:36:42+00:00', 'ip' => '2a09:bac2:6790:1c64::2d4:6e', 'visitor' => '208f1248-0b42-45ab-ac6d-0076ae1d1d3c', 'tip_only' => false, 'song_title' => 'Wanted Dead or Alive', 'artist' => 'Bon Jovi', 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB3ZeGpmpwjZSfi1WjpMEbt', 'amount' => 500, 'created' => '2026-03-15T01:34:34+00:00', 'ip' => '2a09:bac2:6790:1c64::2d4:6e', 'visitor' => '208f1248-0b42-45ab-ac6d-0076ae1d1d3c', 'tip_only' => false, 'song_title' => 'Even Flow', 'artist' => 'Pearl Jam', 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB3ZPGpmpwjZSfi1qwI8yy7', 'amount' => 500, 'created' => '2026-03-15T01:34:19+00:00', 'ip' => '2600:100e:b241:fcf3:f495:d7d9:741e:f9', 'visitor' => 'a4078b15-bd2e-4869-a487-e4a1677f2c9a', 'tip_only' => false, 'song_title' => 'Layla', 'artist' => 'Derek and the Dominos', 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB2o0GpmpwjZSfi0Ukoxh1b', 'amount' => 500, 'created' => '2026-03-15T00:45:20+00:00', 'ip' => '2600:100e:b095:b9de:80f3:af10:c054:c960', 'visitor' => '7a5981d2-db9c-4801-b48f-2126e4645c8d', 'tip_only' => true, 'song_title' => null, 'artist' => null, 'fee' => 45, 'net' => 455],
            ['pi' => 'pi_3TB2UIGpmpwjZSfi1w7O39LP', 'amount' => 1000, 'created' => '2026-03-15T00:24:58+00:00', 'ip' => '2607:fb90:6d58:83a8:8b9:85d5:2483:db0e', 'visitor' => '2532b6c0-0574-42a3-b730-f993b24592be', 'tip_only' => true, 'song_title' => null, 'artist' => null, 'fee' => 59, 'net' => 941],
            ['pi' => 'pi_3TB1trGpmpwjZSfi0RsMoO9f', 'amount' => 500, 'created' => '2026-03-14T23:47:19+00:00', 'ip' => '50.237.200.190', 'visitor' => 'eddf3cb4-4527-4f4c-bfa7-f9c4ce82e2bb', 'tip_only' => true, 'song_title' => null, 'artist' => null, 'fee' => 43, 'net' => 457],
            ['pi' => 'pi_3T9zCtGpmpwjZSfi0RkAEgqc', 'amount' => 1100, 'created' => '2026-03-12T02:42:39+00:00', 'ip' => '2600:387:15:4d1a::3', 'visitor' => 'e60d94b9-46a2-48a3-836c-2e406eba7732', 'tip_only' => false, 'song_title' => "Can't You See", 'artist' => 'Marshall Tucker Band', 'fee' => 62, 'net' => 1038],
            ['pi' => 'pi_3T9zBnGpmpwjZSfi01jMzpM6', 'amount' => 1000, 'created' => '2026-03-12T02:41:31+00:00', 'ip' => '2607:fb90:6d2f:c3f9:4cd1:dc86:db3b:4fa6', 'visitor' => '208f9939-1cda-48fa-8095-de9edb37dfab', 'tip_only' => false, 'song_title' => 'Country Roads', 'artist' => 'John Denver', 'fee' => 59, 'net' => 941],
            ['pi' => 'pi_3T9we4GpmpwjZSfi1HVzVFkG', 'amount' => 1000, 'created' => '2026-03-11T23:58:32+00:00', 'ip' => '50.237.200.190', 'visitor' => 'c430eafd-d622-4d48-b213-25ce8733f258', 'tip_only' => false, 'song_title' => 'Like a Stone', 'artist' => 'Audioslave', 'fee' => 59, 'net' => 941],
            ['pi' => 'pi_3T9wacGpmpwjZSfi1T1fdTKc', 'amount' => 500, 'created' => '2026-03-11T23:54:58+00:00', 'ip' => '2600:100e:b25d:27c0:f4ed:dcaf:df55:9ce1', 'visitor' => '145f7787-c598-4f75-98db-8b19a0b48125', 'tip_only' => false, 'song_title' => "Free Fallin'", 'artist' => 'Tom Petty & The Heartbreakers', 'fee' => 45, 'net' => 455],
        ];

        $existingPIs = DB::table('requests')
            ->where('project_id', self::PROJECT_ID)
            ->whereNotNull('payment_intent_id')
            ->pluck('payment_intent_id')
            ->flip()
            ->all();

        foreach ($records as $record) {
            if (isset($existingPIs[$record['pi']])) {
                continue;
            }

            if ($record['tip_only']) {
                $song = Song::tipJarSupportSong();
            } else {
                $song = Song::findOrCreateByTitleAndArtist($record['song_title'], $record['artist']);
            }

            $createdAt = CarbonImmutable::parse($record['created']);

            $audienceProfileId = null;
            if ($record['visitor'] !== '') {
                $profile = AudienceProfile::query()->firstOrCreate(
                    [
                        'project_id' => self::PROJECT_ID,
                        'visitor_token' => $record['visitor'],
                    ],
                    [
                        'display_name' => 'Audience Member',
                        'last_seen_ip' => $record['ip'],
                        'last_seen_at' => $createdAt,
                    ],
                );
                $audienceProfileId = $profile->id;
            }

            $id = DB::table('requests')->insertGetId([
                'project_id' => self::PROJECT_ID,
                'audience_profile_id' => $audienceProfileId,
                'performance_session_id' => null,
                'song_id' => $song->id,
                'tip_amount_cents' => $record['amount'],
                'score_cents' => $record['amount'],
                'status' => RequestStatus::Played->value,
                'requested_from_ip' => $record['ip'],
                'payment_provider' => 'stripe',
                'payment_intent_id' => $record['pi'],
                'stripe_fee_amount_cents' => $record['fee'],
                'stripe_net_amount_cents' => $record['net'],
                'played_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    public function down(): void
    {
        $paymentIntentIds = [
            'pi_3TB4wUGpmpwjZSfi0Y5TqTH1',
            'pi_3TB4NHGpmpwjZSfi1oJQteaX',
            'pi_3TB3rdGpmpwjZSfi0NbGyeVL',
            'pi_3TB3niGpmpwjZSfi0i4RxCDe',
            'pi_3TB3mqGpmpwjZSfi0UkrRome',
            'pi_3TB3mEGpmpwjZSfi1Lc2vSH9',
            'pi_3TB3hKGpmpwjZSfi18jmXiRr',
            'pi_3TB3biGpmpwjZSfi1OInxEHX',
            'pi_3TB3ZeGpmpwjZSfi1WjpMEbt',
            'pi_3TB3ZPGpmpwjZSfi1qwI8yy7',
            'pi_3TB2o0GpmpwjZSfi0Ukoxh1b',
            'pi_3TB2UIGpmpwjZSfi1w7O39LP',
            'pi_3TB1trGpmpwjZSfi0RsMoO9f',
            'pi_3T9zCtGpmpwjZSfi0RkAEgqc',
            'pi_3T9zBnGpmpwjZSfi01jMzpM6',
            'pi_3T9we4GpmpwjZSfi1HVzVFkG',
            'pi_3T9wacGpmpwjZSfi1T1fdTKc',
        ];

        DB::table('requests')
            ->where('project_id', self::PROJECT_ID)
            ->whereIn('payment_intent_id', $paymentIntentIds)
            ->delete();
    }
};
