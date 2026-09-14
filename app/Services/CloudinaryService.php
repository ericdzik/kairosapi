<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Resize;

class CloudinaryService
{
    private static function cloudinary(): Cloudinary
    {
        return new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key'    => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);
    }

    /**
     * Uploader une image et retourner le public_id
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $folder  ex: 'clients/photos' ou 'produits/images'
     * @return string  le public_id Cloudinary (à stocker en base)
     */
    public static function upload($file, string $folder): string
    {
        $result = static::cloudinary()->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder'         => 'kairos/' . $folder,
                'resource_type'  => 'image',
                'transformation' => [
                    ['quality' => 'auto', 'fetch_format' => 'auto'],
                ],
            ]
        );
        return $result['public_id'];
    }

    /**
     * Supprimer une image par son public_id
     */
    public static function delete(string $publicId): void
    {
        try {
            static::cloudinary()->uploadApi()->destroy($publicId);
        } catch (\Throwable $e) {
            // Ne pas bloquer si la suppression échoue
            \Log::warning("Cloudinary delete failed for $publicId: " . $e->getMessage());
        }
    }

    /**
     * Retourner l'URL publique d'une image
     */
    public static function url(?string $publicId): ?string
    {
        if (!$publicId) return null;

        // Si c'est déjà une URL complète (ancienne donnée locale), la retourner telle quelle
        if (str_starts_with($publicId, 'http')) return $publicId;

        return static::cloudinary()->image($publicId)->toUrl();
    }
}