<?php

declare(strict_types=1);

namespace App\Flysystem;

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class CustomLocalAdapter extends LocalFilesystemAdapter
{
    public function __construct(string $directory)
    {
        parent::__construct(
            $directory,
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0o755,
                    'private' => 0o755,
                ],
                'dir' => [
                    'public' => 0o755,
                    'private' => 0o755,
                ],
            ])
        );
    }
}
