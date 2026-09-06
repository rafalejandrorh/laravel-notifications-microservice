<x-mail::message>
@include('notifications.email.sivacrim-partials.header.v1')

Hola!

Haz recibido este correo porque nosotros recibimos una solicitud para reestablecer la contraseña de tu cuenta.

<x-mail::button :url="$reset_url">
Cambiar contraseña
</x-mail::button>

Este enlace es válido durante los proximos {{ $expire_minutes }} minutos.

Si tú no realizaste la solicitud de reestablecimiento de contraseña, solo ignora este mensaje.

Atentamente, SIVACRIM

@include('notifications.email.sivacrim-partials.footer.v1')

<x-slot:subcopy>
Si tienes problemas para pulsar el botón "Cambiar contraseña", copia y pega esta URL en tu navegador: [{{ $reset_url }}]({{ $reset_url }})
</x-slot:subcopy>
</x-mail::message>
