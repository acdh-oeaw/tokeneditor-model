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

use PDO;

class TokenCollection {

    /**
     *
     * @var PDO $pdo
     */
    private $pdo;
    private $documentId;
    private $userId;
    private $tokenIdFilter;
    private $filters = [];

    public function __construct(PDO $pdo, int $documentId, string $userId) {
        $this->pdo        = $pdo;
        $this->documentId = $documentId;
        $this->userId     = $userId;
    }

    public function setTokenIdFilter(int $id) {
        $this->tokenIdFilter = $id;
    }

    /**
     * 
     * @param type $prop property xpath
     * @param type $val filter value
     */
    public function addFilter(string $prop, string $val) {
        $this->filters[$prop] = $val;
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
					token_id, 
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
				GROUP BY 1
				ORDER BY token_id 
			) t
        ";
        $query    = $this->pdo->prepare($queryStr);
        $param    = array_merge($filterParam, [$pageSize, $offset, $pageSize, $offset]);
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
						json_agg(json_build_object('tokenId', token_id::text) ORDER BY token_id),
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

    public function getStats(string $propxpath = '@state'): string {
        $queryStr = "
            SELECT json_agg(stats)
            FROM (
                SELECT json_build_object('value', value, 'count', count) AS stats
                FROM (
                    SELECT value, count(*) AS count
                    FROM values 
                    WHERE document_id = ? AND property_xpath = ? 
                    GROUP BY value
                    ORDER BY value
                ) t
            ) AS stats
        ";
        $query    = $this->pdo->prepare($queryStr);
        $params   = [$this->documentId, $propxpath];
        $query->execute($params);
        $result   = $query->fetch(PDO::FETCH_COLUMN);

        return $result ? $result : '[]';
    }

    private function getFilters() {
        $query    = $this->pdo->prepare("SELECT property_xpath, name FROM properties WHERE document_id = ?");
        $query->execute([$this->documentId]);
        $propDict = [];
        while ($prop     = $query->fetch(PDO::FETCH_OBJ)) {
            $propDict[$prop->name] = $prop->property_xpath;
        }

        $query  = "";
        $n      = 1;
        $params = [];

        if ($this->tokenIdFilter !== null) {
            $query    .= "
				JOIN (
					SELECT ?::int AS token_id
				) f" . $n++ . " USING (token_id)";
            $params[] = $this->tokenIdFilter;
        }

        foreach ($this->filters as $prop => $val) {
            if (!isset($propDict[$prop])) {
                continue;
            }
            $query    .= "
				JOIN (
					SELECT token_id
					FROM 
						orig_values o
						LEFT JOIN (
							SELECT 
								document_id, property_xpath, token_id,
								first_value(value) OVER (PARTITION BY document_id, property_xpath, token_id ORDER BY date DESC) AS n
							FROM values
							WHERE
								document_id = ?
								AND property_xpath = ?
							ORDER BY date DESC
						) v USING (document_id, property_xpath, token_id)
					WHERE COALESCE(n, o.value) ILIKE ? AND document_id = ?
				) f" . $n++ . " USING (token_id)
            ";
            $params[] = $this->documentId;
            $params[] = $propDict[$prop];
            $params[] = $val;
            $params[] = $this->documentId;
        }

        $query    = "				
			SELECT DISTINCT document_id, token_id
			FROM
				documents_users
				JOIN tokens USING (document_id)
				" . $query . " 
			WHERE document_id = ? AND user_id = ?
			ORDER BY token_id
        ";
        $params[] = $this->documentId;
        $params[] = $this->userId;
        return [$query, $params];
    }

}
