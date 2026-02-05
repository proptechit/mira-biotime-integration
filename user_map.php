<?php
// ----------------------------
// Config
// ----------------------------
$jsonFile = __DIR__ . '/storage/user_map.json';

if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([], JSON_PRETTY_PRINT));
}

$userMap = json_decode(file_get_contents($jsonFile), true) ?? [];

$ENV = 'development'; // change to 'development' if needed

// ----------------------------
// Bitrix / Auth
// ----------------------------
if ($ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(0);

    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

    global $USER;
    $USER_ID = (int)$USER->GetID();
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // dev fallback
    $USER_ID = 1;
}

// ----------------------------
// Auth check
// ----------------------------
if (!$USER_ID) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// ----------------------------
// Admin Access Control (Bitrix Native)
// ----------------------------
$isAdmin = false;

if ($ENV === 'production') {
    $isAdmin = $USER->IsAdmin();
} else {
    $isAdmin = true; // allow in dev
}

if (!$isAdmin) {
    http_response_code(403);
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Access Denied</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>

    <body class="min-h-screen bg-gray-100 flex items-center justify-center">

        <div class="bg-white shadow-lg rounded-xl p-10 max-w-md w-full text-center">
            <div class="text-red-600 text-5xl mb-4">⛔</div>

            <h1 class="text-2xl font-semibold mb-2">Access Denied</h1>

            <p class="text-gray-600 mb-6">
                You don’t have permission to view this page.
            </p>
        </div>

    </body>

    </html>
<?php
    exit;
}

// ----------------------------
// Frontend flags
// ----------------------------
echo "<script>
    localStorage.setItem('user_id', btoa('{$USER_ID}'));
    localStorage.setItem('is_admin', btoa('" . ($isAdmin ? '1' : '0') . "'));
    localStorage.setItem('env', btoa('" . $ENV . "'));
</script>";

// ----------------------------
// Handle Save (Add / Update)
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $badge    = trim($_POST['badge'] ?? '');
    $bitrixId = (int)($_POST['bitrix_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');

    if ($badge !== '') {
        $userMap[$badge] = [
            'bitrix_id' => $bitrixId,
            'name'      => $name
        ];
        ksort($userMap);
        file_put_contents(
            $jsonFile,
            json_encode($userMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    header('Location: user_map.php');
    exit;
}

// ----------------------------
// Handle Delete
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $badge = $_POST['badge'] ?? '';

    if (isset($userMap[$badge])) {
        unset($userMap[$badge]);
        file_put_contents(
            $jsonFile,
            json_encode($userMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    header('Location: user_map.php');
    exit;
}

// ----------------------------
// Group users
// ----------------------------
$mapped   = [];
$unmapped = [];

foreach ($userMap as $badge => $data) {
    if ((int)$data['bitrix_id'] > 0) {
        $mapped[$badge] = $data;
    } else {
        $unmapped[$badge] = $data;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Mapping</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

    <div class="max-w-7xl mx-auto">

        <h1 class="text-2xl font-semibold mb-6">User Mapping</h1>

        <!-- Add / Edit -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-lg font-medium mb-4">Add / Update Mapping</h2>

            <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="action" value="save">

                <input name="badge" placeholder="Badge Number" required class="border rounded px-3 py-2">
                <input name="bitrix_id" placeholder="Bitrix ID (0 = not mapped)" class="border rounded px-3 py-2">
                <input name="name" placeholder="User Name" class="border rounded px-3 py-2 md:col-span-2">

                <button class="bg-blue-600 text-white rounded px-4 py-2 md:col-span-4 hover:bg-blue-700">
                    Save Mapping
                </button>
            </form>
        </div>

        <!-- Mapped -->
        <div class="bg-white rounded-lg shadow mb-10 overflow-x-auto">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-medium text-green-700">
                    ✅ Mapped Users (<?= count($mapped) ?>)
                </h2>
            </div>

            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Badge</th>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Bitrix ID</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($mapped as $badge => $data): ?>
                        <tr>
                            <td class="px-4 py-2 font-mono"><?= htmlspecialchars($badge) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($data['name']) ?></td>
                            <td class="px-4 py-2"><?= (int)$data['bitrix_id'] ?></td>
                            <td class="px-4 py-2 flex gap-3">
                                <button
                                    onclick="fillForm('<?= $badge ?>','<?= $data['bitrix_id'] ?>','<?= htmlspecialchars($data['name']) ?>')"
                                    class="text-blue-600 hover:underline">Edit</button>

                                <form method="post" onsubmit="return confirm('Delete this mapping?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="badge" value="<?= $badge ?>">
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Unmapped -->
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-medium text-red-700">
                    ❌ Not Mapped (<?= count($unmapped) ?>)
                </h2>
            </div>

            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Badge</th>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($unmapped as $badge => $data): ?>
                        <tr class="bg-red-50">
                            <td class="px-4 py-2 font-mono"><?= htmlspecialchars($badge) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($data['name']) ?></td>
                            <td class="px-4 py-2 flex gap-3">
                                <button
                                    onclick="fillForm('<?= $badge ?>','0','<?= htmlspecialchars($data['name']) ?>')"
                                    class="text-blue-600 hover:underline">Map</button>

                                <form method="post" onsubmit="return confirm('Delete this entry?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="badge" value="<?= $badge ?>">
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        function fillForm(badge, bitrixId, name) {
            document.querySelector('[name="badge"]').value = badge;
            document.querySelector('[name="bitrix_id"]').value = bitrixId;
            document.querySelector('[name="name"]').value = name;
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>

</body>

</html>