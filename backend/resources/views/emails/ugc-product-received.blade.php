@extends('emails.layouts.base')

@section('title', 'Produit reçu par la Face')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $producerName !== '' ? ' '.$producerName : '' }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        <strong>{{ $destinataireNom !== '' ? $destinataireNom : 'La Face' }}</strong> a confirmé la réception
        @if($productName !== '')de « <strong>{{ $productName }}</strong> »@else du produit @endif.
        Le chrono Unboxing démarre&nbsp;: la Face va maintenant tourner ses vidéos.
    </p>

    @if(($photosCount ?? 0) > 0)
        <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
            📸 {{ $photosCount }} photo{{ $photosCount > 1 ? 's' : '' }} du produit reçu
            {{ $photosCount > 1 ? 'sont jointes' : 'est jointe' }} à ce deal&nbsp;: comparez-{{ $photosCount > 1 ? 'les' : 'la' }}
            avec le produit envoyé depuis le suivi du deal.
        </p>
    @endif

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir le deal
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 13px; line-height: 1.5;">
        🎬 Vous serez notifié·e dès qu'une vidéo sera déposée et prête à être validée.
    </p>
@endsection
