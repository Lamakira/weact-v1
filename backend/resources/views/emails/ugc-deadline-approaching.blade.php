@extends('emails.layouts.base')

@section('title', 'Ton échéance UGC approche')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        @if($level >= 3)
            ⏰ Dernière ligne droite{{ $faceName !== '' ? ', '.$faceName : '' }} !
        @elseif($level === 2)
            Attention{{ $faceName !== '' ? ', '.$faceName : '' }} !
        @else
            Petit rappel{{ $faceName !== '' ? ', '.$faceName : '' }} 👋
        @endif
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Il te reste <strong>{{ $remaining }}</strong> pour déposer ton <strong>{{ $kindLabel }}</strong>
        @if($productName !== '')pour « <strong>{{ $productName }}</strong> »@endif.
        @if($level >= 3)
            Sans dépôt à temps, ton compte sera suspendu.
        @else
            Ne tarde pas à filmer&nbsp;!
        @endif
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $dealUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Déposer ma vidéo
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 13px; line-height: 1.5;">
        ⏳ Le compte à rebours exact reste affiché dans l'app — c'est ta source de vérité.
    </p>
@endsection
