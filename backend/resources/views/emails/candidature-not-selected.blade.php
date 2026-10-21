@extends('emails.layouts.base')

@section('title', 'Candidature non retenue')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $faceName !== '' ? ' '.$faceName : '' }},
    </h2>
    @if ($noResponse)
        <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
            Le Producteur n'a pas répondu à votre candidature pour la mission « <strong>{{ $missionTitle }}</strong> »
            dans le délai prévu : elle a donc expiré. Ce n'est pas un refus de votre profil, et cela ne remet rien en cause
            pour vos prochaines candidatures.
        </p>
    @else
        <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
            Le Producteur a finalisé sa sélection pour la mission « <strong>{{ $missionTitle }}</strong> » et a retenu
            d'autres Faces. Votre candidature n'a pas été retenue cette fois-ci. Merci d'avoir postulé.
        </p>
    @endif
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        De nouvelles missions sont publiées régulièrement : d'autres opportunités vous attendent.
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $browseUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir les missions ouvertes
                </a>
            </td>
        </tr>
    </table>
@endsection
