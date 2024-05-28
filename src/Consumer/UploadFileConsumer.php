<?php

namespace App\Consumer;

use App\Services\AwsService;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class UploadFileConsumer
 * @package App\Consumer
 */
class UploadFileConsumer implements ConsumerInterface
{
    /** @var AwsService $awsService */
    protected $awsService;

    /**
     * UploadFileConsumer constructor.
     * @param AwsService $awsService
     */
    public function __construct(AwsService $awsService)
    {
        $this->awsService = $awsService;
    }

    /**
     * @return void
     * @throws \Exception
     * @var AMQPMessage $msg
     */
    public function execute(AMQPMessage $msg):void
    {
        $message = $msg->getBody().PHP_EOL;
        $data = json_decode($message, true);

        // upload base64 as file on AWS S3
        $this->awsService->uploadBase64File($data['base64'], $data['awsKey']);
    }
}