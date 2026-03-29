<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Enotf\ProtocolTypeService;

if (!isset($_SESSION['userid']) || !Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    Flash::set('error', 'Ungültige ID.');
    header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/index.php");
    exit();
}

$typeService = new ProtocolTypeService($pdo);
$type = $typeService->getType($id);

if (!$type) {
    Flash::set('error', 'Protokolltyp nicht gefunden.');
} elseif ($type['is_builtin']) {
    Flash::set('error', 'System-Protokolltypen können nicht gelöscht werden.');
} else {
    // Check if protocols exist with this type
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM intra_edivi WHERE protocol_type_id = :id");
    $stmt->execute(['id' => $id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($count > 0) {
        Flash::set('error', 'Protokolltyp kann nicht gelöscht werden, es existieren ' . $count . ' Protokolle mit diesem Typ.');
    } else {
        $typeService->deleteType($id);
        Flash::set('success', 'Protokolltyp "' . htmlspecialchars($type['name']) . '" wurde gelöscht.');
    }
}

header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/index.php");
exit();
