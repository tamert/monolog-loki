<?php

namespace Tamert\Monolog\Loki;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Simplified Loki handler compatible with PHP 7.2 + Monolog 2.x
 */
class LokiHandler extends AbstractProcessingHandler
{
    /** @var string */
    private $lokiUrl;

    /** @var array */
    private $labels;

    /**
     * @param string $lokiUrl
     * @param array $labels
     * @param int|string $level
     * @param bool $bubble
     */
    public function __construct($lokiUrl, array $labels = array('app' => 'php-app'), $level = Logger::DEBUG, $bubble = true)
    {
        $this->lokiUrl = $lokiUrl . '/loki/api/v1/push';
        $this->labels = $labels;
        parent::__construct($level, $bubble);
    }

    /**
     * @param array $record
     */
    protected function write(array $record): void
    {
        $timestamp = (string) sprintf('%.0f', microtime(true) * 1e9);
        $entry = array(
            'streams' => array(array(
                'stream' => $this->labels,
                'values' => array(array($timestamp, $record['formatted'])),
            )),
        );


        $this->sendToLoki($entry);
    }

    /**
     * Sends data to Loki via HTTP POST
     * @param array $entry
     * @return void
     */
    private function sendToLoki(array $entry)
    {
        $payload = json_encode($entry);
        if ($payload === false) {
            return;
        }

        $ch = curl_init($this->lokiUrl);
        if (!is_resource($ch)) {
            return;
        }

        error_log($this->lokiUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));

        $curlResponse = curl_exec($ch);
        $curlInfo = \curl_getinfo($ch);

        if ($curlResponse === false) {
            $curlError = \curl_error($ch);
            error_log("Loki curl error: " . $curlError);
        } else {
            error_log("Loki HTTP code: " . $curlInfo['http_code']);
            error_log("Loki response body: " . $curlResponse);
        }

        curl_close($ch);
    }
}
