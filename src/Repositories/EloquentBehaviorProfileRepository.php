<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Repositories;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\Contracts\BehaviorProfileRepositoryInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;
use JoseQuembi\AngolaGeoGuard\Models\GeoBehaviorProfile;

final class EloquentBehaviorProfileRepository implements BehaviorProfileRepositoryInterface
{
    public function find(string $subjectKey): ?BehaviorProfileData
    {
        $model = GeoBehaviorProfile::query()->where('subject_key', $subjectKey)->first();

        return $model?->toBehaviorProfileData();
    }

    public function save(BehaviorProfileData $profile, ?string $tenantId = null): void
    {
        $model = GeoBehaviorProfile::query()->firstOrNew(['subject_key' => $profile->subjectKey]);
        $model->fillFromBehaviorProfileData($profile, $tenantId);
        $model->save();
    }

    public function pruneOlderThan(DateTimeImmutable $cutoff): int
    {
        return GeoBehaviorProfile::query()
            ->where('last_observed_at', '<', $cutoff)
            ->whereNull('quarantined_until')
            ->delete();
    }
}
