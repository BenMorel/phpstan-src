<?php declare(strict_types = 1);

namespace PHPStan\Rules\Classes;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Internal\SprintfHelper;
use PHPStan\Node\VariableWritesNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function count;
use function is_string;
use function sprintf;

/**
 * @implements Rule<VariableWritesNode>
 */
#[RegisteredRule(level: 1)]
final class UnusedConstructorParametersRule implements Rule
{

	public function __construct(
		#[AutowiredParameter(ref: '%featureToggles.reportPreciseLineForUnusedFunctionParameter%')]
		private bool $reportExactLine,
	)
	{
	}

	public function getNodeType(): string
	{
		return VariableWritesNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$originalNode = $node->getFunctionLike();
		if (!$originalNode instanceof Node\Stmt\ClassMethod) {
			return [];
		}
		if ($originalNode->name->toLowerString() !== '__construct' || $originalNode->stmts === null) {
			return [];
		}
		if (count($originalNode->params) === 0) {
			return [];
		}
		if ($node->isOpaque()) {
			return [];
		}
		if (!$scope->isInClass()) {
			return [];
		}

		$classReflection = $scope->getClassReflection();
		if ($classReflection->isAttributeClass()) {
			return [];
		}

		foreach ($classReflection->getInterfaces() as $interface) {
			if ($interface->hasConstructor()) {
				return [];
			}
		}

		$message = sprintf(
			'Constructor of class %s has an unused parameter $%%s.',
			SprintfHelper::escapeFormatString($classReflection->getDisplayName()),
		);
		if ($classReflection->isAnonymous()) {
			$message = 'Constructor of an anonymous class has an unused parameter $%s.';
		}

		$errors = [];
		foreach ($originalNode->params as $parameter) {
			if ($parameter->flags !== 0) {
				continue;
			}
			if (!$parameter->var instanceof Variable || !is_string($parameter->var->name)) {
				continue;
			}
			$write = $node->getWriteForNode($parameter->var);
			if ($write !== null) {
				// the parameter binds a value - it is unused unless that value
				// is read on some path (overwriting it first is not a use);
				// func_get_args() observes the original values of all parameters
				if ($node->isRead($write) || $node->areAllVariableNamesReferenced()) {
					continue;
				}
			} elseif ($node->isVariableReferenced($parameter->var->name)) {
				// a by-ref parameter gives the caller the variable - any mention counts
				continue;
			}

			$errorBuilder = RuleErrorBuilder::message(sprintf($message, $parameter->var->name))
				->identifier('constructor.unusedParameter');
			if ($this->reportExactLine) {
				$errorBuilder->line($parameter->var->getStartLine());
			}
			$errors[] = $errorBuilder->build();
		}

		return $errors;
	}

}
