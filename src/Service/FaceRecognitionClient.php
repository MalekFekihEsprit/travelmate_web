<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceRecognitionClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $faceApiBaseUrl
    ) {
    }

    public function extractEmbeddingFromUploadedFile(UploadedFile $file): array
    {
        $boundary = '';
        $body = $this->buildMultipartBody([$file], $boundary, 'file'); // ← field name = "file"
    
        $response = $this->httpClient->request('POST', rtrim($this->faceApiBaseUrl, '/').'/extract', [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
        ]);
        
        $data = $response->toArray(false);
        
        if (($data['success'] ?? false) !== true || !isset($data['embedding'])) {
            $detail = $data['detail'] ?? $data['message'] ?? 'Erreur inconnue lors de l’extraction du visage.';
            // Convert array to readable string
            $message = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : (string) $detail;
            throw new \RuntimeException($message);
        }
        
        return $data['embedding'];
    }

    public function enrollFromUploadedFiles(array $files): array
    {
        $validFiles = array_filter($files, fn($f) => $f instanceof UploadedFile);
        if (count($validFiles) < 2) {
            throw new \RuntimeException('Au moins 2 fichiers valides sont requis');
        }
        
        $boundary = '';
        $body = $this->buildMultipartBody($validFiles, $boundary);
        
        $response = $this->httpClient->request('POST', rtrim($this->faceApiBaseUrl, '/').'/enroll', [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
        ]);
        
        $data = $response->toArray(false);
        
        if (($data['success'] ?? false) !== true || !isset($data['embedding'])) {
            $detail = $data['detail'] ?? $data['message'] ?? 'Erreur inconnue lors de l’enrôlement du visage.';
            $message = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : (string) $detail;
            throw new \RuntimeException($message);
        }
        
        return $data['embedding'];
    }

    public function compareEmbeddings(array $embedding1, array $embedding2, float $threshold = 0.68): array
    {
        $response = $this->httpClient->request('POST', rtrim($this->faceApiBaseUrl, '/').'/compare', [
            'json' => [
                'embedding1' => $embedding1,
                'embedding2' => $embedding2,
                'threshold' => $threshold,
            ],
        ]);

        $data = $response->toArray(false);

        if (($data['success'] ?? false) !== true && !isset($data['similarity'])) {
            $message = $data['detail'] ?? 'Erreur inconnue lors de la comparaison faciale.';
            throw new \RuntimeException($message);
        }

        return [
            'similarity' => (float) ($data['similarity'] ?? 0),
            'is_match' => (bool) ($data['is_match'] ?? false),
            'threshold' => (float) ($data['threshold'] ?? $threshold),
        ];
    }

    public function decodeStoredEmbedding(?string $json): ?array
    {
        if (!$json) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function encodeEmbedding(array $embedding): string
    {
        return json_encode($embedding, JSON_THROW_ON_ERROR);
    }

    private function buildMultipartBody(array $files, string &$boundary, string $fieldName = 'files'): string
    {
        $boundary = uniqid('boundary_', true);
        $body = '';
        
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            
            $body .= "--$boundary\r\n";
            $body .= "Content-Disposition: form-data; name=\"$fieldName\"; filename=\"" . $file->getClientOriginalName() . "\"\r\n";
            $body .= "Content-Type: " . ($file->getMimeType() ?: 'image/jpeg') . "\r\n\r\n";
            $body .= file_get_contents($file->getPathname()) . "\r\n";
        }
        
        $body .= "--$boundary--\r\n";
        return $body;
    }
}