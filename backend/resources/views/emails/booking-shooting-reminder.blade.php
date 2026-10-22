@extends('emails.layouts.base')

@section('title', 'Rappel de tournage')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Votre tournage a lieu demain
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if($forProducer)
            Rappel : votre tournage avec <strong>{{ $otherName }}</strong> a lieu demain. Le chat du booking reste ouvert pour les derniers détails.
        @else
            Rappel : votre tournage avec <strong>{{ $otherName }}</strong> a lieu demain. Le chat du booking reste ouvert pour les derniers détails.
        @endif
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    @if($bookingDate)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Date</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $bookingDate }}</td>
                        </tr>
                    @endif
                    @if($dureeHeures)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Durée estimée</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $dureeHeures }} heures</td>
                        </tr>
                    @endif
                    @if($bookingLocation)
                        <tr>
                            <td style="padding: 0; color: #6b7280; font-size: 13px;">Lieu</td>
                            <td style="padding: 0; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $bookingLocation }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $bookingUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir le booking
                </a>
            </td>
        </tr>
    </table>
@endsection
