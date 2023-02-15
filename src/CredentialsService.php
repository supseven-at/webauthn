<?php

declare(strict_types=1);

namespace Supseven\Webauthn;

use Cose\Algorithm\Manager as AlgorithmManager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES256K;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\Ed256;
use Cose\Algorithm\Signature\EdDSA\Ed512;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
use Cose\Algorithms;
use Psr\Http\Message\ServerRequestInterface as Reqest;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AndroidSafetyNetAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TokenBinding\IgnoreTokenBindingHandler;

/**
 * Service offering a single interface to all needed Webauthn objects and functions
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class CredentialsService
{
    /**
     * Internal instance of algorithm manager
     *
     * @var AlgorithmManager|null
     */
    private ?AlgorithmManager $algorithmManager = null;

    /**
     * Internal instance of the attestation manager
     *
     * @var AttestationStatementSupportManager|null
     */
    private ?AttestationStatementSupportManager $attestationStatementSupportManager = null;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create options for registering a new device
     *
     * @param FrontendUserAuthentication|BackendUserAuthentication $login
     * @param MfaProviderPropertyManager $propertyManager
     * @return PublicKeyCredentialCreationOptions
     */
    public function createCredentialCreationOptions(FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): PublicKeyCredentialCreationOptions
    {
        $publicKeyCredentialSourceRepository = new CredentialsRepository($propertyManager);
        $user = $this->createUser($login);
        $challenge = random_bytes(16);
        $publicKeyCredentialParametersList = [
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256K),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES384),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES512),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS256),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS384),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS512),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_PS256),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_PS384),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_PS512),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ED256),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ED512),
        ];

        $authenticatorSelectionCriteria = AuthenticatorSelectionCriteria::create()
            // Do not let the browser ask for a pin before the actual device is contacted
            ->setUserVerification(AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED)
            // Allow all types of key attachments
            ->setAuthenticatorAttachment(AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE);

        $options = PublicKeyCredentialCreationOptions::create(
            $this->createRelayingParty(),
            $user,
            $challenge,
            $publicKeyCredentialParametersList,
        )
            ->setAuthenticatorSelection($authenticatorSelectionCriteria)
            ->setAttestation(PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE);

        foreach ($publicKeyCredentialSourceRepository->findAllForUserEntity($user) as $credentialSource) {
            $options->excludeCredential($credentialSource->getPublicKeyCredentialDescriptor());
        }

        $login->setAndSaveSessionData('tx_webauthn_register', $options->jsonSerialize());

        return $options;
    }

    /**
     * Validate and save credentials of a new device
     *
     * @param Reqest $request
     * @param FrontendUserAuthentication|BackendUserAuthentication $login
     * @param MfaProviderPropertyManager $propertyManager
     * @return bool
     */
    public function saveCredentails(Reqest $request, FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): bool
    {
        try {
            $credentials = $request->getParsedBody()['credential'] ?? null;
            $name = $request->getParsedBody()['name'] ?? null;

            if (!$name || !$credentials) {
                return false;
            }

            $options = PublicKeyCredentialCreationOptions::createFromArray($login->getSessionData('tx_webauthn_register'));

            $publicKeyCredentialSourceRepository = new CredentialsRepository($propertyManager);
            $attestationStatementSupportManager = $this->getAttestationStatementSupportManager();
            $publicKeyCredentialLoader = $this->getPublicKeyCredentailLoader();
            $publicKeyCredentialLoader->setLogger($this->logger);

            $authenticatorAttestationResponseValidator = AuthenticatorAttestationResponseValidator::create(
                $attestationStatementSupportManager,
                $publicKeyCredentialSourceRepository,
                null,
                ExtensionOutputCheckerHandler::create()
            );

            $publicKeyCredential = $publicKeyCredentialLoader->load($credentials);
            $authenticatorAttestationResponse = $publicKeyCredential->getResponse();

            if (!$authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) {
                throw new \UnexpectedValueException('Authenticator attestestation response not correctly loaded');
            }

            $publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
                $authenticatorAttestationResponse,
                $options,
                $request->getUri()->getHost()
            );

            $login->setAndSaveSessionData('tx_webauthn_register', null);

            $publicKeyCredentialSourceRepository->saveNamedCredentialSource(
                $name,
                $publicKeyCredentialSource
            );

            return true;
        } catch(\Throwable $ex) {
            $this->logger->error('Cannot save credentials: ' . $ex->getMessage(), $ex->getTrace());
        }

        return false;
    }

    /**
     * Create options for authenticating a known device
     *
     * @param FrontendUserAuthentication|BackendUserAuthentication $login
     * @param MfaProviderPropertyManager $propertyManager
     * @return PublicKeyCredentialRequestOptions
     * @throws \Exception
     */
    public function createCredentialRequestOptions(FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): PublicKeyCredentialRequestOptions
    {
        $userEntity = $this->createUser($login);
        $publicKeyCredentialSourceRepository = new CredentialsRepository($propertyManager);
        $registeredAuthenticators = $publicKeyCredentialSourceRepository->findAllForUserEntity($userEntity);

        $allowedCredentials = array_map(
            static fn (PublicKeyCredentialSource $credential): PublicKeyCredentialDescriptor => $credential->getPublicKeyCredentialDescriptor(),
            $registeredAuthenticators
        );

        $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::create(random_bytes(32))
            ->allowCredentials(...$allowedCredentials);

        $login->setAndSaveSessionData('tx_webauthn_auth', $publicKeyCredentialRequestOptions->jsonSerialize());

        return $publicKeyCredentialRequestOptions;
    }

    /**
     * Verify an authentication request
     *
     * @param Reqest $request
     * @param FrontendUserAuthentication|BackendUserAuthentication $login
     * @param MfaProviderPropertyManager $propertyManager
     * @return bool
     */
    public function verifyAuth(Reqest $request, FrontendUserAuthentication|BackendUserAuthentication $login, MfaProviderPropertyManager $propertyManager): bool
    {
        try {
            $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::createFromArray($login->getSessionData('tx_webauthn_auth'));
            $login->setAndSaveSessionData('tx_webauthn_auth', null);

            $publicKeyCredentialSourceRepository = new CredentialsRepository($propertyManager);
            $authenticatorAssertionResponseValidator = AuthenticatorAssertionResponseValidator::create(
                $publicKeyCredentialSourceRepository,
                null,
                ExtensionOutputCheckerHandler::create(),
                $this->getAlgorithmManager()
            );
            $publicKeyCredentialLoader = $this->getPublicKeyCredentailLoader();
            $publicKeyCredential = $publicKeyCredentialLoader->load($request->getParsedBody()['credential'] ?? '');
            $authenticatorAssertionResponse = $publicKeyCredential->getResponse();

            if (!$authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse) {
                return false;
            }

            $authenticatorAssertionResponseValidator->check(
                $publicKeyCredential->getRawId(),
                $authenticatorAssertionResponse,
                $publicKeyCredentialRequestOptions,
                $request->getUri()->getHost(),
                $this->createUser($login)->getId(),
            );

            return true;
        } catch (\Throwable $ex) {
            $this->logger->warning('Cannot verify request: ' . $ex->getMessage(), [$ex]);
        }

        return false;
    }

    /**
     * @return AlgorithmManager
     */
    protected function getAlgorithmManager(): AlgorithmManager
    {
        $this->algorithmManager ??= AlgorithmManager::create()->add(
            ES256::create(),
            ES256K::create(),
            ES384::create(),
            ES512::create(),
            RS256::create(),
            RS384::create(),
            RS512::create(),
            PS256::create(),
            PS384::create(),
            PS512::create(),
            Ed256::create(),
            Ed512::create(),
        );

        return $this->algorithmManager;
    }

    /**
     * @return AttestationStatementSupportManager
     */
    protected function getAttestationStatementSupportManager(): AttestationStatementSupportManager
    {
        if (!$this->attestationStatementSupportManager) {
            $this->attestationStatementSupportManager = AttestationStatementSupportManager::create();
            $this->attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());
            $this->attestationStatementSupportManager->add(AndroidKeyAttestationStatementSupport::create());
            $this->attestationStatementSupportManager->add(AndroidSafetyNetAttestationStatementSupport::create());
            $this->attestationStatementSupportManager->add(AppleAttestationStatementSupport::create());
            $this->attestationStatementSupportManager->add(FidoU2FAttestationStatementSupport::create());
            $this->attestationStatementSupportManager->add(PackedAttestationStatementSupport::create($this->getAlgorithmManager()));
            $this->attestationStatementSupportManager->add(TPMAttestationStatementSupport::create());
        }

        return $this->attestationStatementSupportManager;
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
        } else {
            $appID = null;
        }

        $appIcon = (string)($extConf['icon'] ?? $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['loginLogo'] ?? '');

        if ($appIcon) {
            $appIconPath = PathUtility::getAbsoluteWebPath($appIcon);

            if ($appIconPath) {
                $mime = mime_content_type($appIconPath);
                $content = file_get_contents($appIconPath);
                $appIcon = 'data:' . $mime . ';base64,' . base64_encode($content);
            } else {
                throw new \ValueError('App icon path cannot be resolved');
            }
        } else {
            $appIcon = null;
        }

        return PublicKeyCredentialRpEntity::create(
            $name,
            $appID,
            $appIcon
        );
    }

    private function createUser(FrontendUserAuthentication|BackendUserAuthentication $user): PublicKeyCredentialUserEntity
    {
        $name = (string)$user->user[$user->username_column];
        $id = (int)$user->getSession()->getUserId();
        $displayName = trim((string)($user->user['realName'] ?? $user->user['name'] ?? '')) ?: $name;

        return PublicKeyCredentialUserEntity::create(
            $name,
            dechex($id),
            $displayName
        );
    }

    private function getPublicKeyCredentailLoader(): PublicKeyCredentialLoader
    {
        $attestationObjectLoader = AttestationObjectLoader::create($this->getAttestationStatementSupportManager());
        $attestationObjectLoader->setLogger($this->logger);

        $publicKeyCredentailLoader = PublicKeyCredentialLoader::create($attestationObjectLoader);
        $publicKeyCredentailLoader->setLogger($this->logger);

        return $publicKeyCredentailLoader;
    }
}
