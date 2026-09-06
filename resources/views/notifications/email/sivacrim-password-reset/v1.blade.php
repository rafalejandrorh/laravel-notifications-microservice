@include('notifications.email.sivacrim-partials.header.v1')

<p>Hola!</p>

<p>Haz recibido este correo porque nosotros recibimos una solicitud para reestablecer la contraseña de tu cuenta.</p>

<p><a href="{{ $reset_url }}">Cambiar contraseña</a></p>

<p>Este enlace es válido durante los proximos {{ $expire_minutes }} minutos.</p>

<p>Si tú no realizaste la solicitud de reestablecimiento de contraseña, solo ignora este mensaje.</p>

<p>Atentamente, SIVACRIM</p>

@include('notifications.email.sivacrim-partials.footer.v1')
