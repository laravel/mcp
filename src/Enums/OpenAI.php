<?php

namespace Laravel\Mcp\Enums;

enum OpenAI: string
{
    case OUTPUT_TEMPLATE = 'openai/outputTemplate';
    case WIDGET_ACCESSIBLE = 'openai/widgetAccessible';
    case TOOL_INVOKING = 'openai/toolInvocation/invoking';
    case TOOL_INVOKED = 'openai/toolInvocation/invoked';

    case WIDGET_DESCRIPTION = 'openai/widgetDescription';
    case WIDGET_PREFERS_BORDER = 'openai/widgetPrefersBorder';
    case WIDGET_CSP = 'openai/widgetCSP';
    case WIDGET_DOMAIN = 'openai/widgetDomain';

    case LOCALE = 'openai/locale';
    case USER_AGENT = 'openai/userAgent';
    case USER_LOCATION = 'openai/userLocation';

    case MIME_TYPE = 'text/html+skybridge';
}
