<x-mail::message>
@include('notifications.email.sivacrim-partials.header.v1')

Su código de validación es:

<x-mail::panel>
{{ $code }}
</x-mail::panel>

**Nota:** No archive ni elimine este correo hasta que reciba la llamada pertinente de la División de Experticias en Telecomunicaciones.

@include('notifications.email.sivacrim-partials.footer.v1')
</x-mail::message>
