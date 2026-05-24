<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}
/***
 *      _____                   _      _                 _____      _   _            
 *     |  __ \                 | |    (_)               / ____|    | | | |           
 *     | |__) | __ ___  ___ ___| |     _ _ __   ___    | (___   ___| |_| |_ ___ _ __ 
 *     |  ___/ '__/ _ \/ __/ __| |    | | '_ \ / _ \    \___ \ / _ \ __| __/ _ \ '__|
 *     | |   | | |  __/\__ \__ \ |____| | | | |  __/    ____) |  __/ |_| ||  __/ |   
 *     |_|   |_|  \___||___/___/______|_|_| |_|\___|   |_____/ \___|\__|\__\___|_|   
 *                                                                                   
 *                                                                                   
 */
class DatabaseSetter {

    private $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }
/***
 *      ______                _   _                 
 *     |  ____|              | | (_)                
 *     | |__ _   _ _ __   ___| |_ _  ___  _ __  ___ 
 *     |  __| | | | '_ \ / __| __| |/ _ \| '_ \/ __|
 *     | |  | |_| | | | | (__| |_| | (_) | | | \__ \
 *     |_|   \__,_|_| |_|\___|\__|_|\___/|_| |_|___/
 *                                                  
 *                                                  
 */
    public function generateSlug($string) {
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
        $string = str_replace(' ', '-', $string);
        return strtolower($string);
    }

    
    private function getDefaults($type) {
        switch ($type) {
            // Assuming these are the types you want to handle
            // the content after => is just an example
            case 'article':
                return [
                    'name' => 'Untitled Article',
                    'slug' => null,
                    'content' => 'No content provided.',
                    'time' => date('Y-m-d H:i:s'),
                    'picture' => null,
                    'author' => null,
                    'tags' => null,
                    'category' => 1,
                    'public' => '1',
                    'deletable' => '1',
                    'level' => null
                ];
            case 'category':
                return [
                    'name' => 'Unnamed Category'
                ];
            case 'media':
                return [
                    'filename' => null,
                    'name' => 'Untitled Media',
                    'description' => '',
                    'author' => 'Anonymous',
                    'upload_date' => date('Y-m-d H:i:s'),
                ];
            case 'preference':
                return [
                    'user_id' => null,
                    'mode' => 'wallpaper',
                    'color' => 'lila',
                    'language' => 'cs',
                ];
            case 'role':
                return [
                    'name' => 'Unnamed Role',
                    'level' => 1,
                ];
            case 'user':
                return [
                    'username' => 'Anonymous',
                    'mail' => null,
                    'name' => 'Anonymous',
                    'surname' => '',
                    'password' => null,
                    'img' => null,
                    'story' => '',
                    'social_ig' => null,
                    'social_x' => null,
                    'social_fb' => null,
                    'role' => 1,
                    'reset_token' => null,
                    'reset_token_expires' => null,
                ];
            case 'webset':
                return [
                    'key' => null,
                    'value' => "",
                ];
            default:
                throw new Exception("Invalid type: $type");
        }
    }
/***
 *      _____                               
 *     |  __ \                              
 *     | |__) |___ _ __ ___   _____   _____ 
 *     |  _  // _ \ '_ ` _ \ / _ \ \ / / _ \
 *     | | \ \  __/ | | | | | (_) \ V /  __/
 *     |_|  \_\___|_| |_| |_|\___/ \_/ \___|
 *                                          
 *                                          
 */
    private function remove($table, $id) {
        if (empty($id)) {
            throw new Exception("Error: 'id' field is required for removal.");
        }
        validateTable($table);

        $sql = "DELETE FROM $table WHERE id = ?";
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("DB error: " . $this->connection->error);
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new Exception("DB error: Failed to delete record.");
        }
        return true;
    }
/***
 *                  _     _ 
 *         /\      | |   | |
 *        /  \   __| | __| |
 *       / /\ \ / _` |/ _` |
 *      / ____ \ (_| | (_| |
 *     /_/    \_\__,_|\__,_|
 *                          
 *                          
 */
    private function add($table, $data, $defaults) {
        validateTable($table);
        // Filter $data to include only keys that exist in $defaults
        $data = array_intersect_key($data, $defaults);
        $data = array_merge($defaults, $data);
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $params = array_values($data);
        // Remove null or empty values
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
                unset($fields[$key]);
                unset($placeholders[$key]);
            }
        }
        $fields = array_values($fields);
        $placeholders = array_values($placeholders);
        $params = array_values($params);
        $types = implode('', array_map(fn($value) => is_int($value) ? 'i' : 's', $params));

        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("DB error: " . $this->connection->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("DB error: Failed to add record.");
        }
        return true;
    }
