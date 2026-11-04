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

    <p style="margin: 0 0 20px; padding: 16px; background-color: #f3f4f6; border-radius: 8px; color: #111827; font-size: 32px; font-weight: 700; letter-spacing: 8px; text-align: center; font-family: 'Courier New', monospace;">
        {{ $code }}
    </p>

    <p style="margin: 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
        Ce code expire dans {{ $ttlMinutes }} minutes. Ne le communiquez à personne. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.
    </p>
@endsection
