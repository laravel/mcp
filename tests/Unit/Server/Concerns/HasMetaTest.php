<?php

use Laravel\Mcp\Server\Concerns\HasMeta;

it('can set meta with an array', function (): void {
    $object = new class
    {
        use HasMeta;

        public function getMeta(): ?array
        {
            return $this->meta;
        }
    };

    $object->setMeta(['key1' => 'value1', 'key2' => 'value2']);

    expect($object->getMeta())->toEqual([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);
});

it('can set meta with a key-value signature', function (): void {
    $object = new class
    {
        use HasMeta;

        public function getMeta(): ?array
        {
            return $this->meta;
        }
    };

    $object->setMeta('key1', 'value1');
    $object->setMeta('key2', 'value2');

    expect($object->getMeta())->toEqual([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);
});

it('throws exception when using key-value signature without value', function (): void {
    $object = new class
    {
        use HasMeta;
    };

    expect(fn () => $object->setMeta('key1'))
        ->toThrow(InvalidArgumentException::class, 'Value is required when using key-value signature.');
});

it('merges meta into base array', function (): void {
    $object = new class
    {
        use HasMeta;
    };

    $object->setMeta(['key1' => 'value1']);

    $result = $object->mergeMeta([
        'name' => 'test',
        'description' => 'A test',
    ]);

    expect($result)->toEqual([
        'name' => 'test',
        'description' => 'A test',
        '_meta' => [
            'key1' => 'value1',
        ],
    ]);
});

it('returns base array when meta is null', function (): void {
    $object = new class
    {
        use HasMeta;
    };

    $result = $object->mergeMeta([
        'name' => 'test',
        'description' => 'A test',
    ]);

    expect($result)->toEqual([
        'name' => 'test',
        'description' => 'A test',
    ])->not->toHaveKey('_meta');
});

it('merges multiple setMeta calls with arrays', function (): void {
    $object = new class
    {
        use HasMeta;

        public function getMeta(): ?array
        {
            return $this->meta;
        }
    };

    $object->setMeta(['key1' => 'value1']);
    $object->setMeta(['key2' => 'value2', 'key3' => 'value3']);

    expect($object->getMeta())->toEqual([
        'key1' => 'value1',
        'key2' => 'value2',
        'key3' => 'value3',
    ]);
});

it('overwrites existing keys when setting meta', function (): void {
    $object = new class
    {
        use HasMeta;

        public function getMeta(): ?array
        {
            return $this->meta;
        }
    };

    $object->setMeta(['key1' => 'value1']);
    $object->setMeta(['key1' => 'value2']);

    expect($object->getMeta())->toEqual([
        'key1' => 'value2',
    ]);
});
