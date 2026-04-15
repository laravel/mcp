<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Laravel\Mcp\Server\Annotations\Annotation;
use Laravel\Mcp\Server\Concerns\HasAnnotations;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class ResourceLink implements Content
  {
        use HasAnnotations;
        use HasMeta;

    public function __construct(
              protected string $uri,
              protected ?string $name = null,
              protected ?string $mimeType = null,
              protected ?string $description = null,
          ) {
              //
    }

    /**
     * @return array<string, mixed>
       */
    public function toTool(Tool $tool): array
    {
              return $this->toArray();
    }

    /**
     * @return array<string, mixed>
       */
    public function toPrompt(Prompt $prompt): array
    {
              return $this->toArray();
    }

    /**
     * resource_link is only valid in tool results and prompt messages,
         * not as a resource content type. We return toArray() for completeness
                                                                  * but this method should not be called in normal usage.
       *
       * @return array<string, mixed>
       */
    public function toResource(Resource $resource): array
    {
              return $this->toArray();
    }

    public function __toString(): string
    {
              return $this->uri;
    }

    /**
     * @return array<string, mixed>
       */
    public function toArray(): array
    {
              $annotations = $this->annotations();

            $data = array_filter([
                                             'type'        => 'resource_link',
                                             'uri'         => $this->uri,
                                             'name'        => $this->name,
                                             'description' => $this->description,
                                             'mimeType'    => $this->mimeType,
                                         ], fn ($v) => $v !== null);

            if ($annotations !== []) {
                          $data['annotations'] = $annotations;
            }

            return $this->mergeMeta($data);
    }

    /**
     * @return array<int, class-string>
       */
    protected function allowedAnnotations(): array
    {
              return [
                            Annotation::class,
                        ];
    }
  }
