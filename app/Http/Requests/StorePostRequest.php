<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates incoming data for creating a post.
 *
 * Keeping validation in a FormRequest instead of inline in the controller
 * means the controller stays thin and the rules are reusable and testable
 * in isolation.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may create a post.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'body'  => ['required', 'string', 'min:10'],
        ];
    }
}
