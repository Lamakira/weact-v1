@extends('emails.layouts.base')

@section('title', $heading)

@section('content')
    <h2 style="margin: 0 0 16px; color: #111827; font-size: 20px; font-weight: 700; line-height: 1.3;">
        {{ $heading }}
    </h2>
    @foreach ($lines as $line)
        <p style="margin: 0 0 16px; color: #374151; font-size: 15px; line-height: 1.6;">
            {{ $line }}
        </p>
    @endforeach

    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 8px 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $ctaUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #198496; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600;">
                    {{ $ctaLabel }}
                </a>
            </td>
        </tr>
    </table>
@endsection
