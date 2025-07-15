<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Resource;

class RecentMeetingRecordingResource extends Resource
{
    public function description(): string
    {
        return 'The most recent meeting recording';
    }

    public function read(): string
    {
        return "This is a test resource.\0dummy-binary-data";
    }

    public function uri(): string
    {
        return 'file://resources/recent-meeting-recording.mp4';
    }

    public function mimeType(): string
    {
        return 'video/mp4';
    }
}
