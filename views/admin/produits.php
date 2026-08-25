<?php
/**
 * views/admin/produits.php — Gestion des produits
 *
 * Permet à l'admin de :
 *   - Voir tous les produits avec leur stock
 *   - Modifier le stock et le prix d'un produit
 *   - Ajouter un nouveau produit
 *   - Supprimer un produit
 */

require_once '../../includes/auth_admin.php';

$pageTitle = "Gestion des produits";
$pageCss   = "pages.css";
$basePath  = '../../';

require_once '../../includes/db.php';

$success = '';
$errors  = [];

// ── Ajout d'un nouveau produit ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter') {
    $titre       = trim($_POST['titre']       ?? '');
    $auteur      = trim($_POST['auteur']      ?? '');
    $tome        = (int)($_POST['tome']       ?? 1);
    $prix        = (float)($_POST['prix']     ?? 0);
    $stock       = (int)($_POST['stock']      ?? 0);
    $categorieId = (int)($_POST['categorie_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $image       = trim($_POST['image']       ?? '');

    // Validation
    if (empty($titre))       $errors[] = "Le titre est requis.";
    if (empty($auteur))      $errors[] = "L'auteur est requis.";
    if ($prix <= 0)          $errors[] = "Le prix doit être supérieur à 0.";
    if ($categorieId <= 0)   $errors[] = "La catégorie est requise.";
    if (empty($image))       $errors[] = "Le chemin de l'image est requis.";

    if (empty($errors)) {
        // Si un manga du même titre existe déjà (recherche insensible à la casse
        // grâce à la collation de la colonne), on réutilise son orthographe exacte
        // de titre/auteur et sa catégorie, pour que tous les tomes d'un même manga
        // restent parfaitement cohérents entre eux (évite "Dragon Ball" vs "dragon ball",
        // ou un tome classé par erreur dans une autre catégorie).
        $ref = $pdo->prepare("
            SELECT titre, auteur, categorie_id
            FROM produits
            WHERE titre = ?
            LIMIT 1
        ");
        $ref->execute([$titre]);
        $existant = $ref->fetch();

        if ($existant) {
            $titre       = $existant['titre'];
            $auteur      = $existant['auteur'];
            $categorieId = (int)$existant['categorie_id'];
        }

        $pdo->prepare("
            INSERT INTO produits (titre, auteur, tome, prix, stock, categorie_id, image, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$titre, $auteur, $tome, $prix, $stock, $categorieId, $image, $description]);
        $success = 'Produit ajouté avec succès.';
    }
}

// ── Modification du stock ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_stock') {
    $id    = (int)$_POST['produit_id'];
    $stock = max(0, (int)$_POST['stock']);
    $pdo->prepare("UPDATE produits SET stock = ? WHERE id = ?")
        ->execute([$stock, $id]);
    header('Location: produits.php?updated=1');
    exit;
}

// ── Modification du prix ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_prix') {
    $id   = (int)$_POST['produit_id'];
    $prix = max(0, (float)$_POST['prix']);
    $pdo->prepare("UPDATE produits SET prix = ? WHERE id = ?")
        ->execute([$prix, $id]);
    header('Location: produits.php?updated=1');
    exit;
}

// ── Suppression d'un produit ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {
    $id = (int)$_POST['produit_id'];
    try {
        $pdo->prepare("DELETE FROM produits WHERE id = ?")->execute([$id]);
        header('Location: produits.php?deleted=1');
        exit;
    } catch (Exception $e) {
        $errors[] = "Impossible de supprimer ce produit (il est peut-être lié à des commandes).";
    }
}

// ── Filtre par stock ──────────────────────────────────────────
$filtre = $_GET['filtre'] ?? 'tous';
$where  = '';
if ($filtre === 'rupture')    $where = 'WHERE p.stock = 0';
if ($filtre === 'faible')     $where = 'WHERE p.stock > 0 AND p.stock <= 10';
if ($filtre === 'disponible') $where = 'WHERE p.stock > 10';

// ── Chargement des catégories et produits ─────────────────────
$categories = $pdo->query("SELECT id, nom FROM categories ORDER BY id")->fetchAll();

