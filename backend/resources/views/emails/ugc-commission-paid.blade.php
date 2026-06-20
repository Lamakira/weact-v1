@extends('emails.layouts.base')

@section('title', 'Commission réglée — accepte ton deal')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonne nouvelle{{ $faceName !== '' ? ', '.$faceName : '' }} !
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        <strong>{{ $producerName !== '' ? $producerName : 'Le Producteur' }}</strong> a réglé la commission de ton deal UGC
        @if($productName !== '')pour « <strong>{{ $productName }}</strong> »@endif.
        Dernière étape avant de recevoir ton colis&nbsp;: <strong>accepte le deal</strong> pour lancer l'expédition.
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Accepter le deal
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 13px; line-height: 1.5;">
        🎁 Dès que tu acceptes, le Producteur t'expédie le produit et le tunnel anti-arnaque démarre.
    </p>
@endsection
