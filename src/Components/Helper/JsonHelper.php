<?php

namespace App\Components\Helper;

/**
 * Class JsonHelper
 * @package App\Components\Helper
 */
class JsonHelper
{
    /** Characters for generate token */
    private const RANDOM_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    public const LIMIT = 10;

    /**
     * @param $length
     * @return string
     */
    public static function generateCode($length): string
    {
        return substr(str_shuffle(self::RANDOM_CHARS), 0, $length);
    }

    /**
     * This function is used to hide some letters in string
     *
     * @param $string
     * @return string
     */
    public static function hideLetters($string): string
    {
        return preg_replace('/(^..|..$)|(.)/', '*', $string);
    }

    /**
     * This function is used to convert base64 string to image
     *
     * @param $base64_string
     * @param $output_file
     */
    public static function base64_to_image($base64_string, &$output_file):void
    {
        // open the output file for writing
        $ifp = fopen($output_file, 'wb');
        fwrite($ifp, base64_decode($base64_string));
        fclose($ifp);
    }
}