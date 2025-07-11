<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is premium
 */
function isPremium() {
    return isset($_SESSION['is_premium']) && $_SESSION['is_premium'] === true;
}

/**
 * Redirect user if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Redirect user if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: ../user/user.php");
        exit;
    }
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

/**
 * Get notes by user ID
 */
function getNotesByUserId($userId, $orderBy = "created_at DESC") {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY $orderBy");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    while ($note = $result->fetch_assoc()) {
        $notes[] = $note;
    }
    $stmt->close();
    return $notes;
}

/**
 * Get note by ID
 */
function getNoteById($noteId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->bind_param("i", $noteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();
    $stmt->close();
    return $note;
}

/**
 * Get shared notes for a user
 */
function getSharedNotes($userId) {
    global $conn;
    $sql = "SELECT n.*, u.username, ns.permission 
            FROM notes n 
            JOIN note_shares ns ON n.id = ns.note_id 
            JOIN users u ON n.user_id = u.id
            WHERE ns.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    while ($note = $result->fetch_assoc()) {
        $notes[] = $note;
    }
    $stmt->close();
    return $notes;
}

/**
 * Get notes shared by a user
 */
function getSharedByMe($userId) {
    global $conn;

    $sql = "SELECT n.id, n.title, n.is_shared, u.id AS shared_with_id, u.username AS shared_with, ns.permission 
            FROM notes n
            JOIN note_shares ns ON n.id = ns.note_id
            JOIN users u ON ns.user_id = u.id
            WHERE n.user_id = ? AND n.is_shared = 1
            ORDER BY n.title, u.username";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $shared_notes = [];
    while ($row = $result->fetch_assoc()) {
        if (!isset($shared_notes[$row['id']])) {
            $shared_notes[$row['id']] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'shared_with' => []
            ];
        }

        $shared_notes[$row['id']]['shared_with'][] = [
            'id' => $row['shared_with_id'],
            'username' => $row['shared_with'],
            'permission' => $row['permission']
        ];
    }

    return $shared_notes;
}

/**
 * Get today's notes
 */
function getTodaysNotes($userId) {
    global $conn;
    $today = date('Y-m-d');
    $sql = "SELECT * FROM notes WHERE user_id = ? AND DATE(created_at) = ? ORDER BY priority DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    while ($note = $result->fetch_assoc()) {
        $notes[] = $note;
    }
    $stmt->close();
    return $notes;
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('d M Y, H:i', strtotime($date));
}

/**
 * Count notes for a user
 */
function countUserNotes($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM notes WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['count'];
}

/**
 * Get all users (for admin)
 */
function getFilteredUsers($filters, $sort_column, $sort_order) {
    global $conn;
    $where = [];
    $params = [];
    $types = '';

    if($filters['id']!=='') {
        $where[] = 'users.id = ?';
        $params[] = $filters['id'];
        $types .= 'i';
    }
    if($filters['username']!=='') {
        $where[] = 'users.username LIKE ?';
        $params[] = '%'.$filters['username'].'%';
        $types .= 's';
    }
    if($filters['email']!=='') {
        $where[] = 'users.email LIKE ?';
        $params[] = '%'.$filters['email'].'%';
        $types .= 's';
    }
    if($filters['is_premium']!=='') {
        $where[] = 'users.is_premium = ?';
        $params[] = $filters['is_premium'];
        $types .= 'i';
    }

    $orderBy = '';
    if($sort_column==='notes_count') $orderBy = "notes_count $sort_order";
    else $orderBy = "users.$sort_column $sort_order";

    $sql = "SELECT users.*, COUNT(notes.id) AS notes_count
            FROM users
            LEFT JOIN notes ON users.id = notes.user_id";
    if($where) $sql.= " WHERE ".implode(' AND ', $where);
    $sql.= " GROUP BY users.id ORDER BY $orderBy";

    $stmt = $conn->prepare($sql);
    if($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = [];
    while($row = $res->fetch_assoc()) $users[] = $row;
    return $users;
}

function logError($message) {
    $log_file = __DIR__ . '/../logs/error.log';
    $date = date('Y-m-d H:i:s');
    $formatted_message = "[$date] $message\n";
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
}


/**
 * Search users by email (partial match)
 * For sharing functionality
 */
function searchUsersByEmail($email, $currentUserId) {
    global $conn;
    $email = '%' . $email . '%';

    // Only search for users (not admins) and exclude the current user
    $sql = "SELECT id, username, email, is_premium 
            FROM users 
            WHERE email LIKE ? AND id != ? AND role = 'user' 
            ORDER BY username 
            LIMIT 5";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $email, $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    return $users;
}

/**
 * Check if a note is already shared with a user
 */
function isNoteSharedWithUser($noteId, $userId) {
    global $conn;

    $stmt = $conn->prepare("SELECT 1 FROM note_shares WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $noteId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

/**
 * Share a note with a user
 */
function shareNoteWithUser($noteId, $userToShareWithId, $permission = 'view') {
    global $conn;

    // Validate permission
    $permission = ($permission === 'edit') ? 'edit' : 'view';

    // Check if already shared
    if (isNoteSharedWithUser($noteId, $userToShareWithId)) {
        // Update permission if already shared
        $stmt = $conn->prepare("UPDATE note_shares SET permission = ? WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("sii", $permission, $noteId, $userToShareWithId);
    } else {
        // Create new share
        $stmt = $conn->prepare("INSERT INTO note_shares (note_id, user_id, permission) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $noteId, $userToShareWithId, $permission);
    }

    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        // Make sure the note is marked as shared
        $stmt = $conn->prepare("UPDATE notes SET is_shared = 1 WHERE id = ?");
        $stmt->bind_param("i", $noteId);
        $stmt->execute();
        $stmt->close();
    }

    return $result;
}

/**
 * Get users with whom a note is shared
 */
function getNoteSharedUsers($noteId) {
    global $conn;

    $sql = "SELECT u.id, u.username, u.email, ns.permission
            FROM note_shares ns
            JOIN users u ON ns.user_id = u.id
            WHERE ns.note_id = ?
            ORDER BY u.username";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $noteId);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    return $users;
}

?>
