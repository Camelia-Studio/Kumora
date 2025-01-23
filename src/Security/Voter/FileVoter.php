<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ParentDirectory;
use App\Entity\User;
use App\Enum\RoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class FileVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof ParentDirectory;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /**
         * @var ParentDirectory $realSubject
         */
        $realSubject = $subject;
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($realSubject->getUserCreated() === $user) {
            return true;
        }
        return $realSubject->isPublic() && ($realSubject->getOwnerRole() === $user->getFolderRole() || in_array($user->getFolderRole(), $realSubject->getOwnerRole()->getHigherRoles(), true));
    }
}
