(function () {
    const jsonRpcVersion = "2.0";
    const protocolVersion = "2026-01-26";
    const queuedHandlerNames = [
        "ontoolinput",
        "ontoolinputpartial",
        "ontoolresult",
        "ontoolcancelled",
        "onhostcontextchanged",
    ];

    const errorCodes = {
        parseError: -32700,
        invalidRequest: -32600,
        methodNotFound: -32601,
        invalidParams: -32602,
        internalError: -32603,
    };

    let nextRequestId = 0;

    const pendingRequests = new Map();
    const handlers = {};
    const queuedNotifications = queuedHandlerNames.reduce(function (
        queue,
        name,
    ) {
        queue[name] = [];

        return queue;
    }, {});
    const state = {
        hostContext: null,
        hostInfo: null,
        hostCapabilities: null,
    };

    let resizeObserver = null;

    function disconnectResizeObserver() {
        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        }
    }

    const notificationHandlers = {
        "ui/notifications/host-context-changed": applyHostContext,
        "ui/notifications/tool-input": function (params) {
            emit("ontoolinput", params ?? {});
        },
        "ui/notifications/tool-input-partial": function (params) {
            emit("ontoolinputpartial", params ?? {});
        },
        "ui/notifications/tool-result": function (params) {
            emit("ontoolresult", params ?? {});
        },
        "ui/notifications/tool-cancelled": function (params) {
            emit("ontoolcancelled", params ?? {});
        },
    };

    function send(message) {
        message.jsonrpc = jsonRpcVersion;

        window.parent.postMessage(message, "*");
    }

    function request(method, params) {
        return new Promise(function (resolve, reject) {
            const id = ++nextRequestId;

            pendingRequests.set(id, { resolve, reject });

            send({ id, method, params });
        });
    }

    function notify(method, params) {
        const message = { method };

        if (params !== undefined) {
            message.params = params;
        }

        send(message);
    }

    function respond(id, result) {
        send({ id, result });
    }

    function respondWithError(id, code, message) {
        send({ id, error: { code, message } });
    }

    function parseMessage(data) {
        if (typeof data === "string") {
            try {
                return JSON.parse(data);
            } catch (error) {
                return null;
            }
        }

        if (data && typeof data === "object") {
            return data;
        }

        return null;
    }

    function isObject(value) {
        return (
            value !== null && typeof value === "object" && !Array.isArray(value)
        );
    }

    function mergeObjects(original, toMerge) {
        return Object.assign({}, original || {}, toMerge || {});
    }

    function mergeHostContext(update) {
        if (!update) {
            return state.hostContext;
        }

        const current = state.hostContext || {};
        const next = mergeObjects(current, update);

        if (current.styles || update.styles) {
            const currentStyles = current.styles || {};
            const nextStyles = update.styles || {};

            next.styles = mergeObjects(currentStyles, nextStyles);
            next.styles.variables = mergeObjects(
                currentStyles.variables,
                nextStyles.variables,
            );
            next.styles.css = mergeObjects(currentStyles.css, nextStyles.css);
        }

        state.hostContext = next;

        return next;
    }

    function flushQueuedNotifications(name) {
        const callback = handlers[name];
        const queue = queuedNotifications[name];

        if (!callback || !queue || queue.length === 0) {
            return;
        }

        while (queue.length > 0) {
            callback(queue.shift());
        }
    }

    function emit(name, payload) {
        const callback = handlers[name];

        if (callback) {
            callback(payload);
            return;
        }

        if (queuedNotifications[name]) {
            queuedNotifications[name].push(payload);
        }
    }

    function setHandler(name, callback) {
        handlers[name] = callback;
        flushQueuedNotifications(name);
    }

    function applyTheme(theme) {
        if (!theme) {
            return;
        }

        document.documentElement.setAttribute("data-theme", theme);
        document.documentElement.style.colorScheme = theme;
    }

    function applyStyleVariables(variables) {
        if (!variables) {
            return;
        }

        Object.keys(variables)
            .filter((key) => variables[key] !== undefined)
            .forEach(function (key) {
                document.documentElement.style.setProperty(key, variables[key]);
            });
    }

    function applyFonts(fontCss) {
        if (!fontCss) {
            return;
        }

        let style = document.getElementById("__mcp-host-fonts");

        if (!style) {
            style = document.createElement("style");
            style.id = "__mcp-host-fonts";
            document.head.appendChild(style);
        }

        style.textContent = fontCss;
    }

    function applyHostContext(update) {
        const hostContext = mergeHostContext(update);

        if (!hostContext) {
            return;
        }

        applyTheme(hostContext.theme);
        applyStyleVariables(hostContext.styles?.variables);
        applyFonts(hostContext.styles?.css?.fonts);
        emit("onhostcontextchanged", hostContext);
    }

    function currentSize() {
        return {
            width: document.documentElement.scrollWidth,
            height: document.documentElement.scrollHeight,
        };
    }

    function notifySizeChanged() {
        notify("ui/notifications/size-changed", currentSize());
    }

    function autoResize() {
        if (typeof ResizeObserver === "undefined" || !document.body) {
            return;
        }

        disconnectResizeObserver();

        resizeObserver = new ResizeObserver(notifySizeChanged);

        resizeObserver.observe(document.body);

        return disconnectResizeObserver;
    }

    async function handleTeardown(id) {
        try {
            disconnectResizeObserver();

            respond(id, await (handlers.onteardown?.() ?? {}));
        } catch (error) {
            respondWithError(
                id,
                errorCodes.internalError,
                error instanceof Error
                    ? error.message
                    : "Unknown teardown error",
            );
        }
    }

    async function handleCallTool(id, params) {
        if (!handlers.oncalltool) {
            respondWithError(
                id,
                errorCodes.methodNotFound,
                "No tool handler registered.",
            );
            return;
        }

        try {
            respond(id, await handlers.oncalltool(params));
        } catch (error) {
            respondWithError(
                id,
                errorCodes.internalError,
                error instanceof Error ? error.message : "Unknown tool error",
            );
        }
    }

    async function handleListTools(id, params) {
        try {
            respond(
                id,
                await (handlers.onlisttools?.(params) ?? { tools: [] }),
            );
        } catch (error) {
            respondWithError(
                id,
                errorCodes.internalError,
                error instanceof Error
                    ? error.message
                    : "Unknown list tools error",
            );
        }
    }

    function handlePendingResponse(message) {
        if (message.id === undefined || !pendingRequests.has(message.id)) {
            return false;
        }

        const pending = pendingRequests.get(message.id);

        pendingRequests.delete(message.id);

        if (message.error) {
            pending.reject(new Error(message.error.message));
        } else {
            pending.resolve(message.result);
        }

        return true;
    }

    function handleNotification(message) {
        const handler = notificationHandlers[message.method];

        if (handler) {
            handler(message.params);
        }
    }

    const requestHandlers = {
        "ui/resource-teardown": function (message) {
            handleTeardown(message.id);
        },
        "tools/call": function (message) {
            handleCallTool(message.id, message.params);
        },
        "tools/list": function (message) {
            handleListTools(message.id, message.params);
        },
    };

    function handleIncomingRequest(message) {
        const handler = requestHandlers[message.method];

        if (handler) {
            handler(message);
        } else {
            respondWithError(
                message.id,
                errorCodes.methodNotFound,
                "Method not found: " + message.method,
            );
        }
    }

    window.addEventListener("message", function (event) {
        if (event.source !== window.parent) {
            return;
        }

        const message = parseMessage(event.data);

        if (!message || message.jsonrpc !== jsonRpcVersion) {
            return;
        }

        if (handlePendingResponse(message)) {
            return;
        }

        if (message.id === undefined) {
            handleNotification(message);
            return;
        }

        handleIncomingRequest(message);
    });

    window.createMcpApp = async function createMcpApp(setup) {
        const initializeResult = await request("ui/initialize", {
            protocolVersion: protocolVersion,
            appInfo: {
                name: document.title || "MCP App",
                version: "1.0.0",
            },
            appCapabilities: {},
        });

        state.hostInfo = initializeResult?.hostInfo ?? null;
        state.hostCapabilities = initializeResult?.hostCapabilities ?? null;
        applyHostContext(initializeResult?.hostContext ?? null);

        notify("ui/notifications/initialized");

        function callTool(nameOrParams, args) {
            const params = isObject(nameOrParams)
                ? {
                      name: nameOrParams.name,
                      arguments: nameOrParams.arguments || {},
                  }
                : {
                      name: nameOrParams,
                      arguments: args || {},
                  };

            return request("tools/call", params);
        }

        function listResources(cursorOrParams) {
            if (isObject(cursorOrParams)) {
                return request("resources/list", cursorOrParams);
            }

            return request(
                "resources/list",
                cursorOrParams ? { cursor: cursorOrParams } : undefined,
            );
        }

        function readResource(uriOrParams) {
            const params = isObject(uriOrParams)
                ? uriOrParams
                : { uri: uriOrParams };

            return request("resources/read", params);
        }

        function sendMessage(messageOrContent, role) {
            const params =
                isObject(messageOrContent) &&
                ("content" in messageOrContent || "role" in messageOrContent)
                    ? {
                          role: messageOrContent.role || "user",
                          content: messageOrContent.content,
                      }
                    : {
                          role: role || "user",
                          content: messageOrContent,
                      };

            return request("ui/message", params);
        }

        function openLink(urlOrParams) {
            const params = isObject(urlOrParams)
                ? urlOrParams
                : { url: urlOrParams };

            return request("ui/open-link", params);
        }

        function downloadFile(contentsOrParams) {
            const params =
                isObject(contentsOrParams) && "contents" in contentsOrParams
                    ? contentsOrParams
                    : { contents: contentsOrParams };

            return request("ui/download-file", params);
        }

        function requestDisplayMode(modeOrParams) {
            const params = isObject(modeOrParams)
                ? modeOrParams
                : { mode: modeOrParams };

            return request("ui/request-display-mode", params);
        }

        function updateModelContext(params) {
            return request("ui/update-model-context", params || {});
        }

        function requestTeardown() {
            notify("ui/notifications/request-teardown");
        }

        function sendLog(levelOrParams, data, logger) {
            const params = isObject(levelOrParams)
                ? levelOrParams
                : {
                      level: levelOrParams,
                      data: data,
                  };

            if (!isObject(levelOrParams) && logger !== undefined) {
                params.logger = logger;
            }

            notify("notifications/message", params);

            return Promise.resolve();
        }

        await setup({
            hostContext: function () {
                return state.hostContext;
            },
            getHostContext: function () {
                return state.hostContext;
            },
            hostInfo: function () {
                return state.hostInfo;
            },
            getHostInfo: function () {
                return state.hostInfo;
            },
            hostCapabilities: function () {
                return state.hostCapabilities;
            },
            getHostCapabilities: function () {
                return state.hostCapabilities;
            },
            callTool: callTool,
            callServerTool: callTool,
            listResources: listResources,
            readResource: readResource,
            sendMessage: sendMessage,
            openLink: openLink,
            downloadFile: downloadFile,
            requestDisplayMode: requestDisplayMode,
            updateModelContext: updateModelContext,
            requestTeardown: requestTeardown,
            teardown: requestTeardown,
            sendLog: sendLog,
            resize: notifySizeChanged,
            autoResize: autoResize,
            onTeardown: function (callback) {
                handlers.onteardown = callback;
            },
            onCallTool: function (callback) {
                handlers.oncalltool = callback;
            },
            onListTools: function (callback) {
                handlers.onlisttools = callback;
            },
            onToolInput: function (callback) {
                setHandler("ontoolinput", callback);
            },
            onToolInputPartial: function (callback) {
                setHandler("ontoolinputpartial", callback);
            },
            onToolResult: function (callback) {
                setHandler("ontoolresult", callback);
            },
            onToolCancelled: function (callback) {
                setHandler("ontoolcancelled", callback);
            },
            onHostContextChanged: function (callback) {
                setHandler("onhostcontextchanged", callback);
            },
        });
    };
})();
