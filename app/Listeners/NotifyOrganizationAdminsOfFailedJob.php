<?php

namespace App\Listeners;

use App\Contracts\OrganizationScopedJob;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JobFailed;
use App\Services\OperationalMetrics;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Queue\Events\JobFailed as JobFailedEvent;
use Illuminate\Support\Facades\Log;

/**
 * §7.39 requires a notification when a background job fails. A failed job is
 * only attributable to a tenant when the job itself exposes an organization id
 * (OrganizationScopedJob); anything else is reported as a system failure and
 * deliberately not broadcast to a random tenant.
 */
class NotifyOrganizationAdminsOfFailedJob
{
    public function handle(JobFailedEvent $event): void
    {
        $name = $event->job->resolveName();
        $organizationId = $this->organizationId($event->job);

        // Structured operational signal for the §0.30 queue_failure_total metric.
        Log::error('queue.job_failed', [
            'job' => $name,
            'organization_id' => $organizationId,
            'exception' => $this->summarize($event->exception),
        ]);

        app(OperationalMetrics::class)->increment('queue_failure_total', [
            'job' => class_basename($name),
            'organization_id' => $organizationId,
        ]);

        if (! $organizationId) {
            report(new \RuntimeException("Queued job [{$name}] failed without a resolvable organization."));

            return;
        }

        $organization = Organization::find($organizationId);

        if (! $organization) {
            report(new \RuntimeException("Queued job [{$name}] failed for an organization that no longer exists."));

            return;
        }

        $notification = new JobFailed(
            $organization->id,
            class_basename($event->job->resolveQueuedJobClass()),
            $this->summarize($event->exception)
        );

        User::membersWithPermission($organization, 'manage-settings')
            ->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * The resolved job of a failed queue job is the framework's CallQueuedHandler
     * rather than the queued command, so the command is read from the payload.
     * Unserialization is restricted to the class the payload itself declares.
     */
    private function organizationId(QueueJob $job): ?int
    {
        $resolved = $job->getResolvedJob();

        if ($resolved instanceof OrganizationScopedJob) {
            return $resolved->organizationId();
        }

        $class = $job->resolveQueuedJobClass();
        $command = $job->payload()['data']['command'] ?? null;

        if (! is_string($command) || ! class_exists($class)) {
            return null;
        }

        $command = @unserialize($command, ['allowed_classes' => [$class]]);

        return $command instanceof OrganizationScopedJob
            ? $command->organizationId()
            : null;
    }

    private function summarize(?\Throwable $exception): string
    {
        if (! $exception) {
            return 'The job failed without an exception message.';
        }

        return substr($exception->getMessage(), 0, 500);
    }
}
