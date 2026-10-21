@extends('emails.layouts.base')

@section('title', 'Le Producteur a confirmé la prestation')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        À ton tour de confirmer
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        {{ $producerName }} a confirmé la prestation. Confirme à ton tour pour être payé(e) tout de suite.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
        @if($dueAt)
            Sans action de ta part, tu seras payé(e) automatiquement au plus tard le <strong>{{ $dueAt }}</strong>.
        @else
            Sans action de ta part, tu seras payé(e) automatiquement dans les prochaines heures.
        @endif
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
