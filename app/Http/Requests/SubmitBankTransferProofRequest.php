<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitBankTransferProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transfer_reference' => ['required', 'string', 'max:60'],
            'manual_method' => ['required', 'string', Rule::in(['bank_transfer', 'remittance'])],
            'channel_code' => ['required', 'string', Rule::in(['bdo', 'bpi', 'chinabank', 'palawan_express'])],
            'photo_1' => [
                'required',
                'file',
                'max:5120', // KB
                // Some devices/browsers report JPEG as image/jpg.
                'mimetypes:image/jpeg,image/jpg,image/png',
                'extensions:jpg,jpeg,png',
            ],
            'photo_2' => [
                'nullable',
                'file',
                'max:5120', // KB
                // Some devices/browsers report JPEG as image/jpg.
                'mimetypes:image/jpeg,image/jpg,image/png',
                'extensions:jpg,jpeg,png',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $manualMethod = (string) $this->input('manual_method', '');
                $channelCode = (string) $this->input('channel_code', '');

                if ($manualMethod === 'remittance' && $channelCode !== 'palawan_express') {
                    $validator->errors()->add('channel_code', 'Channel must be Palawan Express for remittance.');
                }

                if ($manualMethod === 'bank_transfer' && ! in_array($channelCode, ['bdo', 'bpi', 'chinabank'], true)) {
                    $validator->errors()->add('channel_code', 'Please select the bank you transferred to.');
                }
            },
        ];
    }
}
