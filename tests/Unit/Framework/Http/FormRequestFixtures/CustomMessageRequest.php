<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http\FormRequestFixtures;

use Silver\Http\FormRequest;

final class CustomMessageRequest extends FormRequest
{
    protected function rules(): array
    {
        return ['email' => 'required'];
    }

    protected function messages(): array
    {
        return ['email.required' => 'Email is mandatory'];
    }
}
