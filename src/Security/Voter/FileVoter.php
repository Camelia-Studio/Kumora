<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ParentDirectory;
use App\Entity\ParentDirectoryPermission;
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

        $parentDirectoryPermissionsVisiteurRead = array_filter($realSubject->getParentDirectoryPermissions()->toArray(), static fn (ParentDirectoryPermission $parentDirectoryPermission) => RoleEnum::VISITEUR === $parentDirectoryPermission->getRole());

        if ([] !== $parentDirectoryPermissionsVisiteurRead && !($user instanceof User)) {
            return 'file_read' === $attribute && array_values($parentDirectoryPermissionsVisiteurRead)[0]->isRead();
        }

        if (!$user instanceof User) {
            return false;
        }
        $parentDirectoryPermissions = array_filter($realSubject->getParentDirectoryPermissions()->toArray(), static fn (ParentDirectoryPermission $parentDirectoryPermission) => $parentDirectoryPermission->getRole() === $user->getFolderRole());

        $parentDirectoryPermission = null;

        if ([] !== $parentDirectoryPermissions) {
            $parentDirectoryPermission = array_values($parentDirectoryPermissions)[0];
        }

        $checkNeeded = false;

        if (null !== $parentDirectoryPermission) {
            $checkNeeded = 'file_read' === $attribute ? $parentDirectoryPermission->isRead() : $parentDirectoryPermission->isWrite();
        }

        if ($realSubject->getUserCreated() === $user) {
            return true;
        }
        return $realSubject->isPublic() && ($checkNeeded || $realSubject->getOwnerRole() === $user->getFolderRole() || in_array($user->getFolderRole(), $realSubject->getOwnerRole()->getHigherRoles(), true));
    }
}