/***
 *      ______    _ _ _   
 *     |  ____|  | (_) |  
 *     | |__   __| |_| |_ 
 *     |  __| / _` | | __|
 *     | |___| (_| | | |_ 
 *     |______\__,_|_|\__|
 *                        
 *                        
 */
    private function edit($table, $data, $id, $where) {
        if (empty($id) && $table !== 'webset') {
            throw new Exception("Error: 'id' field is required for editing.");
        }
        validateTable($table);
        $where = validateWhereColumn($where);
        // Map table → defaults key (singularize plurals; explicit cases for tables that aren't simple plural-s)
        $tableToType = [
            'articles'    => 'article',
            'categories'  => 'category',
            'media'       => 'media',
            'preferences' => 'preference',
            'roles'       => 'role',
            'users'       => 'user',
            'webset'      => 'webset',
        ];
        $defaultsKey = $tableToType[$table] ?? (substr($table, -1) === 's' ? substr($table, 0, -1) : $table);
        $defaults = $this->getDefaults($defaultsKey);

        $data = array_intersect_key($data, $defaults);

        // Hash password if editing users table and password is provided
        if ($table === 'users' && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else if ($table === 'users' && empty($data['password'])) {
            unset($data['password']);
        }

        $fields = [];
        $params = [];
        $types = '';

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $fields[] = "$key = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
        }

        $params[] = $id;
        if ($table !== 'webset') {
            $types .= 'i';
        } else {
            $types .= 's';
        }        

        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $where = ?";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("DB error: " . $this->connection->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("DB error: Failed to edit record.");
        }
        return true;
    }
/***
 *                         _ _ 
 *                        | | |
 *                ___ __ _| | |
 *               / __/ _` | | |
 *       ______ | (_| (_| | | |
 *      |______| \___\__,_|_|_|
 *                          
 *              
 */
    public function __call($name, $arguments) {
        $validTypes = ['article', 'category', 'media', 'preference', 'role', 'user', 'webset'];
        if (!in_array($name, $validTypes)) {
            throw new Exception("Invalid method: $name");
        }
        $tables = ['articles', 'categories', 'media', 'preferences', 'roles', 'users', 'webset'];
        $tableMap = array_combine($validTypes, $tables);

        $data = $arguments[0] ?? [];
        $data['type'] = isset($data['type']) ? $data['type'] : $name;
        $action = $data['action'] ?? null;
        $where = isset($data['where']) ? $data['where'] : 'id';

        if (!$action) {
            throw new Exception("Missing 'action' parameter.");
        }
        $id = $data['id'] ?? null;
        $table = $tableMap[$name];
        $defaults = $this->getDefaults($name);

        // Filter $data to include only keys that exist in $defaults
        $data = array_intersect_key($data, $defaults);

        $result = null;
        switch ($action) {
            case 'delete':
            case 'remove':
                if (function_exists('do_action')) do_action("$name.beforeDelete", $id);
                $result = $this->remove($table, $id);
                if (function_exists('do_action')) do_action("$name.afterDelete", $id);
                break;
            case 'edit':
            case 'update':
                if (function_exists('apply_filters')) {
                    $data = apply_filters("$name.beforeSave", $data, $id, 'edit');
                }
                if (function_exists('do_action')) do_action("$name.beforeUpdate", $data, $id);
                $result = $this->edit($table, $data, $id, $where);
                if (function_exists('do_action')) do_action("$name.afterSave", $data, $id, 'edit');
                if (function_exists('do_action')) do_action("$name.afterUpdate", $data, $id);
                break;
            case 'add':
            case 'insert':
                if ($name === 'article') {
                    if (isset($data['name'])) {
                        $data['slug'] = $this->generateSlug($data['name']);
                    } else {
                        throw new Exception("Missing 'name' parameter for article addition.");
                    }
                }
                if ($name === 'user' && !empty($data['password'])) {
                    $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
                }
                if (function_exists('apply_filters')) {
                    $data = apply_filters("$name.beforeSave", $data, null, 'add');
                }
                if (function_exists('do_action')) do_action("$name.beforeInsert", $data);
                $result = $this->add($table, $data, $defaults);
                if ($result && empty($id)) {
                    $id = $this->connection->insert_id;
                }
                if (function_exists('do_action')) do_action("$name.afterSave", $data, $id, 'add');
                if (function_exists('do_action')) do_action("$name.afterInsert", $data, $id);
                break;
            default:
                throw new Exception("Invalid action: $action");
        }

        // Log the action (best-effort — never break the calling flow on failure)
        try {
            $this->logAction($action, $name, $id, $data);
        } catch (Throwable $e) {
            error_log('PressLine activity log failed: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Append a row to the activity_log table.
     * Silently no-ops if the table doesn't exist yet (will be created on first /nastaveni.php load).
     */
    private function logAction($action, $type, $targetId, $data) {
        // Only log meaningful writes
        $action = strtolower($action);
        if (!in_array($action, ['add', 'insert', 'edit', 'update', 'delete', 'remove'], true)) return;

        // Strip secrets
        $loggable = $data;
        if (isset($loggable['password'])) $loggable['password'] = '***';
        if (isset($loggable['smtp_pass'])) $loggable['smtp_pass'] = '***';

        $userId = $_SESSION['user_id'] ?? null;
        $username = null;
        if ($userId) {
            $stmt = $this->connection->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $username = $row['username'] ?? null;
                }
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $payload = json_encode($loggable, JSON_UNESCAPED_UNICODE);

        $sql = 'INSERT INTO activity_log (user_id, username, action, type, target_id, payload, ip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) return; // Table likely doesn't exist yet — silent skip
        $tId = $targetId !== null ? (int)$targetId : 0;
        $userIdParam = $userId !== null ? (int)$userId : 0;
        // Types: i (user_id), s (username), s (action), s (type), i (target_id), s (payload), s (ip)
        $stmt->bind_param('isssiss', $userIdParam, $username, $action, $type, $tId, $payload, $ip);
        @$stmt->execute();
    }
}