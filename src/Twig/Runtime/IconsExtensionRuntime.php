<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class IconsExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
    }

    public function getIcons(string $filename):string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' => 'material-symbols:image',
            'svg' => 'teenyicons:svg-solid',
            'mp4', 'avi', 'mov', 'webm' => 'mdi:video',
            'mp3', 'wav', 'm4a' => 'mdi:music',
            'pdf' => 'teenyicons:pdf-solid',
            'doc', 'docx', 'odt' => 'teenyicons:ms-word-solid',
            'xlsx', 'xls', 'ods', 'csv' => 'icon-park-solid:excel',
            'pptx', 'ppt', 'odp' => 'teenyicons:ms-powerpoint-solid',
            'zip', 'rar', 'tar', 'gz', '7z' => 'teenyicons:archive-solid',
            default => 'line-md:file-filled',
        };
    }
}
