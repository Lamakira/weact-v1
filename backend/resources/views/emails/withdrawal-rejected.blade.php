<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de retrait rejetée</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #198496; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">
                                Votre demande n'a pas pu être traitée
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 20px; color: #374151; font-size: 14px; line-height: 1.6;">
                                Bonjour {{ $faceFirstName }}, votre demande de retrait de <strong>{{ $formattedAmount }} XOF</strong> n'a pas pu être traitée.
                            </p>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Raison communiquée</p>
                            <div style="padding: 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; color: #111827; font-size: 14px; line-height: 1.6;">
                                {{ $withdrawalRequest->notes }}
                            </div>
                            <p style="margin: 20px 0 0; color: #374151; font-size: 14px; line-height: 1.6;">
                                Vous pouvez soumettre une nouvelle demande après correction des informations.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 16px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; text-align: center;">
                                Équipe WEACT
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
