<?php

/**
 * User: arthurt
 * Date: 10/27/18
 * Time: 4:26 PM
 */

namespace App\EventListener;

use App\Controller\Exception\Exception;
use App\Services\ValidateService;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Class RequestListener
 * @package App\EventListener
 */
class RequestListener
{
    /** @var ValidateService */
    protected $validationService;

    /**
     * RequestListener constructor.
     * @param ValidateService $validationService
     */
    public function __construct(ValidateService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * @param RequestEvent $event
     * @throws Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // check if not is master request
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        //check if request content type is json
        if ($request->getContentType() &&
            ($request->getContentType() === 'application/json' || $request->getContentType() === 'json')) {

            $content = $request->getContent();

            if ($content) {
                $request->request->add(json_decode($content, true));
            }

            // get request data and check validation
            $requestData = $request->request->all();

            // check if request is not validated
            if (!\array_key_exists('validated', $requestData)) {
                $this->validationService->checkRequiredParams($requestData);
            }
        }
    }
}