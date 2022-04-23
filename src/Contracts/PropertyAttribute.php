<?php

declare(strict_types=1);

/**
 * This file is part of the Max package.
 *
 * (c) Cheng Yao <987861463@qq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Max\Di\Contracts;

use ReflectionClass;
use ReflectionProperty;

interface PropertyAttribute
{
    public function handle(ReflectionClass $reflectionClass, ReflectionProperty $reflectionProperty, object $object): void;
}
