@extends('emails.layouts.base')

@section('title', 'Payez votre sélection')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Votre sélection n'est pas encore payée
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Vous avez sélectionné des Faces pour la mission
        <strong>«&nbsp;{{ $missionTitle }}&nbsp;»</strong>, mais le paiement n'a pas été finalisé.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Montant à payer</td>
                        <td style="padding: 0 0 10px; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedAmount }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">À payer avant le</td>
                        <td style="padding: 0; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $deadline }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
        Sans paiement avant le <strong>{{ $deadline }}</strong>, la sélection sera annulée et vous devrez la refaire.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $candidaturesUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Payer ma sélection
                </a>
            </td>
        </tr>
    </table>
@endsection
