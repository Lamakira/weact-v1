@extends('emails.layouts.base')

@section('title', 'Booking refusé')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Booking refusé
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        {{ $faceName }} a refusé votre booking. Vous pouvez le proposer à une autre Face.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Raison</td>
                        <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $reasonLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Date du refus</td>
                        <td style="padding: 0; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $refusedAt }}</td>
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
