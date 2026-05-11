<?php
namespace App\Support;

use RuntimeException;

class HttpClient {
    /** @var callable|null */
    private $transport;
    private int $timeoutSeconds;

    public function __construct(?callable $transport = null, int $timeoutSeconds = 15) {
        $this->transport = $transport;
        $this->timeoutSeconds = max(1, $timeoutSeconds);
    }

    public function get(string $url, array $headers = []): array {
        return $this->request('GET', $url, $headers);
    }

    public function post(string $url, array $headers = [], ?string $body = null): array {
        return $this->request('POST', $url, $headers, $body);
    }

    public function delete(string $url, array $headers = []): array {
        return $this->request('DELETE', $url, $headers);
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): array {
        if ($this->transport !== null) {
            $response = ($this->transport)($method, $url, $headers, $body);
            if (!is_array($response) || !isset($response['status'], $response['body'])) {
                throw new RuntimeException('HTTP transport returned an invalid response payload');
            }

            return [
                'status' => (int)$response['status'],
                'headers' => (array)($response['headers'] ?? []),
                'body' => (string)$response['body'],
            ];
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Failed to initialize curl');
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $formattedHeaders,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($curl);
        if ($rawResponse === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $bodyContent = substr($rawResponse, $headerSize);

        return [
            'status' => $status,
            'headers' => $this->parseHeaders($rawHeaders),
            'body' => $bodyContent === false ? '' : $bodyContent,
        ];
    }

    private function parseHeaders(string $rawHeaders): array {
        $headers = [];
        $lines = preg_split('/\r\n|\r|\n/', $rawHeaders) ?: [];
        foreach ($lines as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name === '') {
                continue;
            }
            $headers[$name] = $value;
        }

        return $headers;
    }
}
