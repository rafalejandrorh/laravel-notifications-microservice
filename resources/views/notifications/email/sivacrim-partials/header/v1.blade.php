<div style="text-align: center; font-family: sans-serif;">
    @if (! empty($logo_cicpc_url))
        <img src="{{ $logo_cicpc_url }}" alt="Logo CICPC" style="width: 90px; height: auto; margin-bottom: 10px">
    @endif
    @if (! empty($logo_division_url))
        <img src="{{ $logo_division_url }}" alt="Logo División de Experticias en Telecomunicaciones" style="width: 90px; height: auto; margin-bottom: 10px">
    @endif
    @if (! empty($institution_name))
        <h1 style="text-align: center; font-size: 20px;">{{ $institution_name }}</h1>
        <hr>
    @endif
    @if (! empty($full_name))
        <h2 style="text-align: center; font-size: 16px;">Bienvenido al {{ $full_name }}</h2>
    @endif
</div>
