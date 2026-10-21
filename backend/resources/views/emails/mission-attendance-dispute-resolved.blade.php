@extends('emails.layouts.base')

@section('title', 'Litige tranché')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $recipientName !== '' ? ' '.$recipientName : '' }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        L'équipe WEACT a examiné la contestation d'absence pour la mission « <strong>{{ $missionTitle }}</strong> » et a rendu sa décision.
    </p>

    @if ($favorFace)
        <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #ecfdf5; border-radius: 6px; color: #065f46; font-size: 14px; line-height: 1.5;">
            @if ($isFace)
                La décision est en votre faveur : votre présence est reconnue et <strong>{{ $formattedAmount }}</strong> ont été crédités sur votre portefeuille.
            @else
                La décision est en faveur de la Face : sa présence est reconnue et <strong>{{ $formattedAmount }}</strong> lui ont été versés. Cette place n'est donc pas remboursée.
            @endif
        </p>
    @else
        <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
            @if ($isFace)
                La décision est en faveur du Producteur : l'absence est maintenue, la place est remboursée au Producteur et aucun montant ne vous est versé pour cette mission.
            @else
                La décision est en votre faveur : l'absence est maintenue et <strong>{{ $formattedAmount }}</strong> ont été crédités sur votre portefeuille en remboursement de cette place.
            @endif
        </p>
    @endif

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
