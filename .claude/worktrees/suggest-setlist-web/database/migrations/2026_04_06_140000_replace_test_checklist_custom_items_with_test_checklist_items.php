<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the additive `test_checklist_custom_items` table with a unified
 * per-user `test_checklist_items` table.
 *
 * Every checklist item — built-in or admin-authored — now lives in this table,
 * scoped to a single user. The Volt component seeds the canonical defaults
 * from `ChecklistDefinition` on first visit, after which the admin owns
 * the rows and can edit, delete, or append at will.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('test_checklist_custom_items');

        Schema::create('test_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('section_title', 100);
            $table->unsignedSmallInteger('section_order');
            $table->string('subsection_title', 200);
            $table->unsignedSmallInteger('subsection_order');
            $table->unsignedSmallInteger('position');
            $table->string('text', 500);
            $table->timestamps();

            // Composite index supports the canonical "load all rows for this
            // user, ordered for grouping" query the component issues on every
            // render. Order columns sit ahead of `position` so the database
            // can satisfy the ORDER BY without a filesort.
            $table->index(
                ['user_id', 'section_order', 'subsection_order', 'position'],
                'test_checklist_items_user_order_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_checklist_items');

        Schema::create('test_checklist_custom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('section_slug', 100)->default('');
            $table->string('text', 500);
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'section_slug']);
        });
    }
};
