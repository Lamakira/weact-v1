@extends('emails.layouts.base')

@section('title', 'Paiement confirmé')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Votre paiement est confirmé
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Nous avons bien reçu votre paiement pour la mission « <strong>{{ $missionTitle }}</strong> ».
        {{ $faceCount > 1 ? $faceCount.' Faces sont retenues' : '1 Face est retenue' }} et {{ $faceCount > 1 ? 'viennent' : 'vient' }} d'être prévenue(s).
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Faces payées</td>
                        <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $faceCount }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Montant total payé</td>
                        <td style="padding: 0; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedAmount }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 14px; line-height: 1.5;">
        Prochaine étape : les Faces retenues confirment leur participation. Vous serez prévenu(e) de chaque confirmation.
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
