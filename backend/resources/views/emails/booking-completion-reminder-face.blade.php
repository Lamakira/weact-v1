@extends('emails.layouts.base')

@section('title', 'Confirme la fin de ta prestation')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Confirme la fin de ta prestation
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Ton tournage avec {{ $producerName }} est terminé, mais la prestation n'a pas encore été confirmée.
        Confirme la fin de ta prestation : ton paiement sera libéré une fois le booking clôturé.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $bookingUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Confirmer la prestation
                </a>
            </td>
        </tr>
    </table>
@endsection
