@extends('emails.layouts.base')

@section('title', 'Confirmez votre participation')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $faceFirstName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Vous avez été sélectionnée pour la mission <strong>«&nbsp;{{ $missionTitle }}&nbsp;»</strong>,
        mais vous n'avez pas encore confirmé votre participation.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
        Sans confirmation avant le <strong>{{ $deadline }}</strong>, votre place sera libérée.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $confirmUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Confirmer ma participation
                </a>
            </td>
        </tr>
    </table>
@endsection
