<?php
declare(strict_types=1);

namespace Silver\Init;

final class Image
{
    public static function pull(): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, URL . '/public/images.zip');
        $fp = fopen('System/init/images/se_images.zip', 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        fclose($fp);
    }

    public static function unzip(): void
    {
        $zip = new \ZipArchive();
        $zipPath = getcwd() . '/System/init/images/se_images.zip';

        if ($zip->open($zipPath)) {
            $path = str_replace('\\', '/', getcwd() . '/System/');
            $zip->extractTo($path);
            $zip->close();
        }
    }

    public static function archive(): void
    {
        $rootPath = realpath('System');
        if ($rootPath === false) {
            return;
        }

        $date = date('Y_m_d_H_i_s');
        $zip = new \ZipArchive();
        $zip->open('System/init/backup/' . $date . '-image.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }
}
