@extends('emails.layouts.base')

@section('title', 'Nouvelles candidatures')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        {{ $newCount > 1 ? 'Nouvelles candidatures reçues' : 'Nouvelle candidature reçue' }}
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if ($newCount > 1)
            Vous avez reçu {{ $newCount }} nouvelles candidatures pour votre mission « {{ $missionTitle }} ».
        @else
            Vous avez reçu 1 nouvelle candidature pour votre mission « {{ $missionTitle }} ».
        @endif
    </p>

    <ul style="margin: 0 0 20px; padding: 0 0 0 20px; color: #374151; font-size: 15px; line-height: 1.8;">
        @foreach ($faces as $face)
            <li><strong>{{ $face['name'] }}</strong>@if ($face['city']) ({{ $face['city'] }})@endif</li>
        @endforeach
        @if ($otherCount > 0)
            <li>et {{ $otherCount }} autre(s)</li>
        @endif
    </ul>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #f9fafb; border-radius: 6px; color: #374151; font-size: 14px; line-height: 1.5;">
        Au total, {{ $totalPending }} candidature(s) attendent votre réponse.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $candidaturesUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Voir les candidatures
                </a>
            </td>
        </tr>
    </table>
@endsection
