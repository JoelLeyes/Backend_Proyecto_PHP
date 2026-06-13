<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu cita es en {{ $horas }} horas</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f172a,#2563eb);padding:28px 32px;color:#fff;">
                            <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.8;">AgendaOnline</div>
                            <h1 style="margin:10px 0 0;font-size:28px;line-height:1.2;">Tu cita es en {{ $horas }} horas</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.6;">
                                Hola <strong>{{ $reserva->cliente->name }}</strong>, te avisamos con anticipación para que puedas organizarte:
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;border-radius:12px;padding:20px;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:6px 0;font-size:15px;">
                                        <span style="color:#6b7280;">Servicio</span><br>
                                        <strong>{{ $reserva->servicio->nombre }}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;font-size:15px;border-top:1px solid #e5e7eb;">
                                        <span style="color:#6b7280;">Fecha y hora</span><br>
                                        <strong>{{ $fechaHora }}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;font-size:15px;border-top:1px solid #e5e7eb;">
                                        <span style="color:#6b7280;">Profesional</span><br>
                                        <strong>{{ $reserva->profesional->name }}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;font-size:15px;border-top:1px solid #e5e7eb;">
                                        <span style="color:#6b7280;">Modalidad</span><br>
                                        <strong>{{ ucfirst($reserva->modalidad) }}</strong>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#374151;">
                                Si necesitás cancelar o reprogramar, hacelo desde tu panel antes de que venza el plazo.
                            </p>
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:#2563eb;border-radius:10px;">
                                        <a href="{{ config('app.frontend_url') }}/mis-reservas" style="display:inline-block;padding:12px 18px;color:#fff;text-decoration:none;font-weight:700;">Ver mis reservas</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
