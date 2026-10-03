<?php

namespace App\Tests\Benchmark;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as TransportConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/** Actual Messenger persistence with unique queues in a disposable test DB. */
final class DoctrineBenchmarkBus implements MessageBusInterface
{
    /** @var array<string, DoctrineTransport> */
    public array $lanes = [];

    public function __construct(
        private readonly Connection $database,
        private readonly string $namespace,
    )
    {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $lane = $envelope->last(TransportNamesStamp::class)?->getTransportNames()[0] ?? 'async';

        return $this->transport($lane)->send($envelope);
    }

    public function transport(string $lane): DoctrineTransport
    {
        return $this->lanes[$lane] ??= new DoctrineTransport(new TransportConnection([
            'queue_name' => $this->namespace.'_'.$lane,
            'auto_setup' => false,
            'redeliver_timeout' => 60,
        ], $this->database), new PhpSerializer());
    }
}
