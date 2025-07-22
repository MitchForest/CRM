<?php
declare(strict_types=1);

namespace Api\Repository;

abstract class BaseRepository
{
    protected string $moduleName;
    protected \SugarBean $bean;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
        $this->bean = \BeanFactory::newBean($moduleName);
    }

    /**
     * Find record by ID
     * 
     * @param string $id
     * @return array|null
     */
    public function find(string $id): ?array
    {
        $bean = \BeanFactory::getBean($this->moduleName, $id);
        
        if (empty($bean->id)) {
            return null;
        }

        return $this->beanToArray($bean);
    }

    /**
     * Find records by criteria
     * 
     * @param array $criteria
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @return array
     */
    public function findBy(array $criteria = [], int $limit = 20, int $offset = 0, string $orderBy = 'date_entered DESC'): array
    {
        $where = $this->buildWhereClause($criteria);
        
        $query = $this->bean->create_new_list_query(
            $orderBy,
            $where,
            [],
            [],
            0,
            '',
            true,
            $this->bean,
            true
        );

        // Get total count
        $countResult = $this->bean->db->query("SELECT COUNT(*) as total FROM ($query) as cnt");
        $total = (int) $this->bean->db->fetchByAssoc($countResult)['total'];

        // Add pagination
        $query .= " LIMIT $limit OFFSET $offset";

        // Execute query
        $result = $this->bean->db->query($query);
        $records = [];

        while ($row = $this->bean->db->fetchByAssoc($result)) {
            $bean = \BeanFactory::newBean($this->moduleName);
            $bean->populateFromRow($row);
            $records[] = $this->beanToArray($bean);
        }

        return [
            'data' => $records,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Save record
     * 
     * @param array $data
     * @param string|null $id
     * @return string
     */
    public function save(array $data, ?string $id = null): string
    {
        if ($id) {
            $bean = \BeanFactory::getBean($this->moduleName, $id);
            if (empty($bean->id)) {
                throw new \Exception('Record not found');
            }
        } else {
            $bean = \BeanFactory::newBean($this->moduleName);
        }

        // Set fields
        foreach ($data as $field => $value) {
            if (isset($bean->field_defs[$field])) {
                $bean->$field = $value;
            }
        }

        $bean->save();
        return $bean->id;
    }

    /**
     * Delete record
     * 
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        $bean = \BeanFactory::getBean($this->moduleName, $id);
        
        if (empty($bean->id)) {
            return false;
        }

        $bean->mark_deleted($id);
        return true;
    }

    /**
     * Build WHERE clause from criteria
     * 
     * @param array $criteria
     * @return string
     */
    protected function buildWhereClause(array $criteria): string
    {
        $where = [];
        $where[] = "{$this->bean->table_name}.deleted = 0";

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                if (isset($value['operator']) && isset($value['value'])) {
                    switch ($value['operator']) {
                        case 'like':
                            $where[] = "{$this->bean->table_name}.$field LIKE '%" . $this->bean->db->quote($value['value']) . "%'";
                            break;
                        case 'in':
                            $values = array_map([$this->bean->db, 'quote'], $value['value']);
                            $where[] = "{$this->bean->table_name}.$field IN ('" . implode("','", $values) . "')";
                            break;
                        case '>':
                        case '<':
                        case '>=':
                        case '<=':
                        case '!=':
                            $where[] = "{$this->bean->table_name}.$field {$value['operator']} '" . $this->bean->db->quote($value['value']) . "'";
                            break;
                    }
                }
            } else {
                $where[] = "{$this->bean->table_name}.$field = '" . $this->bean->db->quote($value) . "'";
            }
        }

        return implode(' AND ', $where);
    }

    /**
     * Convert SugarBean to array
     * 
     * @param \SugarBean $bean
     * @return array
     */
    protected function beanToArray(\SugarBean $bean): array
    {
        $data = [];
        
        foreach ($bean->field_defs as $field => $def) {
            if (isset($bean->$field)) {
                $data[$field] = $bean->$field;
            }
        }

        return $data;
    }
}