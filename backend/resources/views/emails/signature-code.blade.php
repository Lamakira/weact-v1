@extends('emails.layouts.base')

@section('title', 'Votre code de signature')

@section('footer_brand', 'WEACT · Bénin')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        {{ $forGuardian ? 'Code de signature pour '.$signerName : 'Votre code de signature' }}
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if($forGuardian)
            {{ $guardianName !== null && $guardianName !== '' ? $guardianName.', ' : '' }}<strong>{{ $signerName }}</strong> a moins de 18 ans&nbsp;: votre signature est nécessaire pour valider l'autorisation d'utilisation de son image. Saisissez ce code sur WeAct pour signer.
        @else
            Saisissez ce code sur WeAct pour signer l'autorisation d'utilisation de l'image.
        @endif
    </p>

    @if($forGuardian && ! empty($summary))
        <p style="margin: 0 0 8px; color: #111827; font-size: 14px; font-weight: 700;">
            Ce que vous signez, en tant que parent ou tuteur de {{ $signerName }}
        </p>
        <table cellpadding="0" cellspacing="0" style="margin: 0 0 20px; width: 100%; font-size: 14px; color: #374151; line-height: 1.5;">
            <tr><td style="padding: 4px 8px 4px 0; font-weight: 700; vertical-align: top;">Producteur</td><td style="padding: 4px 0;">{{ $summary['producer'] }}</td></tr>
            <tr><td style="padding: 4px 8px 4px 0; font-weight: 700; vertical-align: top;">Finalité</td><td style="padding: 4px 0;">{{ $summary['finalite'] }}</td></tr>
            <tr><td style="padding: 4px 8px 4px 0; font-weight: 700; vertical-align: top;">Supports</td><td style="padding: 4px 0;">{{ $summary['supports'] }}</td></tr>
            <tr><td style="padding: 4px 8px 4px 0; font-weight: 700; vertical-align: top;">Territoire</td><td style="padding: 4px 0;">{{ $summary['territoire'] }}</td></tr>
            <tr><td style="padding: 4px 8px 4px 0; font-weight: 700; vertical-align: top;">Durée</td><td style="padding: 4px 0;">{{ $summary['duree'] }}</td></tr>
            <tr><td style="padding: 4px 8px 4px 0; font-weight: 700; vertical-align: top;">Contrepartie</td><td style="padding: 4px 0;">@foreach($summary['contrepartie'] as $line){{ $line }}@if(! $loop->last)<br>@endif @endforeach</td></tr>
        </table>
        <p style="margin: 0 0 20px; color: #374151; font-size: 14px; line-height: 1.6;">
            En saisissant le code ci-dessous, vous signez cette autorisation à la place de {{ $signerName }}, qui est mineure. Le contrat complet vous sera envoyé une fois signé par les deux parties.
        </p>
    @endif

    <p style="margin: 0 0 20px; padding: 16px; background-color: #f3f4f6; border-radius: 8px; color: #111827; font-size: 32px; font-weight: 700; letter-spacing: 8px; text-align: center; font-family: 'Courier New', monospace;">
        {{ $code }}
    </p>

    <p style="margin: 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
        Ce code expire dans {{ $ttlMinutes }} minutes. Ne le communiquez à personne. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.
    </p>
@endsection
