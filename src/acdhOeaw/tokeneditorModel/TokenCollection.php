<?php

/**
 * The MIT License
 *
 * Copyright 2016 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\tokeneditorModel;

use InvalidArgumentException;
use PDO;

class TokenCollection {

    private PDO $pdo;
    private int $documentId;
    private string $userId;
    private int $tokenIdFilter;
    /**
     * @var array<string, string>
     */
    private array $filters  = [];
    /**
     * @var array<string>
     */
    private array $sorting  = [];
    /**
     * @var array<string, string>
     */
    private array $propDict = [];

    public function __construct(PDO $pdo, int $documentId, string $userId) {
        $this->pdo        = $pdo;
        $this->documentId = $documentId;
        $this->userId     = $userId;

        $query    = $this->pdo->prepare("SELECT property_xpath, name FROM properties WHERE document_id = ?");
        $query->execute([$this->documentId]);
        while ($prop     = $query->fetch(PDO::FETCH_OBJ)) {
            $this->propDict[$prop->name] = $prop->property_xpath;
        }
    }

    public function setTokenIdFilter(int $id): void {
        $this->tokenIdFilter = $id;
    }

    public function addFilter(string $prop, string $val): void {
        $this->filters[$prop] = $val;
    }

    /**
     * 
     * @param array<string> $columns sorting order (prepend column name with "-" for
     *   descending
     */
    public function setSorting(array $columns): void {
        $this->sorting = $columns;
    }

    public function getData(int $pageSize = 1000, int $offset = 0): string {
        list($filterQuery, $filterParam) = $this->getFilters();
        $queryStr = "
            WITH filter AS (" . $filterQuery . ")
            SELECT
                json_build_object(
                    'tokenCount', (SELECT count(*) FROM filter), 
                    'data', COALESCE( 
                        json_agg(json_object(array_cat(array['tokenId'], names), array_cat(array[token_id::text], values))), 
                        array_to_json(array[]::text[]) 
                    ) 
                ) 
            FROM ( 
                SELECT 
                    token_id, sort,
                    array_agg(COALESCE(cv.value, v.value) ORDER BY ord) AS values, 
                    array_agg(p.name ORDER BY ord) AS names 
                FROM
                    (
                        SELECT *
                        FROM filter
                        LIMIT ? 
                        OFFSET ?
                    ) f
                    JOIN tokens t USING (document_id, token_id) 
                    JOIN properties p USING (document_id)
                    JOIN orig_values v USING (document_id, property_xpath, token_id) 
                    LEFT JOIN (
                        SELECT *
                        FROM (
                            SELECT 
                                document_id, property_xpath, token_id, value, 
                                row_number() OVER (PARTITION BY document_id, property_xpath, token_id ORDER BY date DESC) AS n
                            FROM 
                                values
                                JOIN (
                                    SELECT *
                                    FROM filter
                                    LIMIT ? 
                                    OFFSET ?
                                ) ff USING (document_id, token_id)
                        ) t
                        WHERE n = 1
                    ) cv USING (document_id, property_xpath, token_id)
                GROUP BY 1, 2
                ORDER BY sort
            ) t
        ";
        $param    = array_merge($filterParam, [$pageSize, $offset, $pageSize, $offset]);
        $query    = $this->pdo->prepare($queryStr);
        $query->execute($param);
        $result   = $query->fetch(PDO::FETCH_COLUMN);
        return $result;
    }

    public function getTokensOnly(int $pageSize = 1000, int $offset = 0): string {
        list($filterQuery, $filterParam) = $this->getFilters();
        $queryStr = "
            WITH filter AS (" . $filterQuery . ")
            SELECT 
                json_build_object(
                    'tokenCount', (SELECT count(*) FROM filter),
                    'data', COALESCE(
                        json_agg(json_build_object('tokenId', token_id::text) ORDER BY sort),
                        '[]'
                    )
                )
            FROM 
                documents_users
                JOIN tokens USING (document_id)
                JOIN (
                    SELECT * 
                    FROM filter 
                    LIMIT ?
                    OFFSET ?
                ) t USING (document_id, token_id)
            WHERE user_id = ?
        ";
        $query    = $this->pdo->prepare($queryStr);
        $params   = array_merge($filterParam, [$pageSize, $offset, $this->userId]);
        $query->execute($params);
        $result   = $query->fetch(PDO::FETCH_COLUMN);

        return $result ? $result : '[]';
    }

    /**
     * Returns given property values frequencies.
     * 
     * Honors filters.
     * 
     * Values are ordered descending by count.
     * 
     * Returned data are serialized to a JSON string.
     * @param string $prop property name
     * @return string
     */
    public function getStats(string $prop): string {
        if (!isset($this->propDict[$prop])) {
            throw new InvalidArgumentException('Unknown property ' . $prop);
        }
        
        list($filterQuery, $filterParam) = $this->getFilters();
        $queryStr = "
            WITH filter AS (" . $filterQuery . ")
            SELECT json_agg(json_build_object('value', value, 'count', count))
            FROM ( 
                SELECT 
                    coalesce(cv.value, v.value) AS value, 
                    count(*) AS count
                FROM
                    filter
                    JOIN orig_values v USING (document_id, token_id) 
                    LEFT JOIN (
                        SELECT *
                        FROM (
                            SELECT 
                                document_id, property_xpath, token_id, value, 
                                row_number() OVER (PARTITION BY document_id, property_xpath, token_id ORDER BY date DESC) AS n
                            FROM 
                                values
                        ) t
                        WHERE n = 1
                    ) cv USING (document_id, property_xpath, token_id)
                WHERE property_xpath = ?
                GROUP BY 1
                ORDER BY count DESC, value
            ) t
        ";
        $param    = array_merge($filterParam, [$this->propDict[$prop]]);
        $query    = $this->pdo->prepare($queryStr);
        $query->execute($param);
        $result   = $query->fetch(PDO::FETCH_COLUMN);

        return $result ? $result : '[]';
    }

    /**
     * @return array{string, array<mixed>}
     */
    private function getFilters(): array {
        $query  = "";
        $n      = 1;
        $params = [];

        if (isset($this->tokenIdFilter)) {
            $query    .= "
                JOIN (
                    SELECT ?::int AS token_id
                ) f" . $n++ . " USING (token_id)";
            $params[] = $this->tokenIdFilter;
        }

        $cols  = '';
        $props = $this->skipSortDir($this->sorting);
        $props = array_merge($props, array_diff(array_keys($this->filters), $props));
        foreach ($props as $prop) {
            if (!isset($this->propDict[$prop])) {
                continue;
            }

            $params[] = $this->documentId;
            $params[] = $this->propDict[$prop];

            $where = '';
            if (isset($this->filters[$prop])) {
                $where    = " AND COALESCE(v.value, o.value) ILIKE ?";
                $params[] = $this->filters[$prop];
            }

            $query .= "
                JOIN (
                    SELECT document_id, token_id, COALESCE(v.value, o.value) AS v$n
                    FROM 
                        orig_values o
                        LEFT JOIN (
                            SELECT *
                            FROM (
                                SELECT 
                                    document_id, property_xpath, token_id, value,
                                    row_number() OVER (PARTITION BY document_id, property_xpath, token_id ORDER BY date DESC) AS n
                                FROM values
                                ORDER BY date DESC
                            ) t
                            WHERE n = 1
                        ) v USING (document_id, property_xpath, token_id)
                    WHERE document_id = ? AND property_xpath = ? $where
                ) f$n USING (document_id, token_id)
            ";
            $cols  .= ', v' . $n;
            $n++;
        }

        $order = [];
        foreach ($this->sorting as $h => $i) {
            $dir = substr($i, 0, 1) === '-' ? ' DESC' : '';
            $i   = substr($i, 0, 1) === '-' ? substr($i, 1) : $i;
            if (isset($this->propDict[$i])) {
                $order[] = 'v' . (count($order) + 1) . $dir;
            }
        }
        $order[] = 'token_id';
        $order   = implode(', ', $order);

        $query  = "
            SELECT 
                document_id, token_id 
                $cols,
                row_number() OVER (ORDER BY $order) AS sort
            FROM
                (SELECT * FROM documents_users WHERE document_id = ? AND user_id = ?) du
                JOIN tokens USING (document_id)
                " . $query . " 
        ";
        $params = array_merge([$this->documentId, $this->userId], $params);
        return [$query, $params];
    }

    /**
     * @param array<string> $a
     * @return array<string>
     */
    private function skipSortDir(array $a): array {
        $r = [];
        foreach ($a as $i) {
            $r[] = preg_replace('/^-/', '', $i);
        }
        return $r;
    }

}
