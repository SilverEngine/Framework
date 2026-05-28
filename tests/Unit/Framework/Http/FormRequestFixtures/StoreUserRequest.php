<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http\FormRequestFixtures;

use Silver\Http\FormRequest;

final class StoreUserRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'email'    => 'required|email|max:255',
            'password' => 'required|min:8|confirmed',
        ];
    }
}
