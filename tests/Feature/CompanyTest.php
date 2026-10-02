<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Company management', function (): void {
    it('allows the owner to view the company', function (): void {
        $company = Company::factory()->create();

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/company');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.name', $company->name)
            ->assertJsonPath('data.slug', $company->slug);
    });

    it('allows an admin to view the company', function (): void {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Admin,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/company');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $company->id);
    });

    it('allows a regular user to view the company', function (): void {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/company');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $company->id);
    });

    it('allows the owner to update the company name', function (): void {
        $company = Company::factory()->create([
            'name' => 'Old Company Name',
            'slug' => 'old-company-name',
        ]);

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/company', [
            'name' => 'New Company Name',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Company Name')
            ->assertJsonPath('data.slug', 'old-company-name');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'New Company Name',
            'slug' => 'old-company-name',
        ]);
    });

    it('does not allow an admin to update the company', function (): void {
        $company = Company::factory()->create([
            'name' => 'Original Name',
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Admin,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/company', [
            'name' => 'Changed Name',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Original Name',
        ]);
    });

    it('does not allow a regular user to update the company', function (): void {
        $company = Company::factory()->create([
            'name' => 'Original Name',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/company', [
            'name' => 'Changed Name',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Original Name',
        ]);
    });

    it('requires authentication to view the company', function (): void {
        $response = $this->getJson('/api/v1/company');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    });

    it('requires authentication to update the company', function (): void {
        $response = $this->putJson('/api/v1/company', [
            'name' => 'New Name',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    });

    it('requires a company name when updating', function (): void {
        $company = Company::factory()->create();

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/company', [
            'name' => '',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $response
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.name.0', 'The name field is required.');
    });

    it('rejects a company name longer than 150 characters', function (): void {
        $company = Company::factory()->create();

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/company', [
            'name' => str_repeat('A', 151),
        ]);

        $response
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath(
                'error.details.name.0',
                'The name field must not be greater than 150 characters.'
            );
    });

    it('does not allow the company slug to be changed', function (): void {
        $company = Company::factory()->create([
            'name' => 'Original Company',
            'slug' => 'original-company',
        ]);

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/company', [
            'name' => 'Updated Company',
            'slug' => 'new-company-slug',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Company')
            ->assertJsonPath('data.slug', 'original-company');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Company',
            'slug' => 'original-company',
        ]);

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
            'slug' => 'new-company-slug',
        ]);
    });

    it('does not allow a user from another company to access this company', function (): void {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $userFromCompanyB = User::factory()->create([
            'company_id' => $companyB->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($userFromCompanyB);

        /*
         * The /company endpoint resolves the authenticated user's
         * own tenant. There is no company ID accepted from the client.
         */
        $response = $this->getJson('/api/v1/company');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $companyB->id)
            ->assertJsonMissing([
                'id' => $companyA->id,
            ]);
    });

    it('does not allow an owner to update another company through the request payload', function (): void {
        $companyA = Company::factory()->create([
            'name' => 'Company A',
        ]);

        $companyB = Company::factory()->create([
            'name' => 'Company B',
        ]);

        $ownerB = User::factory()->create([
            'company_id' => $companyB->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($ownerB);

        $response = $this->putJson('/api/v1/company', [
            'company_id' => $companyA->id,
            'name' => 'Hacked Company',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $companyB->id)
            ->assertJsonPath('data.name', 'Hacked Company');

        $this->assertDatabaseHas('companies', [
            'id' => $companyA->id,
            'name' => 'Company A',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $companyB->id,
            'name' => 'Hacked Company',
        ]);
    });
});
