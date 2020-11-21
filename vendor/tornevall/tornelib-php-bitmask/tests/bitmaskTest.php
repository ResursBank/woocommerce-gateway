<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once(__DIR__ . '/../vendor/autoload.php');
}

use PHPUnit\Framework\TestCase;
use TorneLIB\Module\Bit;

class bitmaskTest extends TestCase
{
    /**
     * @test
     * @testdox Test the structure of the bitmask class
     */
    function bitStructure()
    {
        $myBits = [
            'TEST1' => 1,
            'TEST2' => 2,
            'TEST4' => 4,
            'TEST8' => 8,
        ];
        $myBit = new Bit($myBits);
        $this->assertCount(9, $myBit->getBitStructure());
    }

    /**
     * @test
     * @testdox Test if one bit is on (1)
     */
    function bitActive()
    {
        $myBits = [
            'TEST1' => 1,
            'TEST2' => 2,
            'TEST4' => 4,
            'TEST8' => 8,
        ];
        $myBit = new Bit($myBits);
        $this->assertTrue($myBit->isBit(8, 12));
    }

    /**
     * @test
     * @testdox Test if one bit is off (0)
     */
    function bitNotActive()
    {
        $myBits = [
            'TEST1' => 1,
            'TEST2' => 2,
            'TEST4' => 4,
            'TEST8' => 8,
        ];
        $myBit = new Bit($myBits);
        $this->assertFalse($myBit->isBit(64, 12));
    }

    /**
     * @test
     * @testdox Test if multiple bits are active (muliple settings by bit)
     */
    function multiBitActive()
    {
        $myBits = [
            'TEST1' => 1,
            'TEST2' => 2,
            'TEST4' => 4,
            'TEST8' => 8,
        ];
        $myBit = new Bit($myBits);
        $this->assertTrue($myBit->isBit(([8, 2]), 14));
    }

    /**
     * @test
     * @testdox Test correct returning bits
     */
    function bitArray()
    {
        $myBit = new Bit();
        $bitArray = $myBit->getBitArray("88");      // 8 + 16 + 64
        $this->assertCount(3, $bitArray);
    }

    /**
     * @test
     * @testdox Test large setup of bits
     */
    function bitArray16()
    {
        $myBit = new Bit();
        $myBit->setMaxBits(16);
        $bitArray = $myBit->getBitArray((8 + 256 + 4096 + 8192 + 32768));
        $this->assertCount(5, $bitArray);
    }

    /**
     * @test
     * @testdox Test special bitmodes
     */
    function bitModes()
    {
        $myBit = [
            'DEBIT' => 1,
            'CREDIT' => 2,
            'ANNUL' => 4,
        ];
        $bitClass = new Bit($myBit);
        $bitArray = $bitClass->getBitArray(255);
        $this->assertTrue(
            in_array('DEBIT', $bitArray) &&
            in_array('CREDIT', $bitArray) &&
            in_array('ANNUL', $bitArray) &&
            in_array('BIT_128', $bitArray)
        );
    }
}
