<?php

namespace Laravel\Mcp\Tests\Unit;

use Laravel\Mcp\Support\Stdio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StdioTest extends TestCase
{
    protected $inputStream;
    protected $outputStream;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->inputStream) {
            fclose($this->inputStream);
            $this->inputStream = null;
        }

        if ($this->outputStream) {
            fclose($this->outputStream);
            $this->outputStream = null;
        }
    }

    #[Test]
    public function it_defaults_to_global_stdin_and_stdout()
    {
        $stdio = new Stdio();
        $this->assertSame(STDIN, $stdio->getInputStream());
        $this->assertSame(STDOUT, $stdio->getOutputStream());
    }

    #[Test]
    public function it_uses_provided_streams()
    {
        $this->inputStream = fopen('php://memory', 'r+');
        $this->outputStream = fopen('php://memory', 'r+');

        $stdio = new Stdio($this->inputStream, $this->outputStream);

        $this->assertSame($this->inputStream, $stdio->getInputStream());
        $this->assertSame($this->outputStream, $stdio->getOutputStream());
    }

    #[Test]
    public function it_can_use_provided_input_stream_and_default_output_stream()
    {
        $this->inputStream = fopen('php://memory', 'r+');

        $stdio = new Stdio($this->inputStream, null);

        $this->assertSame($this->inputStream, $stdio->getInputStream());
        $this->assertSame(STDOUT, $stdio->getOutputStream());
    }

    #[Test]
    public function it_can_use_default_input_stream_and_provided_output_stream()
    {
        $this->outputStream = fopen('php://memory', 'r+');

        $stdio = new Stdio(null, $this->outputStream);

        $this->assertSame(STDIN, $stdio->getInputStream());
        $this->assertSame($this->outputStream, $stdio->getOutputStream());
    }

    #[Test]
    public function it_writes_to_output_stream()
    {
        $this->outputStream = fopen('php://memory', 'r+');
        $stdio = new Stdio(null, $this->outputStream);
        $message = 'Hello, world!';

        $stdio->write($message);

        rewind($this->outputStream);
        $this->assertSame($message . PHP_EOL, fgets($this->outputStream));
    }

    #[Test]
    public function it_reads_from_input_stream()
    {
        $this->inputStream = fopen('php://memory', 'r+');
        $stdio = new Stdio($this->inputStream);
        $message = 'Hello, world!' . PHP_EOL;

        fwrite($this->inputStream, $message);
        rewind($this->inputStream);

        $this->assertSame($message, $stdio->read());
    }
}
