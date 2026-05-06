# TYPO3 Extension begroups_roles

[![Latest Stable Version](https://img.shields.io/packagist/v/cron-eu/begroups-roles.svg)](https://packagist.org/packages/cron-eu/begroups-roles)
[![StyleCI](https://styleci.io/repos/699370966/shield?branch=master)](https://styleci.io/repos/699370966)

Use backend user groups as switchable roles

**Note: this is a fork from https://github.com/cron-eu/t3x-begroups_roles,
itself a fork of https://github.com/IchHabRecht/begroups_roles.
It adds support for TYPO3 12, dropping support for any older versions of TYPO3.
Labels, code and icons have been cleaned up. Tests have not been reimplemented.
It is released but is not meant to be an official version.**

![Role switcher](Documentation/Images/role_switcher.png)

## Installation

Simply install the extension with Composer or the Extension Manager.

```
composer require cron-eu/begroups-roles
```

## Usage

1. Add multiple backend groups, each for one purpose
   - Tick the checkbox `Use this group as role`
   - Tick the checkbox `Exclude subgroups` to exclude the subgroups of this group
     (otherwise all subgroups that are marked as role will also appear in the selector)
   - Limit the modules, tables and database mount to the purpose

2. Assign the created (parent) group to backend users
   - Tick the checkbox `Use groups as roles`
   - To allow only one role group simultaneously, tick the checkbox `Restrict to one group`
