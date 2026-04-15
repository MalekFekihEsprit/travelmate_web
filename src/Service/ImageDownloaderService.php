<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\File;
use Psr\Log\LoggerInterface;

/**
 * Downloads a remote image to a temp file and wraps it in a Symfony File object
 * so that VichUploaderBundle can process it normally.
 */
class ImageDownloaderService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Download $url and return a File pointing to a local temp file.
     * Returns null if the download fails or the URL is empty.
     */
    public function download(string $url): ?File
    {
        if ($url === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; TravelMateBot/1.0)',
                    'Accept'     => 'image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer'    => 'https://www.booking.com/',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Image download failed, HTTP {code}: {url}', [
                    'code' => $response->getStatusCode(),
                    'url'  => $url,
                ]);
                return null;
            }

            $contentType = $response->getHeaders()['content-type'][0] ?? 'image/jpeg';
            $extension   = $this->extensionFromMime($contentType);

            // Write to a unique temp file
            $tmpPath = sys_get_temp_dir() . '/hebergement_img_' . uniqid('', true) . '.' . $extension;
            file_put_contents($tmpPath, $response->getContent());

            return new File($tmpPath);
        } catch (\Throwable $e) {
            $this->logger->error('ImageDownloader exception: {message}', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function extensionFromMime(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'png')  => 'png',
            str_contains($mime, 'gif')  => 'gif',
            default                     => 'jpg',
        };
    }
}
