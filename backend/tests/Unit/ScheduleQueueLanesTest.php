<?php

namespace App\Tests\Unit;

use App\Schedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class ScheduleQueueLanesTest extends TestCase
{
    public function testScheduledWorkIsRedispatchedRatherThanRunningInTheScheduler(): void
    {
        $messages = (new Schedule(new ArrayAdapter()))->getSchedule()->getRecurringMessages();
        $lanes = [];
        foreach ($messages as $recurring) {
            $context = new MessageContext('default', $recurring->getId(), $recurring->getTrigger(), new \DateTimeImmutable());
            foreach ($recurring->getMessages($context) as $message) {
                self::assertInstanceOf(RedispatchMessage::class, $message);
                $lanes[] = $message->transportNames;
            }
        }
        self::assertSame([['async'], ['control'], ['control'], ['stock']], $lanes);
    }
}
