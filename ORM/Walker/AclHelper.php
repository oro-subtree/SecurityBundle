<?php

namespace Oro\Bundle\SecurityBundle\ORM\Walker;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\AST\Subselect;
use Doctrine\ORM\Query\AST\RangeVariableDeclaration;
use Doctrine\ORM\Query\AST\Join;
use Doctrine\ORM\Query\AST\ConditionalPrimary;
use Doctrine\ORM\Query\AST\IdentificationVariableDeclaration;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\SecurityBundle\ORM\Walker\Condition\AclConditionStorage;
use Oro\Bundle\SecurityBundle\ORM\Walker\Condition\SubRequestAclConditionStorage;
use Oro\Bundle\SecurityBundle\ORM\Walker\Condition\AclCondition;
use Oro\Bundle\SecurityBundle\ORM\Walker\Condition\JoinAclCondition;
use Oro\Bundle\SecurityBundle\ORM\Walker\Condition\JoinAssociationCondition;
use Oro\Bundle\SecurityBundle\ORM\Walker\Condition\AccessDeniedCondition;

/**
 * Class ACLHelper
 * This class analyse input query for acl and mark it with ORO_ACL_WALKER if it need to be ACL protected.
 */
class AclHelper
{
    const ORO_ACL_WALKER = 'Oro\Bundle\SecurityBundle\ORM\Walker\AclWalker';

