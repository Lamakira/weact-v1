<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking annulé</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #198496; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">
                                Booking annulé
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 20px; color: #374151; font-size: 14px; line-height: 1.6;">
                                Cette demande de booking a été annulée par <strong>{{ $cancellerName }}</strong>.
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Raison</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; text-align: right;">{{ $reasonLabel }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Date d'annulation</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; text-align: right;">{{ $cancelledAt }}</td>
                                </tr>
                            </table>
                            @if($cancelledByFace)
                                <p style="margin: 20px 0 0; padding: 12px 16px; background-color: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px; line-height: 1.5;">
                                    La Face a annulé ce booking. Si un paiement avait été effectué, un remboursement de 90% sera initié.
                                </p>
                            @else
                                <p style="margin: 20px 0 0; padding: 12px 16px; background-color: #ecfdf5; border-radius: 6px; color: #065f46; font-size: 13px; line-height: 1.5;">
                                    Le Producteur a annulé ce booking. Vous n'êtes pas pénalisé(e).
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 16px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; text-align: center;">
                                Merci d'utiliser WEACT.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
