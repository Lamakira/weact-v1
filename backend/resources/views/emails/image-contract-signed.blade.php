@extends('emails.layouts.base')

@section('title', 'Contrat d\'autorisation d\'image signé')

@section('footer_brand', 'WEACT · Bénin')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Votre contrat est signé
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        Les deux parties ont signé l'autorisation d'utilisation de l'image (référence <strong>{{ $reference }}</strong>).
        Vous trouverez le contrat en pièce jointe, au format PDF.
    </p>
    <p style="margin: 0; padding: 12px 16px; background-color: #ecfeff; border-radius: 6px; color: #155e75; font-size: 13px; line-height: 1.5;">
        Les droits d'utilisation ne démarrent qu'à la prise d'effet prévue au contrat. Conservez ce document.
    </p>
@endsection
