<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$tempColumns = [
    'tx_begroupsroles_isrole' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:begroups_roles/Resources/Private/Language/locallang_db.xlf:be_groups.tx_begroupsroles_isrole',
        'description' => 'LLL:EXT:begroups_roles/Resources/Private/Language/locallang_db.xlf:be_groups.tx_begroupsroles_isrole.description',
        'config' => [
            'type' => 'check',
            'default' => 0,
        ],
    ],
];
ExtensionManagementUtility::addTCAcolumns('be_groups', $tempColumns);
ExtensionManagementUtility::addFieldsToPalette('be_groups', 'tx_begroupsroles', 'tx_begroupsroles_isrole');
ExtensionManagementUtility::addToAllTCAtypes('be_groups', '--palette--;LLL:EXT:begroups_roles/Resources/Private/Language/locallang_db.xlf:be_groups.tx_begroupsroles_title;tx_begroupsroles', '', 'after:subgroup');
