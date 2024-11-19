<?php

namespace CommonGateway\CoreBundle\Monolog;

use Doctrine\DBAL\Connection;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\MongoDBFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Utils;
use Ramsey\Uuid\Uuid;

class PostgresqlHandler extends AbstractProcessingHandler
{
    private bool $initialized = false;

    public function __construct(
        private readonly Connection $connection,
        int|string $level = Logger::DEBUG,
        bool $bubble = true
    )
    {
        parent::__construct($level, $bubble);
    }

    protected function write(array $record): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }


        if (!isset($record['formatted']) || 'string' !== gettype($record['formatted'])) {
            throw new \InvalidArgumentException('PostgresqlHandler accepts only formatted records as a string' . Utils::getRecordMessageForException($record));
        }
        $id = Uuid::uuid4();

        $formatted = json_decode(json: $record['formatted'], associative: true);
        $formatted['_id'] = $id;
        $record['formatted'] = json_encode(value: $formatted);

        $this->connection->insert(table: 'logs',data: [
            '_id' => $id,
            'channel' => $record['channel'],
            'logs' => $record['formatted'],
            'time' => (new \DateTime($record['datetime']))->format('U'),
        ]);
    }

    private function initialize()
    {
        $this->connection->executeQuery(
            'CREATE TABLE IF NOT EXISTS logs '
            .'(_id uuid, channel VARCHAR(255), logs jsonb, time INTEGER)'
        );

        $this->initialized = true;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new JsonFormatter();
    }
}
