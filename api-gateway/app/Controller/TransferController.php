<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TransferService;
use Throwable;
use App\Request\TransferRequest;
use App\Exception\Transfer\BusinessException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class TransferController extends AbstractController
{
    public function __construct(
        private readonly TransferService $transferService
    ) {
    }

    public function transfer(TransferRequest $request): PsrResponseInterface
    {
        try {
            $result = $this->transferService->transfer($request->validated());

            return $this->response->json([
                'success' => true,
                'data' => $result,
            ])->withStatus(202);
        } catch (BusinessException $e) {
            return $this->handleBusinessException($e);
        } catch (Throwable $e) {
            return $this->handleUnexpectedError($e);
        }
    }

    private function handleBusinessException(BusinessException $error): PsrResponseInterface
    {
        return $this->response->json([
            'error' => [
                'code' => $error->errorType,
                'message' => $error->getMessage(),
                'details' => $error->getDetails() ?? null,
            ],
        ])->withStatus($error->getCode());
    }

    private function handleUnexpectedError(Throwable $messageError): PsrResponseInterface
    {
        var_dump($messageError->getMessage());

        return $this->response->json([
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => $messageError,
            ],
        ])->withStatus(500);
    }
}