<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Elicitation\Events\ElicitationReceived;
use Laravel\Mcp\Server\Elicitation\Events\ElicitationSent;
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
            $properties[$name] = $field->toArray();

            if ($field->isRequired()) {
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

        Container::getInstance()->make('events')->dispatch(new ElicitationSent(
            mode: $params['mode'],
            message: $params['message'],
            requestId: $id,
        ));

        $response = json_decode($rawResponse, true);

        if (($response['id'] ?? null) !== $id) {
            throw new JsonRpcException("Elicitation response id mismatch: expected [{$id}], received [{$response['id']}].", -32603);
        }

        $result = $response['result'] ?? [];

        $elicitationResult = new ElicitationResult(
            action: $result['action'] ?? 'cancel',
            content: $result['content'] ?? null,
        );

        Container::getInstance()->make('events')->dispatch(new ElicitationReceived(
            action: $elicitationResult->action(),
            requestId: $id,
            hasContent: $elicitationResult->all() !== [],
        ));

        return $elicitationResult;
    }

    /**
     * @throws JsonRpcException
     */
    protected function ensureCapability(string $mode): void
    {
        $elicitation = $this->clientCapabilities[Server::CAPABILITY_ELICITATION] ?? null;

        if ($elicitation === null) {
            throw new JsonRpcException(
                'Client does not support elicitation. Ensure the MCP client declares elicitation support in its capabilities during initialization.',
                -32602,
            );
        }

        // json_decode('{}', true) === [] in PHP, so empty array = empty object = form-only
        $supportedModes = is_array($elicitation) && $elicitation !== []
            ? array_keys($elicitation)
            : ['form'];

        if (! in_array($mode, $supportedModes, true)) {
            throw new JsonRpcException(
                "Client does not support elicitation mode [{$mode}]. The connected client only supports form mode. Use form() instead, or connect a client that declares URL elicitation support.",
                -32602,
            );
        }
    }
}
