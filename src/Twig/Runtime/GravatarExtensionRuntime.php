<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class GravatarExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly string $defaultImage)
    {
    }

    public function getGravatar(string $email): string
    {
        $hash = hash('sha256', strtolower(trim($email)));

        return 'https://gravatar.com/avatar/' . $hash . '?s=2048&d=' . urlencode($this->defaultImage);
    }
}
