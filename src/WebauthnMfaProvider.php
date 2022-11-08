<?php

declare(strict_types=1);

namespace Supseven\Webauthn;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
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
        $sentId = ($request->getQueryParams()['credentialId'] ?? $request->getParsedBody()['credentialId'] ?? '');
        $storedId = $GLOBALS['BE_USER']->getSessionData('tx_webauthn_verified');
        $GLOBALS['BE_USER']->setAndSaveSessionData('tx_webauthn_verified', null);

        return $storedId && $sentId && $storedId === $sentId;
    }

    public function handleRequest(Request $request, MfaProviderPropertyManager $propertyManager, string $type): Response
    {
        $template = GeneralUtility::makeInstance(StandaloneView::class);
        $template->setTemplateRootPaths(['EXT:webauthn/Resources/Private/Templates/']);
        $template->setPartialRootPaths(['EXT:webauthn/Resources/Private/Partials/']);
        $template->setLayoutRootPaths(['EXT:webauthn/Resources/Private/Layouts/']);
        $template->setTemplate(ucfirst($type));

        switch ($type) {
            case 'edit':
                $credentials = [];

                foreach ($propertyManager->getProperty('credentials', []) as $key => $credential) {
                    $credentials[] = [
                        'id'   => $key,
                        'name' => $credential['name'] ?? '',
                    ];
                }

                $template->assign('credentials', $credentials);
                $template->assign('allowDelete', count($credentials) > 1);
                $template->assign('nextDevice', count($credentials) + 1);
                break;
        }

        $template->assign('type', $type);
        $template->assign('request', $request);

        return new HtmlResponse($template->render());
    }

    public function activate(Request $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if ($propertyManager->hasProviderEntry()) {
            $propertyManager->updateProperties(['active' => true]);
        } else {
            $propertyManager->createProviderEntry(['active' => true]);
        }

        return true;
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
                break;

                // Update names
            default:
                $credentials = $propertyManager->getProperty('credentials', []);

                foreach ($params['name'] as $key => $name) {
                    if (isset($credentials[$key]) && is_array($credentials[$key]) && trim($name) !== '') {
                        $credentials[$key]['name'] = $name;
                    }
                }

                $propertyManager->updateProperties(['credentials' => $credentials]);
        }

        return true;
    }
}
