<?php

declare(strict_types=1);

namespace Supseven\Webauthn;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class CredentialsRepository implements PublicKeyCredentialSourceRepository
{
    public function __construct(
        private readonly MfaProviderPropertyManager $propertyManager
    ) {
    }

    public static function createForUser(FrontendUserAuthentication|BackendUserAuthentication $user): self
    {
        $propertyManager = GeneralUtility::makeInstance(
            MfaProviderPropertyManager::class,
            $user,
            'webauthn'
        );

        return new self($propertyManager);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $result = null;

        foreach ($this->loadCredentials() as $credential) {
            if ($credential->getPublicKeyCredentialId() === $publicKeyCredentialId) {
                $result = $credential;
            }
        }

        return $result;
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return $this->loadCredentials();
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $credentials = $this->propertyManager->getProperty('credentials', []);
        $credentials[bin2hex($publicKeyCredentialSource->getPublicKeyCredentialId())] = [
            'data' => $publicKeyCredentialSource->jsonSerialize(),
        ];
        $this->saveCredentials($credentials, false);
    }

    public function saveNamedCredentialSource(string $name, PublicKeyCredentialSource $publicKeyCredentialSource, bool $createIfNone = false): void
    {
        $credentials = $this->propertyManager->getProperty('credentials', []);
        $credentials[bin2hex($publicKeyCredentialSource->getPublicKeyCredentialId())] = [
            'name' => $name,
            'data' => $publicKeyCredentialSource->jsonSerialize(),
        ];

        $this->saveCredentials($credentials, $createIfNone);
    }

    /**
     * @param mixed $credentials
     */
    protected function saveCredentials(mixed $credentials, bool $createIfNone): void
    {
        try {
            $this->propertyManager->updateProperties(['credentials' => $credentials]);
        } catch (\Throwable) {
            if ($createIfNone) {
                $this->propertyManager->createProviderEntry(['credentials' => $credentials, 'active' => true]);
            }
        }
    }

    /**
     * @return array<PublicKeyCredentialSource>
     */
    private function loadCredentials(): array
    {
        $result = [];

        foreach ($this->propertyManager->getProperty('credentials', []) as $credentialItem) {
            $result[] = PublicKeyCredentialSource::createFromArray($credentialItem['data']);
        }

        return $result;
    }
}
