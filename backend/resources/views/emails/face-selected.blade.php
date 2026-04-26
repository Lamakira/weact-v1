@extends('emails.layouts.base')

@section('title', 'Vous avez été sélectionnée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $faceFirstName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Excellente nouvelle&nbsp;! Vous avez été sélectionnée pour la mission
        <strong>«&nbsp;{{ $missionTitle }}&nbsp;»</strong> proposée par <strong>{{ $producerName }}</strong>.
        Le paiement du Producer est confirmé, il ne reste plus qu'à confirmer votre participation pour verrouiller votre place.
    </p>

    {{-- Mission details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Date de tournage</td>
                        <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $shootingDate }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Rémunération</td>
                        <td style="padding: 0 0 10px; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedAmount }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Producer</td>
                        <td style="padding: 0; color: #111827; font-size: 14px; text-align: right;">{{ $producerName }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $confirmUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Confirmer ma participation
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px; line-height: 1.5;">
        ⏰ Sans confirmation de votre part, la place pourrait être proposée à une autre Face. Confirmez dès que possible depuis votre espace.
    </p>
@endsection
