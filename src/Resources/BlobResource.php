<?php

namespace Laravel\Mcp\Resources;

abstract class BlobResource extends Resource
{
    public function handle(): ResourceResult
    {
        return new BlobResourceResult($this);
    }
}
