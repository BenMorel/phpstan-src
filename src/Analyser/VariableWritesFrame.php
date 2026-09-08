<?php declare(strict_types = 1);

namespace PHPStan\Analyser;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Node\Variable\VariableWrite;
use PHPStan\Node\VariableWritesNode;
use function array_values;
use function in_array;
use function is_string;
use function spl_object_id;

/**
 * Write sites of one function-like body, which of them have been read, and
 * which variable names the body mentions at all.
 *
 * Immutable and persistent: every transition returns a new instance, or $this
 * when nothing changes, so the hot read path allocates nothing. The engine
 * (NodeScopeResolver) holds the current frame and swaps it after a transition.
 *
 * A write site is identified by its target Variable node, so re-walks of the
 * same body (loop convergence, closure passes) map onto the same write.
 *
 * @internal
 */
final class VariableWritesFrame
{

	/**
	 * @param array<int, VariableWrite> $writes id => write
	 * @param array<int, int> $idsByNode spl_object_id(target node) => id
	 * @param array<string, list<int>> $idsByName
	 * @param array<int, true> $readIds
	 * @param array<int, true> $redundantIds
	 * @param array<string, true> $referencedNames
	 * @param array<string, true> $untrackedNames
	 */
	private function __construct(
		private array $writes,
		private array $idsByNode,
		private array $idsByName,
		private array $readIds,
		private array $redundantIds,
		private array $referencedNames,
		private array $untrackedNames,
		private bool $opaque,
		private bool $allNamesReferenced,
		private bool $returnsByReference,
	)
	{
	}

	public static function create(bool $returnsByReference): self
	{
		return new self([], [], [], [], [], [], [], false, false, $returnsByReference);
	}

	/**
	 * The body mentions the variable: a read, a write target, or a statement
	 * naming it (global, static, a reference alias).
	 */
	public function withReferenced(string $name): self
	{
		if (
			$name === 'this'
			|| isset($this->referencedNames[$name])
			|| in_array($name, Scope::SUPERGLOBAL_VARIABLES, true)
		) {
			return $this;
		}
		$referencedNames = $this->referencedNames;
		$referencedNames[$name] = true;

		return new self($this->writes, $this->idsByNode, $this->idsByName, $this->readIds, $this->redundantIds, $referencedNames, $this->untrackedNames, $this->opaque, $this->allNamesReferenced, $this->returnsByReference);
	}

	/**
	 * A construct that can observe every variable by name without reading its
	 * current value (func_get_args()).
	 */
	public function withAllNamesReferenced(): self
	{
		if ($this->allNamesReferenced) {
			return $this;
		}

		return new self($this->writes, $this->idsByNode, $this->idsByName, $this->readIds, $this->redundantIds, $this->referencedNames, $this->untrackedNames, $this->opaque, true, $this->returnsByReference);
	}

	/**
	 * @param VariableWrite::KIND_* $kind
	 */
	public function withWrite(Expr\Variable $variable, int $kind, int $id): self
	{
		if (!is_string($variable->name)) {
			return $this;
		}
		$name = $variable->name;
		if (
			$name === 'this'
			|| in_array($name, Scope::SUPERGLOBAL_VARIABLES, true)
			|| isset($this->untrackedNames[$name])
		) {
			return $this;
		}
		$nodeId = spl_object_id($variable);
		if (isset($this->idsByNode[$nodeId])) {
			return $this;
		}

		$writes = $this->writes;
		$writes[$id] = new VariableWrite($name, $variable, $id, $kind);
		$idsByNode = $this->idsByNode;
		$idsByNode[$nodeId] = $id;
		$idsByName = $this->idsByName;
		$idsByName[$name][] = $id;

		return new self($writes, $idsByNode, $idsByName, $this->readIds, $this->redundantIds, $this->referencedNames, $this->untrackedNames, $this->opaque, $this->allNamesReferenced, $this->returnsByReference);
	}

	public function getWrite(Expr\Variable $variable): ?VariableWrite
	{
		$id = $this->idsByNode[spl_object_id($variable)] ?? null;
		if ($id === null) {
			return null;
		}

		return $this->writes[$id];
	}

