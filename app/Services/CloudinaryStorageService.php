<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CloudinaryStorageService
{
    private ?Cloudinary $client = null;

    public function isConfigured(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    /**
     * Upload raw bytes (e.g. decoded base64) to Cloudinary.
     *
     * @return array{public_id: string, secure_url: string, url: string, bytes: int|null, format: string|null}
     */
    public function uploadBuffer(string $bytes, string $folder, ?string $publicId = null, ?string $mime = null): array
    {
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $temp = tempnam(sys_get_temp_dir(), 'agroaide_cloud_');
        if ($temp === false) {
            throw new RuntimeException('Could not create temporary file for Cloudinary upload.');
        }

        $path = $temp.'.'.$extension;
        if (! @rename($temp, $path)) {
            $path = $temp;
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new RuntimeException('Could not write temporary file for Cloudinary upload.');
            }

            return $this->uploadFilePath($path, $folder, $publicId);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Upload from a local filesystem path.
     *
     * @return array{public_id: string, secure_url: string, url: string, bytes: int|null, format: string|null}
     */
    public function uploadFilePath(string $path, string $folder, ?string $publicId = null): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Upload path is not readable.');
        }

        $options = [
            'folder' => trim($folder, '/'),
            'resource_type' => 'image',
            'overwrite' => false,
            'unique_filename' => true,
        ];

        if ($publicId) {
            $options['public_id'] = $publicId;
            $options['unique_filename'] = false;
        }

        $result = $this->client()->uploadApi()->upload($path, $options);

        return $this->normalizeResult($result);
    }

    /**
     * Upload an UploadedFile / stream-backed file.
     *
     * @return array{public_id: string, secure_url: string, url: string, bytes: int|null, format: string|null}
     */
    public function uploadUploadedFile(UploadedFile $file, string $folder, ?string $publicId = null): array
    {
        return $this->uploadFilePath($file->getRealPath() ?: $file->getPathname(), $folder, $publicId);
    }

    /**
     * Delete a Cloudinary asset by public_id.
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        if (! $this->isConfigured() || trim($publicId) === '') {
            return false;
        }

        try {
            $result = $this->client()->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType,
                'invalidate' => true,
            ]);

            $status = is_array($result) ? ($result['result'] ?? null) : null;

            return in_array($status, ['ok', 'not found'], true);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function client(): Cloudinary
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Cloudinary is not configured. Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in .env.'
            );
        }

        if ($this->client === null) {
            $this->client = new Cloudinary([
                'cloud' => [
                    'cloud_name' => config('services.cloudinary.cloud_name'),
                    'api_key' => config('services.cloudinary.api_key'),
                    'api_secret' => config('services.cloudinary.api_secret'),
                ],
                'url' => [
                    'secure' => true,
                ],
            ]);
        }

        return $this->client;
    }

    /**
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>  $result
     * @return array{public_id: string, secure_url: string, url: string, bytes: int|null, format: string|null}
     */
    private function normalizeResult(array|\ArrayAccess $result): array
    {
        $publicId = (string) ($result['public_id'] ?? '');
        $secureUrl = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        if ($publicId === '' || $secureUrl === '') {
            throw new RuntimeException('Cloudinary upload returned an incomplete response.');
        }

        return [
            'public_id' => $publicId,
            'secure_url' => $secureUrl,
            'url' => (string) ($result['url'] ?? $secureUrl),
            'bytes' => isset($result['bytes']) ? (int) $result['bytes'] : null,
            'format' => isset($result['format']) ? (string) $result['format'] : null,
        ];
    }
}
