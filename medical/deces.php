<?php
include 'auth.php';
include 'db.php';
session_start();

$error = "";
$success = "";

// --- Traitement des actions ---
// Suppression d'un enregistrement
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM deces WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $success = "Enregistrement supprimé avec succès.";
    header("Location: deces");
    exit();
}

// Mise à jour d'un enregistrement
if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Traitement du formulaire de mise à jour
        $id = $_GET['id'];
        $patient_id = $_POST['patient_id'] ?? "";
        $date_deces = $_POST['date_deces'] ?? "";
        $cause = trim($_POST['cause'] ?? "");
        if (empty($patient_id) || empty($date_deces) || empty($cause)) {
            $error = "Tous les champs sont requis pour la mise à jour.";
        } else {
            $stmt = $pdo->prepare("UPDATE deces SET patient_id = ?, date_deces = ?, cause = ? WHERE id = ?");
            $stmt->execute([$patient_id, $date_deces, $cause, $id]);
            $success = "Enregistrement mis à jour avec succès.";
            header("Location: deces");
            exit();
        }
    } else {
        // Récupération de l'enregistrement à modifier pour pré-remplir le formulaire
        $stmt = $pdo->prepare("SELECT * FROM deces WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $deces_to_update = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// --- Traitement du formulaire d'ajout ---
// Ce formulaire est soumis sans action GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $patient_id = $_POST['patient_id'] ?? "";
    $date_deces = $_POST['date_deces'] ?? "";
    $cause = trim($_POST['cause'] ?? "");
    if (empty($patient_id) || empty($date_deces) || empty($cause)) {
        $error = "Tous les champs sont requis pour ajouter un enregistrement.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO deces (patient_id, date_deces, cause) VALUES (?, ?, ?)");
        $stmt->execute([$patient_id, $date_deces, $cause]);
        $success = "Enregistrement ajouté avec succès.";
        header("Location: deces");
        exit();
    }
}

// --- Recherche & Pagination ---
// Récupération du terme de recherche et configuration de la pagination
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$limit = 10;
$offset = ($page - 1) * $limit;

if (!empty($search)) {
    $searchParam = '%' . $search . '%';
    // Compter le nombre total de résultats (en fonction du nom du patient)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM deces 
        INNER JOIN patients ON deces.patient_id = patients.id 
        WHERE patients.nom LIKE ?");
    $countStmt->execute([$searchParam]);
    $total_results = $countStmt->fetchColumn();
    
    if ($total_results == 1) {
        // Si un seul résultat, on l'affiche sans pagination
        $query = "SELECT deces.*, patients.nom AS patient_nom FROM deces 
            INNER JOIN patients ON deces.patient_id = patients.id 
            WHERE patients.nom LIKE ? ORDER BY deces.id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$searchParam]);
        $deces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = 1;
        $page = 1;
    } else {
        $query = "SELECT deces.*, patients.nom AS patient_nom FROM deces 
            INNER JOIN patients ON deces.patient_id = patients.id 
            WHERE patients.nom LIKE ? ORDER BY deces.id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $deces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = ceil($total_results / $limit);
    }
} else {
    // Sans recherche : récupération de tous les enregistrements avec pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM deces");
    $countStmt->execute();
    $total_results = $countStmt->fetchColumn();

    $query = "SELECT deces.*, patients.nom AS patient_nom FROM deces 
        INNER JOIN patients ON deces.patient_id = patients.id 
        ORDER BY deces.id DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $deces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_pages = ceil($total_results / $limit);
}

