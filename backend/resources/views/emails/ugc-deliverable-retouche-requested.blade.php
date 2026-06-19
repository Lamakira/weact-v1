@extends('emails.layouts.base')

@section('title', 'Retouche demandée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $faceName !== '' ? ' '.$faceName : '' }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Le Producteur demande une retouche sur ton livrable <strong>{{ $kindLabel }}</strong>
        @if($productName !== '')pour « <strong>{{ $productName }}</strong> »@endif.
        Apporte les ajustements demandés et redépose ta vidéo.
    </p>

    @if($reviewNote !== '')
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fffbeb; border-radius: 6px; margin: 0 0 24px;">
            <tr>
                <td style="padding: 16px 20px;">
                    <p style="margin: 0 0 6px; color: #6b7280; font-size: 13px;">Retouche demandée</p>
                    <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.5;">{{ $reviewNote }}</p>
                </td>
            </tr>
        </table>
    @endif

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Redéposer ma vidéo
                </a>
            </td>
        </tr>
    </table>
@endsection
