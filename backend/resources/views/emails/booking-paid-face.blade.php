@extends('emails.layouts.base')

@section('title', 'Paiement sécurisé')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Paiement sécurisé
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        <strong>{{ $producerName }}</strong> a réglé votre booking : le paiement est sécurisé par WEACT et vous sera versé après la prestation.
        Le chat est désormais ouvert pour organiser le tournage.
    </p>

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
                    @if($bookingLocation)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Lieu</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $bookingLocation }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Montant que vous recevrez</td>
                        <td style="padding: 0; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedAmount }}</td>
                    </tr>
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
