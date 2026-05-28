<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http\FormRequestFixtures;

use Silver\Http\FormRequest;

final class ForbiddenRequest extends FormRequest
{
    protected function rules(): array
    {
        return ['email' => 'required'];
    }

    public function authorize(): bool
    {
        return false;
    }
}
