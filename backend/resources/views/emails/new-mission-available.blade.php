@extends('emails.layouts.base')

@section('title', 'En avant-première : '.$title)

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Bonjour {{ $faceFirstName }},
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Une nouvelle mission vient d'être publiée sur WEACT, et vous la découvrez en avant-première : <strong>{{ $title }}</strong>.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; margin: 0 0 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Type de mission</td>
                        <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $typeLabel }}</td>
                    </tr>
                    @if ($lieu)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Lieu</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $lieu }}</td>
                        </tr>
                    @endif
                    @if ($compensationLabel)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Rémunération</td>
                            <td style="padding: 0 0 10px; color: #198496; font-size: 16px; font-weight: 700; text-align: right;">{{ $compensationLabel }}</td>
                        </tr>
                    @endif
                    @if ($shootingLabel)
                        <tr>
                            <td style="padding: 0 0 10px; color: #6b7280; font-size: 13px;">Date de tournage</td>
                            <td style="padding: 0 0 10px; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $shootingLabel }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 0; color: #6b7280; font-size: 13px;">Candidatures jusqu'au</td>
                        <td style="padding: 0; color: #111827; font-size: 14px; font-weight: 600; text-align: right;">{{ $deadlineLabel }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td align="center">
                <a href="{{ $missionUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir la mission
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
        Vous recevez cet e-mail en avant-première grâce à votre abonnement {{ $planLabel }}.
    </p>
@endsection
