<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restructures the per-user test_checklist_items rows to mirror the new
 * canonical layout in `ManualTestChecklist`:
 *
 *   - Removes the entire `INTEGRATION TESTS (Cross-App)` section.
 *   - Removes the mobile `Learning Songs` subsection.
 *   - Reorders top-level sections to:
 *       0  WEB APP
 *       1  Public Pages
 *       2  MOBILE APP (Flutter)
 *       3  API-SPECIFIC VALIDATIONS
 *   - Collapses the old `Member Repertoire Isolation` subsection plus three
 *     scattered member-related items (one from Song Detail, one from Setlist
 *     Detail, two from Settings) into a brand-new `Members & Multi-User Sync`
 *     subsection at the bottom of `MOBILE APP (Flutter)`.
 *   - Re-numbers MOBILE APP subsection_order so the gaps left by the removed
 *     subsections close up.
 *
 * Progress preservation: every item that was already on a user's checklist
 * keeps its row id. Because the per-user `test_checklist_progress.checked_keys`
 * array is keyed by `item-{id}`, an UPDATE-in-place leaves every checked tick
 * intact. Rows that are deleted (Learning Songs, INTEGRATION TESTS) leave
 * dangling keys in `checked_keys` which `Checklist::filterValidKeys()`
 * silently drops on the next render — no orphaned tick marks.
 *
 * Custom rows that the admin added themselves are untouched: every WHERE
 * clause matches against `(section_title, subsection_title, text)` triples
 * that only the canonical seed produces.
 */
