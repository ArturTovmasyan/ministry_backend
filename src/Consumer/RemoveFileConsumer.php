<?php

namespace App\Consumer;

use App\Services\AwsService;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class RemoveFileConsumer
 * @package App\Consumer
 */
class RemoveFileConsumer implements ConsumerInterface
{
    /** @var AwsService $awsService */
    protected $awsService;

    /**
     * RemoveFileConsumer constructor.
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
     * @return void
     */
    public function execute(AMQPMessage $msg):void
    {
        // remove file from AWS S3
        $message = $msg->getBody().PHP_EOL;
        $filePath = json_decode($message, true);
        $this->awsService->removeFileByPath($filePath);
    }
}