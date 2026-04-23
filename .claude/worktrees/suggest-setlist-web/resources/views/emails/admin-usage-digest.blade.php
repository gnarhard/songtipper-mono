<x-mail::message>
# Weekly admin usage digest

Generated: {{ data_get($payload, 'generated_at') }}

Top storage accounts: {{ count((array) data_get($payload, 'top_storage_users', [])) }}

Top AI accounts: {{ count((array) data_get($payload, 'top_ai_users', [])) }}

Open flags: {{ count((array) data_get($payload, 'open_flags', [])) }}

Current storage: {{ number_format(((int) data_get($payload, 'margin_risk_summary.current_storage_bytes', 0)) / 1024 / 1024 / 1024, 2) }} GB

Monthly AI cost estimate: ${{ number_format(((int) data_get($payload, 'margin_risk_summary.monthly_ai_cost_micros', 0)) / 1000000, 2) }}

Monthly bandwidth estimate: {{ number_format(((int) data_get($payload, 'margin_risk_summary.monthly_bandwidth_bytes', 0)) / 1024 / 1024 / 1024, 2) }} GB

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
