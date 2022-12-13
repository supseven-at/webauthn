<?php

declare(strict_types=1);

namespace Supseven\Webauthn;

use Psr\Http\Message\ServerRequestInterface as Reqest;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Server;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class CredentialsService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function createCredentialCreationOptions(FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): PublicKeyCredentialCreationOptions
    {
        $user = $this->createUser($login);
        $repository = new CredentialsRepository($propertyManager);

        $excludeCredentials = array_map(
            fn (PublicKeyCredentialSource $credential) => $credential->getPublicKeyCredentialDescriptor(),
            $repository->findAllForUserEntity($user)
        );

        $options = $this->createServer($repository)->generatePublicKeyCredentialCreationOptions(
            $user,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $excludeCredentials
        );

        $options->getAuthenticatorSelection()->setUserVerification(AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED);
        $options->getAuthenticatorSelection()->setAuthenticatorAttachment(AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_CROSS_PLATFORM);

        $login->setAndSaveSessionData('tx_webauthn_register', $options->jsonSerialize());

        return $options;
    }

    public function saveCredentails(Reqest $request, FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): bool
    {
        try {
            $credentials = $request->getParsedBody()['credential'] ?? null;
            $name = $request->getParsedBody()['name'] ?? null;

            if (!$name || !$credentials) {
                return false;
            }

            /** @var PublicKeyCredentialCreationOptions $options */
            $options = PublicKeyCredentialCreationOptions::createFromArray($login->getSessionData('tx_webauthn_register'));
            $login->setAndSaveSessionData('tx_webauthn_register', null);

            $repository = new CredentialsRepository($propertyManager);

            $publicKeyCredentialSource = $this->createServer($repository)->loadAndCheckAttestationResponse(
                $credentials,
                $options,
                $request
            );

            $repository->saveNamedCredentialSource(
                $name,
                $publicKeyCredentialSource
            );

            return true;
        } catch(\Throwable $ex) {
            $this->logger->error('Cannot save credentials: ' . $ex->getMessage(), $ex->getTrace());
        }

        return false;
    }

    public function createCredentialRequestOptions(FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): PublicKeyCredentialRequestOptions
    {
        $userEntity = $this->createUser($login);
        $repository = new CredentialsRepository($propertyManager);
        $credentialSources = $repository->findAllForUserEntity($userEntity);
        $allowedCredentials = array_map(
            fn (PublicKeyCredentialSource $credential) => $credential->getPublicKeyCredentialDescriptor(),
            $credentialSources
        );

        $options = $this->createServer($repository)->generatePublicKeyCredentialRequestOptions(
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_DISCOURAGED,
            $allowedCredentials
        );

        $login->setAndSaveSessionData('tx_webauthn_auth', $options->jsonSerialize());

        return $options;
    }

    public function verifyAuth(Reqest $request, FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): bool
    {
        try {
            /** @var PublicKeyCredentialRequestOptions $options */
            $options = PublicKeyCredentialRequestOptions::createFromArray($login->getSessionData('tx_webauthn_auth'));
            $login->setAndSaveSessionData('tx_webauthn_auth', null);

            $userEntity = $this->createUser($login);
            $repository = new CredentialsRepository($propertyManager);
            $server = $this->createServer($repository);

            $server->loadAndCheckAssertionResponse(
                $request->getParsedBody()['credential'],
                $options,
                $userEntity,
                $request
            );

            return true;
        } catch (\Throwable $ex) {
            $this->logger->warning('Cannot verify request: ' . $ex->getMessage(), [$ex]);
        }

        return false;
    }

    private function createRelayingParty(): PublicKeyCredentialRpEntity
    {
        $extConf = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webauthn'] ?? [];
        $name = $extConf['name'] ?? $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ?? 'TYPO3';

        if (!$name) {
            throw new \ValueError('No name configured');
        }

        $appID = (string)($extConf['id'] ?? '');

        if ($appID) {
            if (strlen($appID) > 250 || str_contains($appID, '/') || !filter_var($appID, FILTER_VALIDATE_DOMAIN)) {
                throw new \ValueError('Invalid app ID, must be a domain');
            }

            $appID = 'https://' . $appID;
        } else {
            $appID = null;
        }

        $appIcon = (string)($extConf['icon'] ?? $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['loginLogo'] ?? '');

        if ($appIcon) {
            $appIconPath = PathUtility::getAbsoluteWebPath($appIcon);

            if ($appIconPath) {
                $appIcon = GeneralUtility::locationHeaderUrl($appIconPath);
            } else {
                throw new \ValueError('App icon path cannot be resolved');
            }
        } else {
            $appIcon = null;
        }

        return new PublicKeyCredentialRpEntity(
            $name,
            $appID,
            $appIcon
        );
    }

    private function createUser(FrontendUserAuthentication|BackendUserAuthentication $user): PublicKeyCredentialUserEntity
    {
        $name = (string)$user->user[$user->username_column];
        $id = (int)$user->user[$user->userid_column];
        $displayName = trim((string)($user->user['realName'] ?? $user->user['name'] ?? '')) ?: $name;

        return new PublicKeyCredentialUserEntity(
            $name,
            dechex($id),
            $displayName
        );
    }

    private function createServer(CredentialsRepository $repository): Server
    {
        $rp = $this->createRelayingParty();

        return (new Server($rp, $repository))->setLogger($this->logger);
    }
}
