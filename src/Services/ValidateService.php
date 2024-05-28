<?php

namespace App\Services;

use App\Controller\Exception\Exception;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class ValidateService
 * @package App\Services
 */
class ValidateService
{
    /** @var ValidatorInterface $validator */
    protected $validator;

    /**
     * ValidateService constructor.
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * This function is used to check model validation
     *
     * @param $model
     * @param array $group
     * @throws Exception
     */
    public function checkValidation($model, $group = []): void
    {
        $errors = $this->validator->validate($model, null, $group);
        $returnErrors = [];

        // get errors
        if ($errors->count() > 0) {

            foreach ($errors as $error) {
                $returnErrors[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new Exception(
                'Validation Error',
                JsonResponse::HTTP_BAD_REQUEST,
                ['errors' => $returnErrors]
            );
        }
    }

    /**
     * This function is used to check request required params
     *
     * @param $params
     * @throws Exception
     */
    public function checkRequiredParams($params): void
    {
        foreach ($params as $key => $param) {

            if ($key && $param !== 0 && !$param && !\is_array($param) && !\is_bool($param)) {
                throw new Exception(
                    'Invalid Request Data',
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }
    }

    /**
     * This function is used to check form errors
     *
     * @param $errors
     * @throws Exception
     */
    public function checkFormErrors($errors): void
    {
        $errorCollection = [];

        /** @var FormError $error */
        foreach ($errors as $error) {

            // generate error body with path
            $cause = $error->getCause();

            if (!$cause) {
                $errorCollection[] = $error->getMessage();
            } else {
                $property = $cause->getPropertyPath();
                $property = explode('.', $property);
                $property = end($property) === 'data' ? reset($property) : end($property);

                // get error for child fields
                if (strpos($property, '[') !== false) {
                    preg_match('#\[(.*?)]#', $property, $match);
                    $property = $match[1];
                }

                // add property in error array key
                $errorCollection[$property] = $cause->getMessage();
            }
        }

        throw new Exception(
            'Validation Error',
            JsonResponse::HTTP_BAD_REQUEST,
            ['errors' => $errorCollection]);
    }

    /**
     * This function is used to group array by key
     *
     * @param $array
     * @param $arrayKey
     * @return array
     */
    public function groupArrayByKey($array, $arrayKey): array
    {
        $return = [];

        foreach ($array as $val) {

            $newKey = $val[$arrayKey];
            unset($val[$arrayKey]);

            $return[$newKey][] = $val;
        }

        return $return;
    }
}