<x-mail::message>
# {{ $type === 'feature_request' ? 'Feature Request' : 'Bug Report' }}

**From:** {{ $user->name }} ({{ $user->email }})

**Subject:** {{ $feedbackSubject }}

---

{{ $description }}
</x-mail::message>
