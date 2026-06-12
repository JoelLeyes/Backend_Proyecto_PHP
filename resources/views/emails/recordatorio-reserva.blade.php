<!DOCTYPE html>
<html lang="es">
<head>
<title>Recordatorio de tu reserva</title>
</head>
<body>
<h2>Falta una hora para tu reserva</h2>
<p>Hola <strong>{{ $reserva->cliente->name }}</strong>,</p>
<p>Te enviamos este aviso porque falta una hora para tu turno:</p>
<ul>
    <li><strong>Servicio:</strong> {{ $reserva->servicio->nombre }}</li>
    <li><strong>Fecha y hora:</strong> {{ $fechaHora }}</li>
    <li><strong>Profesional:</strong> {{ $reserva->profesional->name }}</li>
    <li><strong>Modalidad:</strong> {{ $reserva->modalidad }}</li>
</ul>
<p>Podés revisar los detalles desde tu panel de reservas.</p>
<p>¡Nos vemos pronto!</p>
</body>
</html>
