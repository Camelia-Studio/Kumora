<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class IconsExtensionRuntime implements RuntimeExtensionInterface
{
    public function getIcons(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

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
            'torrent' => 'mdi:download',
            'txt', 'md' => 'mdi:text-box',
            'html', 'htm', 'css', 'js', 'json', 'xml', 'yaml', 'yml', 'php', 'py', 'java', 'c', 'cpp', 'cs', 'rb', 'go', 'rs' => 'mdi:code-braces',
            default => 'line-md:file-filled',
        };
    }

    public function getFileType(string $filename): array
    {
        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'AVIF' => ['label' => 'Image'],
            'SVG' => ['label' => 'Image SVG'],
            'MP4', 'AVI', 'MOV', 'WEBM' => ['label' => 'Vidéo'],
            'MP3', 'WAV', 'M4A' => ['label' => 'Audio'],
            'PDF' => ['label' => 'PDF'],
            'DOC', 'DOCX', 'ODT' => ['label' => 'Document'],
            'XLSX', 'XLS', 'ODS', 'CSV' => ['label' => 'Tableur'],
            'PPTX', 'PPT', 'ODP' => ['label' => 'Présentation'],
            'ZIP', 'RAR', 'TAR', 'GZ', '7Z' => ['label' => 'Archive'],
            'TORRENT' => ['label' => 'Torrent'],
            'TXT', 'MD' => ['label' => 'Texte'],
            'HTML', 'HTM', 'CSS', 'JS', 'JSON', 'XML', 'YAML', 'YML', 'PHP', 'PY', 'JAVA', 'C', 'CPP', 'CS', 'RB', 'GO', 'RS' => ['label' => 'Code'],
            default => ['label' => '' !== $extension ? $extension : 'Fichier'],
        };
    }
}
