<?php

namespace Tamert\Monolog\Loki;

use RuntimeException;

class LokiClient
{
    /**
     * cURL bağlantı hatalarında yeniden denenebilecek hata kodları
     * @var int[]
     */
    private static $retriableErrorCodes = array(
        \CURLE_COULDNT_RESOLVE_HOST,
        \CURLE_COULDNT_CONNECT,
        \CURLE_HTTP_NOT_FOUND,
        \CURLE_READ_ERROR,
        \CURLE_OPERATION_TIMEOUTED,
        \CURLE_HTTP_POST_ERROR,
        \CURLE_SSL_CONNECT_ERROR,
    );

    const DEFAULT_CONNECTION_TIMEOUT_MILLISECONDS = 5000;
    const DEFAULT_TIMEOUT_MILLISECONDS = 5000;

    /** @var resource|null */
    private $handle = null;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /** @var string */
    private $endpoint;

    /** @var int */
    private $connectionTimeoutMs;

    /** @var int */
    private $timeoutMs;

    /**
     * @param string $username
     * @param string $password
     * @param string $endpoint
     * @param int $connectionTimeoutMs
     * @param int $timeoutMs
     */
    public function __construct(
        $username,
        $password,
        $endpoint,
        $connectionTimeoutMs = self::DEFAULT_CONNECTION_TIMEOUT_MILLISECONDS,
        $timeoutMs = self::DEFAULT_TIMEOUT_MILLISECONDS
    ) {
        if (!\extension_loaded('curl')) {
            throw new \LogicException('The curl extension is needed to use the Loki client.');
        }

        $this->username = $username;
        $this->password = $password;
        $this->endpoint = $endpoint;
        $this->connectionTimeoutMs = $connectionTimeoutMs;
        $this->timeoutMs = $timeoutMs;
    }

    /**
     * @param array|string $data
     * @return void
     */
    public function send($data)
    {
        if ($this->handle === null) {
            $this->initCurlHandle();
        }

        if (!\is_resource($this->handle)) {
            throw new RuntimeException('Failed to initialize cURL handle');
        }

        \curl_setopt($this->handle, \CURLOPT_POSTFIELDS, $data);
        \curl_setopt($this->handle, \CURLOPT_RETURNTRANSFER, true);

        $this->execute($this->handle, 5, false);
    }

    /**
     * @param resource $ch
     * @param int $retries
     * @param bool $closeAfterDone
     * @return bool|string
     */
    public function execute($ch, $retries = 5, $closeAfterDone = true)
    {
        while ($retries > 0) {
            $retries--;
            $curlResponse = \curl_exec($ch);
            if ($curlResponse === false) {
                $curlErrno = \curl_errno($ch);

                if (!\in_array($curlErrno, self::$retriableErrorCodes, true) || $retries === 0) {
                    $curlError = \curl_error($ch);

                    if ($closeAfterDone) {
                        \curl_close($ch);
                    }

                    throw new RuntimeException(\sprintf('Curl error (code %d): %s', $curlErrno, $curlError));
                }

                continue;
            }

            if ($closeAfterDone) {
                \curl_close($ch);
            }

            return $curlResponse;
        }

        return false;
    }

    /**
     * cURL oturumunu başlatır
     * @return void
     */
    private function initCurlHandle()
    {
        $this->handle = \curl_init();

        if (!\is_resource($this->handle)) {
            throw new RuntimeException('Failed to initialize cURL handle');
        }

        \curl_setopt($this->handle, \CURLOPT_URL, $this->endpoint . '/loki/api/v1/push');
        \curl_setopt($this->handle, \CURLOPT_USERPWD, $this->username . ':' . $this->password);
        \curl_setopt($this->handle, \CURLOPT_POST, true);
        \curl_setopt($this->handle, \CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        // Bazı eski sistemlerde milisaniye sabitleri yoksa saniye cinsinden kullanabilirsin:
        // \curl_setopt($this->handle, \CURLOPT_CONNECTTIMEOUT, (int)($this->connectionTimeoutMs / 1000));
        // \curl_setopt($this->handle, \CURLOPT_TIMEOUT, (int)($this->timeoutMs / 1000));
        \curl_setopt($this->handle, \CURLOPT_CONNECTTIMEOUT_MS, $this->connectionTimeoutMs);
        \curl_setopt($this->handle, \CURLOPT_TIMEOUT_MS, $this->timeoutMs);
    }
}
