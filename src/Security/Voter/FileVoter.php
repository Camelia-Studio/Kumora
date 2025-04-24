<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ParentDirectory;
use App\Entity\ParentDirectoryPermission;
use App\Entity\User;
use App\Repository\AccessGroupRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FileVoter extends Voter
{
    public function __construct(private readonly AccessGroupRepository $accessGroupRepository)
    {
    }

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

        $parentDirectoryPermissionsVisiteurRead = array_filter($realSubject->getParentDirectoryPermissions()->toArray(), static fn (ParentDirectoryPermission $parentDirectoryPermission) => $this->accessGroupRepository->getLowestRole() === $parentDirectoryPermission->getRole());

        if ([] !== $parentDirectoryPermissionsVisiteurRead && !($user instanceof User)) {
            return 'file_read' === $attribute && array_values($parentDirectoryPermissionsVisiteurRead)[0]->isRead();
        }

        if (!$user instanceof User) {
            return false;
        }
        $parentDirectoryPermissions = array_filter($realSubject->getParentDirectoryPermissions()->toArray(), static fn (ParentDirectoryPermission $parentDirectoryPermission) => $parentDirectoryPermission->getRole() === $user->getAccessGroup());

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
        return $realSubject->isPublic() && ($checkNeeded || $realSubject->getOwnerRole() === $user->getAccessGroup() || in_array($user->getAccessGroup(), $this->accessGroupRepository->getHigherRoles($realSubject->getOwnerRole()), true));
    }
}
