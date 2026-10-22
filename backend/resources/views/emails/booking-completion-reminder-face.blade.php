@extends('emails.layouts.base')

@section('title', 'Confirmez la fin de votre prestation')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Confirmez la fin de votre prestation
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Votre tournage avec {{ $producerName }} est terminé, mais la prestation n'a pas encore été confirmée.
        Confirmez la fin de votre prestation : votre paiement sera libéré une fois le booking clôturé.
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
