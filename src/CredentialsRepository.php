<?php

declare(strict_types=1);

namespace Supseven\Webauthn;

use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
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
        $this->propertyManager->updateProperties(['credentials' => $credentials]);
    }

    public function saveNamedCredentialSource(string $name, PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $credentials = $this->propertyManager->getProperty('credentials', []);

        foreach ($credentials as $credential) {
            if ($credential['name'] === $name) {
                throw new \InvalidArgumentException(sprintf('Credential "%s" already exists', $name));
            }
        }

        $credentials[bin2hex($publicKeyCredentialSource->getPublicKeyCredentialId())] = [
            'name' => $name,
            'data' => $publicKeyCredentialSource->jsonSerialize(),
        ];

        $this->propertyManager->updateProperties(['credentials' => $credentials]);
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
