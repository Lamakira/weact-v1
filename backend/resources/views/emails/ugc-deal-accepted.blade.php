@extends('emails.layouts.base')

@section('title', 'Deal UGC accepté')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $producerName !== '' ? ' '.$producerName : '' }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        <strong>{{ $faceName !== '' ? $faceName : 'La Face' }}</strong> a accepté votre deal UGC
        @if($productName !== '')pour « <strong>{{ $productName }}</strong> »@endif.
        Prochaine étape&nbsp;: <strong>expédiez le produit</strong> et renseignez le numéro de suivi pour démarrer le tunnel.
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Préparer l'expédition
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 13px; line-height: 1.5;">
        🚚 Pensez à renseigner le transporteur et le numéro de suivi : la Face est prévenue dès l'expédition.
    </p>
@endsection
