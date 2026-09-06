@include('notifications.email.sivacrim-partials.header.v1')

<p>{{ $primer_nombre }}, Su código único de Inicio de Sesión es: <strong>{{ $code }}</strong>.</p>

@include('notifications.email.sivacrim-partials.footer.v1')
