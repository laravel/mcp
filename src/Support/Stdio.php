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
