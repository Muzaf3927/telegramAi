<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_user_id' => 'required|integer|min:1',
            'to_user_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:255',
        ];
    }
}


