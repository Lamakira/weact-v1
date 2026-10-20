@extends('emails.layouts.base')

@section('title', 'Absence contestée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Une absence est contestée
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        {{ $faceName }} conteste l'absence que vous avez déclarée pour la mission « <strong>{{ $missionTitle }}</strong> »
        et affirme avoir été présente le jour du tournage.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Montant en jeu</td>
                        <td style="padding: 0; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedAmount }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
        Ce qui se passe ensuite : les fonds de cette place restent bloqués, ni versés à la Face ni remboursés,
        le temps que l'équipe WEACT examine la situation et tranche. Vous n'avez aucune démarche à faire
        et vous serez prévenu(e) dès que la décision sera prise.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $missionUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir la mission
                </a>
            </td>
        </tr>
    </table>
@endsection
