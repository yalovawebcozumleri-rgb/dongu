<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProfileAvatarService
{
    public function store(User $user, UploadedFile $file): User
    {
        $bytes = file_get_contents($file->getRealPath());
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($source === false) throw new RuntimeException('Profil fotoğrafı işlenemedi. Farklı bir fotoğraf dene.');

        $oldPaths = $this->paths($user->avatar_path);
        $base = 'avatars/'.$user->id.'/'.Str::uuid();
        $newPaths = [$base.'-128.webp', $base.'-512.webp'];

        try {
            foreach ([128, 512] as $index => $size) {
                $encoded = $this->squareWebp($source, $size);
                if (! Storage::disk('public')->put($newPaths[$index], $encoded)) {
                    throw new RuntimeException('Profil fotoğrafı kaydedilemedi.');
                }
            }
            $user->forceFill(['avatar_path' => $newPaths[1]])->save();
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($newPaths);
            throw $error;
        } finally {
            imagedestroy($source);
        }

        Storage::disk('public')->delete($oldPaths);
        return $user->fresh();
    }

    public function remove(User $user): User
    {
        $paths = $this->paths($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();
        Storage::disk('public')->delete($paths);
        return $user->fresh();
    }

    public function url(?string $path, bool $thumbnail = false): ?string
    {
        if (! $path) return null;
        $target = $thumbnail ? $this->thumbnailPath($path) : $path;
        return request()->getSchemeAndHttpHost().'/storage/'.ltrim((string) $target, '/');
    }

    public function thumbnailPath(?string $path): ?string    {
        if (! $path) return null;
        return str_ends_with($path, '-512.webp') ? Str::replaceLast('-512.webp', '-128.webp', $path) : $path;
    }

    private function paths(?string $path): array
    {
        if (! $path) return [];
        return array_values(array_unique([$path, $this->thumbnailPath($path)]));
    }

    private function squareWebp(\GdImage $source, int $size): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $sourceX = (int) floor(($width - $side) / 2);
        $sourceY = (int) floor(($height - $side) / 2);
        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $size, $size, $side, $side);
        ob_start();
        $ok = imagewebp($target, null, 82);
        $encoded = ob_get_clean();
        imagedestroy($target);
        if (! $ok || ! is_string($encoded) || $encoded === '') throw new RuntimeException('Profil fotoğrafı WebP biçimine dönüştürülemedi.');
        return $encoded;
    }
}
