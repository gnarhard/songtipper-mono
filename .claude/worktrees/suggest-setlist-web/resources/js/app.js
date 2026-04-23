import './bootstrap';

import Sortable from 'sortablejs';
window.Sortable = Sortable;

/**
 * Long-press drag-to-reorder for the admin checklist.
 *
 * The Blade view stamps each <ul> with `x-data="checklistSortable(...)"`.
 * We register the component on `alpine:init` so it is defined before Alpine
 * evaluates any `x-data` on the page.
 *
 * Clicks on the checkbox and edit/delete buttons are preserved because the
 * 300ms `delay` means drag only activates after a sustained press, and the
 * `filter` option explicitly excludes interactive controls from drag starts.
 */
/**
 * Force every checkbox in a container to match the `checked` HTML attribute
 * that the server rendered. Browsers (especially Safari and Firefox) will
 * restore the previous session's `.checked` DOM property on back/forward
 * navigation and bfcache restores, which can leave boxes visually stale
 * after logout → login even though the server state is correct.
 *
 * @param {HTMLElement} container
 */
function syncCheckboxPropertyToAttribute(container) {
    container.querySelectorAll('input[type="checkbox"]').forEach((box) => {
        box.checked = box.hasAttribute('checked');
    });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('checklistSortable', (sectionSlug, subsectionSlug) => ({
        init() {
            const container = this.$el;
            const wire = this.$wire;

            syncCheckboxPropertyToAttribute(container);

            window.Sortable.create(container, {
                delay: 300,
                delayOnTouchOnly: false,
                animation: 150,
                draggable: 'li[data-reorder-id]',
                filter: 'input, button, textarea, a, .no-drag',
                preventOnFilter: false,
                ghostClass: 'opacity-40',
                chosenClass: 'ring-2',
                onEnd: () => {
                    const orderedIds = Array.from(
                        container.querySelectorAll('li[data-reorder-id]'),
                    )
                        .map((node) => Number(node.dataset.reorderId))
                        .filter((id) => Number.isFinite(id) && id > 0);

                    if (orderedIds.length === 0) {
                        return;
                    }

                    wire.reorderItems(sectionSlug, subsectionSlug, orderedIds);
                },
            });
        },
    }));
});

/**
 * `pageshow` fires on every navigation INTO the page, including bfcache
 * restores (when `event.persisted === true`). At that point Alpine's init
 * won't re-run, so we walk every checklist <ul> and re-sync. Safe to run
 * on normal loads too — it's a no-op when the DOM already matches.
 */
window.addEventListener('pageshow', () => {
    document
        .querySelectorAll('[data-test^="checklist-items-list-"]')
        .forEach((list) => syncCheckboxPropertyToAttribute(list));
});
