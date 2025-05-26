<?php

namespace Laravel\Mcp\Support;

use Laravel\Mcp\Contracts\Stdio as StdioContract;

class Stdio implements StdioContract
{
    private $inputStream;
    private $outputStream;

    /**
     * Stdio constructor.
     *
     * @param resource|null $inputStream
     * @param resource|null $outputStream
     */
    public function __construct($inputStream = null, $outputStream = null)
    {
        $this->inputStream = $inputStream ?? STDIN;
        $this->outputStream = $outputStream ?? STDOUT;
    }

    public function write(string $output)
    {
        fwrite($this->outputStream, $output . PHP_EOL);
    }

    public function read()
    {
        return fgets($this->inputStream);
    }

    /**
     * @return resource
     */
    public function getInputStream()
    {
        return $this->inputStream;
    }

    /**
     * @return resource
     */
    public function getOutputStream()
    {
        return $this->outputStream;
    }
}
