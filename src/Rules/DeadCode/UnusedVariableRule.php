<?php declare(strict_types = 1);

namespace PHPStan\Rules\DeadCode;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Variable\VariableWrite;
use PHPStan\Node\VariableWritesNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\VerbosityLevel;
use function in_array;
use function sprintf;
use function str_starts_with;

/**
 * @implements Rule<VariableWritesNode>
 */
final class UnusedVariableRule implements Rule
{

	public function __construct()
	{
	}

	public function getNodeType(): string
	{
		return VariableWritesNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($node->isOpaque()) {
			return [];
		}

		$errors = [];
		foreach ($node->getWrites() as $write) {
			$name = $write->getVariableName();
			if ($node->isUntracked($name)) {
				continue;
			}
			if (str_starts_with($name, '_')) {
				continue;
			}
			if (in_array($write->getKind(), [VariableWrite::KIND_PARAMETER, VariableWrite::KIND_CLOSURE_USE], true)) {
				// reported by UnusedConstructorParametersRule and UnusedClosureUsesRule
				continue;
			}
			if (
				$write->getKind() === VariableWrite::KIND_CATCH
				&& !$scope->getPhpVersion()->supportsNoncapturingCatches()->yes()
			) {
				continue;
			}

			if (!$node->isRead($write)) {
				$errors[] = RuleErrorBuilder::message($this->getMessage($write->getKind(), $name))
					->identifier('variable.unused')
					->line($write->getVariable()->getStartLine())
					->build();
				continue;
			}

			$redundantType = $node->getRedundantType($write);
			if ($redundantType === null) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(sprintf(
				'Variable $%s is assigned value %s but it already has that value.',
				$name,
				$redundantType->describe(VerbosityLevel::value()),
			))
				->identifier('variable.redundantAssignment')
				->line($write->getVariable()->getStartLine())
				->build();
		}

		return $errors;
	}

	/**
	 * @param VariableWrite::KIND_* $kind
	 */
	private function getMessage(int $kind, string $variableName): string
	{
		switch ($kind) {
			case VariableWrite::KIND_ASSIGN:
				return sprintf('Value assigned to variable $%s is never read.', $variableName);
		}

		throw new ShouldNotHappenException(sprintf('Unhandled variable write kind %d', $kind));
	}

}
