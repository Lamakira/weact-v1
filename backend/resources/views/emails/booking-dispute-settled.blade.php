@extends('emails.layouts.base')

@section('title', 'Litige réglé')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Litige réglé
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if($forProducer)
            @if($favorProducer)
                @if($contested)
                    L'administrateur a tranché en votre faveur pour votre booking avec <strong>{{ $faceName }}</strong>.
                @else
                    La Face <strong>{{ $faceName }}</strong> n'a pas contesté dans le délai imparti : votre booking est réglé en votre faveur.
                @endif
            @else
                L'administrateur a tranché en faveur de la Face <strong>{{ $faceName }}</strong> : le paiement lui est versé. Aucun remboursement n'est dû pour ce booking.
            @endif
        @else
            @if($favorProducer)
                @if($contested)
                    L'administrateur a tranché en faveur de <strong>{{ $producerName }}</strong> pour votre booking.
                @else
                    Le délai de contestation est écoulé : <strong>{{ $producerName }}</strong> a été remboursé de ce booking.
                @endif
                @if($penalty)
                    Une pénalité a été appliquée à votre profil.
                @endif
            @else
                L'administrateur a tranché en votre faveur pour votre booking avec <strong>{{ $producerName }}</strong>.
            @endif
        @endif
    </p>

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
