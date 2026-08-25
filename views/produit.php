<?php
/**
 * produit.php — Fiche produit détaillée
 */

$basePath = '../';

$produit     = null;
$suggestions = [];

try {
    require_once '../includes/db.php';

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        header('Location: catalogue.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT p.*, c.slug AS categorie_slug, c.nom AS categorie_nom
        FROM produits p
        JOIN categories c ON c.id = p.categorie_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $produit = $stmt->fetch();

    if (!$produit) {
        header('Location: catalogue.php');
        exit;
    }

    // Chaque tome est un produit à part entière : les suggestions listent
    // des produits individuels de la même catégorie, sans fusion par titre.
    $stmt2 = $pdo->prepare("
        SELECT p.id, p.titre, p.tome, p.prix, p.image
        FROM produits p
        WHERE p.categorie_id = ? AND p.id != ?
        ORDER BY RAND()
        LIMIT 4
    ");
    $stmt2->execute([$produit['categorie_id'], $id]);
    $suggestions = $stmt2->fetchAll();

} catch (Exception $e) {
    $produit = [
        'id'            => 7,
        'titre'         => 'Bleach',
        'auteur'        => 'Tite Kubo',
        'tome'          => 2,
        'prix'          => 7.80,
        'stock'         => 5,
        'categorie_slug'=> 'shonen',
        'categorie_nom' => 'Shonen',
        'image'         => '../asset/img/bleach_tome_2_page_de_couverture.jpg',
        'description'   => "Bleach raconte l'histoire d'Ichigo Kurosaki.",
    ];
    $suggestions = [];
}

$stock        = (int)($produit['stock'] ?? 0);
$enRupture    = $stock <= 0;
$stockFaible  = $stock > 0 && $stock <= 3;

$pageTitle = htmlspecialchars($produit['titre']) . ' — Tome ' . (int)$produit['tome'];
$pageCss   = "produit.css";
$pageJs    = ["produit.js"];

require_once '../includes/header.php';
?>

<main>
    <nav class="chemin" aria-label="Fil d'Ariane">
        <a href="../index.php">Accueil</a>
        <span class="sep">/</span>
        <a href="catalogue.php">Catalogue</a>
        <span class="sep">/</span>
        <span><?= htmlspecialchars($produit['titre']) ?> — Tome <?= (int)$produit['tome'] ?></span>
    </nav>

    <section class="produit">
        <div class="image-col">
            <?php if ($enRupture): ?>
                <!-- Overlay rupture de stock sur l'image -->
                <div style="position:relative;display:inline-block">
                    <img src="../<?= htmlspecialchars($produit['image']) ?>"
                         alt="<?= htmlspecialchars($produit['titre']) ?>"
                         class="image-affiche" id="main-img"
                         style="opacity:0.5">
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
                        <span style="background:var(--red);color:#fff;padding:10px 20px;border-radius:8px;font-weight:700;font-size:1rem;letter-spacing:0.05em">
                            RUPTURE DE STOCK
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <img src="../<?= htmlspecialchars($produit['image']) ?>"
                     alt="<?= htmlspecialchars($produit['titre']) ?>"
                     class="image-affiche" id="main-img">
            <?php endif; ?>
        </div>

        <div class="description">
            <h1><?= htmlspecialchars($produit['titre']) ?> — Tome <?= (int)$produit['tome'] ?></h1>

            <div class="prix-row">
                <span class="prix-label">Prix unitaire</span>
                <span class="prix-valeur"><?= number_format($produit['prix'], 2, ',', '') ?> €</span>
            </div>

            <!-- Badge stock -->
            <div style="margin:12px 0">
                <?php if ($enRupture): ?>
                    <span style="background:rgba(192,57,43,0.2);color:var(--red-bright);padding:6px 14px;border-radius:20px;font-size:0.82rem;font-weight:700">
                        ✕ Rupture de stock
                    </span>
                <?php elseif ($stockFaible): ?>
                    <span style="background:rgba(226,176,74,0.2);color:var(--gold-light);padding:6px 14px;border-radius:20px;font-size:0.82rem;font-weight:700">
                        ⚠ Plus que <?= $stock ?> exemplaire<?= $stock > 1 ? 's' : '' ?>
                    </span>
                <?php else: ?>
                    <span style="background:rgba(39,174,96,0.2);color:#2ecc71;padding:6px 14px;border-radius:20px;font-size:0.82rem;font-weight:700">
                        ✓ En stock (<?= $stock ?> disponibles)
                    </span>
                <?php endif; ?>
            </div>

            <table class="meta-table">
                <tr><td>Genre</td><td><?= htmlspecialchars($produit['categorie_nom']) ?></td></tr>
                <tr><td>Auteur</td><td><?= htmlspecialchars($produit['auteur']) ?></td></tr>
                <tr><td>Tome</td><td><?= (int)$produit['tome'] ?></td></tr>
            </table>

            <p class="description-text"><?= htmlspecialchars($produit['description'] ?? '') ?></p>

            <div class="btn-suite">
                <?php if ($enRupture): ?>
                    <!-- Boutons désactivés si rupture de stock -->
                    <button class="btn btn-outline" disabled
                            style="opacity:0.4;cursor:not-allowed">
                        Indisponible
                    </button>
                    <button class="btn btn-primary" disabled
                            style="opacity:0.4;cursor:not-allowed">
                        Indisponible
                    </button>
                <?php else: ?>
                    <button class="btn btn-outline btn-add"
                            data-id="<?= (int)$produit['id'] ?>"
                            data-titre="<?= htmlspecialchars($produit['titre']) ?>"
                            data-prix="<?= number_format($produit['prix'], 2, ',', '') ?>"
                            data-img="../<?= htmlspecialchars($produit['image']) ?>">
                        🛒 Ajouter au panier
                    </button>
                    <a href="achat.php?id=<?= (int)$produit['id'] ?>" class="btn btn-primary">
                        Acheter maintenant
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($suggestions)): ?>
    <section class="suggestion">
        <h2 class="section-heading">Vous aimerez aussi</h2>
        <div class="suggestion-grid">
            <?php foreach ($suggestions as $s): ?>
            <a href="produit.php?id=<?= (int)$s['id'] ?>" class="card card-link">
                <img src="../<?= htmlspecialchars($s['image']) ?>" alt="<?= htmlspecialchars($s['titre']) ?>">
                <div class="card-body">
                    <div class="card-title"><?= htmlspecialchars($s['titre']) ?></div>
                    <div class="card-row">
                        <span class="card-price"><?= number_format($s['prix'], 2, ',', '') ?> €</span>
                        <span class="card-tome">Tome <?= (int)$s['tome'] ?></span>
                    </div>
                    <div class="card-row" style="margin-top:10px">
                        <button class="btn-add"
                                data-id="<?= (int)$s['id'] ?>"
                                data-titre="<?= htmlspecialchars($s['titre']) ?>"
                                data-prix="<?= number_format($s['prix'], 2, ',', '') ?>"
                                data-img="../<?= htmlspecialchars($s['image']) ?>"
                                onclick="event.preventDefault()">
                            + Ajouter
                        </button>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>