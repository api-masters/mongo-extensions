<?php

namespace MongoExtensions\SoftDeletable\Query\TreeWalker;

use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST\DeleteStatement;
use Doctrine\ORM\Query\AST\DeleteClause;
use Doctrine\ORM\Query\Exec\SingleTableDeleteUpdateExecutor;
use MongoExtensions\SoftDeletable\SoftDeletableListener;
use MongoExtensions\SoftDeletable\Query\TreeWalker\Exec\MultiTableDeleteExecutor;

/**
 * This SqlWalker is needed when you need to use a DELETE DQL query.
 * It will update the "deletedAt" field with the actual date, instead
 * of actually deleting it.
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class SoftDeletableWalker extends SqlWalker
{
    protected $conn;
    protected $platform;
    protected $listener;
    protected $configuration;
    protected $alias;
    protected $deletedAtField;
    protected $meta;

    /**
     * {@inheritDoc}
     */
    public function __construct($query, $parserResult, array $queryComponents)
    {
        parent::__construct($query, $parserResult, $queryComponents);

        $this->conn = $this->getConnection();
        $this->platform = $this->conn->getDatabasePlatform();
        $this->listener = $this->getSoftDeletableListener();
        $this->extractComponents($queryComponents);
    }

    /**
     * {@inheritDoc}
     */
    public function getExecutor($AST)
    {
        switch (true) {
            case ($AST instanceof DeleteStatement):
                $primaryClass = $this->getEntityManager()->getClassMetadata($AST->deleteClause->abstractSchemaName);

                return ($primaryClass->isInheritanceTypeJoined())
                    ? new MultiTableDeleteExecutor($AST, $this, $this->meta, $this->platform, $this->configuration)
                    : new SingleTableDeleteUpdateExecutor($AST, $this);
            default:
                throw new \MongoExtensions\Exception\UnexpectedValueException('SoftDeletable walker should be used only on delete statement');
        }
    }

    /**
     * Change a DELETE clause for an UPDATE clause
     *
     * @param DeleteClause $deleteClause
     *
     * @return string The SQL.
     */
    public function walkDeleteClause(DeleteClause $deleteClause)
    {
        $em = $this->getEntityManager();
        $class = $em->getClassMetadata($deleteClause->abstractSchemaName);
        $tableName = $class->getTableName();
        $this->setSQLTableAlias($tableName, $tableName, $deleteClause->aliasIdentificationVariable);
        $quotedTableName = $class->getQuotedTableName($this->platform);
        $quotedColumnName = $class->getQuotedColumnName($this->deletedAtField, $this->platform);

        $sql = 'UPDATE '.$quotedTableName.' SET '.$quotedColumnName.' = '.$this->platform->getCurrentTimestampSQL();

        return $sql;
    }

    /**
     * Get the currently used SoftDeletableListener
     *
     * @throws \MongoExtensions\Exception\RuntimeException - if listener is not found
     *
     * @return SoftDeletableListener
     */
    private function getSoftDeletableListener()
    {
        if (is_null($this->listener)) {
            $em = $this->getEntityManager();

            foreach ($em->getEventManager()->getListeners() as $event => $listeners) {
                foreach ($listeners as $hash => $listener) {
                    if ($listener instanceof SoftDeletableListener) {
                        $this->listener = $listener;
                        break;
                    }
                }
                if ($this->listener) {
                    break;
                }
            }

            if (is_null($this->listener)) {
                throw new \MongoExtensions\Exception\RuntimeException('The SoftDeletable listener could not be found.');
            }
        }

        return $this->listener;
    }

    /**
     * Search for components in the delete clause
     *
     * @param array $queryComponents
     *
     * @return void
     */
    private function extractComponents(array $queryComponents)
    {
        $em = $this->getEntityManager();

        foreach ($queryComponents as $alias => $comp) {
            if (!isset($comp['metadata'])) {
                continue;
            }
            $meta = $comp['metadata'];
            $config = $this->listener->getConfiguration($em, $meta->name);
            if ($config && isset($config['softDeleteable']) && $config['softDeleteable']) {
                $this->configuration = $config;
                $this->deletedAtField = $config['fieldName'];
                $this->meta = $meta;
            }
        }
    }
}
