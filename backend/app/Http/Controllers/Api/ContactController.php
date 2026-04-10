<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(ContactFormRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $to = config('mail.contact_to', 'contact@weact.bj');

        try {
            Mail::to($to)->send(new ContactFormMail(
                senderName: $validated['name'],
                senderEmail: $validated['email'],
                messageSubject: $validated['subject'],
                senderMessage: $validated['message'],
            ));
        } catch (\Throwable $e) {
            Log::error('Contact form email failed', [
                'error' => $e->getMessage(),
                'sender' => $validated['email'],
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'envoi du message. Veuillez réessayer.',
            ], 500);
        }

        return response()->json([
            'message' => 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.',
        ]);
    }
}
