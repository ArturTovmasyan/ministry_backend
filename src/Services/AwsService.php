<?php

namespace App\Services;

use App\Controller\Exception\Exception;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\Result;

/**
 * Class AwsService
 * @package App\Services
 */
class AwsService
{
    protected $key;
    protected $secret;
    protected $version;
    protected $bucket;
    protected $region;

    /**
     * AwsService constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->key = getenv('AWS_KEY');
        $this->secret = getenv('AWS_SECRET');
        $this->version = getenv('AWS_VERSION');
        $this->bucket = getenv('AWS_BUCKET');
        $this->region = getenv('AWS_REGION');
    }

    /**
     * This function is used to upload file on amazon S3
     *
     * @param $file
     * @param $awsKey
     * @return mixed
     * @throws Exception
     */
    public function uploadBase64File($file, $awsKey)
    {
        //create amazon client
        $s3 = $this->createClient();

        $explodeData = explode(',', $file);
        $decodedData = base64_decode(end($explodeData));
        $explodeData = explode('.', $awsKey);
        $extension = end($explodeData);

        try {
            $result = $s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $awsKey,
                'Body' => $decodedData,
                'ContentType' => 'image/'.$extension,
                'ACL' => 'public-read'
            ]);

            // return the URL to the object.
            return $result['ObjectURL'];

        } catch (S3Exception $e) {
            throw new Exception($e->getAwsErrorMessage(), $e->getStatusCode(), []);
        }
    }

    /**
     * This function is used to get file in S3 bucket
     *
     * @param $path
     * @return Result
     */
    public function getFile($path): Result
    {
        //create amazon client
        $s3 = $this->createClient();

        // Get the object
        return $s3->getObject(array(
            'Bucket' => $this->bucket,
            'Key' => $path
        ));
    }

    /**
     * @param $path
     * @return Result
     */
    public function getObjects($path): ?Result
    {
        //create amazon client
        $s3 = $this->createClient();

        //get all files in amazon S3 bucket
        $objects = $s3->listObjects([
            'Bucket' => $this->bucket,
            'Prefix' => $path
        ]);

        //check if object is exist
        if ($objects) {
            return $objects['Contents'];
        }

        return null;
    }

    /**
     * This function is used to delete image from amazon S3 bucket
     *
     * @param $key
     * @throws \Exception
     */
    public function deleteSingleFile($key): void
    {
        //create amazon client
        $s3 = $this->createClient();

        //remove file in Amazon S3 bucket
        $s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'delay' => 200
        ]);
    }

    /**
     * This function is used to remove all files by passed data
     *
     * @param $data
     * @throws \Exception
     */
    public function removeFileByPath($data): void
    {
        //check if data exists
        if (\count($data) > 0) {

            foreach ($data as $file) {
                //remove photo on amazon S3
                $this->deleteSingleFile($file);
            }
        }
    }

    /**
     * This function is used to create amazon S3 client
     *
     * @return S3Client
     */
    public function createClient(): S3Client
    {
        // Instantiate the S3 client with your AWS credentials
        return new S3Client([
                'version' => $this->version,
                'region' => $this->region,
                'validation' => false,
                'scheme' => 'http',
                'credentials' => [
                    'key' => $this->key,
                    'secret' => $this->secret
                ],
                'use_accelerate_endpoint' => false
            ]
        );
    }
}