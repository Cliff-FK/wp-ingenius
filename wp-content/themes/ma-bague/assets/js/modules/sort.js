/*------------------------------------*/
// INIT SORT TABLE
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_sort() {
    const tAsc = 'asc', tDesc = 'dsc'; // Classes pour indiquer le sens de tri
    const aTr  = 'tbody tr';

    document.addEventListener('click', (e) => {
        // On cible un <button> dans un <th>
        const flt = e.target.closest('th button');
        if (!flt) return;

        // Le tableau doit porter l’attribut [data-tbl-sort]
        const tbl = flt.closest('[data-tbl-sort]');
        if (!tbl) return;

        // Trouve l'index de la colonne (th) cliquée
        const th  = flt.closest('th');
        const idx = [...th.parentNode.children].indexOf(th);

        // Fonction pour extraire la partie numérique et la partie texte de la cellule
        const clean = (td) => {
            let c = td.textContent.trim().replace(/\s/g, ''),
                n = c.match(/^\d+/);  // On récupère un éventuel nombre au début
            return [
                n ? parseInt(n[0]) : NaN,           // partie numérique
                c.replace(/^\d+/, '').trim().toLowerCase() // reste du texte, en minuscule
            ];
        };

        // Récupère toutes les lignes (tr) dans le tbody
        let rows = [...tbl.querySelectorAll(aTr)];

        // Tri ascendant ou descendant selon la classe du bouton
        const isAsc = flt.classList.contains(tAsc);
        rows.sort((a, b) => {
            let [an, at] = clean(a.cells[idx]),
                [bn, bt] = clean(b.cells[idx]);

            // S’il y a deux nombres, on compare en numérique
            if (!isNaN(an) && !isNaN(bn)) {
                return isAsc ? an - bn : bn - an;
            }
            // Sinon on compare en mode texte (localeCompare)
            return isAsc ? at.localeCompare(bt) : bt.localeCompare(at);
        });

        // Réinsère les lignes dans l’ordre
        const tbody = tbl.querySelector('tbody');
        rows.forEach(row => tbody.appendChild(row));

        // Mise à jour des classes sur le bouton cliqué
        // 1) On supprime asc/dsc sur tous les boutons du tableau
        tbl.querySelectorAll('th button').forEach(btn => 
            btn.classList.remove(tAsc, tDesc)
        );
        // 2) On bascule la classe entre asc et dsc sur le bouton cliqué
        flt.classList.add(isAsc ? tDesc : tAsc);
    });
}
