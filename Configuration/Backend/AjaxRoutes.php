<?php

declare(strict_types=1);

use Supseven\Webauthn\BackendAjaxRequestHandler;

return [
    'webauthn_register_options' => [
        'path'   => '/subseven/webauthn/register/options',
        'target' => BackendAjaxRequestHandler::class . '::registerOptions',
    ],
    'webauthn_register_save' => [
        'path'   => '/subseven/webauthn/register/save',
        'target' => BackendAjaxRequestHandler::class . '::registerSave',
    ],
    'webauthn_auth_options' => [
        'path'   => '/subseven/webauthn/auth/options',
        'target' => BackendAjaxRequestHandler::class . '::authOptions',
    ],
    'webauthn_auth_verify' => [
        'path'   => '/subseven/webauthn/auth/verify',
        'target' => BackendAjaxRequestHandler::class . '::authVerify',
    ],
];
