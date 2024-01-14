<?php

declare(strict_types=1);

namespace App\Messenger\Transport\Kafka;

use RdKafka\Conf as KafkaConf;

final class KafkaSenderProperties
{
    /** @var KafkaConf */
    private $kafkaConf;

    /** @var string */
    private $topicName;

    /** @var int */
    private $flushTimeoutMs;

    /** @var int */
    private $flushRetries;

    private ?string $retryTopicName;

    public function __construct(
        KafkaConf $kafkaConf,
        string $topicName,
        ?string $retryTopicName,
        int $flushTimeoutMs,
        int $flushRetries
    ) {
        $this->kafkaConf = $kafkaConf;
        $this->topicName = $topicName;
        $this->flushTimeoutMs = $flushTimeoutMs;
        $this->flushRetries = $flushRetries;
        $this->retryTopicName = $retryTopicName;
    }

    public function getKafkaConf(): KafkaConf
    {
        return $this->kafkaConf;
    }

    public function getTopicName(): string
    {
        return $this->topicName;
    }

    public function getFlushTimeoutMs(): int
    {
        return $this->flushTimeoutMs;
    }

    public function getFlushRetries(): int
    {
        return $this->flushRetries;
    }

    /**
     * @return string|null
     */
    public function getRetryTopicName(): ?string
    {
        return $this->retryTopicName;
    }
}
