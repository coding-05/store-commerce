<?php
session_start();

$db_error = null;

try {
    $pdo = new PDO("mysql:host=localhost;dbname=estore;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $pdo = null;
    $db_error = "Connexion a la base impossible. Importez database.sql puis verifiez vos identifiants MySQL.";
}

/** Protege une valeur avant affichage HTML pour eviter les attaques XSS. */
function e($valeur) {
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

/** Retourne le token CSRF de session ou en cree un nouveau. */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Affiche un champ cache contenant le token CSRF pour les formulaires POST. */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Verifie que la requete POST contient le bon token CSRF. */
function verifierCsrf() {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/** Stocke un message temporaire de succes ou d'erreur pour l'affichage suivant. */
function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Redirige vers une page interne et termine le script proprement. */
function rediriger($page = 'accueil') {
    header('Location: index.php?page=' . urlencode($page));
    exit;
}

/** Verifie si un utilisateur est connecte en session. */
function estConnecte() {
    return !empty($_SESSION['user']);
}

/** Verifie si l'utilisateur connecte possede le role admin. */
function estAdmin() {
    return estConnecte() && ($_SESSION['user']['role'] ?? '') === 'admin';
}

/** Retourne le nombre total d'articles dans le panier de session. */
function compteurPanier() {
    return array_sum($_SESSION['panier'] ?? []);
}

/** Recupere les produits, avec filtre categorie et recherche optionnels. */
function getProduits($categorie = null, $recherche = null) {
    global $pdo;
    if (!$pdo) return [];

    $conditions = [];
    $params = [];

    if ($categorie) {
        $conditions[] = 'categorie = ?';
        $params[] = $categorie;
    }
    if ($recherche) {
        $conditions[] = '(designation LIKE ? OR reference LIKE ? OR description LIKE ?)';
        $mot = '%' . $recherche . '%';
        $params[] = $mot;
        $params[] = $mot;
        $params[] = $mot;
    }

    $sql = 'SELECT * FROM produits';
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY date_ajout DESC';

    $requete = $pdo->prepare($sql);
    $requete->execute($params);
    return $requete->fetchAll();
}

/** Recupere les cinq produits les plus commandes, sinon les derniers ajoutes. */
function getProduitsPopulaires() {
    global $pdo;
    if (!$pdo) return [];

    $sql = "SELECT p.*, COALESCE(v.ventes, 0) AS ventes
            FROM produits p
            LEFT JOIN (
                SELECT produit_id, SUM(quantite) AS ventes
                FROM lignes_commandes
                GROUP BY produit_id
            ) v ON v.produit_id = p.id
            ORDER BY ventes DESC, p.date_ajout DESC
            LIMIT 5";
    return $pdo->query($sql)->fetchAll();
}

/** Simule une IA simple qui classe un produit selon des mots-cles. */
function classifierProduitIA($designation, $description) {
    $mots_femme = ['robe', 'jupe', 'tailleur', 'sac', 'escarpin', 'collier', 'bracelet', 'parfum femme'];
    $mots_homme = ['costume', 'cravate', 'chemise homme', 'chaussure homme', 'montre homme', 'parfum homme'];
    $texte = strtolower($designation . ' ' . $description);

    foreach ($mots_femme as $mot) {
        if (strpos($texte, $mot) !== false) return 'Femme';
    }
    foreach ($mots_homme as $mot) {
        if (strpos($texte, $mot) !== false) return 'Homme';
    }
    return 'Autre';
}

/** Enregistre une visite avec une geolocalisation locale simulee. */
function enregistrerVisite() {
    global $pdo;
    if (!$pdo || !empty($_SESSION['visite_enregistree'])) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $pays = in_array($ip, ['127.0.0.1', '::1'], true) ? 'Local' : 'Maroc';
    $ville = in_array($ip, ['127.0.0.1', '::1'], true) ? 'Developpement' : 'Casablanca';

    $requete = $pdo->prepare('INSERT INTO visites (ip, pays, ville) VALUES (?, ?, ?)');
    $requete->execute([$ip, $pays, $ville]);
    $_SESSION['visite_enregistree'] = true;
}

/** Calcule les statistiques principales du tableau de bord admin. */
function getStatistiques() {
    global $pdo;
    if (!$pdo) {
        return ['visites' => 0, 'commandes' => 0, 'ca' => 0, 'pays' => [], 'populaires' => []];
    }

    $visites = (int) $pdo->query('SELECT COUNT(*) FROM visites')->fetchColumn();
    $commandes = (int) $pdo->query('SELECT COUNT(*) FROM commandes')->fetchColumn();
    $ca = (float) $pdo->query('SELECT COALESCE(SUM(montant_total), 0) FROM commandes')->fetchColumn();
    $pays = $pdo->query('SELECT pays, COUNT(*) AS total FROM visites GROUP BY pays ORDER BY total DESC')->fetchAll();

    return [
        'visites' => $visites,
        'commandes' => $commandes,
        'ca' => $ca,
        'pays' => $pays,
        'populaires' => getProduitsPopulaires()
    ];
}

/** Ajoute un produit au panier de session avec une quantite minimale de 1. */
function ajouterAuPanier($produit_id, $quantite) {
    $_SESSION['panier'] ??= [];
    $produit_id = (int) $produit_id;
    $quantite = max(1, (int) $quantite);
    $_SESSION['panier'][$produit_id] = ($_SESSION['panier'][$produit_id] ?? 0) + $quantite;
}

/** Vide totalement le panier de session. */
function viderPanier() {
    $_SESSION['panier'] = [];
}

/** Charge les lignes detaillees du panier depuis la base. */
function getPanierDetails() {
    global $pdo;
    $panier = $_SESSION['panier'] ?? [];
    if (!$pdo || !$panier) return ['lignes' => [], 'total' => 0];

    $ids = array_keys($panier);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $requete = $pdo->prepare("SELECT * FROM produits WHERE id IN ($placeholders)");
    $requete->execute($ids);
    $produits = $requete->fetchAll();

    $lignes = [];
    $total = 0;
    foreach ($produits as $produit) {
        $quantite = (int) ($panier[$produit['id']] ?? 0);
        if ($quantite < 1) continue;
        $sous_total = $quantite * (float) $produit['prix'];
        $total += $sous_total;
        $lignes[] = ['produit' => $produit, 'quantite' => $quantite, 'sous_total' => $sous_total];
    }

    return ['lignes' => $lignes, 'total' => $total];
}

/** Valide le panier en commande, cree les lignes et baisse le stock. */
function validerCommande($user_id) {
    global $pdo;
    $details = getPanierDetails();
    if (!$pdo || !$details['lignes']) return false;

    try {
        $pdo->beginTransaction();
        foreach ($details['lignes'] as $ligne) {
            if ($ligne['quantite'] > (int) $ligne['produit']['stock']) {
                throw new Exception('Stock insuffisant pour ' . $ligne['produit']['designation']);
            }
        }

        $requete = $pdo->prepare('INSERT INTO commandes (user_id, montant_total) VALUES (?, ?)');
        $requete->execute([(int) $user_id, $details['total']]);
        $commande_id = (int) $pdo->lastInsertId();

        $insert_ligne = $pdo->prepare('INSERT INTO lignes_commandes (commande_id, produit_id, quantite, prix_unitaire) VALUES (?, ?, ?, ?)');
        $update_stock = $pdo->prepare('UPDATE produits SET stock = stock - ? WHERE id = ?');

        foreach ($details['lignes'] as $ligne) {
            $produit = $ligne['produit'];
            $insert_ligne->execute([$commande_id, $produit['id'], $ligne['quantite'], $produit['prix']]);
            $update_stock->execute([$ligne['quantite'], $produit['id']]);
        }

        $pdo->commit();
        viderPanier();
        return true;
    } catch (Exception $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $exception->getMessage());
        return false;
    }
}

/** Recupere l'historique des commandes d'un utilisateur client. */
function getCommandesUtilisateur($user_id) {
    global $pdo;
    if (!$pdo) return [];
    $requete = $pdo->prepare('SELECT * FROM commandes WHERE user_id = ? ORDER BY date_commande DESC');
    $requete->execute([(int) $user_id]);
    return $requete->fetchAll();
}

/** Affiche une carte produit reutilisable dans les pages boutique. */
function afficherCarteProduit($produit) {
    ?>
    <article class="product-card"
        data-product-card
        data-category="<?= e($produit['categorie']) ?>"
        data-size="<?= e($produit['taille'] ?? '') ?>"
        data-price="<?= e($produit['prix']) ?>">
        <a href="index.php?page=produit&id=<?= (int) $produit['id'] ?>">
            <img src="<?= e($produit['image_url'] ?: 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=900&q=80') ?>" alt="<?= e($produit['designation']) ?>">
        </a>
        <div class="product-body">
            <h3><?= e($produit['designation']) ?></h3>
            <div class="meta"><?= e($produit['categorie']) ?> - <?= e($produit['taille'] ?: 'Taille libre') ?> - Stock <?= (int) $produit['stock'] ?></div>
            <div class="price"><?= number_format((float) $produit['prix'], 2, ',', ' ') ?> DH</div>
            <form method="post" action="index.php" data-add-cart data-product-name="<?= e($produit['designation']) ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="ajouter_panier">
                <input type="hidden" name="produit_id" value="<?= (int) $produit['id'] ?>">
                <input type="hidden" name="quantite" value="1">
                <button type="submit" <?= ((int) $produit['stock'] <= 0) ? 'disabled' : '' ?>>Ajouter au panier</button>
            </form>
        </div>
    </article>
    <?php
}

enregistrerVisite();

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$page = $_GET['page'] ?? 'accueil';

if ($action === 'admin_statistiques') {
    $page = 'admin';
}

if ($action && $_SERVER['REQUEST_METHOD'] === 'POST' && !verifierCsrf()) {
    flash('error', 'Protection formulaire invalide. Veuillez recommencer.');
    rediriger($page);
}

try {
    if ($pdo && $action === 'connexion') {
        $email = trim($_POST['email'] ?? '');
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';
        $requete = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $requete->execute([$email]);
        $utilisateur = $requete->fetch();
        $setup_admin = $utilisateur
            && $utilisateur['email'] === 'admin@estore.com'
            && $utilisateur['mot_de_passe'] === 'SETUP_ADMIN123_REHASH_REQUIRED'
            && $mot_de_passe === 'admin123';

        if ($utilisateur && (password_verify($mot_de_passe, $utilisateur['mot_de_passe']) || $setup_admin)) {
            if ($setup_admin) {
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET mot_de_passe = ? WHERE id = ?')->execute([$hash, $utilisateur['id']]);
                $utilisateur['mot_de_passe'] = $hash;
            }
            $_SESSION['user'] = [
                'id' => $utilisateur['id'],
                'nom' => $utilisateur['nom'],
                'email' => $utilisateur['email'],
                'role' => $utilisateur['role']
            ];
            if (!empty($_POST['remember'])) {
                setcookie('remember_email', $email, time() + 60 * 60 * 24 * 30, '', '', false, true);
            }
            flash('success', 'Connexion reussie. Bonjour ' . $utilisateur['nom'] . ' !');
            rediriger('accueil');
        }
        flash('error', 'Email ou mot de passe incorrect.');
        rediriger('connexion');
    }

    if ($pdo && $action === 'inscription') {
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';

        if (!$nom || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($mot_de_passe) < 6) {
            flash('error', 'Nom, email valide et mot de passe de 6 caracteres minimum requis.');
            rediriger('connexion');
        }

        $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
        $requete = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe, role) VALUES (?, ?, ?, "client")');
        $requete->execute([$nom, $email, $hash]);
        flash('success', 'Compte cree. Vous pouvez vous connecter.');
        rediriger('connexion');
    }

    if ($action === 'deconnexion') {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if ($pdo && $action === 'ajouter_panier') {
        ajouterAuPanier($_POST['produit_id'] ?? 0, $_POST['quantite'] ?? 1);
        flash('success', 'Produit ajoute au panier.');
        rediriger('panier');
    }

    if ($action === 'supprimer_panier') {
        unset($_SESSION['panier'][(int) ($_POST['produit_id'] ?? 0)]);
        flash('success', 'Produit retire du panier.');
        rediriger('panier');
    }

    if ($action === 'vider_panier') {
        viderPanier();
        flash('success', 'Panier vide.');
        rediriger('panier');
    }

    if ($pdo && $action === 'valider_commande') {
        if (!estConnecte()) {
            flash('error', 'Connectez-vous pour valider votre commande.');
            rediriger('connexion');
        }
        if (validerCommande($_SESSION['user']['id'])) {
            flash('success', 'Commande validee avec succes.');
            rediriger('compte');
        }
        rediriger('panier');
    }

    if ($pdo && in_array($action, ['admin_ajouter_produit', 'admin_modifier_produit', 'admin_supprimer_produit'], true)) {
        if (!estAdmin()) {
            flash('error', 'Acces reserve a l administrateur.');
            rediriger('accueil');
        }

        if ($action === 'admin_supprimer_produit') {
            $pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([(int) ($_POST['produit_id'] ?? 0)]);
            flash('success', 'Produit supprime.');
            rediriger('admin');
        }

        $designation = trim($_POST['designation'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categorie = trim($_POST['categorie'] ?? '') ?: classifierProduitIA($designation, $description);
        $donnees = [
            trim($_POST['reference'] ?? ''),
            $designation,
            $description,
            (float) ($_POST['prix'] ?? 0),
            $categorie,
            trim($_POST['image_url'] ?? ''),
            trim($_POST['taille'] ?? ''),
            trim($_POST['couleur'] ?? ''),
            max(0, (int) ($_POST['stock'] ?? 0))
        ];

        if ($action === 'admin_ajouter_produit') {
            $sql = 'INSERT INTO produits (reference, designation, description, prix, categorie, image_url, taille, couleur, stock)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $pdo->prepare($sql)->execute($donnees);
            flash('success', 'Produit ajoute. Categorie IA: ' . $categorie);
        } else {
            $donnees[] = (int) ($_POST['produit_id'] ?? 0);
            $sql = 'UPDATE produits SET reference = ?, designation = ?, description = ?, prix = ?, categorie = ?, image_url = ?, taille = ?, couleur = ?, stock = ? WHERE id = ?';
            $pdo->prepare($sql)->execute($donnees);
            flash('success', 'Produit modifie.');
        }
        rediriger('admin');
    }
} catch (Exception $exception) {
    flash('error', 'Erreur: ' . $exception->getMessage());
    rediriger($page);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$produits_page = [];
$titre_page = 'E-STORE';

if ($page === 'femme') {
    $produits_page = getProduits('Femme');
    $titre_page = 'Collection Femme';
} elseif ($page === 'homme') {
    $produits_page = getProduits('Homme');
    $titre_page = 'Collection Homme';
} elseif ($page === 'recherche') {
    $recherche = trim($_GET['q'] ?? '');
    $produits_page = getProduits(null, $recherche);
    $titre_page = 'Recherche';
}

if ($page === 'compte' && !estConnecte()) {
    flash('error', 'Connectez-vous pour acceder a votre compte.');
    rediriger('connexion');
}

if ($page === 'admin' && !estAdmin()) {
    flash('error', 'Acces reserve a l administrateur.');
    rediriger('accueil');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titre_page) ?> - E-STORE</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="loading" data-loading><div class="spinner"></div></div>
<header class="topbar">
    <nav class="nav">
        <a class="brand" href="index.php">E-STORE</a>
        <button class="burger" type="button" data-burger aria-label="Menu">Menu</button>
        <div class="menu" data-menu>
            <a class="<?= $page === 'accueil' ? 'active' : '' ?>" href="index.php?page=accueil">Accueil</a>
            <a class="<?= $page === 'femme' ? 'active' : '' ?>" href="index.php?page=femme">Femme</a>
            <a class="<?= $page === 'homme' ? 'active' : '' ?>" href="index.php?page=homme">Homme</a>
            <a class="<?= $page === 'panier' ? 'active' : '' ?>" href="index.php?page=panier">Panier (<?= compteurPanier() ?>)</a>
            <?php if (estConnecte()): ?>
                <a href="index.php?page=compte">Bonjour <?= e($_SESSION['user']['nom']) ?></a>
                <?php if (estAdmin()): ?><a href="index.php?page=admin">Admin</a><?php endif; ?>
                <a href="index.php?action=deconnexion">Deconnexion</a>
            <?php else: ?>
                <a class="<?= $page === 'connexion' ? 'active' : '' ?>" href="index.php?page=connexion">Connexion</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="container">
    <?php if ($db_error): ?><div class="flash error"><?= e($db_error) ?></div><?php endif; ?>
    <?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

    <?php if ($page === 'accueil'): ?>
        <section class="hero">
            <div>
                <h1>E-STORE</h1>
                <p>Mode femme et homme, panier simple, commandes securisees et classement automatique des nouveaux produits.</p>
                <a class="btn warning" href="index.php?page=femme">Voir les collections</a>
            </div>
        </section>
        <div class="section-title"><h2>Produits populaires</h2><a class="btn light" href="index.php?page=recherche">Tout parcourir</a></div>
        <section class="grid">
            <?php foreach (getProduitsPopulaires() as $produit) afficherCarteProduit($produit); ?>
        </section>

    <?php elseif (in_array($page, ['femme', 'homme', 'recherche'], true)): ?>
        <div class="section-title">
            <h1><?= e($titre_page) ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="recherche">
                <input name="q" placeholder="Rechercher..." value="<?= e($_GET['q'] ?? '') ?>">
            </form>
        </div>
        <section class="filters" data-filters>
            <div class="field"><label>Categorie</label><select name="filtre_categorie"><option value="">Toutes</option><option>Femme</option><option>Homme</option><option>Autre</option></select></div>
            <div class="field"><label>Taille</label><select name="filtre_taille"><option value="">Toutes</option><option>S</option><option>M</option><option>L</option><option>XL</option><option>Unique</option></select></div>
            <div class="field"><label>Prix maximum</label><input type="number" name="filtre_prix" min="0" step="10" placeholder="Ex: 500"></div>
        </section>
        <section class="grid">
            <?php if (!$produits_page): ?><p>Aucun produit trouve.</p><?php endif; ?>
            <?php foreach ($produits_page as $produit) afficherCarteProduit($produit); ?>
        </section>

    <?php elseif ($page === 'produit'):
        $requete = $pdo ? $pdo->prepare('SELECT * FROM produits WHERE id = ?') : null;
        $requete?->execute([(int) ($_GET['id'] ?? 0)]);
        $produit = $requete ? $requete->fetch() : null;
        ?>
        <?php if (!$produit): ?>
            <div class="flash error">Produit introuvable.</div>
        <?php else: ?>
            <section class="admin-layout">
                <img class="product-card" src="<?= e($produit['image_url']) ?>" alt="<?= e($produit['designation']) ?>">
                <div class="panel">
                    <h1><?= e($produit['designation']) ?></h1>
                    <p class="meta"><?= e($produit['reference']) ?> - <?= e($produit['categorie']) ?> - <?= e($produit['taille']) ?> - <?= e($produit['couleur']) ?></p>
                    <p><?= e($produit['description']) ?></p>
                    <p class="price"><?= number_format((float) $produit['prix'], 2, ',', ' ') ?> DH</p>
                    <form method="post" action="index.php" data-add-cart data-product-name="<?= e($produit['designation']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="ajouter_panier">
                        <input type="hidden" name="produit_id" value="<?= (int) $produit['id'] ?>">
                        <div class="field"><label>Quantite</label><input type="number" name="quantite" value="1" min="1" max="<?= (int) $produit['stock'] ?>"></div>
                        <button type="submit">Ajouter au panier</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($page === 'panier'):
        $details = getPanierDetails();
        ?>
        <div class="section-title"><h1>Panier</h1><strong>Total: <?= number_format($details['total'], 2, ',', ' ') ?> DH</strong></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Produit</th><th>Prix</th><th>Quantite</th><th>Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($details['lignes'] as $ligne): $produit = $ligne['produit']; ?>
                    <tr>
                        <td><img class="cart-img" src="<?= e($produit['image_url']) ?>" alt=""> <?= e($produit['designation']) ?></td>
                        <td><?= number_format((float) $produit['prix'], 2, ',', ' ') ?> DH</td>
                        <td><?= (int) $ligne['quantite'] ?></td>
                        <td><?= number_format($ligne['sous_total'], 2, ',', ' ') ?> DH</td>
                        <td>
                            <form method="post">
                                <?= csrfField() ?><input type="hidden" name="action" value="supprimer_panier"><input type="hidden" name="produit_id" value="<?= (int) $produit['id'] ?>">
                                <button class="btn danger" type="submit">Retirer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$details['lignes']): ?><tr><td colspan="5">Votre panier est vide.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <p>
            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="valider_commande"><button type="submit">Valider la commande</button></form>
            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="vider_panier"><button class="btn light" type="submit">Vider</button></form>
        </p>

    <?php elseif ($page === 'connexion'): ?>
        <section class="auth-grid">
            <form class="auth-box" method="post" data-auth-form data-loading-form>
                <?= csrfField() ?><input type="hidden" name="action" value="connexion">
                <h1>Connexion</h1>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($_COOKIE['remember_email'] ?? '') ?>" required></div>
                <div class="field"><label>Mot de passe</label><input type="password" name="mot_de_passe" required minlength="6"></div>
                <label><input type="checkbox" name="remember" value="1"> Rester connecte</label>
                <button type="submit">Se connecter</button>
                <p class="meta">Admin demo: admin@estore.com / admin123</p>
            </form>
            <form class="auth-box" method="post" data-auth-form data-loading-form>
                <?= csrfField() ?><input type="hidden" name="action" value="inscription">
                <h1>Inscription</h1>
                <div class="field"><label>Nom</label><input name="nom" required></div>
                <div class="field"><label>Email</label><input type="email" name="email" required></div>
                <div class="field"><label>Mot de passe</label><input type="password" name="mot_de_passe" required minlength="6"></div>
                <button type="submit">Creer le compte</button>
            </form>
        </section>

    <?php elseif ($page === 'compte'): ?>
        <h1>Mon compte</h1>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Commande</th><th>Date</th><th>Statut</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach (getCommandesUtilisateur($_SESSION['user']['id']) as $commande): ?>
                    <tr><td>#<?= (int) $commande['id'] ?></td><td><?= e($commande['date_commande']) ?></td><td><?= e($commande['statut']) ?></td><td><?= number_format((float) $commande['montant_total'], 2, ',', ' ') ?> DH</td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($page === 'admin'): ?>
        <?php $stats = getStatistiques(); $produits_admin = getProduits(); ?>
        <h1>Tableau de bord admin</h1>
        <section class="stats">
            <div class="stat"><span>Visites</span><strong><?= (int) $stats['visites'] ?></strong></div>
            <div class="stat"><span>Commandes</span><strong><?= (int) $stats['commandes'] ?></strong></div>
            <div class="stat"><span>CA total</span><strong><?= number_format((float) $stats['ca'], 2, ',', ' ') ?> DH</strong></div>
        </section>
        <section class="admin-layout">
            <div class="panel">
                <h2>Ajouter un produit</h2>
                <form method="post" class="form-grid">
                    <?= csrfField() ?><input type="hidden" name="action" value="admin_ajouter_produit">
                    <div class="field"><label>Reference</label><input name="reference" required></div>
                    <div class="field"><label>Designation</label><input name="designation" required></div>
                    <div class="field full"><label>Description</label><textarea name="description"></textarea></div>
                    <div class="field"><label>Prix</label><input type="number" step="0.01" name="prix" required></div>
                    <div class="field"><label>Categorie vide = IA</label><select name="categorie"><option value="">Auto IA</option><option>Femme</option><option>Homme</option><option>Autre</option></select></div>
                    <div class="field"><label>Image URL</label><input name="image_url"></div>
                    <div class="field"><label>Taille</label><input name="taille"></div>
                    <div class="field"><label>Couleur</label><input name="couleur"></div>
                    <div class="field"><label>Stock</label><input type="number" name="stock" min="0" required></div>
                    <button class="full" type="submit">Ajouter</button>
                </form>
                <h2>Visites par pays</h2>
                <?php $max_visites = max(1, ...array_column($stats['pays'], 'total')); ?>
                <?php foreach ($stats['pays'] as $pays): ?>
                    <div class="bar-row"><span><?= e($pays['pays']) ?></span><div class="bar"><span style="width:<?= (int) ($pays['total'] / $max_visites * 100) ?>%"></span></div><strong><?= (int) $pays['total'] ?></strong></div>
                <?php endforeach; ?>
            </div>
            <div class="panel table-wrap">
                <h2>Gestion produits</h2>
                <table>
                    <thead><tr><th>Modifier les produits</th></tr></thead>
                    <tbody>
                    <?php foreach ($produits_admin as $produit): ?>
                        <tr>
                            <td>
                                <form method="post" class="form-grid admin-product-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="produit_id" value="<?= (int) $produit['id'] ?>">
                                    <div class="field"><label>Reference</label><input name="reference" value="<?= e($produit['reference']) ?>" required></div>
                                    <div class="field"><label>Designation</label><input name="designation" value="<?= e($produit['designation']) ?>" required></div>
                                    <div class="field"><label>Prix</label><input type="number" step="0.01" name="prix" value="<?= e($produit['prix']) ?>" required></div>
                                    <div class="field"><label>Categorie</label><select name="categorie"><option <?= $produit['categorie'] === 'Femme' ? 'selected' : '' ?>>Femme</option><option <?= $produit['categorie'] === 'Homme' ? 'selected' : '' ?>>Homme</option><option <?= $produit['categorie'] === 'Autre' ? 'selected' : '' ?>>Autre</option></select></div>
                                    <div class="field"><label>Taille</label><input name="taille" value="<?= e($produit['taille']) ?>"></div>
                                    <div class="field"><label>Couleur</label><input name="couleur" value="<?= e($produit['couleur']) ?>"></div>
                                    <div class="field"><label>Stock</label><input type="number" name="stock" min="0" value="<?= (int) $produit['stock'] ?>" required></div>
                                    <div class="field full"><label>Image URL</label><input name="image_url" value="<?= e($produit['image_url']) ?>"></div>
                                    <div class="field full"><label>Description</label><textarea name="description"><?= e($produit['description']) ?></textarea></div>
                                    <button type="submit" name="action" value="admin_modifier_produit">Modifier</button>
                                    <button class="btn danger" type="submit" name="action" value="admin_supprimer_produit">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php else: ?>
        <div class="flash error">Page introuvable.</div>
    <?php endif; ?>
</main>

<footer class="footer">
    E-STORE - Boutique PHP MVC simple avec PDO, sessions, panier et classification IA simulee.
</footer>
<script src="script.js"></script>
</body>
</html>
