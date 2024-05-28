<?php

namespace App\Entity\Traits;

use App\Components\Helper\JsonHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trait UploadPhotoTrait
 * @package App\Entity\Traits
 */
trait UploadPhotoTrait
{
    /**
     * @var string
     * @ORM\Column(name="file_name", type="string", length=300, nullable=true)
     */
    protected $fileName = '';

    /**
     * @var UploadedFile
     * @Assert\Image(
     *     maxSize="2M",
     *     mimeTypes = {
     *              "image/jpeg",
     *              "image/jpg",
     *              "image/png"
     *          },
     *    mimeTypesMessage = "Wrong format of file. Add only image file."
     * )
     */
    public $file;

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    /** ======================================================== **
     * Override methods
     ** ======================================================== **/

    /**
     * @return null|string
     */
    public function getAbsolutePath():?string
    {
        return null === $this->fileName ? null : self::getUploadRootDir().'/'.$this->fileName;
    }

    /**
     * @return null|string
     */
    public function getWebPath():?string
    {
        return null === $this->fileName ? null : self::getUploadDir().$this->fileName;
    }

    /**
     * @return string
     */
    public static function getUploadRootDir():string
    {
        return __DIR__.'/../../../public/'.self::getUploadDir();
    }

    /**
     * @return string
     */
    public static function getUploadDir():string
    {
        return 'upload/';
    }

    /**
     * Upload image
     */
    public function upload():void
    {
        if (null === $this->file) {
            return;
        }

        $fileNameHash = 'question_'.$this->getId().'_'.time();
        $fileName = $fileNameHash.'.'.$this->file->guessExtension();
        $this->file->move(self::getUploadRootDir().'/', $fileName);
        $this->fileName = $fileName;
        $this->file = null;
    }

    /**
     * Upload base64 string as file
     *
     * @param $base64String
     * @param $uploadPath
     * @param $fullName
     * @return string
     */
    public function uploadBase64($base64String, $uploadPath, $fullName):string
    {
        $base64String = explode(',', $base64String);
        $base64String = $base64String[1];
        $filePath = $uploadPath.$fullName;

        // convert base64 to image
        JsonHelper::base64_to_image($base64String, $filePath);
        $this->fileName = $fullName;

        return $filePath;
    }
}