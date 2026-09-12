<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Attributes\Argument;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

class ArgumentAttributeMeeting extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'argument_attribute_meetings';
}

class ArgumentAttributeServer extends Server
{
    protected array $tools = [
        ArgumentAttributeScalarTool::class,
        ArgumentAttributeMeetingTool::class,
    ];
}

class ArgumentAttributeScalarTool extends Tool
{
    public function handle(#[Argument('meeting_id')] $meetingId): string
    {
        return "Meeting ID: {$meetingId}";
    }
}

class ArgumentAttributeMeetingTool extends Tool
{
    public function handle(#[Argument('meeting_id')] ArgumentAttributeMeeting $meeting): string
    {
        return "Meeting: {$meeting->title}";
    }
}

beforeEach(function (): void {
    Schema::dropIfExists('argument_attribute_meetings');

    Schema::create('argument_attribute_meetings', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });
});

it('resolves scalar arguments by attribute key for tools', function (): void {
    ArgumentAttributeServer::tool(ArgumentAttributeScalarTool::class, [
        'meeting_id' => 'abc-123',
    ])->assertSee('Meeting ID: abc-123');
});

it('resolves eloquent models by attribute key for tools', function (): void {
    $meeting = ArgumentAttributeMeeting::query()->create([
        'title' => 'Weekly sync',
    ]);

    ArgumentAttributeServer::tool(ArgumentAttributeMeetingTool::class, [
        'meeting_id' => $meeting->id,
    ])->assertSee('Meeting: Weekly sync');
});

it('returns a not found error when the eloquent model cannot be resolved', function (): void {
    ArgumentAttributeServer::tool(ArgumentAttributeMeetingTool::class, [
        'meeting_id' => 99999,
    ])->assertHasErrors(['No query results for model [ArgumentAttributeMeeting] 99999']);
});
