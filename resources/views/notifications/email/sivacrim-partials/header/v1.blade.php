<div style="text-align: center;">
    <img src="cid:logo_cicpc.png" alt="Logo CICPC" style="width: 90px; height: auto; margin-bottom: 10px">
    <img src="cid:logo_experticias_telecomunicaciones.png" alt="Logo División de Experticias en Telecomunicaciones" style="width: 90px; height: auto; margin-bottom: 10px">
</div>

@if (! empty($institution_name))
# {{ $institution_name }}

---
@endif

@if (! empty($full_name))
## Bienvenido al {{ $full_name }}
@endif
