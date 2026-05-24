<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

/***
 *      _____                   _      _                ______   _       _               
 *     |  __ \                 | |    (_)              |  ____| | |     | |              
 *     | |__) | __ ___  ___ ___| |     _ _ __   ___    | |__ ___| |_ ___| |__   ___ _ __ 
 *     |  ___/ '__/ _ \/ __/ __| |    | | '_ \ / _ \   |  __/ _ \ __/ __| '_ \ / _ \ '__|
 *     | |   | | |  __/\__ \__ \ |____| | | | |  __/   | | |  __/ || (__| | | |  __/ |   
 *     |_|   |_|  \___||___/___/______|_|_| |_|\___|   |_|  \___|\__\___|_| |_|\___|_|   
 *                                                                                       
 *                                                                                       
 */
class DatabaseFetcher {
    private $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }
/***
 *       __                  _   _                 
 *      / _|                | | (_)                
 *     | |_ _   _ _ __   ___| |_ _  ___  _ __  ___ 
 *     |  _| | | | '_ \ / __| __| |/ _ \| '_ \/ __|
 *     | | | |_| | | | | (__| |_| | (_) | | | \__ \
 *     |_|  \__,_|_| |_|\___|\__|_|\___/|_| |_|___/
 *                                                 
 *                                                 
 */
    private function validateSortInputs($sortColumn, $sortOrder) {
        $sortColumn = preg_replace('/[^a-zA-Z_]/', '', $sortColumn);  // Sanitize the column name
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';  // Ensure it is either DESC or ASC
        return [$sortColumn, $sortOrder];
    }

    private function validatePagination($page, $recordsPerPage) {
        $recordsPerPage = is_numeric($recordsPerPage) && $recordsPerPage > 0 ? (int)$recordsPerPage : 10;
        $page = is_numeric($page) && $page > 0 ? (int)$page : 1;
        $offset = ($page - 1) * $recordsPerPage;
        return [$offset, $recordsPerPage];
    }

    // Function to remove accents
    public function removeAccents($str) {
        // Normalize the string and remove accents
        $normalizedStr = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        return $normalizedStr;
    }

    // Function to preprocess query: Remove spaces, convert to lowercase, and remove accents
    private function preprocessQuery($query) {
        $query = strtolower($query);  // Convert to lowercase
        $query = $this->removeAccents($query);  // Remove accents
        $query = str_replace(' ', '', $query);  // Remove spaces
        return $query;
    }
