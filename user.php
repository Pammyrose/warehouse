<?php
ob_start(); // Start output buffering to prevent stray output
include('login_session.php');
include "connect.php";


// Enable error reporting for debugging (disable display_errors in production)
ini_set('display_errors', 0); // Suppress errors on screen
ini_set('log_errors', 1); // Log errors
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check database connection
if (!$db || $db->connect_error) {
    ob_end_clean();
    error_log("Database connection failed: " . $db->connect_error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle form submissions for add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $login_id = isset($_POST['login_id']) ? intval($_POST['login_id']) : 0;
    $username = validate($_POST['username']);
    $password = validate($_POST['password'], true);
    $name = validate($_POST['name']);
    $original_username = isset($_POST['original_username']) ? validate($_POST['original_username']) : '';

    // Validate inputs
    if (empty($username) || empty($name)) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: Username and Name are required.']);
        error_log("Validation failed: username=$username, name=$name, login_id=$login_id");
        exit;
    }

    if ($login_id == 0 && empty($password)) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: Password is required for new users.']);
        error_log("Validation failed: Password is required for new user, username=$username");
        exit;
    }

    // Check for duplicate username
    $stmt = $db->prepare("SELECT login_id FROM user WHERE user = ? AND login_id != ?");
    $stmt->bind_param("si", $username, $login_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: Username already exists.']);
        error_log("Duplicate username detected: $username");
        $stmt->close();
        exit;
    }
    $stmt->close();

    if ($login_id > 0) {
        // Update user
        if (!empty($password)) {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE user SET user = ?, pass = ?, name = ? WHERE login_id = ?");
            $stmt->bind_param("sssi", $username, $hashed_password, $name, $login_id);
        } else {
            // Update without changing the password
            $stmt = $db->prepare("UPDATE user SET user = ?, name = ? WHERE login_id = ?");
            $stmt->bind_param("ssi", $username, $name, $login_id);
        }
    } else {
        // Add new user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO user (user, pass, name) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $name);
    }

    $success = $stmt->execute();
    if (!$success) {
        $error = $db->error;
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Error saving user: $error"]);
        error_log("Save failed: login_id=$login_id, username=$username, error=$error");
        $stmt->close();
        exit;
    }

    $stmt->close();
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Handle deletion of a user
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $db->prepare("DELETE FROM user WHERE login_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['update_message'] = "User deleted successfully.";
    } else {
        $_SESSION['update_message'] = "Failed to delete user: " . $db->error;
        error_log("Delete failed: login_id=$delete_id, error=" . $db->error);
    }
    $stmt->close();
    ob_end_clean();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Search and sort parameters
$sort = $_GET['sort'] ?? '';
$search = $_GET['search'] ?? '';
$searchEscaped = mysqli_real_escape_string($db, $search);

// Base SQL for fetching users
$sql = "SELECT login_id, user, name FROM user";
$conditions = [];

