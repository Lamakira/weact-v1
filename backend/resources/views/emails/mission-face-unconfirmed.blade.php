@extends('emails.layouts.base')

@section('title', 'Place libérée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Une Face n'a pas confirmé sa participation
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        <strong>{{ $faceName }}</strong> n'a pas confirmé sa participation à la mission
        <strong>«&nbsp;{{ $missionTitle }}&nbsp;»</strong> dans les délais : sa place est libérée.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 14px; line-height: 1.5;">
        {{ $formattedAmount }} ont été crédités sur votre wallet WeAct.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $candidaturesUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir la mission
                </a>
            </td>
        </tr>
    </table>
@endsection
