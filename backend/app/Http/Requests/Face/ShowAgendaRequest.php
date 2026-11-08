<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Http\Requests\Agenda\AgendaRangeRequest;
use App\Models\Face;

class ShowAgendaRequest extends AgendaRangeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->userable_type === Face::class;
    }
}
