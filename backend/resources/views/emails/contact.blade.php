<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau message de contact</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #198496; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">
                                Nouveau message de contact
                            </h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-bottom: 16px;">
                                        <strong style="color: #374151; font-size: 14px;">Nom :</strong>
                                        <span style="color: #111827; font-size: 14px;">{{ $senderName }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 16px;">
                                        <strong style="color: #374151; font-size: 14px;">Email :</strong>
                                        <a href="mailto:{{ $senderEmail }}" style="color: #198496; font-size: 14px; text-decoration: none;">{{ $senderEmail }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 24px; border-bottom: 1px solid #e5e7eb;">
                                        <strong style="color: #374151; font-size: 14px;">Sujet :</strong>
                                        <span style="color: #111827; font-size: 14px;">{{ $subject }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 24px;">
                                        <strong style="color: #374151; font-size: 14px; display: block; margin-bottom: 8px;">Message :</strong>
                                        <div style="color: #374151; font-size: 14px; line-height: 1.6; white-space: pre-wrap;">{{ $senderMessage }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f9fafb; padding: 16px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; text-align: center;">
                                Ce message a été envoyé via le formulaire de contact WEACT.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
