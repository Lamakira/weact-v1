<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'WEACT')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #198496; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;">
                                WEACT
                            </h1>
                        </td>
                    </tr>
                    {{-- Main content slot --}}
                    <tr>
                        <td style="padding: 32px;">
                            @yield('content')
                        </td>
                    </tr>
                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 8px; color: #6b7280; font-size: 12px; line-height: 1.5; text-align: center;">
                                Vous recevez cet email parce que vous êtes inscrit·e sur WEACT.
                                <br>
                                <a href="mailto:{{ config('mail.contact_to') }}?subject=D%C3%A9sinscription%20notifications%20email&body=Bonjour%2C%0A%0AJe%20souhaite%20me%20d%C3%A9sinscrire%20des%20notifications%20email%20WEACT.%0A%0AMerci." style="color: #198496; text-decoration: underline;">Se désinscrire des notifications email</a>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 11px; text-align: center;">
                                WEACT — Bénin · <a href="{{ rtrim((string) config('app.frontend_url'), '/') }}/mentions-legales" style="color: #9ca3af; text-decoration: underline;">Mentions légales</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
