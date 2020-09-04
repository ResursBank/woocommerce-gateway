<?php

namespace TorneLIB\Utils;

use Exception;

/**
 * Class ImageHandler Attachment image auto resizing and handler.
 * @package TorneLIB\Utils
 * @since 6.1.7
 */
class ImageHandler
{
    /**
     * @var int
     * @since 6.1.7
     */
    private $attachmentImageWidth = 400;
    /**
     * @var int
     * @since 6.1.7
     */
    private $attachmentImageHeight = 400;
    /**
     * @var int
     * @since 6.1.7
     */
    private $attachmentImageCompressionJpeg = 80;
    /**
     * @var int
     * @since 6.1.7
     */
    private $attachmentImageCompressionPng = 7;

    /**
     * @param array $attachmentData
     * @return array
     * @throws Exception
     * @since 6.1.7
     */
    public function setAttachmentImage($attachmentData = [])
    {
        if (isset($attachmentData['tmp_name']) && file_exists($attachmentData['tmp_name'])) {
            $imageInformationResponse = @getimagesize($attachmentData['tmp_name']);
            if (is_array($imageInformationResponse)) {
                $attachmentData['image'] = $this->getImageInformation(
                    $imageInformationResponse,
                    $attachmentData['tmp_name']
                );
            }
        }

        return $attachmentData;
    }

    /**
     * @param $imageInformationResponse
     * @param string $fileName
     * @return array
     * @throws Exception
     * @since 6.1.7
     */
    private function getImageInformation($imageInformationResponse, $fileName = '')
    {
        $imageInformation = [];
        if (is_array($imageInformationResponse) && isset($imageInformationResponse[2])) {
            $imageInformation = [
                'w' => isset($imageInformationResponse[0]) ? $imageInformationResponse[0] : 0,
                'h' => isset($imageInformationResponse[1]) ? $imageInformationResponse[1] : 0,
                'type' => isset($imageInformationResponse[2]) ? $imageInformationResponse[2] : 0,
                'attributes' => isset($imageInformationResponse[3]) ? $imageInformationResponse[3] : null,
                'mime' => isset($imageInformationResponse['mime']) ? $imageInformationResponse['mime'] : null,
                'resized' => $this->getResizedImage(
                    $fileName,
                    $imageInformationResponse[1],
                    $imageInformationResponse[0],
                    $imageInformationResponse[2]
                ),
            ];
        }

        return $imageInformation;
    }

    /**
     * @param $imageFileName
     * @param $currentHeight
     * @param $currentWidth
     * @param $imageType
     * @return array
     * @throws Exception
     * @since 6.1.7
     */
    private function getResizedImage($imageFileName, $currentHeight, $currentWidth, $imageType)
    {
        $resizedImageInformation = [];
        $resizeHeight = $this->attachmentImageHeight;
        $resizeWidth = $this->attachmentImageWidth;

        if ($currentHeight >= $resizeHeight) {
            $resizeWidth = ($resizeHeight / $currentHeight) * $currentWidth;
        } elseif ($currentWidth >= $resizeWidth) {
            $resizeWidth = ($resizeWidth / $currentWidth) * $currentHeight;
            $resizeHeight = $resizeWidth;
        }

        if (($imageTypeExtension = $this->getAllowedImageType($imageType))) {
            $hashName = sprintf('/tmp/%s.%s', sha1($imageFileName), $imageTypeExtension);
            $worker = $this->getImage($imageFileName, $imageType);
            $destinationFileHandle = @imagecreatetruecolor($resizeWidth, $resizeHeight);
            @imagecopyresized(
                $destinationFileHandle,
                $worker,
                0,
                0,
                0,
                0,
                $resizeWidth,
                $resizeHeight,
                $currentWidth,
                $currentHeight
            );
            if ($this->setImage($destinationFileHandle, $hashName, $imageType)) {
                $resizedImageData = @getimagesize($hashName);
                $resizedImageInformation = [
                    'tmp_name' => $hashName,
                    'w' => isset($resizedImageData[0]) ? $resizedImageData[0] : 0,
                    'h' => isset($resizedImageData[1]) ? $resizedImageData[1] : 0,
                    'error' => 0,
                    'size' => filesize($hashName),
                ];
            } else {
                // Usually returned to an API.
                $resizedImageInformation = [
                    'error' => 400,
                    'errorstring' => 'Image resize failed',
                ];
            }
        }

        return $resizedImageInformation;
    }

    /**
     * @param $imageType
     * @return mixed|null
     * @since 6.1.7
     */
    private function getAllowedImageType($imageType)
    {
        $allowedTypes = [
            '1' => 'gif',
            '2' => 'jpg',
            '3' => 'png',
        ];
        if (isset($allowedTypes[$imageType])) {
            return $allowedTypes[$imageType];
        }

        return null;
    }

    /**
     * @param $fileName
     * @param $fileType
     * @return null|resource
     * @since 6.1.7
     */
    private function getImage($fileName, $fileType)
    {
        $returnWorkFile = null;
        if ($fileType == 1) {
            $returnWorkFile = @imagecreatefromgif($fileName);
        } elseif ($fileType == 2) {
            $returnWorkFile = @imagecreatefromjpeg($fileName);
        } elseif ($fileType == 3) {
            $returnWorkFile = @imagecreatefrompng($fileName);
        }

        return $returnWorkFile;
    }

    /**
     * @param $destinationFileHandle
     * @param $destinationFileName
     * @param $imageType
     * @return bool
     * @since 6.1.7
     */
    private function setImage($destinationFileHandle, $destinationFileName, $imageType)
    {
        if ($imageType == 1) {
            @imagegif($destinationFileHandle, $destinationFileName);
        } elseif ($imageType == 2) {
            @imagejpeg($destinationFileHandle, $destinationFileName, $this->attachmentImageCompressionJpeg);
        } elseif ($imageType == 3) {
            @imagepng($destinationFileHandle, $destinationFileName, $this->attachmentImageCompressionPng);
        }
        if (file_exists($destinationFileName) && filesize($destinationFileName)) {
            return true;
        }

        return false;
    }
}
