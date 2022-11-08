<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'webauthn' => [
        'source'   => 'EXT:webauthn/Resources/Public/Icons/Extension.svg',
        'provider' => SvgIconProvider::class,
    ],
];
