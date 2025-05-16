<?php
include 'auth.php';
include 'db.php';
session_start();

$error = "";
$success = "";

// Suppression d'un membre
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $success = "Membre supprimé avec succès.";
    header("Location: staff");
    exit();
}

// Mise à jour d'un membre
if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_GET['id'];
        $nom = trim($_POST['nom'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $groupe_sanguin = trim($_POST['groupe_sanguin'] ?? '');
        if (empty($nom)) {
            $error = "Le nom est obligatoire.";
        } else {
            $stmt = $pdo->prepare("UPDATE staff SET nom = ?, role = ?, groupe_sanguin = ? WHERE id = ?");
            $stmt->execute([$nom, $role, $groupe_sanguin, $id]);
            $success = "Membre mis à jour avec succès.";
            header("Location: staff");
            exit();
        }
    } else {
        // Pré-remplissage du formulaire de modification
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $staff_to_update = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Traitement du formulaire d'ajout (sans action GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $nom = trim($_POST['nom'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $groupe_sanguin = trim($_POST['groupe_sanguin'] ?? '');
    if (empty($nom)) {
        $error = "Le nom est obligatoire.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO staff (nom, role, groupe_sanguin) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $role, $groupe_sanguin]);
        $success = "Membre ajouté avec succès.";
        header("Location: staff");
        exit();
    }
}

// --- Recherche & Pagination ---
// Récupération du terme de recherche et configuration de la pagination
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$limit = 10;
$offset = ($page - 1) * $limit;

if (!empty($search)) {
    $searchParam = '%' . $search . '%';
    // Compter le nombre total de résultats
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE nom LIKE ?");
    $countStmt->execute([$searchParam]);
    $total_results = $countStmt->fetchColumn();
    
    if ($total_results == 1) {
        // Si un seul résultat, on l'affiche sans pagination
        $query = "SELECT * FROM staff WHERE nom LIKE ? ORDER BY id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$searchParam]);
        $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = 1;
        $page = 1;
    } else {
        $query = "SELECT * FROM staff WHERE nom LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = ceil($total_results / $limit);
    }
} else {
    // Sans recherche : récupération de tous les membres avec pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM staff");
    $countStmt->execute();
    $total_results = $countStmt->fetchColumn();
    
    $query = "SELECT * FROM staff ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_pages = ceil($total_results / $limit);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Kusuri - Personnel Médical</title>
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
        /* Styles pour le formulaire d'ajout et de modification */
        .form-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .form-container h3 {
            text-align: center;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group button {
            width: 100%;
            padding: 10px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-group button:hover {
            background: #ff2222;
        }
        /* Styles pour la table */
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        .action-links a {
            margin: 0 5px;
            text-decoration: none;
            color: var(--primary);
        }
        .action-links a:hover {
            text-decoration: underline;
        }
        .message {
            text-align: center;
            font-weight: bold;
            margin: 10px;
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
        <h2 style="text-align: center;">Personnel Médical</h2>
        
        <!-- Formulaire de recherche centré -->
        <div class="search-container">
            <form method="get" action="staff.php">
                <input type="text" name="search" placeholder="Rechercher un membre..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Rechercher</button>
            </form>
        </div>
        
        <?php if ($error): ?>
            <p class="message" style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="message" style="color:green;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        
        <!-- Affichage de la liste du personnel -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Rôle</th>
                    <th>Groupe Sanguin</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($staffMembers) > 0): ?>
                    <?php foreach($staffMembers as $staff): ?>
                        <tr>
                            <td><?= htmlspecialchars($staff['id']) ?></td>
                            <td><?= htmlspecialchars($staff['nom']) ?></td>
                            <td><?= htmlspecialchars($staff['role']) ?></td>
                            <td><?= htmlspecialchars($staff['groupe_sanguin']) ?></td>
                            <td class="action-links">
                                <a href="staff?action=update&id=<?= htmlspecialchars($staff['id']) ?>">Modifier</a>
                                <a href="staff?action=delete&id=<?= htmlspecialchars($staff['id']) ?>" onclick="return confirm('Voulez-vous vraiment supprimer ce membre ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">Aucun membre trouvé.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Liens de pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="staff?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Précédent</a>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="current-page"><?= $p ?></span>
                    <?php else: ?>
                        <a href="staff?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="staff?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Suivant</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire pour ajouter un nouveau membre -->
        <div class="form-container">
            <h3>Ajouter un Membre du Personnel</h3>
            <form method="post" action="staff">
                <div class="form-group">
                    <label for="nom">Nom :</label>
                    <input type="text" id="nom" name="nom" placeholder="Nom du membre" required>
                </div>
                <div class="form-group">
                    <label for="role">Rôle :</label>
                    <input type="text" id="role" name="role" placeholder="Rôle du membre">
                </div>
                <div class="form-group">
                    <label for="groupe_sanguin">Groupe Sanguin :</label>
                    <input type="text" id="groupe_sanguin" name="groupe_sanguin" placeholder="Groupe sanguin">
                </div>
                <div class="form-group">
                    <button type="submit">Ajouter</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
