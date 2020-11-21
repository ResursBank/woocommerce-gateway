<?php

namespace TorneLIB;

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
	die();
}

require_once(__DIR__ . '/../vendor/autoload.php');

use PHPUnit\Framework\TestCase;
use TorneLIB\Config\Flag;

class flagTests extends TestCase
{
	/**
	 * @test
	 * @throws \Exception
	 */
	public function setStaticFlagKey()
	{
		$flagStatus = Flag::setFlag('firstFlag', 'present');

		static::assertTrue((
		$flagStatus ? true : false &&
			Flag::getFlag('firstFlag') === 'present'
		));
	}

	/**
	 * @test
	 */
	public function setStaticTrueFlag()
	{
		Flag::setFlag('secondFlag', true);
		static::assertTrue(Flag::getFlag('secondFlag'));
	}

	/**
	 * @test
	 */
	public function setStaticTrueWithoutKey()
	{
		Flag::setFlag('thirdFlag');
		static::assertTrue(Flag::isFlag('thirdFlag'));
	}

	public function hasFlags()
	{
		Flag::setFlag('existingFlag', 'yes');

		static::assertTrue(
			Flag::hasFlag('existingFlag') === 'yes' &&
			!Flag::hasFlag('unExistingFlag')
		);
	}

	/**
	 * @test
	 */
	public function getAll()
	{
		Flag::setFlag('firstFlag', 'present');
		Flag::setFlag('secondFlag', true);
		Flag::setFlag('thirdFlag');

		static::assertCount(3, Flag::getAllFlags());
	}

	/**
	 * @test
	 */
	public function clearAll()
	{
		Flag::setFlag('firstFlag', 'present');
		Flag::setFlag('secondFlag', true);
		Flag::setFlag('thirdFlag');
		Flag::clearAllFlags();

		static::assertCount(0, Flag::getAllFlags());
	}

	/**
	 * @test
	 */
	public function clearOne()
	{
		Flag::setFlag('firstFlag', 'present');
		Flag::setFlag('secondFlag', true);
		Flag::setFlag('thirdFlag');
		Flag::removeFlag('secondFlag');

		static::assertCount(2, Flag::getAllFlags());
		Flag::clearAllFlags();
	}

	/**
	 * @test
	 * @throws \Exception
	 */
	public function manyFlags()
	{
		Flag::setFlags([
			'flag1' => 'yes',
			'flag2' => 'current',
			'flag3' => 'present'
		]);

		static::assertCount(3, Flag::getAllFlags());
	}

	/**
	 * @test
	 * @testdox Using the non static class (which actually calls for the static one).
	 * @throws \Exception
	 */
	public function setNonStaticFlagKey()
	{
		$flag = new Flags();
		$flagStatus = $flag->setFlag('firstFlag', 'present');
		static::assertTrue(
			(
			$flagStatus ? true : false &&
				$flag->getFlag('firstFlag') === 'present'
			)
		);
	}

	/**
	 * @test
	 */
	public function setStaticFlagInside()
	{
		Flags::_setFlag('this_is', 'static');
		static::assertTrue(Flags::_getFlag('this_is') === 'static');
	}
}