    /**
     * @var OwnershipConditionDataBuilder
     */
    protected $builder;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @param $builder
     */
    function __construct(OwnershipConditionDataBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Mark query as acl protected
     *
     * @param Query|QueryBuilder $query
     * @param string $permission
     *
     * @return Query
     */
    public function apply($query, $permission = "VIEW")
    {
        if ($query instanceof QueryBuilder) {
            $query = $query->getQuery();
        }
        /** @var Query $query */
        $this->em = $query->getEntityManager();

        $ast = $query->getAST();
        if ($ast instanceof SelectStatement) {
            list ($whereConditions, $joinConditions) = $this->processRequest($ast, $permission, $query);
            $conditionStorage = new AclConditionStorage($whereConditions, $joinConditions);
            if ($ast->whereClause) {
                $this->processSubRequests($ast, $conditionStorage, $permission, $query);
            }

            if (!$conditionStorage->isEmpty()) {
                $query->setHint(Query::HINT_CUSTOM_TREE_WALKERS,
                    array_merge(
                        $query->getHints(),
                        array(self::ORO_ACL_WALKER)
                    )
                );
                $query->setHint(AclWalker::ORO_ACL_CONDITION, $conditionStorage);
            }
        }

        return $query;
    }

    /**
     * @param SelectStatement $ast
     * @param AclConditionStorage $storage
     * @param $permission
     */
    protected function processSubRequests(SelectStatement $ast, AclConditionStorage $storage, $permission)
    {
        $conditionalExpression = $ast->whereClause->conditionalExpression;
        if ($conditionalExpression instanceof ConditionalPrimary) {
            $expression = $conditionalExpression->simpleConditionalExpression;
            if (isset($expression->subselect)
                && $expression->subselect instanceof Subselect
            ) {
                $subRequestAclStorage = $this->processSubRequest($expression->subselect, $permission);
                if (!$subRequestAclStorage->isEmpty()) {
                    $storage->setSubRequests($subRequestAclStorage);
                }
            }
        } else {
            $subQueryAcl = [];
            foreach ($conditionalExpression->conditionalFactors as $factorId => $expression) {
                if (isset($expression->simpleConditionalExpression->subselect)
                    && $expression->simpleConditionalExpression->subselect instanceof Subselect
                ) {
                    $subRequestAclStorage = $this->processSubRequest(
                        $expression->simpleConditionalExpression->subselect,
                        $permission
                    );
                    if (!$subRequestAclStorage->isEmpty()) {
                        $subRequestAclStorage->setFactorId($factorId);
                        $subQueryAcl[] = $subRequestAclStorage;
                    }
                }
            }
            if (!empty($subQueryAcl)) {
                $storage->setSubRequests($subQueryAcl);
            }
        }
    }

    /**
     * @param Subselect $subSelect
     * @param $permission
     * @return SubRequestAclConditionStorage
     */
    protected function processSubRequest(Subselect $subSelect, $permission)
    {
        list ($whereConditions, $joinConditions) = $this->processRequest($subSelect, $permission);
        return new SubRequestAclConditionStorage($whereConditions, $joinConditions);
    }

    /**
     * @param Subselect|SelectStatement $select
     * @param string $permission
     * @return array
     */
    protected function processRequest($select, $permission)
    {
        if ($select instanceof SelectStatement) {
            $isSubRequest = false;
        } else {
            $isSubRequest = true;
        }
        $whereConditions = [];
        $joinConditions = [];
        $fromClause = $isSubRequest ? $select->subselectFromClause : $select->fromClause;

        foreach ($fromClause->identificationVariableDeclarations as $fromKey => $identificationVariableDeclaration) {
            $condition = $this->processRangeVariableDeclaration(
                $identificationVariableDeclaration->rangeVariableDeclaration,
                $permission
            );
            if ($condition) {
                $whereConditions[] = $condition;
            }

            // check joins
            if (!empty($identificationVariableDeclaration->joins)) {
                /** @var $join Join */
                foreach ($identificationVariableDeclaration->joins as $joinKey => $join) {
                    if ($join->joinAssociationDeclaration instanceof RangeVariableDeclaration) {
                        $condition = $this->processRangeVariableDeclaration(
                            $join->joinAssociationDeclaration,
                            $permission,
                            true
                        );
                    } else {
                        $condition = $this->processJoinAssociationPathExpression(
                            $identificationVariableDeclaration, $joinKey, $permission
                        );
                    }
                    if ($condition) {
                        $condition->setFromKey($fromKey);
                        $condition->setJoinKey($joinKey);
                        $joinConditions[] = $condition;
                    }
                }
            }
        }

        return array($whereConditions, $joinConditions);
    }

    /**
     * @param IdentificationVariableDeclaration $declaration
     * @param $key
     * @param $permission
     * @return JoinAssociationCondition
     */
    protected function processJoinAssociationPathExpression(IdentificationVariableDeclaration $declaration, $key,  $permission)
    {
        $metadata = $this->em->getClassMetadata($declaration->rangeVariableDeclaration->abstractSchemaName);
        /** @var Join $join */
        $join = $declaration->joins[$key];
        $fieldName = $join->joinAssociationDeclaration->joinAssociationPathExpression->associationField;

        $associationMapping = $metadata->getAssociationMapping($fieldName);
        $targetEntity = $associationMapping['targetEntity'];

        $resultData = $this->builder->getAclConditionData($targetEntity, $permission);

        if ($resultData && is_array($resultData)) {
            list($entityField, $value) = $resultData;

            return new JoinAssociationCondition(
                $join->joinAssociationDeclaration->aliasIdentificationVariable,
                $entityField,
                $value,
                $targetEntity,
                $associationMapping['joinColumns']
            );
        } elseif ($resultData === false) {
            return new AccessDeniedCondition(
                $join->joinAssociationDeclaration->aliasIdentificationVariable,
                $targetEntity
            );
        }
    }

    /**
     * @param RangeVariableDeclaration $rangeVariableDeclaration
     * @param $permission
     * @param bool $isJoin
     * @return null|AclCondition|JoinAclCondition
     */
    protected function processRangeVariableDeclaration(
        RangeVariableDeclaration $rangeVariableDeclaration,
        $permission,
        $isJoin = false
    ) {
        $entityName = $rangeVariableDeclaration->abstractSchemaName;
        $entityAlias = $rangeVariableDeclaration->aliasIdentificationVariable;

        $resultData = $this->builder->getAclConditionData($entityName, $permission);

        if ($resultData && is_array($resultData)) {
            list($entityField, $value) = $resultData;
            if ($isJoin) {

                return new JoinAclCondition(
                    $entityAlias, $entityField, $value
                );
            } else {

                return new AclCondition(
                    $entityAlias, $entityField, $value
                );
            }
        } elseif ($resultData === false) {
            return new AccessDeniedCondition(
                $entityAlias
            );
        }

        return null;
    }
}