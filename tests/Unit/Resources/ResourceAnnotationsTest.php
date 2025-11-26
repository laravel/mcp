<?php

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Annotations\Audience;
use Laravel\Mcp\Server\Annotations\LastModified;
use Laravel\Mcp\Server\Annotations\Priority;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

it('returns no annotation object when no annotations are present', function (): void {
    $resource = new class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::text('Test content');
        }
    };

    expect($resource->annotations())->toBe([])
        ->and($resource->toArray())->not->toHaveKey('annotations');
});

it('includes audience annotation in resource', function (): void {
    $resource = new #[Audience([Role::USER, Role::ASSISTANT])] class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::text('Test content');
        }
    };

    $annotations = $resource->annotations();
    expect($annotations)->toHaveKey('audience')
        ->and($annotations['audience'])->toBe(['user', 'assistant']);
});

it('includes priority annotation in resource', function (): void {
    $resource = new #[Priority(0.8)] class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::text('Test content');
        }
    };

    $annotations = $resource->annotations();
    expect($annotations)->toHaveKey('priority')
        ->and($annotations['priority'])->toBe(0.8);
});

it('includes the lastModified annotation in resource', function (): void {
    $resource = new #[LastModified('2025-01-12T15:00:58Z')] class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::text('Test content');
        }
    };

    $annotations = $resource->annotations();
    expect($annotations)->toHaveKey('lastModified')
        ->and($annotations['lastModified'])->toBe('2025-01-12T15:00:58Z');
});

it('includes multiple annotations in resource', function (): void {
    $resource = new #[Audience(Role::USER)] #[Priority(1.0)] #[LastModified('2025-01-12T15:00:58Z')] class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::text('Test content');
        }
    };

    $annotations = $resource->annotations();
    expect($annotations)->toHaveKey('audience')
        ->and($annotations)->toHaveKey('priority')
        ->and($annotations)->toHaveKey('lastModified')
        ->and($annotations['audience'])->toBe(['user'])
        ->and($annotations['priority'])->toBe(1.0)
        ->and($annotations['lastModified'])->toBe('2025-01-12T15:00:58Z');
});

it('includes annotations in resource toArray', function (): void {
    $resource = new #[Audience(Role::USER)] #[Priority(0.5)] class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::text('Test content');
        }
    };

    $array = $resource->toArray();
    expect($array)->toHaveKey('annotations')
        ->and($array['annotations'])->toHaveKey('audience')
        ->and($array['annotations'])->toHaveKey('priority')
        ->and($array['annotations']['audience'])->toBe(['user'])
        ->and($array['annotations']['priority'])->toBe(0.5);
});

it('validates audience roles', function (): void {
    expect(function (): void {
        $resource = new #[Audience(['invalid_role'])] class extends Resource
        {
            public function description(): string
            {
                return 'Test';
            }

            public function handle(): Response
            {
                return Response::text('Test');
            }
        };
        $resource->annotations();
    })->toThrow(
        InvalidArgumentException::class,
        'All values of '.Audience::class.' attributes must be instances of '.Role::class
    );
});

it('validates priority range minimum', function (): void {
    expect(function (): void {
        $resource = new #[Priority(-0.1)] class extends Resource
        {
            public function description(): string
            {
                return 'Test';
            }

            public function handle(): Response
            {
                return Response::text('Test');
            }
        };
        $resource->annotations();
    })->toThrow(InvalidArgumentException::class);
});

it('validates priority range maximum', function (): void {
    expect(function (): void {
        $resource = new #[Priority(1.1)] class extends Resource
        {
            public function description(): string
            {
                return 'Test';
            }

            public function handle(): Response
            {
                return Response::text('Test');
            }
        };
        $resource->annotations();
    })->toThrow(InvalidArgumentException::class);
});

it('validates lastModified ISO 8601 format', function (): void {
    expect(function (): void {
        $resource = new #[LastModified('not-a-valid-timestamp')] class extends Resource
        {
            public function description(): string
            {
                return 'Test';
            }

            public function handle(): Response
            {
                return Response::text('Test');
            }
        };
        $resource->annotations();
    })->toThrow(InvalidArgumentException::class);
});

it('accepts valid ISO 8601 formats for lastModified', function (): void {
    $resource = new #[LastModified('2025-01-12T15:00:58+00:00')] class extends Resource
    {
        public function description(): string
        {
            return 'Test';
        }

        public function handle(): Response
        {
            return Response::text('Test');
        }
    };

    $annotations = $resource->annotations();
    expect($annotations['lastModified'])->toBe('2025-01-12T15:00:58+00:00');
});

it('accepts priority of 0.0', function (): void {
    $resource = new #[Priority(0.0)] class extends Resource
    {
        public function description(): string
        {
            return 'Test';
        }

        public function handle(): Response
        {
            return Response::text('Test');
        }
    };

    expect($resource->annotations()['priority'])->toBe(0.0);
});

it('accepts priority of 1.0', function (): void {
    $resource = new #[Priority(1.0)] class extends Resource
    {
        public function description(): string
        {
            return 'Test';
        }

        public function handle(): Response
        {
            return Response::text('Test');
        }
    };

    expect($resource->annotations()['priority'])->toBe(1.0);
});

it('accepts empty audience array', function (): void {
    $resource = new #[Audience([])] class extends Resource
    {
        public function description(): string
        {
            return 'Test';
        }

        public function handle(): Response
        {
            return Response::text('Test');
        }
    };

    expect($resource->annotations()['audience'])->toBe([]);
});

it('accepts only user role', function (): void {
    $resource = new #[Audience(Role::USER)] class extends Resource
    {
        public function description(): string
        {
            return 'Test';
        }

        public function handle(): Response
        {
            return Response::text('Test');
        }
    };

    expect($resource->annotations()['audience'])->toBe(['user']);
});

it('accepts only an assistant role', function (): void {
    $resource = new #[Audience(Role::ASSISTANT)] class extends Resource
    {
        public function description(): string
        {
            return 'Test';
        }

        public function handle(): Response
        {
            return Response::text('Test');
        }
    };

    expect($resource->annotations()['audience'])->toBe(['assistant']);
});

it('rejects tool annotations on resources', function (): void {
    expect(function (): void {
        $resource = new #[IsReadOnly] class extends Resource
        {
            public function description(): string
            {
                return 'Test';
            }

            public function handle(): Response
            {
                return Response::text('Test');
            }
        };

        $resource->annotations();
    })->toThrow(InvalidArgumentException::class, 'Annotation [Laravel\Mcp\Server\Tools\Annotations\IsReadOnly] cannot be used on');
});
