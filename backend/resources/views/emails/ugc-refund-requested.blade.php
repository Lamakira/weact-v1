<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remboursement commission UGC à effectuer</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #198496; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">
                                Remboursement commission UGC à effectuer
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 20px; color: #374151; font-size: 14px; line-height: 1.6;">
                                Une demande de remboursement de commission UGC a été enregistrée.
                                Le remboursement est manuel : retrouvez la transaction dans le dashboard FedaPay
                                et effectuez le remboursement.
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Type</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $isBooking ? 'Booking UGC' : 'Mission UGC' }} #{{ $owner->id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">{{ $isBooking ? 'Produit' : 'Titre' }}</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; text-align: right;">{{ $isBooking ? $owner->nom_produit : $owner->titre }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Producteur</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; text-align: right;">{{ $isBooking ? ($owner->producer?->userable?->display_name ?? $owner->producer?->email ?? '—') : ($owner->producer?->display_name ?? '—') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Montant à rembourser</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $formattedAmount }} FCFA</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px; color: #6b7280; font-size: 13px;">Transaction FedaPay</td>
                                    <td style="padding: 0 0 12px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $fedapayTransactionId }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0; color: #6b7280; font-size: 13px;">Raison</td>
                                    <td style="padding: 0; color: #111827; font-size: 14px; text-align: right;">{{ $reasonLabel }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 16px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; text-align: center;">
                                Procédure : docs/runbook-ugc-commission-refund.md — le webhook transaction.refunded
                                réglera la demande après le remboursement dashboard.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
