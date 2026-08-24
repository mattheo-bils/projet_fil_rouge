<?php
/**
 * index.php — Page d'accueil de MangaMarket
 *
 * Affiche :
 *   - Un hero avec un manga mis en avant
 *   - Un carrousel de catégories
 *   - Une grille de mangas tendance (depuis la BDD ou fallback statique)
 */

$pageTitle = "Accueil";
$pageCss   = "index.css";
$pageJs    = ["index.js"]; // Carrousel + recherche
$basePath  = '';

require_once 'includes/header.php';

// ── Chargement des tendances depuis la BDD ────────────────
$tendances = [];
try {
    require_once 'includes/db.php';
    // Sélection aléatoire de 5 produits avec dédoublonnage par titre.
    // On prend UNE ligne cohérente par manga (le tome le plus bas) au lieu
    // de piocher MIN(prix)/MIN(image)/MIN(id) indépendamment, ce qui pouvait
    // afficher des infos venant de tomes différents.
    $stmt = $pdo->query("
        SELECT p.id, p.titre, p.tome, p.prix, p.image, c.slug AS categorie
        FROM produits p
        JOIN categories c ON c.id = p.categorie_id
        JOIN (
            SELECT titre, categorie_id, MIN(tome) AS tome_min
            FROM produits
            GROUP BY titre, categorie_id
        ) repr ON repr.titre = p.titre
              AND repr.categorie_id = p.categorie_id
              AND repr.tome_min = p.tome
        GROUP BY p.id
        ORDER BY RAND()
        LIMIT 5
    ");
    $tendances = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback statique si la BDD n'est pas disponible
    $tendances = [
        ['id'=>1,  'titre'=>'Berserk',       'tome'=>1,  'prix'=>7.20, 'image'=>'asset/img/berserk_tome_1_page_de_couverture.jpg',        'categorie'=>'seinen'],
        ['id'=>2,  'titre'=>'Dragon Ball',   'tome'=>35, 'prix'=>7.20, 'image'=>'asset/img/dragonball_tome_35_page_de_couverture.jpg',     'categorie'=>'shonen'],
        ['id'=>3,  'titre'=>'One Piece',     'tome'=>35, 'prix'=>7.20, 'image'=>'asset/img/one_piece_tome_35_page_de_couverture.jpg',      'categorie'=>'shonen'],
        ['id'=>4,  'titre'=>'Inazuma Eleven','tome'=>1,  'prix'=>7.20, 'image'=>'asset/img/inazuma_eleven_tome_1_page_de_couverture.jpg',  'categorie'=>'kodomo'],
        ['id'=>5,  'titre'=>'Vagabond',      'tome'=>1,  'prix'=>7.20, 'image'=>'asset/img/vagabond_tome_1_page_de_couverture.jpg',        'categorie'=>'seinen'],
    ];
}
?>

<main>
    <!-- ── Section Hero ──────────────────────────────────── -->
    <section class="hero noise">
        <div class="hero-content">
            <span class="hero-tag">Nouveauté Shonen</span>
            <h1>Commencer un shonen ?<br>Voici le premier tome de <em>Dragon Ball</em></h1>
            <div class="hero-buttons">
                <a href="views/produit.php?id=2" class="btn btn-primary">Voir l'article</a>
                <a href="views/catalogue.php" class="btn btn-outline">Voir la collection</a>
            </div>
        </div>
        <!-- Indicateur de défilement -->
        <div class="hero-scroll">
            <span class="material-symbols-outlined" style="font-size:18px">expand_more</span>
            scroll
        </div>
    </section>

    <!-- ── Section Catégories (carrousel) ────────────────── -->
    <section class="categories-section">
        <h2 class="section-heading">Catégories</h2>
        <div class="carousel-wrapper">
            <!-- Piste du carrousel, animée par index.js -->
            <div class="carousel-inner" id="carousel-inner">
                <?php
                // Liste des catégories avec leur slug et nom affiché
                $cats = [
                    'kodomo' => 'Kodomo',
                    'shonen' => 'Shonen',
                    'shojo'  => 'Shojo',
                    'seinen' => 'Seinen',
                    'josei'  => 'Josei',
                ];
                foreach ($cats as $slug => $nom): ?>
                <!-- Slide cliquable → filtre le catalogue par catégorie -->
                <a href="views/catalogue.php?categorie=<?= $slug ?>" id="<?= $slug ?>" class="slide">
                    <div class="slide-bg"></div>
                    <div class="slide-label">
                        <span>Catégorie</span><?= $nom ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Boutons de navigation du carrousel -->
        <div class="carousel-controls">
            <button class="carousel-btn" id="prev">
                <span class="material-symbols-outlined">arrow_back</span>
            </button>
            <button class="carousel-btn" id="next">
                <span class="material-symbols-outlined">arrow_forward</span>
            </button>
        </div>
    </section>

    <!-- ── Section Tendances ─────────────────────────────── -->
    <section class="trending-section">
        <h2 class="section-heading">Tendances</h2>
        <div class="cards-grid">
            <?php foreach ($tendances as $m): ?>
            <!-- Carte produit cliquable vers la fiche produit -->
            <a href="views/produit.php?id=<?= (int)$m['id'] ?>" class="card card-link">
                <img src="<?= htmlspecialchars($m['image']) ?>"
                     alt="<?= htmlspecialchars($m['titre']) ?> Tome <?= (int)$m['tome'] ?>">
                <div class="card-body">
                    <div class="card-title"><?= htmlspecialchars($m['titre']) ?></div>
                    <div class="card-row">
                        <span class="card-price"><?= number_format($m['prix'], 2, ',', '') ?> €</span>
                        <span class="card-tome">Tome <?= (int)$m['tome'] ?></span>
                    </div>
                    <div class="card-row" style="margin-top:10px">
                        <!-- Bouton géré par header.js (ajout au panier via localStorage ou BDD) -->
                        <button class="btn-add"
                                data-id="<?= (int)$m['id'] ?>"
                                data-titre="<?= htmlspecialchars($m['titre']) ?>"
                                data-prix="<?= number_format($m['prix'], 2, ',', '') ?>"
                                data-img="<?= htmlspecialchars($m['image']) ?>"
                                onclick="event.preventDefault()">
                            + Ajouter
                        </button>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>