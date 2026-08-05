<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PruneListings extends Command
{
    protected $signature = 'listings:prune {--dry-run : Silinecek ilanları sayar ancak silmez}';

    protected $description = 'Saklama süresi dolan ilanları ve fotoğraflarını kalıcı olarak temizler';

    public function handle(): int
    {
        $query = Listing::withTrashed()
            ->where(function (Builder $query) {
                $query
                    ->where(fn (Builder $query) => $query
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now()->subDays(config('marketplace.expired_listing_retention_days'))))
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNotNull('deleted_at')
                        ->where('deleted_at', '<=', now()->subDays(config('marketplace.deleted_listing_retention_days'))));
            });

        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Kalıcı olarak temizlenecek ilan sayısı: {$total}");
            return self::SUCCESS;
        }

        $deleted = 0;
        $query->with('photos')->chunkById(100, function ($listings) use (&$deleted) {
            foreach ($listings as $listing) {
                Storage::disk('public')->delete($listing->photos->pluck('path')->all());
                $listing->forceDelete();
                $deleted++;
            }
        });

        $this->info("{$deleted} ilan ve ilişkili dosyaları kalıcı olarak temizlendi.");

        return self::SUCCESS;
    }
}