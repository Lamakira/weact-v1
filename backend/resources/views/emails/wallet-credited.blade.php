@extends('emails.layouts.base')

@section('title', 'Portefeuille crédité')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $producerName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Bonne nouvelle — votre portefeuille WEACT a été crédité.
    </p>

    {{-- Wallet credit details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Motif</td>
                        <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $motifLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Montant crédité</td>
                        <td style="padding: 0 0 10px; color: #16a34a; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedAmount }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Nouveau solde</td>
                        <td style="padding: 0; color: #111827; font-size: 16px; font-weight: 700; text-align: right;">{{ $formattedNewBalance }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $walletUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir mon portefeuille
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #d1fae5; border-radius: 6px; color: #065f46; font-size: 13px; line-height: 1.5;">
        Votre solde est disponible et utilisable dès maintenant depuis votre portefeuille.
    </p>
@endsection
