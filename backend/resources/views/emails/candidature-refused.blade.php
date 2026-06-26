@extends('emails.layouts.base')

@section('title', 'Candidature non retenue')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour{{ $faceName !== '' ? ' '.$faceName : '' }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Votre candidature à la mission UGC « <strong>{{ $missionTitle }}</strong> »
        n'a pas été retenue cette fois-ci. Ne baissez pas les bras — de nouvelles missions
        sont publiées régulièrement.
    </p>

    {{-- CTA button --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $browseUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir d'autres missions
                </a>
            </td>
        </tr>
    </table>
@endsection
