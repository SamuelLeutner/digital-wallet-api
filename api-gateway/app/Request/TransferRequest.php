<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class TransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'numeric', 'min:0.01'],
            'payer' => [
                'required',
                'integer',
                'exists:users,id',
                'different:payee'
            ],
            'payee' => [
                'required',
                'integer',
                'exists:users,id',
                'not_in:' . $this->input('payer')
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'O valor da transferência é obrigatório.',
            'value.numeric' => 'O valor deve ser um número válido.',
            'value.min' => 'O valor mínimo para transferência é :min.',
            'payer.required' => 'O ID do pagador é obrigatório.',
            'payer.integer' => 'O ID do pagador deve ser um número inteiro.',
            'payer.exists' => 'O pagador selecionado não existe em nossos registros.',
            'payer.different' => 'O pagador não pode ser o mesmo que o beneficiário.',
            'payee.required' => 'O ID do beneficiário é obrigatório.',
            'payee.integer' => 'O ID do beneficiário deve ser um número inteiro.',
            'payee.exists' => 'O beneficiário selecionado não existe em nossos registros.',
            'payee.not_in' => 'O beneficiário não pode ser o mesmo que o pagador.',
        ];
    }
}