	/**
	 * Markers of every write of the variable registered so far - the set a new
	 * write of the same variable kills.
	 *
	 * @return list<Expr>
	 */
	public function getMarkerExprsForName(string $name): array
	{
		$exprs = [];
		foreach ($this->idsByName[$name] ?? [] as $id) {
			$exprs[] = $this->writes[$id]->getMarkerExpr();
		}

		return $exprs;
	}

	/**
	 * Records a read of the variable: every unread write whose marker still
	 * reaches $scope has now been read.
	 */
	public function withReadsFor(string $name, MutatingScope $scope): self
	{
		$self = $this->withReferenced($name);
		$ids = $self->idsByName[$name] ?? null;
		if ($ids === null) {
			return $self;
		}

		return $self->withReadsOf($ids, $scope);
	}

	/**
	 * Records a read of every variable (get_defined_vars(), include, eval, $$name).
	 */
	public function withAllReachingRead(MutatingScope $scope): self
	{
		$self = $this->withAllNamesReferenced();
		$ids = [];
		foreach ($self->idsByName as $nameIds) {
			foreach ($nameIds as $id) {
				$ids[] = $id;
			}
		}

		return $self->withReadsOf($ids, $scope);
	}

	/**
	 * @param list<int> $ids
	 */
	private function withReadsOf(array $ids, MutatingScope $scope): self
	{
		$readIds = null;
		foreach ($ids as $id) {
			if (isset($this->readIds[$id])) {
				continue;
			}
			if ($scope->hasExpressionType($this->writes[$id]->getMarkerExpr())->no()) {
				continue;
			}
			if ($readIds === null) {
				$readIds = $this->readIds;
			}
			$readIds[$id] = true;
		}
		if ($readIds === null) {
			return $this;
		}

		return new self($this->writes, $this->idsByNode, $this->idsByName, $readIds, $this->redundantIds, $this->referencedNames, $this->untrackedNames, $this->opaque, $this->allNamesReferenced, $this->returnsByReference);
	}

	/**
	 * A write that assigns the value the variable provably already has - see
	 * AssignHandler. Re-evaluated on every convergence pass of a loop or a
	 * closure; the last pass, walked on the converged scope, wins.
	 */
	public function withRedundancy(VariableWrite $write, bool $redundant): self
	{
		$id = $write->getId();
		if ($redundant === isset($this->redundantIds[$id])) {
			return $this;
		}
		$redundantIds = $this->redundantIds;
		if ($redundant) {
			$redundantIds[$id] = true;
		} else {
			unset($redundantIds[$id]);
		}

		return new self($this->writes, $this->idsByNode, $this->idsByName, $this->readIds, $redundantIds, $this->referencedNames, $this->untrackedNames, $this->opaque, $this->allNamesReferenced, $this->returnsByReference);
	}

	public function withUntracked(string $name): self
	{
		if (isset($this->untrackedNames[$name])) {
			return $this;
		}
		$untrackedNames = $this->untrackedNames;
		$untrackedNames[$name] = true;

		return new self($this->writes, $this->idsByNode, $this->idsByName, $this->readIds, $this->redundantIds, $this->referencedNames, $untrackedNames, $this->opaque, $this->allNamesReferenced, $this->returnsByReference);
	}

	/**
	 * Whether the function-like returns by reference - a returned variable is
	 * then aliased to the caller.
	 */
	public function returnsByReference(): bool
	{
		return $this->returnsByReference;
	}

	public function withOpaque(): self
	{
		if ($this->opaque) {
			return $this;
		}

		return new self($this->writes, $this->idsByNode, $this->idsByName, $this->readIds, $this->redundantIds, $this->referencedNames, $this->untrackedNames, true, $this->allNamesReferenced, $this->returnsByReference);
	}

	/**
	 * @return list<VariableWrite>
	 */
	public function getWrites(): array
	{
		return array_values($this->writes);
	}

	public function createNode(Node\FunctionLike $functionLike): VariableWritesNode
	{
		return new VariableWritesNode($functionLike, $this->getWrites(), $this->readIds, $this->redundantIds, $this->referencedNames, $this->untrackedNames, $this->opaque, $this->allNamesReferenced);
	}

}
