<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What the signed-in person may use: the licence of this copy, narrowed by
 * their own account — the people the system is given to (employees of a
 * bank or a valuation company) get Premium by a tick and work until the day
 * written on their account. Administrators of the company that runs the
 * system have all that the licence gives.
 */
final class Access
{
    private function __construct(
        public readonly License $license,
        public readonly ?User $user,
    ) {}

    public static function current(): self
    {
        return self::for(auth()->user());
    }

    public static function for(?User $user): self
    {
        return new self(License::current(), $user);
    }

    /** An administrator of the company that runs the system (those of an organisation get their access as the others). */
    private function isAdmin(): bool
    {
        return $this->user === null || $this->user->isMainAdmin();
    }

    /** The day the person's own access ends (none for administrators). */
    public function userUntil(): ?CarbonImmutable
    {
        return ! $this->isAdmin() && $this->user->access_until ? CarbonImmutable::parse($this->user->access_until)->endOfDay() : null;
    }

    /** Their own access has ended, whatever the licence of the copy. */
    public function userEnded(): bool
    {
        return $this->userUntil()?->isPast() ?? false;
    }

    public function allowsChanges(): bool
    {
        return $this->license->allowsChanges() && ! $this->userEnded();
    }

    public function isPremium(): bool
    {
        return $this->allowsChanges() && $this->license->isPremium() && ($this->isAdmin() || $this->user->premium);
    }

    /** The nearer of the end of the licence and of the person's own access. */
    public function until(): ?CarbonImmutable
    {
        $ends = array_filter([$this->license->until, $this->userUntil()]);

        return $ends === [] ? null : min($ends);
    }

    public function daysLeft(): ?int
    {
        return $this->until() ? (int) max(0, now()->startOfDay()->diffInDays($this->until()->startOfDay(), false)) : null;
    }

    public function endsSoon(): bool
    {
        return $this->allowsChanges() && $this->daysLeft() !== null && $this->daysLeft() <= License::WARN_DAYS;
    }

    public function planLabel(): string
    {
        return $this->isPremium() || (! $this->allowsChanges() && $this->license->plan === License::PREMIUM && ($this->isAdmin() || $this->user->premium))
            ? __('Premium')
            : __('Standard');
    }

    /** The plan is shown in the top bar, except to the administrator of the seller's own copy. */
    public function shown(): bool
    {
        return ! ($this->license->state === 'seller' && $this->isAdmin());
    }

    /** Why nothing can be changed now, for the notice and the refusal. */
    public function lockedMessage(): string
    {
        if ($this->userEnded()) {
            return __('Your access ended on :date: the system is open for viewing only.', ['date' => $this->userUntil()->format('d.m.Y')]);
        }

        return $this->license->state === 'expired'
            ? __('The licence ended on :date: the system is open for viewing only.', ['date' => $this->license->until->format('d.m.Y')])
            : __('No valid licence key: the system is open for viewing only.');
    }
}
