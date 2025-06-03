<?php
require_once '../config/db.php';
require_once '../utils/functions.php';

// Require user to be logged in
requireLogin();

$error = '';
$success = '';

// Check if note ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user.php");
    exit;
}

$note_id = (int)$_GET['id'];

// Get the note
$note = getNoteById($note_id);

// Check if the note exists and belongs to the user
if (!$note || $note['user_id'] != $_SESSION['user_id']) {
    header("Location: user.php");
    exit;
}

// Get users with whom this note is shared
$sharedUsers = [];
if (isPremium()) {
    $sharedUsers = getNoteSharedUsers($note_id);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $priority = $_POST['priority'];
    $is_shared = isset($_POST['is_shared']) ? 1 : 0;

    // Premium features check
    if (!isPremium() && ($priority === 'Alta' || $priority === 'Immediata')) {
        $error = "High priority is a premium feature. Please upgrade your account.";
    } elseif (!isPremium() && $is_shared) {
        $error = "Note sharing is a premium feature. Please upgrade your account.";
    } else {
        // Validate input
        if (empty($title) || empty($content)) {
            $error = "Title and content are required";
        } else {
            // Update the note
            $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, priority = ?, is_shared = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sssiii", $title, $content, $priority, $is_shared, $note_id, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $success = "Note updated successfully!";
                // Update note data
                $note['title'] = $title;
                $note['content'] = $content;
                $note['priority'] = $priority;
                $note['is_shared'] = $is_shared;
            } else {
                $error = "Error updating note. Please try again.";
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Note | Ricordella</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="../assets/style/user.css">
    <link rel="stylesheet" href="../assets/style/default-user.css">
    <link rel="stylesheet" href="../assets/style/font-general.css">
    <link rel="stylesheet" href="../assets/style/note-form.css">
    <link rel="stylesheet" href="../assets/style/galileo-ai.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/img/logo-favicon.ico" type="image/x-icon">
</head>
<body>
    <header>
        <div class="logo">Ricordella</div>
        <nav>
            <a href="user.php">My Notes</a>
            <a href="daily_notes.php">Today's Notes</a>
            <a href="shared_notes.php">Shared Notes</a>
            <a href="create_note.php">Create Note</a>
        </nav>
        <div class="user-info">
            <span>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <?php if (isPremium()): ?>
            <span class="premium-badge"><i class="fas fa-crown"></i> Premium</span>
            <?php endif; ?>
            <a href="../logout.php" class="logout" title="Logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <main>
        <h1>Edit Note</h1>

        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" class="note-form">
            <div class="form-row">
                <div class="form-group title-container">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-control" required
                        value="<?php echo htmlspecialchars($note['title']); ?>">
                    <?php if (isPremium()): ?>
                    <button type="button" id="generate-from-title" class="generate-title-btn" title="Generate content from title">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="priority">Priority</label>
                    <div class="dropdown-select">
                        <select id="priority" name="priority" class="form-control">
                            <option value="Bassa" <?php echo $note['priority'] === 'Bassa' ? 'selected' : ''; ?>>Low</option>
                            <option value="Normale" <?php echo $note['priority'] === 'Normale' ? 'selected' : ''; ?>>Normal</option>
                            <option value="Alta" <?php echo $note['priority'] === 'Alta' ? 'selected' : ''; ?> <?php echo !isPremium() ? 'disabled' : ''; ?>>
                                High
                            </option>
                            <option value="Immediata" <?php echo $note['priority'] === 'Immediata' ? 'selected' : ''; ?> <?php echo !isPremium() ? 'disabled' : ''; ?>>
                                Immediate
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (isPremium()): ?>
            <!-- AI Tools Section -->
            <div class="ai-tools">
                <div class="ai-tools-header">
                    <h3><i class="fas fa-robot"></i> AI Assistant</h3>
                </div>
                <div class="ai-tools-content">
                    <div class="ai-actions">
                        <button type="button" class="ai-action" data-action="generate">
                            <i class="fas fa-pen-fancy"></i> Generate
                        </button>
                        <button type="button" class="ai-action" data-action="summarize">
                            <i class="fas fa-compress-alt"></i> Summarize
                        </button>
                        <button type="button" class="ai-action" data-action="translate">
                            <i class="fas fa-language"></i> Translate
                        </button>
                        <button type="button" class="ai-action" data-action="transcribe">
                            <i class="fas fa-microphone"></i> Transcribe
                        </button>
                    </div>

                    <div class="ai-prompt-container">
                        <input type="text" id="ai-prompt-field" class="ai-prompt-field" placeholder="Select an AI action and enter instructions here...">
                    </div>

                    <button type="button" id="ai-process" class="ai-process-btn">
                        <i class="fas fa-magic"></i> Process with AI
                    </button>

                    <div id="ai-error"></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="content">Content</label>
                <textarea id="content" name="content" class="form-control" rows="8" required><?php echo htmlspecialchars($note['content']); ?></textarea>
            </div>

            <?php if (isPremium()): ?>
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="share-option" name="is_shared" <?php echo $note['is_shared'] ? 'checked' : ''; ?>>
                    <label for="share-option">Enable sharing for this note</label>
                </div>
            </div>
            <?php else: ?>
            <div class="form-group premium-feature">
                <div class="checkbox-group">
                    <input type="checkbox" id="share-option" name="is_shared" disabled <?php echo $note['is_shared'] ? 'checked' : ''; ?>>
                    <label for="share-option">Enable sharing for this note</label>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn primary">
                    <i class="fas fa-save"></i> Update Note
                </button>
                <a href="user.php" class="btn secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <?php if (isPremium()): ?>
        <!-- Sharing Section -->
        <div class="sharing-container">
            <div class="sharing-header">
                <h3><i class="fas fa-share-alt share-icon"></i> Share this note</h3>
                <button type="button" id="toggle-share-search" class="share-toggle-btn">
                    <i class="fas fa-plus"></i> Add people
                </button>
            </div>

            <div class="search-section" id="search-section">
                <div class="search-input-wrapper">
                    <div class="search-icon-wrapper">
                        <i class="fas fa-search"></i>
                    </div>
                    <input type="text" id="user-search" class="search-input" placeholder="Search users by email">
                </div>
                <div class="search-results" id="search-results"></div>
            </div>

            <div class="shared-users-list" id="shared-users-list">
                <?php if (empty($sharedUsers)): ?>
                    <div class="no-shared-users">This note isn't shared with anyone</div>
                <?php else: ?>
                    <?php foreach ($sharedUsers as $user): ?>
                        <div class="shared-user" data-user-id="<?php echo $user['id']; ?>">
                            <div class="shared-user-info">
                                <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                            <div class="shared-user-controls">
                                <div class="permission-toggle">
                                    <select class="permission-select" data-user-id="<?php echo $user['id']; ?>">
                                        <option value="view" <?php echo $user['permission'] === 'view' ? 'selected' : ''; ?>>Can view</option>
                                        <option value="edit" <?php echo $user['permission'] === 'edit' ? 'selected' : ''; ?>>Can edit</option>
                                    </select>
                                </div>
                                <button type="button" class="remove-share-btn" data-user-id="<?php echo $user['id']; ?>" title="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($note['is_shared']): ?>
        <div class="alert info" style="margin-top: 20px;">
            <i class="fas fa-info-circle"></i> This note is currently shared. Upgrade to Premium to manage sharing settings.
        </div>
        <?php endif; ?>

        <?php if (!isPremium() && ($note['priority'] === 'Alta' || $note['priority'] === 'Immediata' || $note['is_shared'])): ?>
        <div class="alert info" style="margin-top: 20px;">
            <i class="fas fa-info-circle"></i> This note uses premium features. Upgrade to Premium to modify these settings!
        </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Ricordella - Your Notes App</p>
    </footer>

    <?php if (isPremium()): ?>
    <!-- AI Result Container (fixed at bottom) -->
    <div id="ai-result-container" class="ai-result-container">
        <div class="ai-result-header">
            <span id="ai-result-header">AI Result</span>
            <button type="button" class="ai-result-close">&times;</button>
        </div>
        <div id="ai-result" class="ai-result"></div>
        <div class="ai-result-actions">
            <div class="ai-insert-options">
                <div class="ai-option-group">
                    <input type="radio" id="replace-option" name="insert-mode" value="replace" checked>
                    <label for="replace-option">Replace content</label>
                </div>
                <div class="ai-option-group">
                    <input type="radio" id="prepend-option" name="insert-mode" value="prepend">
                    <label for="prepend-option">Add to beginning</label>
                </div>
            </div>
            <div>
                <button type="button" id="ai-apply" class="ai-btn ai-apply-btn">Apply</button>
                <button type="button" id="ai-cancel" class="ai-btn ai-cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- AI Loader -->
    <div id="ai-loader-container" class="ai-loader-container">
        <div class="loader"></div>
    </div>

    <script src="../assets/script/ricordella-ai.js"></script>
    <?php endif; ?>

    <!-- Sharing functionality script -->
    <?php if (isPremium()): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const noteId = <?php echo $note_id; ?>;
            const shareOption = document.getElementById('share-option');
            const toggleShareSearchBtn = document.getElementById('toggle-share-search');
            const searchSection = document.getElementById('search-section');
            const userSearchInput = document.getElementById('user-search');
            const searchResults = document.getElementById('search-results');
            const sharedUsersList = document.getElementById('shared-users-list');

            // Share option toggle
            if (shareOption) {
                shareOption.addEventListener('change', function() {
                    toggleShareSearchBtn.style.display = this.checked ? 'flex' : 'none';

                    if (!this.checked) {
                        searchSection.classList.remove('active');
                    }
                });

                // Initialize
                toggleShareSearchBtn.style.display = shareOption.checked ? 'flex' : 'none';
            }

            // Toggle search section
            if (toggleShareSearchBtn) {
                toggleShareSearchBtn.addEventListener('click', function() {
                    searchSection.classList.toggle('active');
                    if (searchSection.classList.contains('active')) {
                        userSearchInput.focus();
                    }
                });
            }

            // User search functionality
            let searchTimeout;
            if (userSearchInput) {
                userSearchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();

                    if (query.length < 2) {
                        searchResults.classList.remove('active');
                        searchResults.innerHTML = '';
                        return;
                    }

                    //User search functionality timeout
                    searchTimeout = setTimeout(() => {
                        fetch(`../utils/search_users.php?email=${encodeURIComponent(query)}`)
                            .then(response => response.json())
                            .then(data => {
                                searchResults.innerHTML = '';

                                if (data.length === 0) {
                                    searchResults.innerHTML = '<div class="user-result">No users found</div>';
                                    searchResults.classList.add('active');
                                    return;
                                }

                                data.forEach(user => {
                                    const userElement = document.createElement('div');
                                    userElement.className = 'user-result';
                                    userElement.dataset.id = user.id;
                                    userElement.dataset.username = user.username;
                                    userElement.dataset.email = user.email;

                                    const firstLetter = user.username.charAt(0).toUpperCase();

                                    userElement.innerHTML = `
                                        <div class="user-info">
                                            <div class="user-avatar">${firstLetter}</div>
                                            <div class="user-details">
                                                <div class="user-name">
                                                    ${user.username}
                                                    ${parseInt(user.is_premium) === 1 ?
                                                    '<span class="premium-badge-small"><i class="fas fa-crown" style="color:gold;"></i></span>' : ''}
                                                </div>
                                                <div class="user-email">${user.email}</div>
                                            </div>
                                        </div>
                                        <button type="button" class="add-user-btn">Add</button>
                                    `;

                                    searchResults.appendChild(userElement);
                                });

                                searchResults.classList.add('active');
                            })
                            .catch(error => {
                                console.error('Error searching users:', error);
                                searchResults.innerHTML = '<div class="user-result">Error searching users</div>';
                                searchResults.classList.add('active');
                            });
                    }, 300);
                });
            }

            // Handle add user click
            searchResults.addEventListener('click', function(e) {
                const addUserBtn = e.target.closest('.add-user-btn');
                if (addUserBtn) {
                    const userResult = addUserBtn.closest('.user-result');
                    const userId = userResult.dataset.id;
                    const username = userResult.dataset.username;
                    const email = userResult.dataset.email;
                    const firstLetter = username.charAt(0).toUpperCase();

                    // Share the note with this user
                    shareNoteWithUser(noteId, userId, username, email, firstLetter, 'view');

                    // Clear search
                    userSearchInput.value = '';
                    searchResults.classList.remove('active');
                    searchSection.classList.remove('active');
                }
            });

            // Permission change handler
            document.addEventListener('change', function(e) {
                const permissionSelect = e.target.closest('.permission-select');
                if (permissionSelect) {
                    const userId = permissionSelect.dataset.userId;
                    const permission = permissionSelect.value;

                    updatePermission(noteId, userId, permission);
                }
            });

            // Remove share handler
            document.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.remove-share-btn');
                if (removeBtn) {
                    const userId = removeBtn.dataset.userId;
                    removeShare(noteId, userId);
                }
            });

            // Close result container when clicking the X
            document.querySelector('.ai-result-close').addEventListener('click', function() {
                document.getElementById('ai-result-container').classList.remove('active');
            });

            // Function to share a note with a user
            function shareNoteWithUser(noteId, userId, username, email, firstLetter, permission) {
                fetch('../utils/share_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'share',
                        note_id: noteId,
                        user_id: userId,
                        permission: permission
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateSharedUsersList(data.shared_users);
                    } else {
                        console.error('Error sharing note:', data.error);
                        alert('Error sharing note: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error sharing note:', error);
                    alert('Error sharing note. Please try again.');
                });
            }

            // Function to update permission
            function updatePermission(noteId, userId, permission) {
                fetch('../utils/share_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'share',
                        note_id: noteId,
                        user_id: userId,
                        permission: permission
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Error updating permission:', data.error);
                        alert('Error updating permission: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error updating permission:', error);
                    alert('Error updating permission. Please try again.');
                });
            }

            // Function to remove share
            function removeShare(noteId, userId) {
                if (confirm('Are you sure you want to remove this user from sharing?')) {
                    fetch('../utils/share_action.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'unshare',
                            note_id: noteId,
                            user_id: userId
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateSharedUsersList(data.shared_users);
                        } else {
                            console.error('Error removing share:', data.error);
                            alert('Error removing share: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error removing share:', error);
                        alert('Error removing share. Please try again.');
                    });
                }
            }

            // Function to update shared users list
            function updateSharedUsersList(users) {
                sharedUsersList.innerHTML = '';

                if (!users || users.length === 0) {
                    sharedUsersList.innerHTML = '<div class="no-shared-users">This note isn\'t shared with anyone</div>';
                    return;
                }

                users.forEach(user => {
                    const firstLetter = user.username.charAt(0).toUpperCase();

                    const userElement = document.createElement('div');
                    userElement.className = 'shared-user';
                    userElement.dataset.userId = user.id;

                    userElement.innerHTML = `
                        <div class="shared-user-info">
                            <div class="user-avatar">${firstLetter}</div>
                            <div class="user-details">
                                <div class="user-name">${user.username}</div>
                                <div class="user-email">${user.email}</div>
                            </div>
                        </div>
                        <div class="shared-user-controls">
                            <div class="permission-toggle">
                                <select class="permission-select" data-user-id="${user.id}">
                                    <option value="view" ${user.permission === 'view' ? 'selected' : ''}>Can view</option>
                                    <option value="edit" ${user.permission === 'edit' ? 'selected' : ''}>Can edit</option>
                                </select>
                            </div>
                            <button type="button" class="remove-share-btn" data-user-id="${user.id}" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;

                    sharedUsersList.appendChild(userElement);
                });
            }
        });
    </script>
    <?php endif; ?>

    <script src="../assets/script/note-actions.js"></script>
</body>
</html>