@component('mail::message')
# Low Balance Alert

AsanFinance account balance is critically low.

**Current balance: {{ $balance }} AZN**

Please top up the account immediately to avoid service interruption.

Thanks,
{{ config('app.name') }}
@endcomponent
