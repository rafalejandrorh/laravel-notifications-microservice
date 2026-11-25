<x-mail::message>
Recibimos una solicitud para restablecer tu contraseña.

<x-mail::button :url="$reset_url">
Restablecer contraseña
</x-mail::button>

<x-slot:subcopy>
Si tienes problemas para pulsar el botón "Restablecer contraseña", copia y pega esta URL en tu navegador: [{{ $reset_url }}]({{ $reset_url }})
</x-slot:subcopy>
</x-mail::message>
