@extends('emails.layouts.base')

@section('title', 'Rappel de tournage')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        {{ $forProducer ? 'Votre tournage a lieu demain' : 'Ton tournage a lieu demain' }}
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if($forProducer)
            Rappel : le tournage de votre mission « {{ $missionTitle }} » a lieu demain.
        @else
            Rappel : ton tournage pour la mission « {{ $missionTitle }} » a lieu demain.
        @endif
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    @if($shootingDate)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Date</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $shootingDate }}</td>
                        </tr>
                    @endif
                    @if($missionDuration)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Durée</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $missionDuration }}</td>
                        </tr>
                    @endif
                    @if($missionLocation)
                        <tr>
                            <td style="padding: 0; color: #6b7280; font-size: 13px;">Lieu</td>
                            <td style="padding: 0; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $missionLocation }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $missionUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir la mission
                </a>
            </td>
        </tr>
    </table>
@endsection
