<?php

$EM_CONF[$_EXTKEY] = [
    'title'          => 'Webauthn',
    'description'    => 'MFA provider using the webauthn standard for authentication',
    'category'       => 'misc',
    'author'         => 'Supseven',
    'author_email'   => 'office@supseven.at',
    'author_company' => 'supseven',
    'state'          => 'stable',
    'version'        => '1.0.0',
    'constraints'    => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Supseven\\Webauthn\\' => 'src/',
        ],
    ],
];
