<?php

namespace Laravel\Mcp\Tests;

use Laravel\Mcp\Support\Stdio;

trait CapturesStandardOutput
{
    private function captureStandardOutput(string $input, callable $callback)
    {
        $inputStream = $this->getFakeInputStream($input);
        $outputStream = $this->getFakeOutputStream();

        $stdio = new Stdio($inputStream, $outputStream);

        $this->app->instance(Stdio::class, $stdio);

        $callback();

        $rawOutput = $this->getRawOutput($outputStream);

        $this->closeStreams($inputStream, $outputStream);

        return json_decode(trim($rawOutput), true);
    }

    private function getFakeInputStream(string $input)
    {
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $input);
        rewind($inputStream);

        return $inputStream;
    }

    private function getFakeOutputStream()
    {
        return fopen('php://memory', 'r+');
    }

    private function getRawOutput($outputStream)
    {
        rewind($outputStream);

        return stream_get_contents($outputStream);
    }

    private function closeStreams($inputStream, $outputStream)
    {
        fclose($inputStream);
        fclose($outputStream);
    }
}
