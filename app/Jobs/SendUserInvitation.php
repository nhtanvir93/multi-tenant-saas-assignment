<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\UserInvitationEmail;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an invitation email to a newly created tenant user.
 */
final class SendUserInvitation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Retry delays in seconds.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job.
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $userId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TenantContext $tenantContext): void
    {
        $tenantContext->runAs(
            $this->companyId,
            function (): void {
                $user = User::query()->find($this->userId);

                if ($user === null) {
                    return;
                }

                Mail::to($user->email)->send(
                    new UserInvitationEmail($user),
                );
            },
        );
    }
}
