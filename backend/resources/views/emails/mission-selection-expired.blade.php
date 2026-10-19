@extends('emails.layouts.base')

@section('title', 'Sélection annulée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Votre sélection a été annulée
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Le délai de 24&nbsp;h pour payer votre sélection sur la mission
        <strong>«&nbsp;{{ $missionTitle }}&nbsp;»</strong> est dépassé : la sélection a été annulée.
        Vous pouvez en refaire une à tout moment.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 14px; line-height: 1.5;">
        Si un paiement aboutit malgré tout, son montant sera crédité sur votre wallet WeAct.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $candidaturesUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Refaire ma sélection
                </a>
            </td>
        </tr>
    </table>
@endsection
