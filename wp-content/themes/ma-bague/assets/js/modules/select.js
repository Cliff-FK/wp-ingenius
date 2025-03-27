/*------------------------------------*/
// INIT SELECT ACTIONS
/*------------------------------------*/
import * as vars from './vars.js';
import '../_libs/nice-select2.min.js' 
import { AccurateSearch } from '../_libs/accuratesearch.min.js';

export function wbd_option() {
    var firstOptsSelect = document.querySelectorAll("[class*='b-'] select:not([nice-select]) option:first-child")
    if(firstOptsSelect && vars.frontDOM) firstOptsSelect.forEach(e => e.setAttribute("disabled", "").setAttribute("selected", ""));
}

/*------------------------------------*/
// INIT NICESELECT2 LIB
// https://bluzky.github.io/nice-select2
/*------------------------------------*/

export function wbd_niceSelect() {
    if(typeof NiceSelect === 'object' ){
        if (window.AllNiceSelect) { window.AllNiceSelect.forEach(slt => slt.destroy()); } // Détruire les instances existantes
        window.AllNiceSelect = []; // Re-init les futures intances   

        document.querySelectorAll('[nice-select]').forEach((select ,i)=> {
            var selectStr = select.getAttribute("selectedtext") ? select.getAttribute("selectedtext") : "";
            var placeStr = select.getAttribute("placeholder") ? select.getAttribute("placeholder") : "";
            if(select.options.length >= 7 || select.name=='city-cp') {window.AllNiceSelect[i] = NiceSelect.bind(select, { searchable: true, placeholder: placeStr, selectedtext: selectStr })}
                else {window.AllNiceSelect[i] = NiceSelect.bind(select, { searchable: false, placeholder: placeStr, selectedtext: selectStr })}
            if(select.nextElementSibling) select.nextElementSibling.setAttribute('data-lenis-prevent','')
            select.style.display = 'none';    
        })

        if(typeof wbd_php_vars !== 'undefined' && wbd_php_vars.THM_PATH ){
        if (!document.querySelector("[name='city-cp']")) return;
        fetch(`${wbd_php_vars.THM_PATH}/inc/immolead/donneesGouv.json`).then(f => f.ok ? f.json() : null).then(data => {
            if(data){window.AllNiceSelect.forEach(instance => {
                if (instance.el.name=='city-cp' && instance.config.searchable) {
                    const accurateSearch = new AccurateSearch();
                    data.forEach(item => { accurateSearch.addText(item, item); });
                    let timeout;  // Variable pour gérer le debounce
                    // Ajouter l'écouteur d'événement pour l'input
                    instance.dropdown.querySelector('.nice-select-search').addEventListener('input', (e) => {
                        clearTimeout(timeout);  // Annuler le précédent délai
                        if (e.target.value.length >= 2) {
                            timeout = setTimeout(() => {
                                const results = accurateSearch.search(e.target.value).slice(0, 30);  // Rechercher avec AccurateSearch
                                instance.el.innerHTML = results.map(result => { // Maj le vrai select
                                    return `<option value="${result}">${result}</option>`;
                                }).join('');
                                instance.dropdown.querySelector('ul').innerHTML = results.map(result => { // Maj le faux select
                                    return `<li data-value="${result}" class='option'>${result}</li>`;
                                }).join('');

                                instance.dropdown.querySelectorAll('.option').forEach(opt => {
                                    opt.addEventListener('click', (e) => {
                                        const selectedValue = e.target.getAttribute('data-value'); // Utiliser data-value pour la sélection
                                        const currentElement = instance.dropdown.querySelector('.current'); // Maj .current manuellement
                                        if (currentElement) currentElement.textContent = e.target.textContent;  // Maj le texte de .current
                                        instance.el.value = selectedValue; // Maj la valeur du select original avec le data-value
                                        instance.el.classList.remove('open'); // Fermer le dropdown niceselect après la sélection
                                    });
                                });
                            }, 300); // Délai de 300ms avant d'exécuter la recherche
                        }
                    });
                }
        })};})}
    
    }

}


// https://geo.api.gouv.fr/communes