if (!empty($search)) {
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $lastTerm = end($searchTerms);
    $lastTermEscaped = mysqli_real_escape_string($db, $lastTerm);
    $conditions[] = "(LOWER(user) LIKE LOWER('%$lastTermEscaped%') OR LOWER(name) LIKE LOWER('%$lastTermEscaped%'))";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Sorting
if ($sort === 'az') {
    $sql .= " ORDER BY name ASC";
} elseif ($sort === 'za') {
    $sql .= " ORDER BY name DESC";
}

// Execute the query for table
$result = mysqli_query($db, $sql);
if (!$result) {
    ob_end_clean();
    error_log("Table query failed: " . mysqli_error($db));
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error executing query: ' . mysqli_error($db)]);
    exit;
}

// Fetch suggestions for search
$suggestions_sql = "SELECT DISTINCT user, name FROM user";
if (!empty($search)) {
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $priorTerms = array_slice($searchTerms, 0, -1);
    $suggestionConditions = [];
    foreach ($priorTerms as $term) {
        if (!empty($term)) {
            $termEscaped = mysqli_real_escape_string($db, $term);
            $suggestionConditions[] = "(LOWER(user) LIKE LOWER('%$termEscaped%') OR LOWER(name) LIKE LOWER('%$termEscaped%'))";
        }
    }
    if (!empty($suggestionConditions)) {
        $suggestions_sql .= " WHERE " . implode(" AND ", $suggestionConditions);
    }
}
$suggestions_result = mysqli_query($db, $suggestions_sql);
if (!$suggestions_result) {
    error_log("Suggestion query failed: " . mysqli_error($db));
    $suggestions = [];
} else {
    $suggestions = [];
    while ($row = mysqli_fetch_assoc($suggestions_result)) {
        if (!empty($row['user'])) $suggestions[] = $row['user'];
        if (!empty($row['name']) && $row['name'] !== $row['user']) $suggestions[] = $row['name'];
    }
    $suggestions = array_unique($suggestions);
    usort($suggestions, 'strnatcasecmp');
}
$suggestions_json = json_encode(array_values($suggestions), JSON_HEX_QUOT | JSON_HEX_TAG);

$db->close();
ob_end_flush(); // Flush remaining output for HTML rendering
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .suggestions-container {
            position: absolute;
            z-index: 20;
            width: 100%;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            top: 100%;
            left: 0;
        }
        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            color: #1f2937;
        }
        .suggestion-item:hover {
            background-color: #f3f4f6;
        }
        #myModal {
            display: none;
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
        }
        #myModal.show {
            display: flex;
        }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="bg-white content-wrapper flex items-start justify-center min-h-screen p-2 lg:ml-[250px]">

    <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between pb-4 mt-10">
        
        <div class="relative inline-block">
            <button id="dropdownToggle" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                Sort by Name
                <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/></svg>
            </button>

            <div id="dropdownMenu" class="absolute z-10 hidden w-28 bg-white divide-y divide-gray-100 rounded-lg shadow-sm">
                <ul class="space-y-1 text-sm text-gray-700" aria-labelledby="dropdownToggle">
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                            <input id="filter-radio-az" type="radio" value="az" name="filter-radio" <?php if ($sort === 'az') echo 'checked'; ?> onclick="updateSort('az')" class="w-4 h-4">
                            <label for="filter-radio-az" class="ms-2 text-sm font-medium">A – Z</label>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                            <input id="filter-radio-za" type="radio" value="za" name="filter-radio" <?php if ($sort === 'za') echo 'checked'; ?> onclick="updateSort('za')" class="w-4 h-4">
                            <label for="filter-radio-za" class="ms-2 text-sm font-medium">Z – A</label>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <label for="table-search" class="sr-only">Search</label>
        <div class="relative justify-end">
            <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-5 h-5 text-gray-500" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <form id="searchForm" method="GET" class="flex space-x-2 items-center">
                <div class="relative w-80">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" id="table-search" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-full focus:ring-blue-500 focus:border-blue-500" placeholder="Search..." autocomplete="off" />
                    <div id="suggestions" class="suggestions-container hidden"></div>
                </div>
                <button type="button" id="addUserBtn" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">+</button>
            </form>
        </div>

        <div class="flex-auto w-full">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 mt-2">
                <thead class="bg-gray-900 text-xs text-white uppercase text-center">
                    <tr>
                        <th scope="col" class="px-7 py-2" style="width: 50px;">No</th>
                        <th scope="col" class="px-7 py-2" style="width: 200px;">Username</th>
                        <th scope="col" class="px-7 py-2" style="width: 200px;">Name</th>
                        <th scope="col" class="px-7 sm:px-7 py-2 sm:py-3" style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    if (mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)) : ?>
                            <tr class="bg-white text-black text-center border-b">
                                <th scope="row" class="px-7 py-4 font-medium whitespace-nowrap"><?php echo $counter++; ?></th>
                                <td><?php echo htmlspecialchars($row['user']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td>
                                    <button type="button" class="editUserBtn font-medium text-yellow-500 hover:underline mr-3" data-id="<?php echo $row['login_id']; ?>">Edit</button>
                                    <a href="?delete_id=<?php echo $row['login_id']; ?>" class="font-medium text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="myModal">
        <div id="modalContent" class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative"></div>
    </div>
</div>

<script>
    const suggestions = <?php echo $suggestions_json; ?>;
    let debounceTimeout;

    function updateSort(sortValue) {
        const url = new URL(window.location.href);
        const currentSearch = url.searchParams.get('search');
        url.searchParams.set('sort', sortValue);
        if (currentSearch) url.searchParams.set('search', currentSearch);
        window.location.href = url.toString();
    }

    function loadModalContent(url, isEdit = false, userId = '') {
        const myModal = document.getElementById('myModal');
        const modalContent = document.getElementById('modalContent');
        
        fetch(url, {
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) {
                console.error('Fetch error:', response.statusText);
                throw new Error(`Failed to load form: ${response.statusText}`);
            }
            return response.text();
        })
        .then(data => {
            modalContent.innerHTML = data;
            const form = modalContent.querySelector('form');
            if (form) {
                form.action = 'user.php';
                const userIdInput = form.querySelector('input[name="login_id"]');
                if (userIdInput && !isEdit) userIdInput.value = '';
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    fetch('user.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => {
                        if (!response.ok) {
                            console.error('Form submission error:', response.statusText);
                            throw new Error(`Form submission failed: ${response.statusText}`);
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON parse error:', e.message, 'Raw text:', text);
                                throw new Error(`JSON.parse: ${e.message}`);
                            }
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            myModal.classList.remove('show');
                            location.reload();
                        } else {
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                            errorDiv.textContent = data.message || 'An error occurred during submission.';
                            modalContent.prepend(errorDiv);
                            console.error('Submission error:', data.message);
                        }
                    })
                    .catch(error => {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                        errorDiv.textContent = 'Failed to process user: ' + error.message;
                        modalContent.prepend(errorDiv);
                        console.error('Fetch error:', error.message);
                    });
                });
            }
            const closeBtn = modalContent.querySelector('#closeModal');
            const cancelBtn = modalContent.querySelector('button[type="button"]');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    myModal.classList.remove('show');
                });
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    myModal.classList.remove('show');
                });
            }
            myModal.classList.add('show');
        })
        .catch(error => {
            console.error('Error loading modal content:', error);
            modalContent.innerHTML = '<p class="text-red-700">Failed to load form: ' + error.message + '</p>';
            myModal.classList.add('show');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('dropdownToggle');
        const menu = document.getElementById('dropdownMenu');
        const searchInput = document.getElementById('table-search');
        const suggestionsContainer = document.getElementById('suggestions');
        const addUserBtn = document.getElementById('addUserBtn');
        const editUserButtons = document.querySelectorAll('.editUserBtn');

        // Dropdown toggle for sorting
        toggle.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // Load user_add.php for add button
        addUserBtn.addEventListener('click', () => {
            loadModalContent('user_add.php?modal=true');
        });

        // Load user_view.php for edit buttons
        editUserButtons.forEach(button => {
            button.addEventListener('click', () => {
                const userId = button.getAttribute('data-id');
                loadModalContent(`user_view.php?id=${userId}&modal=true`, true, userId);
            });
        });

        // Hide dropdown, suggestions, and modal when clicking outside
        document.addEventListener('click', function(event) {
            if (!toggle.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
            if (!searchInput.contains(event.target) && !suggestionsContainer.contains(event.target)) {
                suggestionsContainer.classList.add('hidden');
            }
            if (!document.getElementById('modalContent').contains(event.target) && !addUserBtn.contains(event.target) && !Array.from(editUserButtons).some(btn => btn.contains(event.target)) && myModal.classList.contains('show')) {
                myModal.classList.remove('show');
            }
        });

        // Search suggestions with debouncing
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = this.value.trim();
                suggestionsContainer.innerHTML = '';
                
                if (query.length < 1) {
                    suggestionsContainer.classList.add('hidden');
                    return;
                }

                const terms = query.split(',').map(term => term.trim()).filter(term => term !== '');
                if (terms.length === 0) {
                    suggestionsContainer.classList.add('hidden');
                    return;
                }
                const lastTerm = terms[terms.length - 1].toLowerCase();

                const filteredSuggestions = suggestions
                    .filter(item => item.toLowerCase().includes(lastTerm))
                    .slice(0, 10);

                if (filteredSuggestions.length > 0) {
                    filteredSuggestions.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.textContent = item;
                        div.addEventListener('click', () => {
                            terms[terms.length - 1] = item;
                            searchInput.value = terms.join(', ');
                            suggestionsContainer.classList.add('hidden');
                            searchInput.form.submit();
                        });
                        suggestionsContainer.appendChild(div);
                    });
                    suggestionsContainer.classList.remove('hidden');
                } else {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = 'No suggestions found';
                    suggestionsContainer.appendChild(div);
                    suggestionsContainer.classList.remove('hidden');
                }
            }, 300);
        });

        // Close modal on Esc key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && myModal.classList.contains('show')) {
                myModal.classList.remove('show');
            }
        });
    });
</script>

</body>
</html>