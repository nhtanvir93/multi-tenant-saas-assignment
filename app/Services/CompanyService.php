<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;

final class CompanyService
{
    /**
     * @param  array{name: string}  $data
     */
    public function update(Company $company, array $data): Company
    {
        $company->update([
            'name' => $data['name'],
        ]);

        return $company->refresh();
    }
}
