<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a AgendaOnline</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f172a,#2563eb);padding:28px 32px;color:#fff;">
                            <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.8;">AgendaOnline</div>
                            <h1 style="margin:10px 0 0;font-size:28px;line-height:1.2;">Bienvenido, {{ $user->name }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Tu cuenta ya quedó creada con éxito en AgendaOnline.
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Desde ahora podés explorar profesionales, reservar servicios y administrar tu perfil desde una sola plataforma.
                            </p>
                            <table cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
                                <tr>
                                    <td style="background:#2563eb;border-radius:10px;">
                                        <a href="{{ config('app.url') }}" style="display:inline-block;padding:12px 18px;color:#fff;text-decoration:none;font-weight:700;">Ir a la plataforma</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:24px 0 0;font-size:13px;color:#6b7280;line-height:1.5;">
                                Si no creaste esta cuenta, podés ignorar este correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>