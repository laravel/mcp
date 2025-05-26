<?php

namespace Laravel\Mcp\Support;

class Stdio
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

    public function write(string $message)
    {
        fwrite($this->outputStream, $message . PHP_EOL);
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
