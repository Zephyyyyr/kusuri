<?php
session_start();

// Définir le mot de passe à utiliser pour accéder à la page
$spy_password = "Y44d52EXCu3xdw"; // Remplacez "monmotdepasse" par le mot de passe souhaité

// Vérification du mot de passe pour l'accès à la page
if (!isset($_SESSION['spy_authenticated'])) {
    if (isset($_POST['spy_password'])) {
        if ($_POST['spy_password'] === $spy_password) {
            $_SESSION['spy_authenticated'] = true;
        } else {
            $error = "Mot de passe incorrect.";
        }
    }
    
    if (!isset($_SESSION['spy_authenticated'])) {
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Accès Restreint</title>
        </head>
        <body>
            <h2>Accès Restreint</h2>
            <?php if(isset($error)) echo "<p style='color:red;'>".htmlspecialchars($error)."</p>"; ?>
            <form method="post">
                <input type="password" name="spy_password" placeholder="Mot de passe" required>
                <button type="submit">Valider</button>
            </form>
        </body>
        </html>
        <?php
        exit();
    }
}

include 'auth.php';
include 'db.php';

$error = "";
$success = "";

// Suppression d'un dossier psy
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM psy WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $success = "Dossier psy supprimé avec succès.";
    header("Location: psy");
    exit();
}

// Mise à jour d'un dossier psy
if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_GET['id'];
        $patient_id = $_POST['patient_id'] ?? "";
        $details = trim($_POST['details'] ?? "");
        $date_creation = $_POST['date_creation'] ?? "";
        if (empty($patient_id) || empty($details) || empty($date_creation)) {
            $error = "Tous les champs sont requis pour la mise à jour.";
        } else {
            $stmt = $pdo->prepare("UPDATE psy SET patient_id = ?, details = ?, date_creation = ? WHERE id = ?");
            $stmt->execute([$patient_id, $details, $date_creation, $id]);
            $success = "Dossier psy mis à jour avec succès.";
            header("Location: psy");
            exit();
        }
    } else {
        // Récupération du dossier psy à modifier pour pré-remplir le formulaire
        $stmt = $pdo->prepare("SELECT * FROM psy WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $psy_to_update = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Ajout d'un dossier psy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $patient_id = $_POST['patient_id'] ?? "";
    $details = trim($_POST['details'] ?? "");
    $date_creation = $_POST['date_creation'] ?? "";
    if (empty($patient_id) || empty($details) || empty($date_creation)) {
        $error = "Tous les champs sont requis pour ajouter un dossier psy.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO psy (patient_id, details, date_creation) VALUES (?, ?, ?)");
        $stmt->execute([$patient_id, $details, $date_creation]);
        $success = "Dossier psy ajouté avec succès.";
        header("Location: psy");
        exit();
    }
}

// Gestion de la recherche et de la pagination
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) { 
    $page = 1; 
}
$limit = 10;
$offset = ($page - 1) * $limit;

