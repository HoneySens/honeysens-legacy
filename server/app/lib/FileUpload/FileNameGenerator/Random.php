<?php
/**
 * Created by PhpStorm.
 * User: decola
 * Date: 11.07.14
 * Time: 14:23
 */

namespace FileUpload\FileNameGenerator;

use FileUpload\Util;

class Random implements FileNameGenerator {

    /**
     * Maximum length of the filename
     * @var int
     */
    private $name_length = 32;

    /**
     * Pathresolver
     * @var PathResolver
     */
    private $pathresolver;

    /**
     * Filesystem
     * @var FileSystem
     */
    private $filesystem;

    function __construct($name_length = 32) {
        $this->name_length = $name_length;
    }

    /**
     * Get file_name
     * @param  string       $source_name
     * @param  string       $type
     * @param  string       $tmp_name
     * @param  integer      $index
     * @param  string       $content_range
     * @param  Pathresolver $pathresolver
     * @param  Filesystem   $filesystem
     * @return string
     */
    public function getFileName($source_name, $type, $tmp_name, $index, $content_range, $pathresolver, $filesystem)
    {
        $this->pathresolver = $pathresolver;
        $this->filesystem = $filesystem;
        $extension = substr($source_name, strrpos($source_name, '.')+1);
        return($this->getUniqueFilename($source_name, $type, $index, $content_range, $extension));
    }

    protected function generateRandom () {
        return(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $this->name_length));
    }

    /**
     * Get unique but consistent name
     * @param  string  $name
     * @param  string  $type
     * @param  integer $index
     * @param  array   $content_range
     * @param  string  $extension
     * @return string
     */
    protected function getUniqueFilename($name, $type, $index, $content_range, $extension) {
        $name = $this->generateRandom().".".$extension;
        while($this->filesystem->isDir($this->pathresolver->getUploadPath($name))) {
            $name = $this->generateRandom().".".$extension;
        }

        $uploaded_bytes = Util::fixIntegerOverflow(intval($content_range[1]));

        while($this->filesystem->isFile($this->pathresolver->getUploadPath($name))) {
            if($uploaded_bytes == $this->getFilesize($this->pathresolver->getUploadPath($name))) {
                break;
            }

            $name = $this->generateRandom().".".$extension;
        }

        return $name;
    }
}