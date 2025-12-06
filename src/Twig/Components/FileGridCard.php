<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\ParentDirectory;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FileGridCard
{
    public array $file;
    public ParentDirectory $parentDirectory;
    public array $selectedFiles;
    public string $search;

    public function isImage(): bool
    {
        if ('file' !== $this->file['type']) {
            return false;
        }

        $extension = pathinfo(basename((string) $this->file['path']), PATHINFO_EXTENSION);

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'], true);
    }

    public function isSelected(): bool
    {
        return in_array($this->file['path'], $this->selectedFiles, true);
    }
}
