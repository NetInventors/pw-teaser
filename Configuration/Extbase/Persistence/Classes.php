<?php

declare(strict_types=1);

use PwTeaserTeam\PwTeaser\Domain\Model\Category;
use PwTeaserTeam\PwTeaser\Domain\Model\Content;
use PwTeaserTeam\PwTeaser\Domain\Model\Page;

return [
    Page::class => [
        'tableName'  => 'pages',
        'properties' => [
            'navTitle'          => [ 'fieldName' => 'nav_title' ],
            'authorEmail'       => [ 'fieldName' => 'author_email' ],
            'crdate'            => [ 'fieldName' => 'crdate' ],
            'tstamp'            => [ 'fieldName' => 'tstamp' ],
            'lastUpdated'       => [ 'fieldName' => 'lastUpdated' ],
            'starttime'         => [ 'fieldName' => 'starttime' ],
            'endtime'           => [ 'fieldName' => 'endtime' ],
            'newUntil'          => [ 'fieldName' => 'newUntil' ],
            'sorting'           => [ 'fieldName' => 'sorting' ],
            'l18nConfiguration' => [ 'fieldName' => 'l18n_cfg' ],
        ],
    ],

    Content::class => [
        'tableName'  => 'tt_content',
        'properties' => [
            'pid'    => [ 'fieldName' => 'pid' ],
            'colPos' => [ 'fieldName' => 'colPos' ],
            'ctype'  => [ 'fieldName' => 'CType' ],
            'tstamp' => [ 'fieldName' => 'tstamp' ],
            'crdate' => [ 'fieldName' => 'crdate' ],
        ],
    ],

    Category::class => [
        'tableName'  => 'sys_category',
        'properties' => [
            'parentcategory' => [ 'fieldName' => 'parent' ],
        ],
    ],
];
