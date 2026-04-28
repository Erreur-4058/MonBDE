<?php require_once 'staff_protect.php'; 

require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
$db = getDB();

$msg = "";
// --- 1. TRAITEMENT DES ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action : Ajouter du stock
    if (isset($_POST['action']) && $_POST['action'] === 'add_stock') {
        $stmt = $db->prepare("UPDATE produits SET stock = stock + ? WHERE id = ? AND stock != -1");
        $stmt->execute([(int)$_POST['qte_totale'], (int)$_POST['id']]);
        $msg = "success:Stock mis à jour (+".$_POST['qte_totale'].")";
    }
    // Action : Lier un code inconnu
    elseif (isset($_POST['action']) && $_POST['action'] === 'link_barcode') {
        $id = (int)$_POST['product_id'];
        $ean = $_POST['scanned_ean'];
        
        if ($_POST['type_liaison'] === 'pack') {
            // On lie le code au pack ET on règle le nombre d'unités par pack
            $u_pack = (int)$_POST['unites_par_pack'];
            $stmt = $db->prepare("UPDATE produits SET code_barre_pack = ?, unites_par_pack = ? WHERE id = ?");
            $stmt->execute([$ean, $u_pack, $id]);
        } else {
            // On lie le code à l'unité simple
            $stmt = $db->prepare("UPDATE produits SET code_barre = ? WHERE id = ?");
            $stmt->execute([$ean, $id]);
        }
        $msg = "success:Produit configuré et lié !";
    }
}