// Récupération de la liste des patients pour alimenter les formulaires (ajout/mise à jour)
$patientQuery = "SELECT id, nom FROM patients ORDER BY nom ASC";
$patientStmt = $pdo->prepare($patientQuery);
$patientStmt->execute();
$patients = $patientStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Kusuri - Ninjas Décédés</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Formulaire de recherche centré */
        .search-container {
            display: flex;
            justify-content: center;
            margin: 20px auto;
            width: 100%;
        }
        .search-container form {
            display: flex;
            align-items: center;
        }
        .search-container input[type="text"] {
            width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .search-container button {
            padding: 10px 20px;
            margin-left: 10px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .search-container button:hover {
            background: #ff2222;
        }
        /* Styles pour les formulaires */
        .form-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .form-container h3 {
            margin-bottom: 10px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group button {
            padding: 10px;
            background: var(--primary);
            border: none;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
            width: 100%;
        }
        .form-group button:hover {
            background: #ff2222;
        }
        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 90%;
        }
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .action-links a {
            margin-right: 10px;
            text-decoration: none;
            color: var(--primary);
        }
        .action-links a:hover {
            text-decoration: underline;
        }
        /* Pagination */
        .pagination {
            text-align: center;
            margin: 20px auto;
        }
        .pagination a, .pagination span {
            display: inline-block;
            margin: 0 5px;
            padding: 8px 12px;
            text-decoration: none;
            color: var(--primary);
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .pagination a:hover {
            background: var(--primary);
            color: #fff;
        }
        .pagination .current-page {
            background: var(--primary);
            color: #fff;
            border: 1px solid var(--primary);
        }
        .message {
            text-align: center;
            font-weight: bold;
            margin: 10px;
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo">
            <svg viewBox="0 0 100 100" width="50" height="50">
                <path d="M50 5 L90 25 L90 75 L50 95 L10 75 L10 25 Z" fill="#ff4444"/>
                <text x="50" y="60" text-anchor="middle" fill="white" font-size="40">医</text>
            </svg>
            <h1>Kusuri</h1>
        </div>
        <ul>
            <li><a href="patients">Liste des Patients</a></li>
            <li><a href="dossiers">Dossiers Médicaux</a></li>
				<li><a href="psy">Dossiers Psy</a></li>
            <li><a href="visites">Visites Médicales</a></li>
            <li><a href="deces">Ninjas Décédés</a></li>
            <li><a href="staff">Personnel Médical</a></li>
            <li><a href="logout">Déconnexion</a></li>
        </ul>
    </nav>
    <main>
        <h2 style="text-align: center;">Liste des Ninjas Décédés</h2>
        
        <!-- Formulaire de recherche centré -->
        <div class="search-container">
            <form method="get" action="deces">
                <input type="text" name="search" placeholder="Rechercher par nom de patient..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Rechercher</button>
            </form>
        </div>
        
        <?php if ($error): ?>
            <p class="message" style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="message" style="color:green;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <!-- Affichage de la liste des enregistrements -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Date de Décès</th>
                    <th>Cause</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($deces) > 0): ?>
                    <?php foreach($deces as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['id']) ?></td>
                            <td><?= htmlspecialchars($d['patient_nom']) ?></td>
                            <td><?= htmlspecialchars($d['date_deces']) ?></td>
                            <td><?= htmlspecialchars($d['cause']) ?></td>
                            <td class="action-links">
                                <a href="deces?action=update&id=<?= htmlspecialchars($d['id']) ?>">Modifier</a>
                                <a href="deces?action=delete&id=<?= htmlspecialchars($d['id']) ?>" onclick="return confirm('Voulez-vous vraiment supprimer cet enregistrement ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">Aucun enregistrement trouvé.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Liens de pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="deces?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Précédent</a>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="current-page"><?= $p ?></span>
                    <?php else: ?>
                        <a href="deces?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="deces?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Suivant</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire pour ajouter un nouvel enregistrement -->
        <div class="form-container">
            <h3>Ajouter un nouvel enregistrement</h3>
            <form method="post" action="deces">
                <div class="form-group">
                    <label for="patient_id">Patient :</label>
                    <select name="patient_id" id="patient_id" required>
                        <option value="">Sélectionnez un patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>"><?= htmlspecialchars($patient['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_deces">Date de Décès :</label>
                    <input type="date" name="date_deces" id="date_deces" required>
                </div>
                <div class="form-group">
                    <label for="cause">Cause :</label>
                    <input type="text" name="cause" id="cause" required>
                </div>
                <div class="form-group">
                    <button type="submit">Ajouter</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
