<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'YAML to TCA loader',
    'description' => 'Load contentelements from yaml in register in new element wizard',
    'state' => 'stable',
    'author' => 'Michael Stein (mxsteini)',
    'author_email' => 'info@michaelstein-itb.de',
    'author_company' => 'Michael Stein IT-Beratung',
    'version' => '1.0.12',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-13.4.99',
        ],
    ],
];
