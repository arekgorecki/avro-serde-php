<?php

declare(strict_types=1);

namespace FlixTech\AvroSerializer\Benchmarks;

use FlixTech\AvroSerializer\Objects\RecordSerializer;
use FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException;
use FlixTech\SchemaRegistryApi\Registry;
use FlixTech\SchemaRegistryApi\Registry\BlockingRegistry;
use FlixTech\SchemaRegistryApi\Registry\Cache\AvroObjectCacheAdapter;
use FlixTech\SchemaRegistryApi\Registry\CachedRegistry;
use FlixTech\SchemaRegistryApi\Registry\PromisingRegistry;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * @BeforeMethods({"setUp"})
 */
class AvroEncodingBench
{
    const ASYNC = 'async';
    const ASYNC_CACHED = 'async_cached';
    const SYNC = 'sync';
    const SYNC_CACHED = 'sync_cached';

    const TEST_MODES = [
        self::ASYNC,
        self::ASYNC_CACHED,
        self::SYNC,
        self::SYNC_CACHED,
    ];

    const TEST_RECORD = [
        'name' => 'Thomas',
        'age' => 36,
    ];

    const SCHEMA_JSON = /** @lang JSON */
        <<<JSON
{
  "type": "record",
  "name": "user",
  "fields": [
    {"name": "name", "type": "string"},
    {"name": "age", "type": "int"}
  ]
}
JSON;

    /**
     * @var \FlixTech\AvroSerializer\Objects\RecordSerializer[]
     */
    private $serializers = [];

    /**
     * @var string[]
     */
    private $messages = [];

    /**
     * @var \AvroSchema
     */
    private $schema;

    public function setUp()
    {
        $this->schema = \AvroSchema::parse(self::SCHEMA_JSON);

        $this->prepareTestForMode(self::ASYNC, new PromisingRegistry(
            new Client(['base_uri' => getenv('SCHEMA_REGISTRY_HOST')])
        ));

        $this->prepareTestForMode(self::SYNC, new BlockingRegistry(
            new PromisingRegistry(
                new Client(['base_uri' => getenv('SCHEMA_REGISTRY_HOST')])
            )
        ));

        $this->prepareTestForMode(self::ASYNC_CACHED, new CachedRegistry(
            new PromisingRegistry(
                new Client(['base_uri' => getenv('SCHEMA_REGISTRY_HOST')])
            ),
            new AvroObjectCacheAdapter()
        ));

        $this->prepareTestForMode(self::SYNC_CACHED, new CachedRegistry(
            new BlockingRegistry(
                new PromisingRegistry(
                    new Client(['base_uri' => getenv('SCHEMA_REGISTRY_HOST')])
                )
            ),
            new AvroObjectCacheAdapter()
        ));
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchEncodeWithSyncRegistry()
    {
        $this->serializers[self::SYNC]->encodeRecord('test', $this->schema, self::TEST_RECORD);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchDecodeWithSyncRegistry()
    {
        $this->serializers[self::SYNC]->decodeMessage($this->messages[self::SYNC]);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchEncodeWithAsyncRegistry()
    {
        $this->serializers[self::ASYNC]->encodeRecord('test', $this->schema, self::TEST_RECORD);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchDecodeWithAsyncRegistry()
    {
        $this->serializers[self::ASYNC]->decodeMessage($this->messages[self::ASYNC]);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchEncodeWithAsyncCachedRegistry()
    {
        $this->serializers[self::ASYNC_CACHED]->encodeRecord('test', $this->schema, self::TEST_RECORD);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchDecodeWithAsyncCachedRegistry()
    {
        $this->serializers[self::ASYNC_CACHED]->decodeMessage($this->messages[self::ASYNC_CACHED]);
    }


    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchEncodeWithSyncCachedRegistry()
    {
        $this->serializers[self::SYNC_CACHED]->encodeRecord('test', $this->schema, self::TEST_RECORD);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     *
     * @throws \Exception
     * @throws \FlixTech\SchemaRegistryApi\Exception\SchemaRegistryException
     */
    public function benchDecodeWithSyncCachedRegistry()
    {
        $this->serializers[self::SYNC_CACHED]->decodeMessage($this->messages[self::SYNC_CACHED]);
    }

    private function prepareTestForMode(string $mode, Registry $registry)
    {
        $result = $registry->register('test', $this->schema);
        !$result instanceof PromiseInterface ?: $result->wait();

        $this->serializers[$mode] = new RecordSerializer($registry);

        try {
            $this->messages[$mode] = $this->serializers[$mode]->encodeRecord(
                'test',
                $this->schema,
                self::TEST_RECORD
            );
        } catch (\Exception $e) {
        } catch (SchemaRegistryException $e) {
        }
    }
}
