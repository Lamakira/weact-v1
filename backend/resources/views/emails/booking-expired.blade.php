@extends('emails.layouts.base')

@section('title', 'Booking expiré')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        {{ $kind === 'ugc_commission' ? 'Demande UGC expirée' : 'Booking expiré' }}
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if($forProducer)
            @if($kind === 'face_no_response')
                <strong>{{ $faceName }}</strong> n'a pas répondu à votre demande de booking dans le délai imparti : elle a expiré. Aucun paiement n'a été prélevé, vous pouvez proposer votre booking à une autre Face.
            @elseif($kind === 'ugc_commission')
                La commission de votre demande UGC auprès de <strong>{{ $faceName }}</strong> n'a pas été réglée dans les 48 h : la demande a expiré. Vous pouvez en créer une nouvelle.
            @else
                Votre booking avec <strong>{{ $faceName }}</strong> n'a pas été payé dans les 24 h suivant son acceptation : il a expiré. Vous pouvez refaire une demande si vous le souhaitez.
            @endif
        @else
            @if($kind === 'face_no_response')
                La demande de booking de <strong>{{ $producerName }}</strong> a expiré, faute de réponse de votre part dans le délai imparti. Pensez à répondre plus vite aux prochaines demandes pour ne pas les manquer.
            @elseif($kind === 'ugc_commission')
                La demande UGC de <strong>{{ $producerName }}</strong> a expiré : le Producteur n'a pas réglé la commission dans les 48 h. Vous n'avez rien à faire.
            @else
                Le booking avec <strong>{{ $producerName }}</strong> a expiré : le Producteur n'a pas payé dans les 24 h suivant votre acceptation. Vous êtes de nouveau disponible pour ces dates.
            @endif
        @endif
    </p>

    @if($bookingDate)
        <p style="margin: 0 0 24px; color: #6b7280; font-size: 13px;">Date du tournage prévue : {{ $bookingDate }}</p>
    @endif

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
