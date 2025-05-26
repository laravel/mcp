<?php

namespace Laravel\Mcp\Support;

use Laravel\Mcp\Contracts\Stdio as StdioContract;

class FakeStdio implements StdioContract
{
    private array $input;
    private array $output;

    public function withInput(string $input)
    {
        $this->input[] = $input;
    }

    public function getOutput()
    {
        return array_pop($this->output);
    }

    public function write(string $output)
    {
        $this->output[] = $output;
    }

    public function read()
    {
        return array_shift($this->input);
    }
}