if (!empty($search)) {
    $searchParam = '%' . $search . '%';
    // Compter le nombre de résultats
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM psy 
        INNER JOIN patients ON psy.patient_id = patients.id 
        WHERE patients.nom LIKE ?");
    $countStmt->execute([$searchParam]);
    $total_results = $countStmt->fetchColumn();

    // Si un seul résultat, on le sélectionne sans pagination
    if ($total_results == 1) {
        $query = "SELECT psy.*, patients.nom AS patient_nom FROM psy 
            INNER JOIN patients ON psy.patient_id = patients.id 
            WHERE patients.nom LIKE ? ORDER BY psy.id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$searchParam]);
        $psy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = 1;
        $page = 1;
    } else {
        $query = "SELECT psy.*, patients.nom AS patient_nom FROM psy 
            INNER JOIN patients ON psy.patient_id = patients.id 
            WHERE patients.nom LIKE ? ORDER BY psy.id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $psy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = ceil($total_results / $limit);
    }
} else {
    // Sans recherche : récupération de tous les dossiers psy avec pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM psy");
    $countStmt->execute();
    $total_results = $countStmt->fetchColumn();

    $query = "SELECT psy.*, patients.nom AS patient_nom FROM psy 
        INNER JOIN patients ON psy.patient_id = patients.id 
        ORDER BY psy.id DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $psy = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_pages = ceil($total_results / $limit);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Kusuri - Dossiers Psy</title>
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
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .form-container h3 {
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group button {
            width: 100%;
            padding: 10px;
            background: var(--primary);
            border: none;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
        }
        .form-group button:hover {
            background: #ff2222;
        }
        /* Styles pour la grille des dossiers */
        .dossier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px;
        }
        .dossier-card {
            background: #fff;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }
        .dossier-card h3 {
            margin-top: 0;
        }
        .dossier-card small {
            display: block;
            margin-top: 10px;
            color: #555;
        }
        .dossier-card .action-links {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .dossier-card .action-links a {
            margin-left: 5px;
            text-decoration: none;
            color: var(--primary);
        }
        .dossier-card .action-links a:hover {
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
        <h2 style="text-align: center;">Dossiers Psy</h2>
        
        <!-- Formulaire de recherche centré -->
        <div class="search-container">
            <form method="get" action="psy.php">
                <input type="text" name="search" placeholder="Rechercher un dossier par nom de patient..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Rechercher</button>
            </form>
        </div>
        
        <?php if ($error): ?>
            <p class="message" style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="message" style="color:green;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        
        <?php
        // Récupérer la liste des patients pour les formulaires
        $patientQuery = "SELECT id, nom FROM patients ORDER BY nom ASC";
        $patientStmt = $pdo->prepare($patientQuery);
        $patientStmt->execute();
        $patientsList = $patientStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <!-- Affichage du formulaire de modification si l'action est "update" -->
        <?php if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($psy_to_update)): ?>
        <div class="form-container">
            <h3>Modifier le Dossier Psy</h3>
            <form method="post" action="psy?action=update&id=<?= htmlspecialchars($psy_to_update['id']) ?>">
                <div class="form-group">
                    <label for="patient_id">Patient :</label>
                    <select name="patient_id" id="patient_id" required>
                        <option value="">Sélectionnez un patient</option>
                        <?php foreach ($patientsList as $patient): ?>
                            <option value="<?= htmlspecialchars($patient['id']) ?>" <?= $patient['id'] == $psy_to_update['patient_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="details">Détails :</label>
                    <textarea name="details" id="details" rows="5" required><?= htmlspecialchars($psy_to_update['details']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="date_creation">Date de création :</label>
                    <input type="date" name="date_creation" id="date_creation" required value="<?= htmlspecialchars($psy_to_update['date_creation']) ?>">
                </div>
                <div class="form-group">
                    <button type="submit">Modifier</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Affichage des dossiers psy sous forme de grille -->
        <div class="dossier-grid">
            <?php if (count($psy) > 0): ?>
                <?php foreach($psy as $dossier): ?>
                    <div class="dossier-card">
                        <div class="action-links">
                            <a href="psy?action=update&id=<?= htmlspecialchars($dossier['id']) ?>">Modifier</a>
                            <a href="psy?action=delete&id=<?= htmlspecialchars($dossier['id']) ?>" onclick="return confirm('Voulez-vous vraiment supprimer ce dossier ?');">Supprimer</a>
                        </div>
                        <h3><?= htmlspecialchars($dossier['patient_nom']) ?></h3>
                        <p><?= nl2br(htmlspecialchars($dossier['details'])) ?></p>
                        <small>Date de création : <?= htmlspecialchars($dossier['date_creation']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="message">Aucun dossier trouvé.</p>
            <?php endif; ?>
        </div>
        
        <!-- Liens de pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="psy?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Précédent</a>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="current-page"><?= $p ?></span>
                    <?php else: ?>
                        <a href="psy?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="psy?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Suivant</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire pour ajouter un nouveau dossier psy (affiché uniquement si l'on n'est pas en mode modification) -->
        <?php if (!isset($_GET['action']) || (isset($_GET['action']) && $_GET['action'] !== 'update')): ?>
        <div class="form-container">
            <h3>Ajouter un Nouveau Dossier Psy</h3>
            <form method="post" action="psy">
                <div class="form-group">
                    <label for="patient_id">Patient :</label>
                    <select name="patient_id" id="patient_id" required>
                        <option value="">Sélectionnez un patient</option>
                        <?php foreach ($patientsList as $patient): ?>
                            <option value="<?= htmlspecialchars($patient['id']) ?>"><?= htmlspecialchars($patient['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="details">Détails :</label>
                    <textarea name="details" id="details" rows="5" required placeholder="Détails du dossier psy"></textarea>
                </div>
                <div class="form-group">
                    <label for="date_creation">Date de création :</label>
                    <input type="date" name="date_creation" id="date_creation" required>
                </div>
                <div class="form-group">
                    <button type="submit">Ajouter</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
    </main>
</body>
</html>
