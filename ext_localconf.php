<?php

use IchHabRecht\BegroupsRoles\Hook\SwitchUserRoleHook;

defined('TYPO3') || die();

call_user_func(function () {
    // Register hook to adjust current user group
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['postUserLookUp']['begroups_roles'] =
        SwitchUserRoleHook::class . '->setUserGroup';
});
