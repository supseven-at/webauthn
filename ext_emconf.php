<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title'        => 'Webauthn',
    'description'  => 'MFA provider using the webauthn standard for authentication',
    'category'     => 'misc',
    'author'       => 'Supseven',
    'author_email' => 'office@supseven.at',
    'state'        => 'excludeFromUpdates',
    'version'      => '1.0.0',
    'constraints'  => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];
