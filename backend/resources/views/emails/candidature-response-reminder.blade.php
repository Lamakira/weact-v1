@extends('emails.layouts.base')

@section('title', 'Candidatures en attente')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Des candidatures attendent votre réponse
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        {{ $pendingCount }} candidature(s) à votre mission « {{ $missionTitle }} » n'ont pas encore de réponse.
        Retenez ou écartez les Faces pour qu'elles puissent s'organiser.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
        Répondez avant le {{ $deadlineLabel }} : passé ce délai, les candidatures sans réponse expirent automatiquement.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $candidaturesUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir les candidatures
                </a>
            </td>
        </tr>
    </table>
@endsection
