<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;

enum ReservationStep: string
{
    case Starting = 'starting';
}

class RestaurantT extends Server
{
    protected array $tools = [
        ReservationTool::class,
        SerializableNotificationTool::class,
        ReservationProgressTool::class,
    ];
}

class ReservationProgressTool extends Tool
{
    public function handle(): Generator
    {
        yield Response::progress(1, total: 2, message: 'Checking tables');

        yield Response::progress(2);

        yield 'Your booking is confirmed!';
    }
}

class SerializableNotificationTool extends Tool
{
    public function handle(): Generator
    {
        yield Response::notification('booking/step', ['step' => ReservationStep::Starting]);

        yield Response::notification('booking/empty', []);

        yield 'Your booking is confirmed!';
    }
}

class ReservationTool extends Tool
{
    public function handle(Request $request): Generator
    {
        yield Response::notification('booking/starting', ['step' => 1]);

        $date = $request->date('date');

        if ($date?->isPast()) {
            yield Response::error('The booking date cannot be in the past.');
        }

        if ($date?->year === 2999) {
            yield [
                'You must be joking! That date is too far in the future.',
                'Please select a more reasonable date.',
            ];
        }

        yield Response::notification('booking/completed', ['step' => 2]);

        yield 'Your booking is confirmed!';
    }
}

it('may assert that text is seen when returning string content', function (): void {
    $response = RestaurantT::tool(ReservationTool::class);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert two notifications got sent', function (): void {
    $response = RestaurantT::tool(ReservationTool::class);

    $response->assertNotificationCount(2)
        ->assertSentNotification('booking/starting', ['step' => 1])
        ->assertSentNotification('booking/completed', ['step' => 2]);
});

it('may fail to assert the notification count is wrong', function (): void {
    $response = RestaurantT::tool(ReservationTool::class);

    $response->assertNotificationCount(3);
})->throws(ExpectationFailedException::class);

it('may fail to assert a notification that was not sent', function (): void {
    $response = RestaurantT::tool(ReservationTool::class);

    $response->assertSentNotification('booking/unknown');
})->throws(AssertionFailedError::class);

it('may fail to assert a notification that was sent with wrong params', function (): void {
    $response = RestaurantT::tool(ReservationTool::class);

    $response->assertSentNotification('booking/starting', ['step' => 2]);
})->throws(AssertionFailedError::class);

it('serializes notification params before asserting', function (): void {
    $response = RestaurantT::tool(SerializableNotificationTool::class);

    $response->assertSentNotification('booking/step', ['step' => ReservationStep::Starting])
        ->assertSentNotification('booking/step', ['step' => 'starting']);
});

it('may assert a notification that was sent with empty params', function (): void {
    $response = RestaurantT::tool(SerializableNotificationTool::class);

    $response->assertSentNotification('booking/empty', []);
});

it('may assert progress notifications got sent', function (): void {
    $response = RestaurantT::tool(ReservationProgressTool::class);

    $response->assertNotificationCount(2)
        ->assertSentProgress(1, 2, 'Checking tables')
        ->assertSentProgress(2);
});

it('may fail to assert progress that was not sent', function (array $expected): void {
    $response = RestaurantT::tool(ReservationProgressTool::class);

    $response->assertSentProgress(...$expected);
})->with([
    'wrong progress' => [[3]],
    'missing total' => [[1]],
    'wrong message' => [[1, 2, 'Seating guests']],
])->throws(AssertionFailedError::class);
