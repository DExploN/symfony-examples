<?php

declare(strict_types=1);

namespace App\Messenger\Transport\Kafka;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerInterface;
use RdKafka\KafkaConsumer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaReceiver implements ReceiverInterface
{
    private LoggerInterface $logger;
    private SerializerInterface $serializer;
    private RdKafkaFactory $rdKafkaFactory;
    private KafkaReceiverProperties $properties;
    private KafkaConsumer $consumer;
    private bool $subscribed;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RdKafkaFactory $rdKafkaFactory,
        KafkaReceiverProperties $properties
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->rdKafkaFactory = $rdKafkaFactory;
        $this->properties = $properties;

        $this->subscribed = false;
    }

    public function get(): iterable
    {
        $message = $this->getSubscribedConsumer()->consume($this->properties->getReceiveTimeoutMs());

        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                $this->logger->info(
                    sprintf(
                        'Kafka: Message %s %s %s received ',
                        $message->topic_name,
                        $message->partition,
                        $message->offset
                    )
                );

                $envelope = $this->serializer->decode([
                    'body' => $message->payload,
                    'headers' => $message->headers ?? [],
                    'key' => $message->key,
                    'offset' => $message->offset,
                    'timestamp' => $message->timestamp,
                ]);
                /** @var DelayStamp | null $delay */
                $delay = $envelope->last(DelayStamp::class);
                /** @var RedeliveryStamp | $redelivery $delay */
                $redelivery = $envelope->last(RedeliveryStamp::class);
                if ($delay && $redelivery) {
                    $messageStartHandleDate = $redelivery->getRedeliveredAt();
                    $ms = $delay->getDelay();
                    $startTimeMs = $messageStartHandleDate->getTimestamp() * 1000 + $ms;
                    if (($waitTimeMs = $startTimeMs - microtime(true) * 1000) > 0) {
                        $this->logger->info('Kafka: sleep after redelivery ms = ' . $waitTimeMs);
                        $waitTimeMc = $waitTimeMs * 1000;
                        if ($waitTimeMc >= 1000000) {
                            sleep((int)ceil($waitTimeMc / 1000000));
                        } else {
                            usleep($waitTimeMc);
                        }
                    }
                }
                return [$envelope->with(new KafkaMessageStamp($message))];
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                $this->logger->info('Kafka: Partition EOF reached. Waiting for next message ...');
                break;
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                $this->logger->debug('Kafka: Consumer timeout.');
                break;
            case RD_KAFKA_RESP_ERR__TRANSPORT:
                $this->logger->debug('Kafka: Broker transport failure.');
                break;
            default:
                throw new TransportException($message->errstr(), $message->err);
        }

        return [];
    }

    public function ack(Envelope $envelope): void
    {
        $consumer = $this->getConsumer();

        /** @var KafkaMessageStamp $transportStamp */
        $transportStamp = $envelope->last(KafkaMessageStamp::class);
        $message = $transportStamp->getMessage();

        if ($this->properties->isCommitAsync()) {
            $consumer->commitAsync($message);

            $this->logger->info(
                sprintf(
                    'Offset topic=%s partition=%s offset=%s to be committed asynchronously in ack.',
                    $message->topic_name,
                    $message->partition,
                    $message->offset
                )
            );
        } else {
            $consumer->commit($message);

            $this->logger->info(
                sprintf(
                    'Offset topic=%s partition=%s offset=%s successfully committed in ack.',
                    $message->topic_name,
                    $message->partition,
                    $message->offset
                )
            );
        }
    }

    public function reject(Envelope $envelope): void
    {
        $consumer = $this->getConsumer();

        /** @var KafkaMessageStamp $transportStamp */
        $transportStamp = $envelope->last(KafkaMessageStamp::class);
        $message = $transportStamp->getMessage();

        if ($this->properties->isCommitAsync()) {
            $consumer->commitAsync($message);

            $this->logger->info(
                sprintf(
                    'Offset topic=%s partition=%s offset=%s to be committed asynchronously in reject.',
                    $message->topic_name,
                    $message->partition,
                    $message->offset
                )
            );
        } else {
            $consumer->commit($message);

            $this->logger->info(
                sprintf(
                    'Offset topic=%s partition=%s offset=%s successfully committed in reject.',
                    $message->topic_name,
                    $message->partition,
                    $message->offset
                )
            );
        }
    }

    private function getSubscribedConsumer(): KafkaConsumer
    {
        $consumer = $this->getConsumer();

        if (false === $this->subscribed) {
            $this->logger->info('Partition assignment...');
            $consumer->subscribe([$this->properties->getTopicName()]);

            $this->subscribed = true;
        }

        return $consumer;
    }

    private function getConsumer(): KafkaConsumer
    {
        return $this->consumer ?? $this->consumer = $this->rdKafkaFactory->createConsumer(
            $this->properties->getKafkaConf()
        );
    }
}