/***
 *      _                      _     
 *     | |                    | |    
 *     | |__  _   _ _ __   ___| |__  
 *     | '_ \| | | | '_ \ / __| '_ \ 
 *     | |_) | |_| | | | | (__| | | |
 *     |_.__/ \__,_|_| |_|\___|_| |_|
 *                                                                
 */
    // Helper method to handle the common logic for fetching "bunch" of records
    private function bunch($type, $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder, $level = null) {
        // Handle if $searchBy is false and shift the parameters
        if ($searchBy === false) {
            $searchBy = '';  // Set $searchBy to empty string
            $level = $sortOrder;  // Shift $sortOrder to level
            $sortOrder = $sortBy;  // Shift $sortBy to sortOrder
            $sortBy = $recordsPerPage;  // Shift $recordsPerPage to sortBy
            $recordsPerPage = $page;  // Shift $page to recordsPerPage
            $page = $value;  // Shift $value to page
            $value = false;
        }
        // Validate and sanitize the sorting inputs
        [$sortBy, $sortOrder] = $this->validateSortInputs($sortBy, $sortOrder);
        [$offset, $recordsPerPage] = $this->validatePagination($page, $recordsPerPage);

        $allowedSearchColumns = ['id', 'name', 'username', 'category', 'tag', 'author', 'filename', 'key'];
        $searchBy = preg_replace('/[^a-zA-Z_]/', '', $searchBy);
        if ($searchBy !== '' && !in_array($searchBy, $allowedSearchColumns, true)) {
            throw new Exception("Invalid search column.");
        }

        // Preprocess the value to remove accents and spaces, and convert to lowercase
        if ($value) {
            $value = $this->preprocessQuery($value);
        }

        if (!$value) {
            return $this->allFetch($type, $page, $recordsPerPage, $sortBy, $sortOrder, $level);
        }

        $query = "";
        $stmt = null;
        $paramType = is_numeric($value) ? 'i' : 's';
        switch ($type) {
            case 'media':
                $query = "SELECT * FROM media WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ? ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'article':
                if ($searchBy == 'tag') {
                    $query = "SELECT * FROM articles WHERE tags COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%') AND level <= $level ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage;";
                    $stmt = mysqli_prepare($this->connection, $query);
                    mysqli_stmt_bind_param($stmt, $paramType, $value);
                } else {
                    $query = "SELECT * FROM articles WHERE $searchBy COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%') AND level <= $level ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage;";
                    $stmt = mysqli_prepare($this->connection, $query);
                    mysqli_stmt_bind_param($stmt, $paramType, $value);
                }
                break;
            case 'user':
                $query = "SELECT * FROM users WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')  ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'category':
                $query = "SELECT * FROM categories WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')  ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'tag':
                $query = "SELECT * FROM articles WHERE tags COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%') ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'role':
                $query = "SELECT * FROM roles WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')  ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'webset':
                $query = "SELECT * FROM webset WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')  ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            default:
                throw new Exception("Invalid searchBy parameter.");
        }        

        // Execute the statement and fetch the result
        if ($stmt) {
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result) {
                return mysqli_fetch_all($result, MYSQLI_ASSOC);
            } else {
                throw new Exception("Error: " . mysqli_error($this->connection));
            }
        } else {
            throw new Exception("Error preparing the query: " . mysqli_error($this->connection));
        }
    }

    
    // Methods for fetching records (media, articles, users, categories, tags)
    public function medias($searchBy, $value, $page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->bunch('media', $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function articles($searchBy, $value, $page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC', $level = 0) {
        $level = (int)$level;
        return $this->bunch('article', $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder, $level);
    }

    public function users($searchBy, $value, $page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->bunch('user', $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function categories($searchBy, $value, $page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->bunch('category', $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function tags($searchBy, $value, $page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->bunch('tag', $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function roles($searchBy, $value, $page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->bunch('role', $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function websets($searchBy, $value, $page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->bunch('webset', $searchBy, $value, $page, $recordsPerPage, $sortBy, $sortOrder);
    }

/***
 *       __     _       _              _ _ 
 *      / _|   | |     | |       /\   | | |
 *     | |_ ___| |_ ___| |__    /  \  | | |
 *     |  _/ _ \ __/ __| '_ \  / /\ \ | | |
 *     | ||  __/ || (__| | | |/ ____ \| | |
 *     |_| \___|\__\___|_| |_/_/    \_\_|_|
 *                                         
 *                                         
 */

    // Helper method to fetch all records (if $value is falsy)
    private function allFetch($type, $page, $recordsPerPage, $sortBy, $sortOrder, $level = null) {
        [$sortBy, $sortOrder] = $this->validateSortInputs($sortBy, $sortOrder);
        [$offset, $recordsPerPage] = $this->validatePagination($page, $recordsPerPage);

        $query = "";
        switch ($type) {
            case 'media':
                $query = "SELECT * FROM media ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                break;
            case 'article':
                $query = "SELECT * FROM articles WHERE level <= $level ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                break;
            case 'user':
                $query = "SELECT * FROM users ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                break;
            case 'category':
                $query = "SELECT * FROM categories ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                break;
            case 'tag':
                // Updated query to return distinct tags as a single list
                $query = "SELECT DISTINCT tags FROM articles";
                break;
            case 'role':
                $query = "SELECT * FROM roles ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                break;
            case 'webset':
                $query = "SELECT * FROM webset ORDER BY $sortBy $sortOrder LIMIT $offset, $recordsPerPage";
                break;
            default:
                throw new Exception("Invalid type.");
        }

        $result = mysqli_query($this->connection, $query);

        if ($result) {
            if ($type === 'tag') {
                $tags = []; // Initialize an empty array to hold all tags
            
                while ($row = mysqli_fetch_assoc($result)) {
                    // Check if the row's 'tags' column is not NULL or empty
                    if (!empty($row['tags'])) {
                        // Split the comma-separated string into individual tags
                        $tagsArray = explode(', ', $row['tags']);
                        // Merge them into the main tags array
                        $tags = array_merge($tags, $tagsArray);
                    }
                }
            
                // Remove duplicates by using array_unique
                return array_unique($tags);
            }
            return mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            throw new Exception("Error: " . mysqli_error($this->connection));
        }
    }

    // Fetch all records for a specific type (allMedia, allArticles, allUsers, allCategories, allTags)
    public function allMedia($page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->allFetch('media', $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function allArticles($page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC', $level = 0) {
        $level = (int)$level;
        return $this->allFetch('article', $page, $recordsPerPage, $sortBy, $sortOrder, $level);
    }

    public function allUsers($page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->allFetch('user', $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function allCategories($page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->allFetch('category', $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function allTags($page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->allFetch('tag', $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function allRoles($page = 1, $recordsPerPage = 10, $sortBy = 'id', $sortOrder = 'ASC') {
        return $this->allFetch('role', $page, $recordsPerPage, $sortBy, $sortOrder);
    }

    public function allLevels() {
        $query = "SELECT DISTINCT level FROM roles";
        $result = mysqli_query($this->connection, $query);
        if ($result) {
            return mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            throw new Exception("Error: " . mysqli_error($this->connection));
        }
    }

    public function allWebsets($page = 1, $recordsPerPage = 10, $sortBy = 'key', $sortOrder = 'ASC') {
        $query = "SELECT * FROM webset";
        $result = mysqli_query($this->connection, $query);
        if ($result) {
            return mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            throw new Exception("Error: " . mysqli_error($this->connection));
        }
    }

/***
 *          _             _      
 *         (_)           | |     
 *      ___ _ _ __   __ _| | ___ 
 *     / __| | '_ \ / _` | |/ _ \
 *     \__ \ | | | | (_| | |  __/
 *     |___/_|_| |_|\__, |_|\___|
 *                   __/ |       
 *                  |___/        
 */
    // Helper method to handle the common logic for fetching a single record
    private function single($type, $searchBy, $value, $level = null) {
        $allowedColumns = ['id', 'key', 'category', 'user_id', 'username', 'name', 'filename', 'reset_token', 'tag'];
        $searchBy = preg_replace('/[^a-zA-Z_]/', '', $searchBy);
        if (!in_array($searchBy, $allowedColumns, true)) {
            throw new Exception("Invalid search column.");
        }

        if (empty($searchBy) || empty($value)) {
            throw new Exception("Invalid search field.");
        }

        $query = "";
        $stmt = null;

        // Preprocess the value to remove accents and spaces, and convert to lowercase
        if ($value) {
            $value = $this->preprocessQuery($value);
        }
        $paramType = is_numeric($value) ? 'i' : 's';
        switch ($type) {
            case 'media':
                $query = "SELECT * FROM media WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ?";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'article':
                $query = "SELECT * FROM articles WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ? AND level <= $level";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'user':
                $query = "SELECT * FROM users WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ?";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'category':
                $query = "SELECT * FROM categories WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ?";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'tags':
                $query = "SELECT * FROM articles WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ?";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'role':
                $query = "SELECT * FROM roles WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ?";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'preference':
                $query = "SELECT * FROM preferences WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ?";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            case 'webset':
                $query = "SELECT * FROM webset WHERE REPLACE(LOWER($searchBy), ' ', '') COLLATE utf8mb4_general_ci = ?";
                $stmt = mysqli_prepare($this->connection, $query);
                mysqli_stmt_bind_param($stmt, $paramType, $value);
                break;
            default:
                throw new Exception("Invalid searchBy parameter.");
        }
        

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            return mysqli_fetch_assoc($result);
        } else {
            throw new Exception("Error: " . mysqli_error($this->connection));
        }
    }
    
      // New method for fetching a single record (media, articles, etc.)
    public function media($searchBy, $value) {
        return $this->single('media', $searchBy, $value);
    }

    public function article($searchBy, $value, $level = 0) {
        return $this->single('article', $searchBy, $value, $level);
    }

    public function user($searchBy, $value) {
        return $this->single('user', $searchBy, $value);
    }

    public function category($searchBy, $value) {
        return $this->single('category', $searchBy, $value);
    }

    public function tag($searchBy, $value) {
        return $this->single('tags', $searchBy, $value);
    }

    public function role($searchBy, $value) {
        return $this->single('role', $searchBy, $value);
    }

    public function preference($searchBy, $value) {
        return $this->single('preference', $searchBy, $value);
    }

    public function webset($searchBy, $value) {
        return $this->single('webset', $searchBy, $value);
    }
/***
 *                            _   
 *                           | |  
 *       ___ ___  _   _ _ __ | |_ 
 *      / __/ _ \| | | | '_ \| __|
 *     | (_| (_) | |_| | | | | |_ 
 *      \___\___/ \__,_|_| |_|\__|
 *                                
 *                                
 */
    public function count($type, $filter = '') {
        validateTable($type);
        $query = "SELECT COUNT(*) AS count FROM $type";
        if ($filter) {
            $query .= " WHERE ";
            foreach ($filter as $key => $value) {
                if (!preg_match('/^FIND_IN_SET/', $key)) {
                    $key = preg_replace('/[^a-zA-Z_()]/', '', $key);
                }
                $operator = '=';
                // Check if $value contains an operator
                if (preg_match('/^(<=|>=|<>|<|>|=)/', $value, $matches)) {
                    $operator = $matches[1]; // Extract the operator
                    $value = substr($value, strlen($operator)); // Remove the operator from $value
                } // FIND_IN_SET doesnt have operator, rather FIND_IN_SET('$value', REPLACE($key, ', ', ','))
                elseif (preg_match('/^FIND_IN_SET/', $key)) {
                    $operator = 'FIND_IN_SET';
                    $value = str_replace(',', ', ', $value);
                }
                // If value looks like a SQL function (e.g., CURDATE()), don’t quote it
                if (preg_match('/^\w+\(.*\)$/', $value)) {
                    $query .= "$key $operator $value AND ";
                } else {
                    $query .= "$key $operator '" . mysqli_real_escape_string($this->connection, $value) . "' AND ";
                }
            }
            $query = substr($query, 0, -5);
        }
    
        $result = mysqli_query($this->connection, $query);
    
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return isset($row['count']) ? $row['count'] : 0;
        } else {
            throw new Exception("Error: " . mysqli_error($this->connection));
        }
    }    

    public function countMedia($filter = '') {
        return $this->count('media', $filter);
    }

    public function countArticles($filter = '') {
        // Translate tag filter (which targets the CSV `tags` column) into a LIKE on `tags`.
        if (is_array($filter) && isset($filter['tag'])) {
            $tagVal = $filter['tag'];
            unset($filter['tag']);
            return $this->countArticlesWithTag($tagVal, $filter);
        }
        return $this->count('articles', $filter);
    }

    /**
     * Count articles whose `tags` CSV contains the given tag.
     * Other filters in $extra are applied with simple = comparison.
     */
    private function countArticlesWithTag($tag, array $extra = []) {
        $sql = "SELECT COUNT(*) AS c FROM articles WHERE tags COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')";
        $types = 's';
        $params = [$tag];

        foreach ($extra as $key => $value) {
            $key = preg_replace('/[^a-zA-Z_()]/', '', $key);
            $operator = '=';
            if (preg_match('/^(<=|>=|<>|<|>|=)/', $value, $m)) {
                $operator = $m[1];
                $value = substr($value, strlen($operator));
            }
            $sql .= " AND $key $operator ?";
            $params[] = $value;
            $types .= is_numeric($value) ? 'i' : 's';
        }

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    }

    public function countUsers($filter = '') {
        return $this->count('users', $filter);
    }

    public function countCategories($filter = '') {
        return $this->count('categories', $filter);
    }

    public function countTags($filter = '') {
        return $this->count('tags', $filter);
    }

    public function countRoles($filter = '') {
        return $this->count('roles', $filter);
    }

    public function countWebsets($filter = '') {
        return $this->count('webset', $filter);
    }
/***
 *          _           _                   _   
 *         | |         | |                 | |  
 *       __| | ___  ___| |_ _ __ _   _  ___| |_ 
 *      / _` |/ _ \/ __| __| '__| | | |/ __| __|
 *     | (_| |  __/\__ \ |_| |  | |_| | (__| |_ 
 *      \__,_|\___||___/\__|_|   \__,_|\___|\__|
 *                                              
 *                                              
 */
    public function __destruct() {
        if ($this->connection) {
            mysqli_close($this->connection);
        }
    }
}
?>