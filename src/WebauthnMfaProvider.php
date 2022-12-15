<?php

declare(strict_types=1);

namespace Supseven\Webauthn;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaViewType;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class WebauthnMfaProvider implements MfaProviderInterface
{
    public function __construct(private readonly CredentialsService $credentialsService)
    {
    }

    public function canProcess(Request $request): bool
    {
        return true;
    }

    public function isActive(MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$propertyManager->getProperty('active')) {
            return false;
        }

        $credentials = $propertyManager->getProperty('credentials');

        return is_array($credentials) && count($credentials) > 0;
    }

    public function isLocked(MfaProviderPropertyManager $propertyManager): bool
    {
        return false;
    }

    public function verify(Request $request, MfaProviderPropertyManager $propertyManager): bool
    {
        return $this->credentialsService->verifyAuth($request, $GLOBALS['BE_USER'], $propertyManager);
    }

    public function handleRequest(Request $request, MfaProviderPropertyManager $propertyManager, string $type): Response
    {
        $template = GeneralUtility::makeInstance(StandaloneView::class);
        $template->setTemplateRootPaths(['EXT:webauthn/Resources/Private/Templates/']);
        $template->setPartialRootPaths(['EXT:webauthn/Resources/Private/Partials/']);
        $template->setLayoutRootPaths(['EXT:webauthn/Resources/Private/Layouts/']);
        $template->setTemplate(ucfirst($type));

        switch ($type) {
            case MfaViewType::SETUP:
                $options = $this->credentialsService->createCredentialCreationOptions($GLOBALS['BE_USER'], $propertyManager);
                $template->assign('credentailCreationOptions', $options->jsonSerialize());
                $template->assign('deviceName', $request->getParsedBody()['name'] ?? null);
                break;

            case MfaViewType::EDIT:
                $credentials = [];
                $options = $this->credentialsService->createCredentialCreationOptions($GLOBALS['BE_USER'], $propertyManager);

                foreach ($propertyManager->getProperty('credentials', []) as $key => $credential) {
                    $credentials[] = [
                        'id'   => $key,
                        'name' => $credential['name'] ?? '',
                    ];
                }

                $template->assign('credentailCreationOptions', $options->jsonSerialize());
                $template->assign('credentials', $credentials);
                $template->assign('allowDelete', count($credentials) > 1);
                $template->assign('nextDevice', count($credentials) + 1);
                $template->assign('nextDeviceName', $request->getParsedBody()['name'] ?? null);
                break;

            case MfaViewType::AUTH:
                $options = $this->credentialsService->createCredentialRequestOptions($GLOBALS['BE_USER'], $propertyManager);
                $template->assign('credentailAuthOptions', $options->jsonSerialize());
                break;
        }

        $template->assign('type', $type);
        $template->assign('request', $request);

        return new HtmlResponse($template->render());
    }

    public function activate(Request $request, MfaProviderPropertyManager $propertyManager): bool
    {
        // Reset properties
        if ($propertyManager->hasProviderEntry()) {
            $propertyManager->deleteProviderEntry();
        }

        $propertyManager->createProviderEntry(['active' => true, 'credentials' => []]);
        $success = $this->credentialsService->saveCredentails($request, $GLOBALS['BE_USER'], $propertyManager);

        if (!$success) {
            $propertyManager->deleteProviderEntry();
        }

        return $success;
    }

    public function deactivate(Request $request, MfaProviderPropertyManager $propertyManager): bool
    {
        $propertyManager->deleteProviderEntry();

        return true;
    }

    public function unlock(Request $request, MfaProviderPropertyManager $propertyManager): bool
    {
        return true;
    }

    public function update(Request $request, MfaProviderPropertyManager $propertyManager): bool
    {
        $params = array_replace($request->getQueryParams() ?: [], $request->getParsedBody() ?: []);

        switch ($params['updateAction'] ?? '') {
            // Remove a credential
            case 'remove':
                $credentials = $propertyManager->getProperty('credentials', []);

                // Do not proceed if it is the last credential
                if (count($credentials) < 2) {
                    return false;
                }

                $key = $params['credential'];

                if (!isset($credentials[$key])) {
                    return false;
                }

                unset($credentials[$key]);
                $propertyManager->updateProperties(['credentials' => $credentials]);
                break;

                // Add a new credential
            case 'add':
                return $this->credentialsService->saveCredentails($request, $GLOBALS['BE_USER'], $propertyManager);

                // Update names
            default:
                $credentials = $propertyManager->getProperty('credentials', []);

                foreach ($params['newNames'] ?? [] as $key => $name) {
                    if (isset($credentials[$key]) && is_array($credentials[$key]) && trim($name) !== '') {
                        $credentials[$key]['name'] = $name;
                    }
                }

                $propertyManager->updateProperties(['credentials' => $credentials]);
        }

        return true;
    }
}
