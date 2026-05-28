<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http\FormRequestFixtures;

use Silver\Http\FormRequest;

final class TrimmingRequest extends FormRequest
{
    protected function rules(): array
    {
        return ['name' => 'required|min:2'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }
}
