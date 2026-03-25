<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle demande de retrait</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #198496; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">
                                Nouvelle demande de retrait
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 20px; color: #374151; font-size: 14px; line-height: 1.6;">
                                {{ $faceFirstName }} a soumis une nouvelle demande de retrait.
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Montant</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $formattedAmount }} XOF</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Opérateur</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; text-align: right;">{{ $withdrawalRequest->payment_mode }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Numéro</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; text-align: right;">{{ $withdrawalRequest->phone_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Email</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; text-align: right;">{{ $withdrawalRequest->user?->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0; color: #6b7280; font-size: 13px;">Soumise le</td>
                                    <td style="padding: 0; color: #111827; font-size: 14px; text-align: right;">{{ $withdrawalRequest->created_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>
                            <p style="margin: 24px 0 0;">
                                <a href="{{ $adminUrl }}" style="display: inline-block; background-color: #198496; color: #ffffff; text-decoration: none; padding: 12px 18px; border-radius: 6px; font-size: 14px; font-weight: 600;">
                                    Ouvrir le panel finance
                                </a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 16px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; text-align: center;">
                                Traitement manuel du retrait en attente.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
