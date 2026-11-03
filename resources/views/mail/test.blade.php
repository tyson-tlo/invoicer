<x-mail::message>
{{ __('This is a test email from :app.', ['app' => config('app.name')]) }}

{{ __('If you received this, your email provider is connected correctly.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
