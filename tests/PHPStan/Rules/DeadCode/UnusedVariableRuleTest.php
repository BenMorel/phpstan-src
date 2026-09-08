<?php declare(strict_types = 1);

namespace PHPStan\Rules\DeadCode;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RequiresPhp;

/**
 * @extends RuleTestCase<UnusedVariableRule>
 */
class UnusedVariableRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new UnusedVariableRule();
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/unused-variable.php'], [
			[
				'Value assigned to variable $a is never read.',
				27,
			],
			[
				'Value assigned to variable $a is never read.',
				32,
			],
			[
				'Value assigned to variable $a is never read.',
				40,
			],
			[
				'Value assigned to variable $x is never read.',
				46,
			],
			[
				'Value assigned to variable $a is never read.',
				70,
			],
			[
				'Value assigned to variable $a is never read.',
				76,
			],
			[
				'Value assigned to variable $a is never read.',
				93,
			],
			[
				'Value assigned to variable $a is never read.',
				95,
			],
			[
				'Value assigned to variable $a is never read.',
				101,
			],
			[
				'Value assigned to variable $a is never read.',
				113,
			],
			[
				'Foreach key variable $k is never read.',
				119,
			],
			[
				'Foreach value variable $v is never read.',
				126,
			],
			[
				'Foreach value variable $v is never read.',
				133,
			],
			[
				'Value assigned to variable $a is never read.',
				148,
			],
			[
				'Value of variable $i after ++ is never read.',
				157,
			],
			[
				'Value assigned to variable $x is never read.',
				223,
			],
			[
				'Value assigned to variable $x is never read.',
				251,
			],
			[
				'Value assigned to variable $s is never read.',
				264,
			],
			[
				'Value assigned to variable $a is never read.',
				276,
			],
			[
				'Value assigned to variable $f is never read.',
				283,
			],
			[
				'Value assigned to variable $a is never read.',
				303,
			],
			[
				'Value assigned to variable $x is never read.',
				337,
			],
			[
				'Value assigned to variable $title is never read.',
				422,
			],
			[
				'Value assigned to variable $b is never read.',
				614,
			],
			[
				'Value assigned to variable $a is never read.',
				632,
			],
			[
				'Value assigned to variable $a is never read.',
				637,
			],
			[
				'Value assigned to variable $a is never read.',
				703,
			],
			[
				'Value assigned to variable $a is never read.',
				739,
			],
			[
				'Value assigned to variable $a is never read.',
				744,
			],
			[
				'Value assigned to variable $tags is never read.',
				840,
			],
			[
				'Value of variable $i after -- is never read.',
				864,
			],
		]);
	}

	#[RequiresPhp('>= 8.0.0')]
	public function testPhp8(): void
	{
		$this->analyse([__DIR__ . '/data/unused-variable-php8.php'], [
			[
				'Catch variable $e is never read.',
				23,
			],
			[
				'Value assigned to variable $nightsFrom is never read.',
				98,
			],
		]);
	}

	public function testBug12789(): void
	{
		$this->analyse([__DIR__ . '/data/bug-12789.php'], [
			[
				'Value assigned to variable $RetVal is never read.',
				12,
			],
		]);
	}

	public function testBug13472(): void
	{
		$this->analyse([__DIR__ . '/data/bug-13472.php'], [
			[
				'Value assigned to variable $v is never read.',
				14,
			],
			[
				'Foreach value variable $item is never read.',
				41,
			],
		]);
	}

	public function testBug14258(): void
	{
		$this->analyse([__DIR__ . '/data/bug-14258.php'], [
			[
				'Value assigned to variable $cutsomerId is never read.',
				15,
			],
		]);
	}

	public function testBug12012(): void
	{
		$this->analyse([__DIR__ . '/data/bug-12012.php'], [
			[
				'Value assigned to variable $s1 is never read.',
				10,
			],
			[
				'Value assigned to variable $s1 is never read.',
				12,
			],
		]);
	}

	public function testBug11483(): void
	{
		$this->analyse([__DIR__ . '/data/bug-11483.php'], [
			[
				'Value assigned to variable $hello is never read.',
				9,
			],
		]);
	}

	public function testBug10202(): void
	{
		$this->analyse([__DIR__ . '/data/bug-10202.php'], [
			[
				'Value assigned to variable $x is never read.',
				9,
			],
			[
				'Value assigned to variable $x is never read.',
				12,
			],
			[
				'Value assigned to variable $x is never read.',
				14,
			],
		]);
	}

	#[RequiresPhp('< 8.0.0')]
	public function testCatchVariableNotReportedBeforePhp80(): void
	{
		$this->analyse([__DIR__ . '/data/unused-variable-catch.php'], []);
	}

	#[RequiresPhp('>= 8.0.0')]
	public function testCatchVariableReportedSincePhp80(): void
	{
		$this->analyse([__DIR__ . '/data/unused-variable-catch.php'], [
			[
				'Catch variable $e is never read.',
				9,
			],
		]);
	}

	public function testRedundantAssignment(): void
	{
		$this->analyse([__DIR__ . '/data/unused-variable-redundant.php'], [
			[
				'Variable $x is assigned value true but it already has that value.',
				26,
			],
			[
				'Value assigned to variable $x is never read.',
				42,
			],
			[
				'Variable $x is assigned value 1 but it already has that value.',
				43,
			],
			[
				'Variable $x is assigned value null but it already has that value.',
				51,
			],
			[
				'Variable $s is assigned value \'a\' but it already has that value.',
				60,
			],
			[
				'Variable $a is assigned value array{k: 1} but it already has that value.',
				69,
			],
			[
				'Value assigned to variable $x is never read.',
				95,
			],
			[
				'Variable $x is assigned value 1 but it already has that value.',
				118,
			],
		]);
	}

	public function testByRefReturn(): void
	{
		$this->analyse([__DIR__ . '/data/unused-variable-by-ref-return.php'], [
			[
				'Value assigned to variable $x is never read.',
				26,
			],
			[
				'Value assigned to variable $unused is never read.',
				32,
			],
			[
				'Value assigned to variable $x is never read.',
				68,
			],
		]);
	}

	public function testDeadBranchWrites(): void
	{
		$this->analyse([__DIR__ . '/data/unused-variable-dead-branch.php'], [
			[
				'Value assigned to variable $x is never read.',
				79,
			],
		]);
	}

}
