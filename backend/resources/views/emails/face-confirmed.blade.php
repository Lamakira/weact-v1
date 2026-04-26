@extends('emails.layouts.base')

@section('title', 'Participation confirmée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $producerName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Bonne nouvelle&nbsp;! <strong>{{ $faceName }}</strong> a confirmé sa participation à la mission
        <strong>«&nbsp;{{ $missionTitle }}&nbsp;»</strong>. La place est désormais verrouillée et vous pouvez finaliser la logistique du tournage.
    </p>

    {{-- Confirmation details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Face confirmée</td>
                        <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $faceName }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Date de tournage</td>
                        <td style="padding: 0 0 10px; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $shootingDate }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Mission</td>
                        <td style="padding: 0; color: #111827; font-size: 14px; text-align: right;">{{ $missionTitle }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $candidaturesUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir les candidatures
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #e0f2fe; border-radius: 6px; color: #075985; font-size: 13px; line-height: 1.5;">
        💡 Pensez à contacter rapidement la Face confirmée pour finaliser la logistique du tournage&nbsp;: lieu précis, horaires, tenue, contact sur place.
    </p>
@endsection