$produits = $pdo->query("
    SELECT p.id, p.titre, p.auteur, p.tome, p.prix, p.stock,
           c.nom AS categorie
    FROM produits p
    JOIN categories c ON c.id = p.categorie_id
    $where
    ORDER BY p.titre, p.tome
")->fetchAll();

require_once '../../includes/header.php';
?>

<main>
<div class="profil-page">
    <div class="profil-header">
        <div class="profil-avatar" style="background:linear-gradient(135deg,var(--red),var(--red-dark))">📚</div>
        <div>
            <h1>Gestion des produits</h1>
            <p class="profil-since"><a href="dashboard.php" style="color:var(--grey-400)">← Dashboard</a></p>
        </div>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert-success" style="margin-bottom:20px">✓ Produit mis à jour.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert-success" style="margin-bottom:20px">✓ Produit supprimé.</div>
    <?php elseif ($success): ?>
        <div class="alert-success" style="margin-bottom:20px">✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert-error" style="margin-bottom:20px">
            <?php foreach ($errors as $e): ?><p>⚠ <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── Formulaire d'ajout ── -->
    <div class="profil-card profil-card-full" style="margin-bottom:24px">
        <h2>➕ Ajouter un produit</h2>
        <form method="post" action="produits.php">
            <input type="hidden" name="action" value="ajouter">
            <div class="form-row">
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="titre" placeholder="Ex: One Piece"
                           value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Auteur</label>
                    <input type="text" name="auteur" placeholder="Ex: Eiichiro Oda"
                           value="<?= htmlspecialchars($_POST['auteur'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tome</label>
                    <input type="number" name="tome" min="1" value="<?= (int)($_POST['tome'] ?? 1) ?>" required>
                </div>
                <div class="form-group">
                    <label>Prix (€)</label>
                    <input type="number" name="prix" min="0.01" step="0.01"
                           placeholder="7.20" value="<?= htmlspecialchars($_POST['prix'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Stock</label>
                    <input type="number" name="stock" min="0" value="<?= (int)($_POST['stock'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>Catégorie</label>
                    <select name="categorie_id" required
                            style="width:100%;background:var(--navy-mid);border:1px solid rgba(255,255,255,0.1);
                                   color:var(--white);border-radius:var(--radius-md);padding:12px 14px;font-family:inherit">
                        <option value="">-- Choisir --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"
                                <?= (isset($_POST['categorie_id']) && (int)$_POST['categorie_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Chemin de l'image</label>
                <input type="text" name="image"
                       placeholder="asset/img/mon_manga_tome_1_page_de_couverture.jpg"
                       value="<?= htmlspecialchars($_POST['image'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"
                          placeholder="Description du manga…"
                          style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);
                                 color:var(--white);border-radius:var(--radius-md);padding:12px 14px;
                                 font-family:inherit;resize:vertical"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:12px 28px">
                ➕ Ajouter le produit
            </button>
        </form>
    </div>

    <!-- ── Filtres ── -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
        <?php
        $filtres = [
            'tous'       => 'Tous',
            'rupture'    => '🔴 Rupture',
            'faible'     => '🟡 Stock faible',
            'disponible' => '🟢 Disponible',
        ];
        foreach ($filtres as $slug => $label): ?>
        <a href="produits.php?filtre=<?= $slug ?>"
           class="btn <?= $filtre === $slug ? 'btn-primary' : 'btn-outline' ?>"
           style="padding:8px 16px;font-size:0.85rem">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Tableau des produits ── -->
    <div class="profil-card profil-card-full">
        <h2><?= count($produits) ?> produit<?= count($produits) > 1 ? 's' : '' ?></h2>
        <table class="commandes-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Titre</th>
                    <th>Auteur</th>
                    <th>Cat.</th>
                    <th>T.</th>
                    <th>Prix</th>
                    <th>Stock</th>
                    <th>Modifier stock</th>
                    <th>Modifier prix</th>
                    <th>Suppr.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produits as $p):
                    $enRupture   = $p['stock'] <= 0;
                    $stockFaible = $p['stock'] > 0 && $p['stock'] <= 3;
                ?>
                <tr>
                    <td style="color:var(--grey-400)"><?= (int)$p['id'] ?></td>
                    <td><strong><?= htmlspecialchars($p['titre']) ?></strong></td>
                    <td style="color:var(--grey-400);font-size:0.82rem"><?= htmlspecialchars($p['auteur']) ?></td>
                    <td style="font-size:0.82rem"><?= htmlspecialchars($p['categorie']) ?></td>
                    <td>T.<?= (int)$p['tome'] ?></td>
                    <td style="color:var(--gold-light);font-weight:700">
                        <?= number_format($p['prix'], 2, ',', '') ?> €
                    </td>
                    <td>
                        <span style="font-weight:700;color:<?=
                            $enRupture   ? 'var(--red-bright)'  :
                            ($stockFaible ? 'var(--gold-light)'  : '#2ecc71')
                        ?>">
                            <?= $enRupture ? 'Rupture' : $p['stock'] ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" action="produits.php" style="display:flex;gap:4px">
                            <input type="hidden" name="action"     value="update_stock">
                            <input type="hidden" name="produit_id" value="<?= (int)$p['id'] ?>">
                            <input type="number" name="stock" value="<?= (int)$p['stock'] ?>"
                                   min="0" max="9999"
                                   style="width:65px;background:var(--navy-mid);
                                          border:1px solid rgba(255,255,255,0.1);
                                          color:var(--white);border-radius:6px;
                                          padding:4px 6px;font-size:0.85rem">
                            <button type="submit" class="btn btn-primary"
                                    style="padding:4px 10px;font-size:0.8rem">✓</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="produits.php" style="display:flex;gap:4px">
                            <input type="hidden" name="action"     value="update_prix">
                            <input type="hidden" name="produit_id" value="<?= (int)$p['id'] ?>">
                            <input type="number" name="prix"
                                   value="<?= number_format($p['prix'], 2, '.', '') ?>"
                                   min="0" max="999" step="0.01"
                                   style="width:75px;background:var(--navy-mid);
                                          border:1px solid rgba(255,255,255,0.1);
                                          color:var(--white);border-radius:6px;
                                          padding:4px 6px;font-size:0.85rem">
                            <button type="submit" class="btn btn-primary"
                                    style="padding:4px 10px;font-size:0.8rem">✓</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="produits.php"
                              onsubmit="return confirm('Supprimer <?= htmlspecialchars(addslashes($p['titre'])) ?> ?')">
                            <input type="hidden" name="action"     value="supprimer">
                            <input type="hidden" name="produit_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn"
                                    style="padding:4px 10px;font-size:0.8rem;
                                           background:rgba(192,57,43,0.2);color:var(--red-bright)">
                                🗑
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</main>

<?php require_once '../../includes/footer.php'; ?>