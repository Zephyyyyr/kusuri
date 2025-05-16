<?php
include 'db.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = "";
$success = "";

// --- Traitement des actions pour utilisateurs connectés ---
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && isset($_GET['action'])) {
    // Suppression d'un rendez-vous
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("DELETE FROM visites WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $success = "Rendez-vous supprimé avec succès.";
        header("Location: visites");
        exit();
    }
    // Mise à jour du statut d'un rendez-vous
    if ($_GET['action'] === 'update' && isset($_GET['id'])) {
        // Si le formulaire d'update a été soumis
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
            $new_status = $_POST['new_status'];
            $stmt = $pdo->prepare("UPDATE visites SET statut = ? WHERE id = ?");
            $stmt->execute([$new_status, $_GET['id']]);
            $success = "Statut mis à jour avec succès.";
            header("Location: visites");
            exit();
        } else {
            // On mémorise l'ID du rendez-vous à mettre à jour pour afficher le formulaire
            $update_id = $_GET['id'];
        }
    }
}

// --- Traitement du formulaire de réservation (pour tous) ---
// Chaque réservation insère une nouvelle entrée dans la table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $nom          = trim($_POST['nom'] ?? '');
    $date_visite  = $_POST['date_visite']  ?? '';
    $heure_visite = $_POST['heure_visite'] ?? '';
    $raison       = trim($_POST['raison'] ?? '');

    if (empty($nom)) {
        $error = "Votre nom est obligatoire.";
    } elseif (empty($date_visite) || empty($heure_visite) || empty($raison)) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        // Insertion d'un nouveau rendez-vous
        $stmt = $pdo->prepare("INSERT INTO visites (date_visite, heure_visite, raison, nom, statut) VALUES (?, ?, ?, ?, 'en attente')");
        $stmt->execute([$date_visite, $heure_visite, $raison, $nom]);
        $appointment_id = $pdo->lastInsertId();
        // Pour les utilisateurs non connectés, on stocke l'ID du dernier rendez-vous créé
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            setcookie("appointment_id", $appointment_id, time() + (30 * 24 * 3600), "/");
        }
        $success = "Rendez-vous réservé avec succès.";
        header("Location: visites");
        exit();
    }
}

