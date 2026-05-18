<?php

declare(strict_types=1);

namespace IchHabRecht\BegroupsRoles\Hook;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Nicole Cordes <cordes@cps-it.de>, CPS-IT GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\GroupResolver;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sets current user group
 */
class SwitchUserRoleHook
{
    protected ?BackendUserAuthentication $backendUser = null;

    public function __construct(
        protected ConnectionPool $connection
    ) {
        $this->backendUser = $GLOBALS['BE_USER'];
    }

    /**
     * Assign user group from session data
     * @throws \Doctrine\DBAL\Exception
     */
    public function setUserGroup(): void
    {
        if (empty($this->backendUser->user['tx_begroupsroles_enabled'])) {
            return;
        }

        $role = $this->backendUser->getSessionData('tx_begroupsroles_role');
        if ($role === null) {
            $role = 0;

            $usergroups = $this->backendUser->user[$this->backendUser->usergroup_column];
            $this->backendUser->user['tx_begroupsroles_groups'] = implode(
                ',',
                array_unique(
                    array_merge(
                        GeneralUtility::intExplode(',', $usergroups, true),
                        $this->getUsergroups($usergroups)
                    )
                )
            );
            // Store the list of groups the user is part of (direct or inherited)
            $queryBuilder = $this->connection->getQueryBuilderForTable($this->backendUser->user_table);
            $queryBuilder->update($this->backendUser->user_table)
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($this->backendUser->user['uid'], \PDO::PARAM_INT)
                    )
                )
                ->set('tx_begroupsroles_groups', $this->backendUser->user['tx_begroupsroles_groups'])
                ->executeStatement();
        }

        $possibleUsergroups = GeneralUtility::intExplode(',', $this->backendUser->user['tx_begroupsroles_groups'] ?? '', true);
        if (empty($role) && !empty($this->backendUser->user['tx_begroupsroles_limit'])) {
            $queryBuilder = $this->connection->getQueryBuilderForTable($this->backendUser->user_table);
            $expressionBuilder = $queryBuilder->expr();
            $rows = $queryBuilder->select('uid')
                ->from($this->backendUser->usergroup_table)
                ->where(
                    $expressionBuilder->in(
                        'uid',
                        $queryBuilder->createNamedParameter($possibleUsergroups, ArrayParameterType::INTEGER)
                    ),
                    $expressionBuilder->eq(
                        'tx_begroupsroles_isrole',
                        $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)
                    )
                )
                ->orderBy('title')
                ->executeQuery()
                ->fetchAllAssociative();

            $rows = array_combine(array_map('intval', array_column($rows, 'uid')), $rows);
            $orderedUsergroups = array_keys(array_intersect_key($rows, array_flip($possibleUsergroups)));

            $role = !empty($orderedUsergroups[0]) ? $orderedUsergroups[0] : 0;
        }
        if (in_array($role, $possibleUsergroups, true)) {
            $this->backendUser->user[$this->backendUser->usergroup_column] = $role;
            $groupResolver = GeneralUtility::makeInstance(GroupResolver::class);
            $groups = $groupResolver->resolveGroupsForUser($this->backendUser->user, $this->backendUser->usergroup_table);
            $dbMountPoints = [];
            $fileMountPoints = [];
            $this->backendUser->userGroupsUID = [];
            foreach ($groups as $group) {
                $this->backendUser->userGroupsUID[] = $group['uid'];
                $dbMountPoints = array_merge(
                    $dbMountPoints,
                    GeneralUtility::intExplode(',', $group['db_mountpoints'] ?? '', true)
                );
                $fileMountPoints = array_merge(
                    $fileMountPoints,
                    GeneralUtility::intExplode(',', $group['file_mountpoints'] ?? '', true)
                );
            }
            $this->backendUser->user['db_mountpoints'] = implode(',', array_unique($dbMountPoints));
            $this->backendUser->user['file_mountpoints'] = implode(',', array_unique($fileMountPoints));
            if (!empty($this->backendUser->user['admin'])) {
                $this->backendUser->user['options'] |= Permission::PAGE_SHOW | Permission::PAGE_EDIT;
                $this->backendUser->user['admin'] = 0;
            }
            GeneralUtility::makeInstance(Context::class)->setAspect(
                'backend.user',
                GeneralUtility::makeInstance(
                    UserAspect::class,
                    $this->backendUser
                )
            );
        } else {
            $role = 0;
        }
        $this->backendUser->setAndSaveSessionData('tx_begroupsroles_role', $role);
    }

    /**
     * @param string $groupList
     * @param array $processedUsergroups
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getUsergroups(string $groupList, array $processedUsergroups = []): array
    {
        $queryBuilder = $this->connection->getQueryBuilderForTable($this->backendUser->user_table);
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        $expressionBuilder = $queryBuilder->expr();
        $statement = $queryBuilder->select('uid', 'subgroup')
            ->from($this->backendUser->usergroup_table)
            ->where(
                $expressionBuilder->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                ),
                $expressionBuilder->in(
                    'uid',
                    $queryBuilder->createNamedParameter(
                        GeneralUtility::intExplode(',', $groupList, true),
                        ArrayParameterType::INTEGER
                    )
                )
            )
            ->executeQuery();

        $usergroups = [];
        while ($row = $statement->fetchAssociative()) {
            if (isset($processedUsergroups[$row['uid']])) {
                continue;
            }

            $processedUsergroups[$row['uid']] = $row['uid'];
            $usergroups[$row['uid']] = $row['uid'];
            if (!empty($row['subgroup'])) {
                $subgroupList = GeneralUtility::intExplode(',', $row['subgroup'], true);
                $subgroups = $this->getUsergroups($row['subgroup'], $processedUsergroups);
                if (!empty($subgroups)) {
                    $usergroups = array_merge(
                        $usergroups,
                        array_intersect($subgroupList, $subgroups),
                        array_diff($subgroups, $subgroupList)
                    );
                }
            }
        }

        return $usergroups;
    }
}
