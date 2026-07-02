@extends('emails.layouts.base')

@section('title', 'Abonnement '.$planLabel.' activé')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $faceFirstName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Votre abonnement <strong>{{ $planLabel }}</strong> est activé. Désormais, <strong>{{ $premiumMediaSummary }}</strong> sont visibles publiquement sur votre profil.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Plan</td>
                        <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $planLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Montant payé</td>
                        <td style="padding: 0 0 10px; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedPaidAmount }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Valable jusqu'au</td>
                        <td style="padding: 0; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $expiresLabel }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td align="center">
                <a href="{{ $profileUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir mon profil
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
        Cet email confirme l'activation de votre abonnement annuel. Vous recevrez un rappel 30 jours puis 7 jours avant la fin de la période.
    </p>
@endsection