// --- Récupération des rendez-vous à afficher ---
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    // Pour les utilisateurs connectés, on ajoute la recherche et la pagination
    $search = trim($_GET['search'] ?? '');
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) { $page = 1; }
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    if (!empty($search)) {
        $searchParam = '%' . $search . '%';
        // Compter le nombre total de résultats pour la recherche
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM visites WHERE nom LIKE ?");
        $countStmt->execute([$searchParam]);
        $total_results = $countStmt->fetchColumn();
        
        if ($total_results == 1) {
            // Si un seul résultat, on l'affiche sans pagination
            $stmt = $pdo->prepare("SELECT * FROM visites WHERE nom LIKE ? ORDER BY date_visite, heure_visite");
            $stmt->execute([$searchParam]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_pages = 1;
            $page = 1;
        } else {
            $stmt = $pdo->prepare("SELECT * FROM visites WHERE nom LIKE ? ORDER BY date_visite, heure_visite LIMIT ? OFFSET ?");
            $stmt->bindParam(1, $searchParam, PDO::PARAM_STR);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->bindParam(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_pages = ceil($total_results / $limit);
        }
    } else {
        // Sans recherche : récupération de tous les rendez-vous avec pagination
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM visites");
        $countStmt->execute();
        $total_results = $countStmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT * FROM visites ORDER BY date_visite, heure_visite LIMIT ? OFFSET ?");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = ceil($total_results / $limit);
    }
} else {
    // Utilisateur non connecté : on affiche uniquement le rendez-vous correspondant au cookie (s'il existe)
    if (isset($_COOKIE['appointment_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM visites WHERE id = ?");
        $stmt->execute([$_COOKIE['appointment_id']]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $appointments = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Kusuri - Visites Médicales</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Formulaire de recherche (pour utilisateurs connectés) */
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
        /* Design amélioré pour le formulaire de réservation */
        .appointment-booking {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            text-align: center;
        }
        .booking-form .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .booking-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .booking-form input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .booking-form button {
            width: 100%;
            padding: 10px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .booking-form button:hover {
            background: #ff2222;
        }
        /* Styles pour les actions (modifier/supprimer) */
        .action-links a {
            margin-right: 10px;
            text-decoration: none;
            color: var(--primary);
        }
        .action-links a:hover {
            text-decoration: underline;
        }
        .update-form {
            max-width: 300px;
            margin: 10px auto;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
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
            <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
                <li><a href="patients">Liste des Patients</a></li>
                <li><a href="dossiers">Dossiers Médicaux</a></li>
				<li><a href="psy">Dossiers Psy</a></li>
                <li><a href="visites">Visites Médicales</a></li>
                <li><a href="deces">Ninjas Décédés</a></li>
                <li><a href="staff">Personnel Médical</a></li>
                <li><a href="logout">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="visites">Prendre Rendez-vous</a></li>
                <li><a href="login">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main>
        <h2 style="text-align: center;">Visites Médicales</h2>

        <?php if ($error): ?>
            <p class="message" style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="message" style="color:green;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
            <!-- Formulaire de recherche (pour utilisateurs connectés) -->
            <div class="search-container">
                <form method="get" action="visites">
                    <input type="text" name="search" placeholder="Rechercher par nom..." value="<?= htmlspecialchars($search ?? '') ?>">
                    <button type="submit">Rechercher</button>
                </form>
            </div>
            <h3 style="text-align: center;">Liste complète des rendez-vous</h3>
            <?php if (count($appointments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Raison</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= htmlspecialchars($appointment['id']) ?></td>
                            <td><?= htmlspecialchars($appointment['nom']) ?></td>
                            <td><?= htmlspecialchars($appointment['date_visite']) ?></td>
                            <td><?= htmlspecialchars($appointment['heure_visite']) ?></td>
                            <td><?= htmlspecialchars($appointment['raison']) ?></td>
                            <td><?= htmlspecialchars($appointment['statut']) ?></td>
                            <td class="action-links">
                                <a href="visites?action=update&id=<?= htmlspecialchars($appointment['id']) ?>">Modifier</a>
                                <a href="visites?action=delete&id=<?= htmlspecialchars($appointment['id']) ?>" onclick="return confirm('Voulez-vous vraiment supprimer ce rendez-vous ?');">Supprimer</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Liens de pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="visites?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Précédent</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p == $page): ?>
                                <span class="current-page"><?= $p ?></span>
                            <?php else: ?>
                                <a href="visites?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="visites?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Suivant</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="message">Aucun rendez-vous trouvé.</p>
            <?php endif; ?>
        <?php else: ?>
            <!-- Pour les utilisateurs non connectés, affichage centré dans la div appointment-booking -->
            <div class="appointment-booking">
                <h3>Votre rendez-vous</h3>
                <?php if (count($appointments) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Raison</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['id']) ?></td>
                                <td><?= htmlspecialchars($appointment['nom']) ?></td>
                                <td><?= htmlspecialchars($appointment['date_visite']) ?></td>
                                <td><?= htmlspecialchars($appointment['heure_visite']) ?></td>
                                <td><?= htmlspecialchars($appointment['raison']) ?></td>
                                <td><?= htmlspecialchars($appointment['statut']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="message">Vous n'avez pas encore pris de rendez-vous.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de réservation au design amélioré (accessible à tous) -->
        <div class="appointment-booking">
            <h3>Prendre un rendez-vous</h3>
            <form method="post" action="visites" class="booking-form">
                <div class="form-group">
                    <label for="nom">Votre nom :</label>
                    <input type="text" id="nom" name="nom" required placeholder="Votre nom">
                </div>
                <div class="form-group">
                    <label for="date_visite">Date :</label>
                    <input type="date" id="date_visite" name="date_visite" required>
                </div>
                <div class="form-group">
                    <label for="heure_visite">Heure :</label>
                    <input type="time" id="heure_visite" name="heure_visite" required>
                </div>
                <div class="form-group">
                    <label for="raison">Raison :</label>
                    <input type="text" id="raison" name="raison" required placeholder="Exemple : Consultation de routine">
                </div>
                <button type="submit">Réserver</button>
            </form>
        </div>
    </main>
</body>
</html>
