@include('notifications.email.sivacrim-partials.header.v1-text')

Hola!

Haz recibido este correo porque nosotros recibimos una solicitud para reestablecer la contraseña de tu cuenta.

Cambiar contraseña: {{ $reset_url }}

Este enlace es válido durante los proximos {{ $expire_minutes }} minutos.

Si tú no realizaste la solicitud de reestablecimiento de contraseña, solo ignora este mensaje.

Atentamente, SIVACRIM

@include('notifications.email.sivacrim-partials.footer.v1-text')
