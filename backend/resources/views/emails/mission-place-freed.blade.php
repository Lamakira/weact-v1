@extends('emails.layouts.base')

@section('title', 'Une place s\'est libérée')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        Une place s'est libérée
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        {{ $faceName }} n'a pas confirmé sa participation à « {{ $missionTitle }} » : sa place est libérée.
        Vous pouvez la proposer à une des Faces que vous n'aviez pas retenues, sans frais supplémentaires.
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #fffbeb; border-radius: 6px; color: #92400e; font-size: 14px; line-height: 1.5;">
        Choisissez une remplaçante avant le {{ $refundAtLabel }}. Sans remplaçante confirmée à cette date, {{ $formattedAmount }} seront crédités sur votre wallet.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $candidaturesUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Choisir une remplaçante
                </a>
            </td>
        </tr>
    </table>
@endsection
