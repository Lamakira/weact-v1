@extends('emails.layouts.base')

@section('title', 'Rappel : abonnement Premium')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $faceFirstName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Votre abonnement <strong>Premium annuel</strong> expire dans <strong>{{ $daysRemaining }} jours</strong>, le <strong>{{ $expiresLabel }}</strong>.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px; line-height: 1.5;">
        ⏰ Sans renouvellement, vos <strong>photos 3-4</strong> et votre <strong>vidéo de jeu</strong> seront automatiquement cachées au public à l'expiration.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td align="center">
                <a href="{{ $profileUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Renouveler maintenant
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
        Renouvelez avant l'expiration pour garder votre profil Premium actif sans interruption. Vos fichiers (photos et vidéo de jeu) sont conservés en toute sécurité dans tous les cas.
    </p>
@endsection
