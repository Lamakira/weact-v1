@extends('emails.layouts.base')

@section('title', 'Candidature acceptée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $faceName !== '' ? ' '.$faceName : '' }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Votre candidature à la mission UGC « <strong>{{ $missionTitle }}</strong> »
        @if($productName !== '')(produit&nbsp;: <strong>{{ $productName }}</strong>)@endif a été
        <strong>acceptée</strong>. Confirmez votre participation pour démarrer le tunnel.
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $reconfirmUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Confirmer ma participation
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 13px; line-height: 1.5;">
        ✅ Une fois votre participation confirmée, le producteur prépare l'expédition du produit.
    </p>
@endsection
