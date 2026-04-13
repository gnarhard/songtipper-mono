# Presentation Boundary Rules

These rules are repo-specific and are stricter than the generic Flutter
guidance because this app has already accumulated large screens with mixed UI
and application logic.

## Hard boundaries

- Code under `lib/**/presentation/` is for rendering, ephemeral widget state,
  and collecting user input.
- Presentation code must not import from `lib/**/data/` or
  `lib/core/outbox/`.
- If a screen needs repository-backed lookup, upload staging, sync decisions,
  batching, or cross-feature orchestration, move that work into
  `controller/`, `domain/`, or another non-presentation layer first.

## Preferred shape

- Expose controller methods that match user intents, such as
  `createSetWithSongs`, `ensureSongsLoaded`, or `queueChartUpload`, instead of
  chaining multiple repository calls inside widgets.
- Keep widgets responsible for:
  - rendering
  - short-lived local state
  - dialog presentation
  - navigation triggers
  - forwarding user actions to controllers
- Keep controllers or services responsible for:
  - repository access
  - sorting/filtering that affects application behavior
  - derived view data that is shared by multiple screens
  - offline/outbox decisions
  - chart lookup, upload, caching, or sync workflows

## Refactor rule

- When a presentation file becomes large, first remove data-layer and outbox
  dependencies before splitting widgets further.
- Add or update unit tests around the new controller/service boundary whenever
  logic is moved out of a screen.

## Filter panel toggle pattern

Screens with filterable lists hide the filter panel behind an app-bar action
button. Follow this pattern consistently:

- **App-bar button**: use an `_AppBarTextAction`-style widget (icon + label
  stacked vertically, fixed ~56 px width, `TextButton` base). Use
  `Icons.tune` and the label `'Filters'`. Highlight the icon and label with
  `colorScheme.primary` when any filter is non-default.
- **State**: a single `bool _showFilters = false` on the screen's `State`.
  Toggle it with `setState` in the button's `onPressed`.
- **Animated reveal**: wrap the filter section in
  `AnimatedSize(duration: 200 ms, curve: Curves.easeInOut, alignment:
  Alignment.topCenter)` switching between the full panel and
  `SizedBox.shrink()`.
- **Rotation affordance**: wrap the `Icons.tune` in
  `AnimatedRotation(turns: _showFilters ? 0.5 : 0.0)` so the icon flips
  upside-down when filters are open, giving a clear open/close signal.
- **Ownership**: filter visibility is ephemeral UI state owned by the screen,
  not by the controller. The `showFilters` bool is passed down to the child
  tab/widget as a constructor parameter.

Screens currently following this pattern:
`RepertoireListScreen`, `PerformScreen` (Previous tab).