$tous_les_produits = $db->query("SELECT id, nom FROM produits ORDER BY nom ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background: #121212; color: white; font-family: sans-serif; }
        #reader { width: 100%; max-width: 450px; margin: auto; border-radius: 15px; overflow: hidden; border: 2px solid #333; }
        .card-custom { background: #1e1e1e; border-radius: 20px; padding: 25px; margin-top: 20px; display: none; border: 1px solid #333; }
        .qty-display { font-size: 3.5rem; font-weight: 900; color: #f59e0b; }
        .btn-main { background: #f59e0b; color: black; font-weight: 800; border: none; padding: 15px; border-radius: 12px; }
        #pack_config { background: #252525; border-radius: 10px; padding: 15px; display: none; margin-top: 15px; border-left: 4px solid #f59e0b; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Scanner</h3>
        <a href="admin.php" class="btn btn-outline-light btn-sm">Quitter</a>
    </div>

    <?php if($msg): list($type, $txt) = explode(':', $msg); ?>
        <div class="alert alert-<?= $type ?>"><?= $txt ?></div>
    <?php endif; ?>

    <div id="reader"></div>

    <!-- 1. AJOUT DE STOCK -->
    <div id="res-section" class="card-custom shadow">
        <div class="text-center mb-4">
            <h2 id="p-nom" class="h4 mb-1"></h2>
            <span id="p-type-badge" class="badge"></span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_stock"><input type="hidden" name="id" id="p-id"><input type="hidden" id="p-multiplier" value="1">
            <div class="text-center mb-4">
                <label id="label-mode" class="text-muted small d-block mb-2"></label>
                <div class="d-flex justify-content-center align-items-center gap-4">
                    <button type="button" class="btn btn-lg btn-outline-secondary" onclick="changeQty(-1)">−</button>
                    <input type="number" id="input-qty" class="qty-display bg-transparent border-0 text-center" value="1" style="width:120px" oninput="calcul()">
                    <button type="button" class="btn btn-lg btn-outline-secondary" onclick="changeQty(1)">+</button>
                </div>
            </div>
            <div class="bg-dark rounded-3 p-3 text-center mb-4 border border-secondary">
                <span class="text-muted small">TOTAL À AJOUTER</span><br>
                <span class="h2 text-success fw-bold">+ <span id="total-final">0</span></span> <small>unités</small>
                <input type="hidden" name="qte_totale" id="hidden-total">
            </div>
            <button type="submit" class="btn btn-main w-100 py-3">CONFIRMER</button>
            <button type="button" class="btn btn-link w-100 text-white-50 mt-2" onclick="resetScanner()">Annuler</button>
        </form>
    </div>

    <!-- 2. LIAISON CODE INCONNU -->
    <div id="link-section" class="card-custom shadow">
        <div class="text-center mb-3"><h3>Code inconnu</h3><code class="text-warning" id="unknown-ean-display"></code></div>
        <form method="POST">
            <input type="hidden" name="action" value="link_barcode"><input type="hidden" name="scanned_ean" id="hidden-scanned-ean">
            <div class="mb-3">
                <label class="small text-muted">Associer à quel produit ?</label>
                <select name="product_id" class="form-select bg-dark text-white border-secondary" required>
                    <option value="">-- Choisir le produit --</option>
                    <?php foreach($tous_les_produits as $tp): ?>
                        <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="small text-muted d-block mb-2">Type de code :</label>
                <div class="btn-group w-100">
                    <input type="radio" class="btn-check" name="type_liaison" id="type_unite" value="unite" checked onclick="togglePackField(false)">
                    <label class="btn btn-outline-info" for="type_unite">L'unité</label>
                    <input type="radio" class="btn-check" name="type_liaison" id="type_pack" value="pack" onclick="togglePackField(true)">
                    <label class="btn btn-outline-warning" for="type_pack">Le PACK</label>
                </div>
            </div>
            <!-- CHAMP CONDITIONNEL -->
            <div id="pack_config">
                <label class="small fw-bold text-warning">Configuration du Pack</label>
                <input type="number" name="unites_par_pack" class="form-control bg-dark text-white border-warning mt-1" placeholder="Ex: 24 (canettes)">
                <small class="text-muted" style="font-size:0.7rem">Combien d'unités individuelles contient ce pack ?</small>
            </div>
            <button type="submit" class="btn btn-success w-100 py-3 mt-4 fw-bold text-dark">ENREGISTRER LA LIAISON</button>
            <button type="button" class="btn btn-link w-100 text-white-50 mt-2" onclick="resetScanner()">Annuler</button>
        </form>
    </div>

    <div class="mt-3"><input type="text" id="manual_ean" class="form-control bg-dark text-white border-secondary" placeholder="Code manuel"></div>
</div>

<script>
    const html5QrCode = new Html5Qrcode("reader");

    function togglePackField(show) {
        document.getElementById('pack_config').style.display = show ? 'block' : 'none';
        const input = document.querySelector('#pack_config input');
        input.required = show;
        if(show) input.focus();
    }

    async function onScanSuccess(decodedText) {
        const response = await fetch(`scanner_search.php?ean=${decodedText}`);
        const res = await response.json();
        html5QrCode.stop();
        document.getElementById('reader').style.display = 'none';

        if (res.success) {
            const p = res.data;
            document.getElementById('p-id').value = p.id;
            document.getElementById('p-nom').innerText = p.nom;
            document.getElementById('input-qty').value = 1;
            document.getElementById('res-section').style.display = 'block';
            if (res.found_as === 'pack') {
                document.getElementById('p-type-badge').innerText = "PACK DE " + p.unites_par_pack;
                document.getElementById('p-type-badge').className = "badge bg-warning text-dark";
                document.getElementById('p-multiplier').value = p.unites_par_pack;
                document.getElementById('label-mode').innerText = "NOMBRE DE PACKS";
            } else {
                document.getElementById('p-type-badge').innerText = "UNITÉ SIMPLE";
                document.getElementById('p-type-badge').className = "badge bg-info text-dark";
                document.getElementById('p-multiplier').value = 1;
                document.getElementById('label-mode').innerText = "NOMBRE D'UNITÉS";
            }
            calcul();
        } else {
            document.getElementById('unknown-ean-display').innerText = decodedText;
            document.getElementById('hidden-scanned-ean').value = decodedText;
            document.getElementById('link-section').style.display = 'block';
        }
    }

    function calcul() {
        const qty = parseInt(document.getElementById('input-qty').value) || 0;
        const mult = parseInt(document.getElementById('p-multiplier').value) || 1;
        const total = qty * mult;
        document.getElementById('total-final').innerText = total;
        document.getElementById('hidden-total').value = total;
    }

    function changeQty(v) {
        const input = document.getElementById('input-qty');
        input.value = Math.max(1, parseInt(input.value) + v);
        calcul();
    }

    function resetScanner() {
        document.getElementById('res-section').style.display = 'none';
        document.getElementById('link-section').style.display = 'none';
        document.getElementById('reader').style.display = 'block';
        document.getElementById('manual_ean').value = "";
        html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess);
    }

    html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess);
    document.getElementById('manual_ean').addEventListener('change', (e) => onScanSuccess(e.target.value));
</script>
</body>
</html>