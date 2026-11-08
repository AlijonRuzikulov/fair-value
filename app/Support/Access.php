<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What the signed-in person may do: the people the system is given to
 * (employees of a bank or a valuation company) work until the day written
 * on their account, if one is written; after it they can only look.
 * Administrators of the company that runs the system have no such day.
 */
final class Access
{
    private function __construct(public readonly ?User $user) {}

    public static function current(): self
    {
        return self::for(auth()->user());
    }

    public static function for(?User $user): self
    {
        return new self($user);
    }

    /** An administrator of the company that runs the system (those of an organisation get their access as the others). */
    private function isAdmin(): bool
    {
        return $this->user === null || $this->user->isMainAdmin();
    }

    /** The day the person's access ends (none for administrators, or when no day is written). */
    public function until(): ?CarbonImmutable
    {
        return ! $this->isAdmin() && $this->user->access_until ? CarbonImmutable::parse($this->user->access_until)->endOfDay() : null;
    }

    public function ended(): bool
    {
        return $this->until()?->isPast() ?? false;
    }

    public function allowsChanges(): bool
    {
        return ! $this->ended();
    }

    public function daysLeft(): ?int
    {
        return $this->until() ? (int) max(0, now()->startOfDay()->diffInDays($this->until()->startOfDay(), false)) : null;
    }

    /** Days before the end when the warning appears. */
    public const WARN_DAYS = 30;

    public function endsSoon(): bool
    {
        return ! $this->ended() && $this->daysLeft() !== null && $this->daysLeft() <= self::WARN_DAYS;
    }

    /** Why nothing can be changed now, for the notice and the refusal. */
    public function lockedMessage(): string
    {
        return __('Your access ended on :date: the system is open for viewing only.', ['date' => $this->until()->format('d.m.Y')]);
    }
}
