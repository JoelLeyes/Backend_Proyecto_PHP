<!DOCTYPE html>
<html lang="es">
<head>
<title>Recordatorio de tu reserva</title>
</head>
<body>
<h2>Tu cita es en {{ $horas }} horas</h2>
<p>Hola <strong>{{ $reserva->cliente->name }}</strong>,</p>
<p>Te avisamos con anticipación sobre tu próximo turno:</p>
<ul>
    <li><strong>Servicio:</strong> {{ $reserva->servicio->nombre }}</li>
    <li><strong>Fecha y hora:</strong> {{ $fechaHora }}</li>
    <li><strong>Profesional:</strong> {{ $reserva->profesional->name }}</li>
    <li><strong>Modalidad:</strong> {{ $reserva->modalidad }}</li>
</ul>
<p>Si necesitás cancelar o reprogramar, hacelo desde tu panel antes de que sea demasiado tarde.</p>
<p>¡Nos vemos pronto!</p>
</body>
</html>
