<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f172a,#2563eb);padding:28px 32px;color:#fff;">
                            <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.8;">AgendaOnline</div>
                            <h1 style="margin:10px 0 0;font-size:24px;line-height:1.2;">Recuperá tu contraseña</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Hola {{ $user->name }}, recibimos una solicitud para restablecer la contraseña de tu cuenta.
                            </p>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.6;">
                                Hacé clic en el botón para crear una nueva contraseña. El enlace expira en <strong>60 minutos</strong>.
                            </p>
                            <table cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="background:#2563eb;border-radius:10px;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:12px 24px;color:#fff;text-decoration:none;font-weight:700;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.5;">
                                Si no podés hacer clic en el botón, copiá este enlace en tu navegador:
                            </p>
                            <p style="margin:0 0 24px;font-size:12px;color:#2563eb;word-break:break-all;">
                                {{ $url }}
                            </p>
                            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.5;">
                                Si no solicitaste este cambio, podés ignorar este correo. Tu contraseña no será modificada.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
