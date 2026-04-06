<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation;

use Closure;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Elicitation\Fields\ElicitField;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class Elicitation
{
    /**
     * @param  array<string, mixed>|null  $clientCapabilities
     */
    public function __construct(
        protected Transport $transport,
        protected ?array $clientCapabilities = null,
    ) {}

    /**
     * Send a form-mode elicitation request.
     *
     * @param  Closure(ElicitSchema): array<string, ElicitField>|array<string, mixed>  $schema
     */
    public function form(string $message, Closure|array $schema): ElicitationResult
    {
        $this->ensureCapability('form');

        $requestedSchema = $schema instanceof Closure
            ? $this->buildSchema($schema)
            : $schema;

        return $this->send([
            'mode' => 'form',
            'message' => $message,
            'requestedSchema' => $requestedSchema,
        ]);
    }

    /**
     * Send a URL-mode elicitation request.
     */
    public function url(string $message, string $url, ?string $elicitationId = null): ElicitationResult
    {
        $this->ensureCapability('url');

        $elicitationId ??= Str::uuid()->toString();

        $result = $this->send([
            'mode' => 'url',
            'message' => $message,
            'url' => $url,
            'elicitationId' => $elicitationId,
        ]);

        $result->setElicitationId($elicitationId);

        return $result;
    }

    /**
     * Send a completion notification for URL mode elicitation.
     */
    public function notifyComplete(string $elicitationId): void
    {
        $this->transport->send(JsonRpcResponse::notification(
            method: 'notifications/elicitation/complete',
            params: ['elicitationId' => $elicitationId],
        )->toJson());
    }

    /**
     * @param  Closure(ElicitSchema): array<string, ElicitField>  $callback
     * @return array<string, mixed>
     */
    protected function buildSchema(Closure $callback): array
    {
        $schema = new ElicitSchema;
        $fields = $callback($schema);

        $properties = [];
        $required = [];

        foreach ($fields as $name => $field) {
            $fieldArray = $field->toArray();
            $isRequired = $fieldArray['_required'] ?? false;
            unset($fieldArray['_required']);
            $properties[$name] = $fieldArray;

            if ($isRequired) {
                $required[] = $name;
            }
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws JsonRpcException
     */
    protected function send(array $params): ElicitationResult
    {
        $id = Str::uuid()->toString();

        $request = json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'elicitation/create',
            'params' => $params,
        ], JSON_THROW_ON_ERROR);

        $rawResponse = $this->transport->sendRequest($request);
        $response = json_decode($rawResponse, true);

        if (($response['id'] ?? null) !== $id) {
            throw new JsonRpcException('Elicitation response id mismatch.', -32603);
        }

        $result = $response['result'] ?? [];

        return new ElicitationResult(
            action: $result['action'] ?? 'cancel',
            content: $result['content'] ?? null,
        );
    }

    /**
     * @throws JsonRpcException
     */
    protected function ensureCapability(string $mode): void
    {
        $elicitation = $this->clientCapabilities['elicitation'] ?? null;

        if ($elicitation === null) {
            throw new JsonRpcException(
                'Client does not support elicitation.',
                -32602,
            );
        }

        // json_decode('{}', true) === [] in PHP, so empty array = empty object = form-only
        $supportedModes = is_array($elicitation) && $elicitation !== []
            ? array_keys($elicitation)
            : ['form'];

        if (! in_array($mode, $supportedModes, true)) {
            throw new JsonRpcException(
                "Client does not support elicitation mode [{$mode}].",
                -32602,
            );
        }
    }
}
