<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the input required to change a company's subscription plan.
 *
 * This request validates only the shape of the incoming data.
 * Subscription business rules such as upgrade-only, same-plan rejection,
 * and inactive-plan rejection are enforced by SubscriptionService.
 */
final class UpdateSubscriptionRequest extends FormRequest
{
    /**
     * Determine whether the current user may submit this request.
     *
     * Authorization is handled by SubscriptionPolicy so that authorization
     * remains separate from input validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for a subscription plan change.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'integer',
                'exists:plans,id',
            ],
        ];
    }
}
