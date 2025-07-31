<?php

declare(strict_types=1);

namespace Brainbits\MonologSentryTests\EventListener;

use Nyholm\NSA;
use PHPUnit\Framework\MockObject\MockObject; // phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use Sentry\State\HubInterface;
use Sentry\State\Scope;

trait HubExpections
{
    private function expectHubIsNotConfigured(MockObject&HubInterface $hub): void
    {
        $hub->expects($this->never())
            ->method('configureScope');
    }

    private function expectHubIsConfigured(MockObject&HubInterface $hub): void
    {
        $hub->expects($this->atLeastOnce())
            ->method('configureScope');
    }

    private function expectHubIsConfiguredWithValues(
        MockObject&HubInterface $hub,
        string $propertyName,
        mixed $value,
    ): void {
        $hub->expects($this->atLeastOnce())
            ->method('configureScope')
            ->with($this->callback(function (callable $closure) use ($propertyName, $value) {
                $scope = new Scope();
                $closure($scope);

                $tagsContext = NSA::getProperty($scope, $propertyName);

                $this->assertEquals($value, $tagsContext);

                return true;
            }));
    }
}
