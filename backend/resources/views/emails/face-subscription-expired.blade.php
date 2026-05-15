@extends('emails.layouts.base')

@section('title', 'Abonnement Premium expiré')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $faceFirstName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Votre abonnement <strong>Premium annuel</strong> a pris fin le <strong>{{ $expiredOnLabel }}</strong>. Vos <strong>photos 3-4</strong> et votre <strong>vidéo de jeu</strong> sont à nouveau cachées au public.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px; line-height: 1.5;">
        ✓ Vos fichiers ne sont <strong>pas supprimés</strong>. Renouvelez votre abonnement pour rendre à nouveau publiques vos photos 3-4 et votre vidéo de jeu — vous n'avez pas besoin de les téléverser à nouveau.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td align="center">
                <a href="{{ $profileUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Renouveler mon abonnement
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
        Si vous avez des questions sur le renouvellement, contactez notre équipe support.
    </p>
@endsection
