<?php

/**
 * This file is part of the jordanbrauer/unit-converter PHP package.
 *
 * @copyright 2017 Jordan Brauer <jbrauer.inc@gmail.com>
 * @license MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types = 1);

namespace UnitConverter\Unit\Mass;

/**
 * USShortTon data class.
 *
 * @version 1.0.0
 * @since 0.3.9
 * @author Teun Willems
 */
class USShortTon extends MassUnit
{
    protected function configure (): void
    {
        $this
            ->setName("US Short Ton")

            ->setSymbol("ust")

            ->setUnits(2.204623)
            ;
    }
}