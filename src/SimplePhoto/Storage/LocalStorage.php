<?php

/*
 * This file is part of the SimplePhoto package.
 *
 * (c) Laju Morrison <morrelinko@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SimplePhoto\Storage;

use SimplePhoto\Toolbox\BaseUrlInterface;
use SimplePhoto\Toolbox\HttpBaseUrl;
use SimplePhoto\Utils\FileUtils;
use SimplePhoto\Utils\TextUtils;

/**
 * @author Laju Morrison <morrelinko@gmail.com>
 */
class LocalStorage implements StorageInterface
{
    /**
     * @var string
     */
    protected $projectRoot;

    /**
     * @var null|string
     */
    protected $savePath;

    /**
     * @var \SimplePhoto\Toolbox\BaseUrlInterface
     */
    protected $baseUrlImpl;

    /**
     * Constructor
     *
     * @param string $projectRoot Root of your project
     * @param null|string $savePath
     * @param \SimplePhoto\Toolbox\BaseUrlInterface $baseUrlImpl
     */
    public function __construct($projectRoot, $savePath, BaseUrlInterface $baseUrlImpl = null)
    {
        $this->projectRoot = FileUtils::normalizePath($projectRoot);
        $this->savePath = $savePath;
        $this->baseUrlImpl = $baseUrlImpl;
    }

    /**
     * {@inheritDocs}
     */
    public function upload($file, $destination, array $options = array())
    {
        if (!is_file($file)) {
            throw new \RuntimeException(
                'Unable to upload; File [{$file}] does not exists.'
            );
        }

        $fileName = basename($file);
        if ($destination) {
            if (TextUtils::endsWith($destination, '/')) {
                $destination = $destination . $fileName;
            }
        } else {
            $destination = $fileName;
        }

        $savePath = $this->normalizePath($destination, true);
        $this->verifyPathExists(dirname($this->normalizePath($savePath, true)), true);

        if (copy($file, $savePath)) {
            return $this->normalizePath($destination, false, false);
        }

        return false;
    }

    /**
     * {@inheritDocs}
     */
    public function deletePhoto($file)
    {
        if (!$this->exists($file)) {
            // If the file does not exists,
            // it is considered deleted
            return true;
        }

        // Delete from file system
        if (unlink($this->normalizePath($file, true, true))) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDocs}
     */
    public function getPhotoPath($file)
    {
        return $this->normalizePath($file, true, true);
    }

    /**
     * {@inheritDocs}
     */
    public function getPhotoUrl($file)
    {
        if ($this->baseUrlImpl == null) {
            $this->baseUrlImpl = new HttpBaseUrl();
        }

        $basePath = $this->projectRoot . '/' . $this->savePath;
        $filePath = ltrim(preg_replace('!^' . $this->projectRoot . '/?!', '', $file), '/');
        $path = FileUtils::normalizePath($basePath . '/' . $filePath);

        return rtrim(str_replace($this->projectRoot, $this->baseUrlImpl->getBaseUrl(), $path), '/');
    }

    /**
     * {@inheritDocs}
     */
    public function getPhotoResource($file)
    {
        $tmpName = tempnam(sys_get_temp_dir(), 'temp');
        copy($this->normalizePath($file, true), $tmpName);

        return $tmpName;
    }

    /**
     * {@inheritDocs}
     */
    public function exists($file)
    {
        $file = $this->normalizePath($file, true, true);

        return file_exists($file) && is_file($file);
    }

    /**
     * @return mixed
     */
    public function getSavePath()
    {
        return $this->savePath;
    }

    /**
     * @param $savePath
     */
    public function setSavePath($savePath)
    {
        $this->savePath = $savePath;
    }

    /**
     * Gets the full path for saving photo
     *
     * @return string
     */
    public function getPath()
    {
        return $this->normalizePath(null, true, true);
    }

    /**
     * @param $directory
     *
     * @return bool
     */
    public function directoryExists($directory)
    {
        return is_dir($this->normalizePath($directory));
    }

    /**
     * @param $directory
     * @param bool $recursive
     * @param int $mode
     *
     * @return bool
     */
    public function createDirectory($directory, $recursive = true, $mode = 0777)
    {
        if ($this->directoryExists($directory)) {
            return true;
        }

        if (mkdir($this->normalizePath($directory), $mode, $recursive)) {
            return true;
        }

        return false;
    }

    /**
     * @param $path
     * @param bool $createIfNotExists
     *
     * @return string
     * @throws \RuntimeException
     */
    public function verifyPathExists($path, $createIfNotExists = false)
    {
        if (!is_dir($path) && !$createIfNotExists) {
            throw new \RuntimeException(sprintf(
                'Directory: %s not found',
                $path
            ));
        }

        if ($createIfNotExists) {
            $this->createDirectory($path);
        }

        return $path;
    }

    /**
     * @param $path
     * @param bool $withRoot Set to true to prepend project root to the normalized path
     * @param $withBasePath
     *
     * @return string
     */
    public function normalizePath($path, $withRoot = false, $withBasePath = true)
    {
        $dir = null;
        if (!FileUtils::isAbsolute($path)) {
            $dir = ($withRoot ? $this->projectRoot . '/' : null) .
                ($withBasePath ? $this->savePath . '/' : null);
        }

        return FileUtils::normalizePath($dir . $path);
    }
}