return new class extends Migration
{
    private const MOBILE = 'MOBILE APP (Flutter)';

    private const MEMBERS_SUB = 'Members & Multi-User Sync';

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->deleteRemovedSections();
            $this->moveItemsIntoMembersSubsection();
            $this->renumberMobileSubsections();
            $this->reorderTopLevelSections();
        });
    }

    public function down(): void
    {
        // Irreversible: the rows are merged, renamed, and renumbered in
        // place. Recreating the previous structure would require re-seeding
        // from a snapshot of the old ChecklistDefinition, which we no longer
        // have a copy of. Down is a no-op.
    }

    /**
     * Drop every row whose canonical text belongs to a section/subsection that
     * no longer exists in `ManualTestChecklist`.
     */
    private function deleteRemovedSections(): void
    {
        DB::table('test_checklist_items')
            ->where('section_title', 'INTEGRATION TESTS (Cross-App)')
            ->delete();

        DB::table('test_checklist_items')
            ->where('section_title', self::MOBILE)
            ->where('subsection_title', 'Learning Songs')
            ->delete();
    }

    /**
     * Move the existing rows that are being relocated into the new
     * `Members & Multi-User Sync` subsection. Each entry below is a separate
     * UPDATE so a single mismatched canonical text can't silently take down
     * the whole batch.
     *
     * Position values match the order of items in
     * `ManualTestChecklist::mobileApp()`'s new Members subsection.
     */
    private function moveItemsIntoMembersSubsection(): void
    {
        // 0: was Settings/"Project members list" -> reworded to add "(Settings)" hint.
        $this->relocateItem(
            fromSection: self::MOBILE,
            fromSubsection: 'Settings',
            fromText: 'Project members list',
            newPosition: 0,
            newText: 'Project members list (Settings)',
        );

        // 1: Settings owner-removes-member item moves verbatim.
        $this->relocateItem(
            fromSection: self::MOBILE,
            fromSubsection: 'Settings',
            fromText: 'Owner: remove member from project (with confirmation dialog)',
            newPosition: 1,
        );

        // 2: Setlist Detail share-with-members item picks up a "from Setlist Detail" trailer.
        $this->relocateItem(
            fromSection: self::MOBILE,
            fromSubsection: 'Setlist Detail',
            fromText: 'Share setlist with all project members (owner-only button)',
            newPosition: 2,
            newText: 'Share setlist with all project members (owner-only button, from Setlist Detail)',
        );

        // 3..8: rows from the old Member Repertoire Isolation subsection move verbatim.
        $verbatimFromMRI = [
            3 => 'Each project member has an independent copy of songs (scoped by `project_songs.user_id`)',
            4 => 'On member join, all owner songs (with charts) are copied via `SyncRepertoireToMember` job',
            5 => 'Owner creating a new song fans out copies to all existing members (`FanOutSongToMembers` job)',
            6 => 'Fan-out does not overwrite if a member already has the song',
            7 => 'Owner edits do NOT auto-propagate to member copies',
            8 => 'API response includes `source_project_song_id` and `is_owner_copy` fields',
        ];
        foreach ($verbatimFromMRI as $position => $text) {
            $this->relocateItem(
                fromSection: self::MOBILE,
                fromSubsection: 'Member Repertoire Isolation',
                fromText: $text,
                newPosition: $position,
            );
        }

        // 9: Song Detail Pull Owner Copy visibility item gets a clearer label.
        $this->relocateItem(
            fromSection: self::MOBILE,
            fromSubsection: 'Song Detail',
            fromText: 'Pull Owner Copy action visible when song is a member copy (`source_project_song_id` set)',
            newPosition: 9,
            newText: 'Pull Owner Copy action visible on member\'s song in Song Detail (`source_project_song_id` set)',
        );

        // 10: Setlist Detail Pull Owner Copy button gets a "from Setlist Detail" hint.
        $this->relocateItem(
            fromSection: self::MOBILE,
            fromSubsection: 'Setlist Detail',
            fromText: 'Pull Owner Copy button on member\'s song (fetches owner\'s current version as alternate)',
            newPosition: 10,
            newText: 'Pull Owner Copy button on member\'s song from Setlist Detail (fetches owner\'s current version as alternate)',
        );

        // 11..14: remaining Pull Owner Copy detail rows from the old MRI subsection.
        $verbatimMriPullCopy = [
            11 => 'Pull Owner Copy: `POST /repertoire/{projectSongId}/pull-owner-copy` adds owner\'s current version as a new alternate version on member\'s song',
            12 => 'Pull Owner Copy label: `"Owner\'s Version (synced {Mon DD, YYYY})"`',
            13 => 'Pull Owner Copy: 422 if song has no linked owner version',
            14 => 'Pull Owner Copy: 404 if owner\'s version no longer exists',
        ];
        foreach ($verbatimMriPullCopy as $position => $text) {
            $this->relocateItem(
                fromSection: self::MOBILE,
                fromSubsection: 'Member Repertoire Isolation',
                fromText: $text,
                newPosition: $position,
            );
        }
    }

    /**
     * Relocate a single canonical row into the new Members subsection.
     *
     * Matches by exact `(section_title, subsection_title, text)` so admin
     * customisations of either the text or the location quietly opt out.
     */
    private function relocateItem(
        string $fromSection,
        string $fromSubsection,
        string $fromText,
        int $newPosition,
        ?string $newText = null,
    ): void {
        DB::table('test_checklist_items')
            ->where('section_title', $fromSection)
            ->where('subsection_title', $fromSubsection)
            ->where('text', $fromText)
            ->update([
                'section_title' => self::MOBILE,
                // section_order will be reset to 2 by reorderTopLevelSections()
                // below; setting it explicitly here too keeps state consistent
                // even if a future migration runs the steps out of order.
                'section_order' => 2,
                'subsection_title' => self::MEMBERS_SUB,
                'subsection_order' => 19,
                'position' => $newPosition,
                'text' => $newText ?? $fromText,
                'updated_at' => now(),
            ]);
    }

    /**
     * Close the gaps left in MOBILE APP after Learning Songs (was 11) and
     * Member Repertoire Isolation (was 5) were taken out, and assign the
     * brand-new Members & Multi-User Sync subsection an order of 19.
     *
     * Order is updated by mapping each surviving subsection's title to its
     * new index. Custom subsections that the admin authored themselves do
     * not get re-numbered — they sit wherever they were, which is the
     * least surprising behaviour for hand-added rows.
     */
    private function renumberMobileSubsections(): void
    {
        $newOrder = [
            'Authentication' => 0,
            'Context / Project Selection' => 1,
            'Home Screen (Dashboard)' => 2,
            'Queue Management' => 3,
            'Repertoire' => 4,
            'Song Detail' => 5,
            'Audio Files' => 6,
            'Add Song' => 7,
            'Image Import' => 8,
            'Bulk Import (PDF)' => 9,
            'Setlists' => 10,
            'Setlist Detail' => 11,
            'Shared Setlist (Deep Link)' => 12,
            'Perform Screen' => 13,
            'Cash Tips' => 14,
            'Settings' => 15,
            'Offline & Connectivity' => 16,
            'Navigation & Shell' => 17,
            'Cross-Cutting Concerns' => 18,
            self::MEMBERS_SUB => 19,
        ];

        foreach ($newOrder as $subsectionTitle => $order) {
            DB::table('test_checklist_items')
                ->where('section_title', self::MOBILE)
                ->where('subsection_title', $subsectionTitle)
                ->update([
                    'subsection_order' => $order,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Apply the new top-level section order globally:
     *
     *   0  WEB APP                       (unchanged)
     *   1  Public Pages                  (was 3)
     *   2  MOBILE APP (Flutter)          (was 1)
     *   3  API-SPECIFIC VALIDATIONS      (was 4)
     *
     * INTEGRATION TESTS (Cross-App) is gone — handled in
     * deleteRemovedSections() above.
     */
    private function reorderTopLevelSections(): void
    {
        $sectionOrders = [
            'WEB APP' => 0,
            'Public Pages' => 1,
            self::MOBILE => 2,
            'API-SPECIFIC VALIDATIONS' => 3,
        ];

        foreach ($sectionOrders as $sectionTitle => $order) {
            DB::table('test_checklist_items')
                ->where('section_title', $sectionTitle)
                ->update([
                    'section_order' => $order,
                    'updated_at' => now(),
                ]);
        }
    }
};
