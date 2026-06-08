@extends('emails.layouts.base')

@section('title', 'Nouvelle demande de booking')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $faceFirstName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        <strong>{{ $producerName }}</strong> souhaite vous booker pour {{ $typeContenu }}.
        Voici les détails de la demande&nbsp;:
    </p>

    {{-- Booking details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    @if($bookingDate)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Date du tournage</td>
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
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Lieu</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $bookingLocation }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Rémunération</td>
                        <td style="padding: 0; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedAmount }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $bookingUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir la demande
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px; line-height: 1.5;">
        ⏰ Pensez à répondre rapidement&nbsp;: la demande a une fenêtre d'acceptation limitée et expire automatiquement passé ce délai.
    </p>
@endsection
