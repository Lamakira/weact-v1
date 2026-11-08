<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use App\Http\Requests\Agenda\AgendaRangeRequest;
use App\Models\Producer;

class ShowFaceAvailabilityRequest extends AgendaRangeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->userable_type === Producer::class;
    }
}
