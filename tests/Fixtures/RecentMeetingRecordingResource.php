<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Resources\BlobResource;

class RecentMeetingRecordingResource extends BlobResource
{
    public function description(): string
    {
        return 'The most recent meeting recording';
    }

    public function read(): string
    {
        // Return dummy binary data for the video as a string (not base64-encoded here; encoding handled in ResourceResult).
        return 'dummy-binary-data';
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
