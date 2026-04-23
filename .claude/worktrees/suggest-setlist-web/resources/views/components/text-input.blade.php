@props(['disabled' => false])

<input @disabled($disabled)
  @if ($attributes->get('type') === 'email')
    oninput="this.value = this.value.toLowerCase().trim()"
  @endif
  {{ $attributes->merge(['class' => 'rounded-xl border border-ink-border-dark/50 bg-surface px-3 py-2 text-sm text-ink shadow-sm placeholder:text-ink-muted focus:border-ink-border-dark focus:ring-2 focus:ring-ink/20 dark:border-ink-border-dark/80 dark:bg-surface-inverse dark:text-ink-inverse dark:placeholder:text-ink-soft dark:focus:border-ink-border dark:focus:ring-ink-inverse/20']) }}>


