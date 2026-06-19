@extends('emails.layouts.base')

@section('title', 'Livrable validé')

@section('content')
    <h2 style="margin: 0 0 16px; color: #198496; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bravo{{ $faceName !== '' ? ' '.$faceName : '' }} ! 🎉
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Ton livrable <strong>{{ $kindLabel }}</strong>
        @if($productName !== '')pour « <strong>{{ $productName }}</strong> »@endif
        vient d'être validé par le Producteur. Retrouve la suite de ton deal&nbsp;:
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir mon deal
                </a>
            </td>
        </tr>
    </table>
@endsection
