@extends('emails.layouts.base')

@section('title', 'Message non lu')

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        {{ $unreadCount > 1 ? 'Des messages vous attendent' : 'Un message vous attend' }}
    </h2>
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.6;">
        @if($unreadCount > 1)
            {{ $senderName }} vous a envoyé {{ $unreadCount }} messages que vous n'avez pas encore lus.
        @else
            {{ $senderName }} vous a envoyé un message que vous n'avez pas encore lu.
        @endif
    </p>

    <p style="margin: 0 0 24px; padding: 12px 16px; background-color: #f9fafb; border-radius: 6px; color: #374151; font-size: 14px; line-height: 1.5;">
        Concerne : {{ $contextLabel }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $threadUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    Lire {{ $unreadCount > 1 ? 'les messages' : 'le message' }}
                </a>
            </td>
        </tr>
    </table>
@endsection
