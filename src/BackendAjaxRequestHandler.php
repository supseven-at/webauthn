<?php

declare(strict_types=1);

namespace Supseven\Webauthn;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class BackendAjaxRequestHandler
{
    public function __construct(private readonly CredentialsService $credentialsService)
    {
    }

    public function registerOptions(Request $request): Response
    {
        $opts = $this->credentialsService->createCredentialCreationOptions($GLOBALS['BE_USER']);

        return new JsonResponse($opts->jsonSerialize());
    }

    public function registerSave(Request $request): Response
    {
        $success = $this->credentialsService->saveCredentails($request, $GLOBALS['BE_USER'], true);

        return new JsonResponse(['success' => $success], $success ? 202 : 406);
    }

    public function authOptions(Request $request): Response
    {
        $options = $this->credentialsService->createAuthOptions($GLOBALS['BE_USER']);

        return new JsonResponse($options->jsonSerialize());
    }

    public function authVerify(Request $request): Response
    {
        $id = $this->credentialsService->verifyAssertation($request, $GLOBALS['BE_USER']);

        return new JsonResponse(['success' => !is_null($id), 'id' => $id], $id ? 202 : 406);
    }
}
