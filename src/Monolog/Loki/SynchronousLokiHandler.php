<?php

namespace Tamert\Monolog\Loki;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\Processor\HostnameProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use RuntimeException;

/**
 * Synchronous Loki Handler - PHP 7.2 + Monolog 2.x uyumlu
 */
class SynchronousLokiHandler extends AbstractProcessingHandler
{
    const DEFAULT_THROW_EXCEPTION = false;

    /** @var LokiClient */
    private $client;

    /** @var array */
    private $labels;

    /** @var bool */
    private $throwExceptions;

    /**
     * @param string $username
     * @param string $password
     * @param string $endpoint
     * @param int|string $level
     * @param array $labels
     * @param bool $bubble
     * @param int $connectionTimeoutMs
     * @param int $timeoutMs
     * @param bool $throwExceptions
     * @param LokiClient|null $client
     */
    public function __construct(
        $username,
        $password,
        $endpoint,
        $level = Logger::DEBUG,
        array $labels = array(),
        $bubble = true,
        $connectionTimeoutMs = LokiClient::DEFAULT_CONNECTION_TIMEOUT_MILLISECONDS,
        $timeoutMs = LokiClient::DEFAULT_TIMEOUT_MILLISECONDS,
        $throwExceptions = self::DEFAULT_THROW_EXCEPTION,
        LokiClient $client = null
    ) {
        parent::__construct($level, $bubble);

        $this->labels = $labels;
        $this->throwExceptions = $throwExceptions;
        $this->client = $client ?: new LokiClient($username, $password, $endpoint, $connectionTimeoutMs, $timeoutMs);

        $this->pushProcessor(new IntrospectionProcessor($level, array('Loki\\')));
        $this->pushProcessor(new WebProcessor());
        $this->pushProcessor(new ProcessIdProcessor());
        $this->pushProcessor(new HostnameProcessor());
    }

    /**
     * @param array $record
     * @return void
     * @throws \ErrorException
     */
    protected function write(array $record)
    {
        try {
            if (!isset($record['formatted']) || !is_string($record['formatted'])) {
                throw new RuntimeException('Formatted records must be a string, got ' . gettype($record['formatted']));
            }

            $this->client->send($record['formatted']);
        } catch (\ErrorException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }

            trigger_error('Failed to send log record to Loki: ' . $e->getMessage(), E_USER_WARNING);
        }
    }

    /**
     * @param array $records
     * @return void
     * @throws \ErrorException
     */
    public function handleBatch(array $records)
    {
        $formatter = $this->getFormatter();
        $formattedRecords = $formatter->formatBatch($records);

        if (!is_string($formattedRecords)) {
            throw new RuntimeException('Formatted batch must be a string, got ' . gettype($formattedRecords));
        }

        try {
            $this->client->send($formattedRecords);
        } catch (\ErrorException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }

            trigger_error(
                'Failed to send ' . count($records) . ' log records to Loki: ' . $e->getMessage(),
                E_USER_WARNING
            );
        }
    }

    /**
     * @return LokiJsonFormatter
     */
    protected function getDefaultFormatter()
    {
        return new LokiJsonFormatter($this->labels);
    }

    /**
     * @return FormatterInterface
     */
    public function getFormatter()
    {
        return $this->getDefaultFormatter();
    }
}
