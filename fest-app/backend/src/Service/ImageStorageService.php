<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageStorageService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function saveImage(UploadedFile $file, string $directory, ?string $previousPath = null): string
    {
        $directory = trim($directory, '/');
        $relativeDirectory = 'uploads/' . $directory;
        $targetDirectory = $this->projectDir . '/public/' . $relativeDirectory;

        if (!is_dir($targetDirectory)) {
            @mkdir($targetDirectory, 0775, true);
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        $file->move($targetDirectory, $filename);

        if ($previousPath) {
            $this->deleteIfExists($previousPath);
        }

        return '/' . $relativeDirectory . '/' . $filename;
    }

    private function deleteIfExists(string $path): void
    {
        $relativePath = ltrim($path, '/');

        if (!str_starts_with($relativePath, 'uploads/')) {
            return;
        }

        $absolutePath = $this->projectDir . '/public/' . $relativePath;

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
