<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Http\Contracts\ValidatesData;

/**
 * Base class for typed, self-validating request DTOs.
 *
 * Subclasses define {@see rules()} (required) and optionally override
 * {@see authorize()}, {@see messages()}, or {@see prepareForValidation()}.
 *
 *     final class StoreUserRequest extends FormRequest
 *     {
 *         protected function rules(): array
 *         {
 *             return [
 *                 'email'    => 'required|email|max:255',
 *                 'password' => 'required|min:8|confirmed',
 *             ];
 *         }
 *     }
 *
 *     public function store(StoreUserRequest $req): mixed { … }
 *
 * The container auto-resolves the type-hint, runs `validateResolved()`
 * before the action fires, and:
 * - on `authorize() === false` → throws {@see AuthorizationException} (403)
 * - on rule failure            → throws {@see ValidationException}    (422)
 *
 * The error-handler middleware picks the exception up and renders the
 * right response shape for the client (JSON 422 / flash + redirect-back).
 */
abstract class FormRequest extends Request implements ValidatesData
{
    /** @var array<string, mixed>|null memoised validated subset */
    private ?array $validated = null;

    /**
     * @return array<string, string> field => 'rule1|rule2:arg|…'
     */
    abstract protected function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Optional per-field message overrides. Keys are either 'field' (any
     * rule) or 'field.rule' (rule-specific). Values are templates where
     * `KEY` is replaced with the field name.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Hook called before rules run — typical use is normalising input
     * (trim, lowercase, merge defaults). Mutate via {@see merge()}.
     */
    protected function prepareForValidation(): void
    {
    }

    /**
     * Merge values into the request payload (visible to {@see input()},
     * {@see all()} and the rules).
     *
     * @param array<string, mixed> $input
     */
    public function merge(array $input): void
    {
        $_REQUEST = array_replace($_REQUEST, $input);
        $_POST    = array_replace($_POST, $input);
    }

    public function validateResolved(): void
    {
        $this->prepareForValidation();

        if (!$this->authorize()) {
            throw new AuthorizationException();
        }

        $validator = app(Validator::class);
        $rules     = $this->rules();
        $data      = $this->all();
        $result    = $validator->check($data, $rules, $this->messages());

        if ($result->fails()) {
            throw new ValidationException(
                errors: $result->toArray(),
                oldInput: $this->scrubOldInput($data),
            );
        }

        $this->validated = array_intersect_key($data, $rules);
    }

    /**
     * The subset of `all()` that was covered by `rules()`. Safe to mass-
     * assign onto a model.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validated ?? [];
    }

    /**
     * Strip secrets from the redirect-back "old input" flash payload.
     * Override to widen.
     *
     * @return list<string>
     */
    protected function dontFlash(): array
    {
        return ['password', 'password_confirmation', 'current_password', '_token'];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function scrubOldInput(array $data): array
    {
        foreach ($this->dontFlash() as $key) {
            unset($data[$key]);
        }
        return $data;
    }
}
