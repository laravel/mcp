<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation;

use Laravel\Mcp\Server\Elicitation\Fields\BooleanField;
use Laravel\Mcp\Server\Elicitation\Fields\EnumField;
use Laravel\Mcp\Server\Elicitation\Fields\IntegerField;
use Laravel\Mcp\Server\Elicitation\Fields\MultiEnumField;
use Laravel\Mcp\Server\Elicitation\Fields\NumberField;
use Laravel\Mcp\Server\Elicitation\Fields\StringField;

class ElicitSchema
{
    public function string(string $title): StringField
    {
        return new StringField($title);
    }

    public function number(string $title): NumberField
    {
        return new NumberField($title);
    }

    public function integer(string $title): IntegerField
    {
        return new IntegerField($title);
    }

    public function boolean(string $title): BooleanField
    {
        return new BooleanField($title);
    }

    /**
     * @param  array<int, string>  $options
     */
    public function enum(string $title, array $options): EnumField
    {
        return new EnumField($title, $options);
    }

    /**
     * @param  array<int, string>  $options
     */
    public function multiEnum(string $title, array $options): MultiEnumField
    {
        return new MultiEnumField($title, $options);
    }
}
