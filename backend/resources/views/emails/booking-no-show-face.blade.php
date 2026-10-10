@extends('emails.layouts.base')

@section('title', 'Absence signalée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Une absence a été signalée
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        {{ $producerName }} a signalé ton absence sur un booking. Tu peux contester jusqu'au
        <strong>{{ $dueAt }}</strong>. Sans contestation de ta part, le Producteur sera remboursé.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $bookingUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir le booking et contester
                </a>
            </td>
        </tr>
    </table>
@endsection
