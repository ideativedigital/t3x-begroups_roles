#
# Table structure for table 'be_users'
#
CREATE TABLE be_groups (
    tx_begroupsroles_isrole tinyint(4) UNSIGNED default 0 not null,
		tx_begroupsroles_subgroup tinyint(4) UNSIGNED default 0 not null
);

#
# Table structure for table 'be_users'
#
CREATE TABLE be_users (
    tx_begroupsroles_enabled tinyint(4) UNSIGNED default 0 not null,
    tx_begroupsroles_limit tinyint(4) UNSIGNED default 0 not null,
    tx_begroupsroles_groups text
);
