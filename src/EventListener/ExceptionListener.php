<?php
/**
 * User: arthurt
 * Date: 10/27/18
 * Time: 4:23 PM
 */

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionListener
{
    /**
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        // get the exception object from the received event
        $exception = $event->getThrowable();
        $response = new JsonResponse();

        // generate error response data body
        $errorCode = method_exists($exception, 'getCode') ? $exception->getCode() : JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
        $responseData = ['status' => $errorCode, 'message' => $exception->getMessage()];

        // check if in exception exist custom error data
        $errorData = method_exists($exception, 'getData') ? $exception->getData() : null;

        if ($errorData) {
            $responseData['data'] = $errorData;
        }

        // Customize your response object to display the exception details
        $response->setContent(json_encode($responseData));

        // sends the modified response object to the event
        $event->setResponse($response);
    }
}