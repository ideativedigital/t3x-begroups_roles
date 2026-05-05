<?php

declare(strict_types=1);

namespace IchHabRecht\BegroupsRoles\Backend\ToolbarItems;

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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Renders roles switcher to toolbar
 */
class RoleSwitcher implements ToolbarItemInterface
{
    protected BackendUserAuthentication $backendUser;
    protected Connection $connection;
    private IconFactory $iconFactory;
    protected LanguageService $languageService;
    protected PageRenderer $pageRenderer;
    protected UriBuilder $uriBuilder;
    protected array $groups = [];
    protected int $role = 0;

    public function __construct(
        Connection $connection = null,
        IconFactory $iconFactory = null,
        $languageService = null,
        PageRenderer $pageRenderer = null,
        UriBuilder $uriBuilder = null
    ) {
        $this->backendUser = $GLOBALS['BE_USER'];
        $this->connection = $connection ?: GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->backendUser->user_table);
        $this->iconFactory = $iconFactory ?: GeneralUtility::makeInstance(IconFactory::class);
        $this->languageService = $languageService ?: $GLOBALS['LANG'];
        $this->pageRenderer = $pageRenderer ?: GeneralUtility::makeInstance(PageRenderer::class);
        $this->uriBuilder = $uriBuilder ?: GeneralUtility::makeInstance(UriBuilder::class);
    }

    /**
     * Checks whether the user has access to this toolbar item
     *
     * @return  bool
     * @throws \Doctrine\DBAL\Exception
     */
    public function checkAccess(): bool
    {
        if (empty($this->backendUser->user['tx_begroupsroles_enabled'])) {
            return false;
        }

        $this->role = (int)$this->backendUser->getSessionData('tx_begroupsroles_role');

        $queryBuilder = $this->connection->createQueryBuilder();
        $expressionBuilder = $queryBuilder->expr();
        $rows = $queryBuilder->select('uid', 'title')
            ->from($this->backendUser->usergroup_table)
            ->where(
                // Restrict selection to groups the user is part of
                $expressionBuilder->in(
                    'uid',
                    $queryBuilder->createNamedParameter(
                        GeneralUtility::intExplode(',', $this->backendUser->user['tx_begroupsroles_groups'] ?? '', true),
                        ArrayParameterType::INTEGER
                    )
                ),
                // Restrict to group that have been marked as roles
                $expressionBuilder->eq(
                    'tx_begroupsroles_isrole',
                    $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)
                )
            )
            ->orderBy('title')
            ->executeQuery()
            ->fetchAllAssociative();

        $this->groups = array_combine(array_map('intval', array_column($rows, 'uid')), $rows);

        return !empty($this->groups);
    }

    /**
     * Render "item" part of this toolbar
     *
     * @return string
     */
    public function getItem(): string
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/BegroupsRoles/Toolbar/RoleSwitcher');

        $title = $this->languageService->sL('LLL:EXT:begroups_roles/Resources/Private/Language/locallang_be.xlf:switch_group');
        $groupTitle = !empty($this->groups[$this->role])
            ? $this->groups[$this->role]['title']
            : $this->languageService->sL('LLL:EXT:begroups_roles/Resources/Private/Language/locallang_be.xlf:all_groups');

        return '<span title="' . htmlspecialchars($title) . '">'
            . $this->iconFactory->getIcon('begroups-roles-switchUserGroup', Icon::SIZE_SMALL)->render()
            . ' [' . htmlspecialchars($groupTitle) . ']'
            . '</span>';
    }

    /**
     * TRUE if this toolbar item has a collapsible drop down
     *
     * @return bool
     */
    public function hasDropDown(): bool
    {
        return true;
    }

    /**
     * Render "drop down" part of this toolbar
     *
     * @return string Drop down HTML
     */
    public function getDropDown(): string
    {
        $groupIcon = $this->iconFactory->getIcon('status-user-group-backend', Icon::SIZE_SMALL)->render('inline');

        $result = [];
        $result[] = '<p class="h3 dropdown-headline">' .
            $this->languageService->sL('LLL:EXT:begroups_roles/Resources/Private/Language/locallang_be.xlf:switch_role') .
            '</p>';
        $result[] = '<ul class="dropdown-list">';

        if (!empty($this->role) && empty($this->backendUser->user['tx_begroupsroles_limit'])) {
            $result[] = $this->formatDropDownItem(
                0,
                $groupIcon,
                $this->languageService->sL('LLL:EXT:begroups_roles/Resources/Private/Language/locallang_be.xlf:all_groups')
            );
        }

        foreach ($this->groups as $group) {
            if ($this->role !== (int)$group['uid']) {
                $result[] = $this->formatDropDownItem(
                    (int)$group['uid'],
                    $groupIcon,
                    $group['title']
                );
            }
        }

        $result[] = '</ul>';

        return implode(LF, $result);
    }

    /**
     * Return properly format toolbar dropdown item
     */
    protected function formatDropDownItem(int $group, string $icon, string $label): string
    {
        $item = <<<'EOD'
            <li>
                <a href="#" class="dropdown-item" data-role="%d">
                    <span class="dropdown-item-columns">
                        <span class="dropdown-item-column dropdown-item-column-icon">%s</span>
                        <span class="dropdown-item-column dropdown-item-column-title">%s</span>
                    </span>
                </a>
            </li>
EOD;
        return sprintf(
            $item,
            $group,
            $icon,
            htmlspecialchars($label)
        );
    }

    /**
     * Returns an array with additional attributes added to containing <li> tag of the item.
     *
     * @return array
     */
    public function getAdditionalAttributes(): array
    {
        return [];
    }

    /**
     * Returns an integer between 0 and 100 to determine the position of this item relative to others
     *
     * @return int
     */
    public function getIndex(): int
    {
        return 80;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws RouteNotFoundException
     */
    public function switchRoleAction(ServerRequestInterface $request): ResponseInterface
    {
        $newRole = (int)GeneralUtility::_POST('role');
        if ($newRole <= 0 || !GeneralUtility::inList($this->backendUser->user['tx_begroupsroles_groups'], (string)$newRole)) {
            $newRole = 0;
        }

        $this->backendUser->setAndSaveSessionData('tx_begroupsroles_role', $newRole);

        return new JsonResponse([
            'redirectUrl' => (string)$this->uriBuilder->buildUriFromRoute('main'),
        ]);
    }
}
