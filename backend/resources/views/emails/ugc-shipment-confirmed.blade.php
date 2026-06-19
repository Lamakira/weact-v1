@extends('emails.layouts.base')

@section('title', 'Produit expédié')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonne nouvelle{{ $faceName !== '' ? ', '.$faceName : '' }} !
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        <strong>{{ $producerName !== '' ? $producerName : 'Le Producteur' }}</strong> a expédié ton produit
        @if($productName !== '')« <strong>{{ $productName }}</strong> »@endif. Surveille ta livraison&nbsp;:
    </p>

    {{-- Shipment details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    @if($transporteur !== '')
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Transporteur</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $transporteur }}</td>
                        </tr>
                    @endif
                    @if($numeroSuivi !== '')
                        <tr>
                            <td style="padding: 0; color: #6b7280; font-size: 13px;">Numéro de suivi</td>
                            <td style="padding: 0; color: #198496; font-size: 14px; font-weight: 700; text-align: right;">{{ $numeroSuivi }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Suivre mon deal
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 13px; line-height: 1.5;">
        📦 Dès que tu reçois le colis, confirme la réception dans l'app pour démarrer le tournage de tes vidéos.
    </p>
@endsection
