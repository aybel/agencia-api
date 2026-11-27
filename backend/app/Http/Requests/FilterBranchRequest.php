<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
    /** @return array<string, 'string'|'integer'|'numeric'|'date'> */
    protected function filterableFields(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'city_id' => 'integer',
            'address' => 'string',
            'created_at' => 'date',
            'updated_at' => 'date',
        ];
    }
}
