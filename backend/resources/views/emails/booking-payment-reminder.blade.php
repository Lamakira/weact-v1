@extends('emails.layouts.base')

@section('title', 'Paiement du booking en attente')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Votre booking attend votre paiement
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if($bookingDate)
            {{ $faceName }} a accepté votre demande pour le {{ $bookingDate }}.
        @else
            {{ $faceName }} a accepté votre demande.
        @endif
        Réglez {{ $formattedAmount }} pour confirmer le booking et ouvrir la discussion avec la Face.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
        Payez avant le {{ $deadlineLabel }} : passé ce délai, le booking est annulé automatiquement.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $bookingUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Payer le booking
                </a>
            </td>
        </tr>
    </table>
@endsection
