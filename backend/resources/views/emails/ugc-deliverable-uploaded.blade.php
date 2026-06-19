@extends('emails.layouts.base')

@section('title', 'Nouvelle vidéo à valider')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $producerName !== '' ? ' '.$producerName : '' }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Une nouvelle vidéo <strong>{{ $kindLabel }}</strong>
        @if($productName !== '')pour « <strong>{{ $productName }}</strong> »@endif
        vient d'être déposée. Visionnez-la et validez-la depuis votre espace&nbsp;:
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Valider la vidéo
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px; line-height: 1.5;">
        ⏰ Pensez à traiter le livrable rapidement&nbsp;: la fenêtre de validation est limitée dans le temps.
    </p>
@endsection
