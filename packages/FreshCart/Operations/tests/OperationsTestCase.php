<?php

namespace FreshCart\Operations\Tests;

use FreshCart\Operations\Tests\Concerns\OperationsTestBench;
use Tests\TestCase;
use Webkul\Admin\Tests\Concerns\AdminTestBench;
use Webkul\Core\Tests\Concerns\CoreAssertions;

class OperationsTestCase extends TestCase
{
    use AdminTestBench, CoreAssertions, OperationsTestBench;
}
