<x-mail::message>
@include('notifications.email.sivacrim-partials.header.v1')

{{ $primer_nombre }}, su código único de inicio de sesión es:

<x-mail::panel>
{{ $code }}
</x-mail::panel>

@include('notifications.email.sivacrim-partials.footer.v1')
</x-mail::message>
