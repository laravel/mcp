type BridgeConfig = {
    endpoint: string;
    version: string;
};

type ToolContent = {
    type: string;
    text?: string;
};

type ToolListing = {
    name: string;
    title?: string;
    description?: string;
    inputSchema: Record<string, unknown>;
    annotations?: Record<string, unknown>;
};

type JsonRpcMessage = {
    result?: { content?: ToolContent[]; tools?: ToolListing[] };
    error?: { message?: string };
};

type ModelContext = {
    registerTool(
        tool: {
            name: string;
            description: string;
            inputSchema: Record<string, unknown>;
            annotations: Record<string, unknown>;
            execute: (input: Record<string, unknown>, options?: { signal?: AbortSignal }) => Promise<string>;
        },
        options?: { signal?: AbortSignal },
    ): unknown;
};

declare global {
    interface Window {
        __laravelWebMcp?: BridgeConfig;
    }

    interface Document {
        modelContext?: ModelContext;
    }

    interface Navigator {
        modelContext?: ModelContext;
    }
}

void (async (): Promise<void> => {
    const config = window.__laravelWebMcp;
    const context = document.modelContext ?? navigator.modelContext;

    if (!config || !context || typeof context.registerTool !== 'function') {
        return;
    }

    const cookie = (name: string): string | undefined => document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(`${name}=`))
        ?.split('=')[1];

    let id = 0;

    const parse = (contentType: string, body: string): JsonRpcMessage | undefined => {
        if (!contentType.includes('text/event-stream')) {
            return JSON.parse(body) as JsonRpcMessage;
        }

        return body
            .split('\n')
            .filter((line) => line.startsWith('data:'))
            .map((line) => JSON.parse(line.slice(5)) as JsonRpcMessage)
            .findLast((message) => 'result' in message || 'error' in message);
    };

    const call = async (
        method: string,
        params: Record<string, unknown>,
        signal?: AbortSignal,
    ): Promise<NonNullable<JsonRpcMessage['result']>> => {
        const response = await fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            signal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json, text/event-stream',
                'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') ?? ''),
            },
            body: JSON.stringify({
                jsonrpc: '2.0',
                id: ++id,
                method,
                params: {
                    ...params,
                    _meta: {
                        'io.modelcontextprotocol/protocolVersion': config.version,
                        'io.modelcontextprotocol/clientCapabilities': {},
                    },
                },
            }),
        });

        const message = parse(response.headers.get('content-type') ?? '', await response.text());

        if (!message?.result || message.error) {
            throw new Error(message?.error?.message ?? 'The MCP server returned an empty response.');
        }

        return message.result;
    };

    const { tools = [] } = await call('tools/list', {});

    for (const tool of tools) {
        context.registerTool({
            name: tool.name,
            description: tool.description ?? tool.title ?? tool.name,
            inputSchema: tool.inputSchema,
            annotations: tool.annotations ?? {},
            execute: async (input, options = {}): Promise<string> => {
                const result = await call('tools/call', {
                    name: tool.name,
                    arguments: input,
                }, options.signal);

                return (result.content ?? [])
                    .filter((item) => item.type === 'text')
                    .map((item) => item.text)
                    .join('\n');
            },
        });
    }
})();

export {};
