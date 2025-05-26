<?php

namespace Laravel\Mcp\Tests\Unit;

use Laravel\Mcp\Support\Stdio;
use PHPUnit\Framework\TestCase;

class StdioTest extends TestCase
{
    public function test_it_defaults_to_global_stdin_and_stdout()
    {
        $stdio = new Stdio();
        $this->assertSame(STDIN, $stdio->getInputStream());
        $this->assertSame(STDOUT, $stdio->getOutputStream());
    }

    public function test_it_uses_provided_streams()
    {
        $inputStream = fopen('php://memory', 'r+');
        $outputStream = fopen('php://memory', 'r+');

        $stdio = new Stdio($inputStream, $outputStream);

        $this->assertSame($inputStream, $stdio->getInputStream());
        $this->assertSame($outputStream, $stdio->getOutputStream());

        // Important to close streams opened in tests
        fclose($inputStream);
        fclose($outputStream);
    }

    public function test_it_can_use_provided_input_stream_and_default_output_stream()
    {
        $inputStream = fopen('php://memory', 'r+');

        $stdio = new Stdio($inputStream, null);

        $this->assertSame($inputStream, $stdio->getInputStream());
        $this->assertSame(STDOUT, $stdio->getOutputStream());

        fclose($inputStream);
    }

    public function test_it_can_use_default_input_stream_and_provided_output_stream()
    {
        $outputStream = fopen('php://memory', 'r+');

        $stdio = new Stdio(null, $outputStream);

        $this->assertSame(STDIN, $stdio->getInputStream());
        $this->assertSame($outputStream, $stdio->getOutputStream());

        fclose($outputStream);
    }
}
