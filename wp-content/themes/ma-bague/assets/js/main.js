/*------------------------------------*/
// IMPORT MODULES ES6 (LIB & CUSTOM)
/*------------------------------------*/
// import './_libs/lottie.min.js' 
import './_libs/instant-page.min.js'
import './_libs/masonry.min.js'

import * as vars from './modules/vars.js'
import { wbd_poster_iframe } from './modules/poster-iframe.js'
import { wbd_breakpoint } from './modules/breakpoint.js'
import { wbd_locoScroll } from './modules/locoscroll.js'
import { wbd_swiper } from './modules/swiper.js'
import { wbd_gmap } from './modules/gmap.js'
import { wbd_tab } from './modules/tab.js'
import { wbd_progressBar } from './modules/progress.js'
import { wbd_accordion } from './modules/accordion.js'
import { wbd_masonry } from './modules/masonry.js'
import { wbd_anchor } from './modules/anchor.js'
import { wbd_modal } from './modules/modal.js'
import { wbd_sort } from './modules/sort.js'
import * as select from './modules/select.js'
import * as form from './modules/form.js';
import * as nav from './modules/nav.js'

// import { wbd_cursor } from './modules/cursor.js'
// import { wbd_skew } from './modules/skew.js'
// import { wbd_video } from './modules/video.js'

// Importer le fichier CSS principal avec vite.js
import  '../scss/style.scss' 

/*------------------------------------*/
// INIT ON START FUNCTIONS
/*------------------------------------*/
function wbd_initDOM(code = ()=> {}) {
    if (vars.adminDOM && window.acf) { window.acf.addAction('render_block_preview', ()=> { code() }) }
    if (vars.compoDOM || (vars.frontDOM && typeof up === 'undefined')) { document.addEventListener("DOMContentLoaded", ()=> { code() }) }
    if (vars.frontDOM && typeof up !== 'undefined') { up.on('up:fragment:inserted', ()=> { code() }) }
}

wbd_initDOM(() => {
    Promise.all([
        nav. wbd_mobNav(),
        nav.wbd_burgerNav(),
        nav.wbd_headerNav(),
        wbd_breakpoint(),
        wbd_locoScroll(),
    ]).then(() => {
        form.wbd_form_submit();
        form.wbd_form_focus();
        form.wbd_form_active_btn();
        form.wbd_form_dynamic_required();
        form.wbd_form_honeypot();
        select.wbd_option();
        select.wbd_niceSelect();
        wbd_poster_iframe();
        wbd_swiper();
        wbd_accordion();
        wbd_masonry();
        wbd_anchor();
        wbd_modal();
        wbd_tab();
        wbd_gmap();
        wbd_sort();
    });
});

/*------------------------------------*/
// INIT ON RESIZE FUNCTIONS
/*------------------------------------*/
window.addEventListener("resize", (() => {
    let t; return ()=> { clearTimeout(t);
      t = setTimeout(()=> {
        wbd_breakpoint();
    }, 90);
};})());

/*------------------------------------*/
// INIT ON SCROLL FUNCTIONS
/*------------------------------------*/
window.addEventListener("scroll", ()=> {
    wbd_progressBar()
    nav.wbd_headerNav()
})

/*------------------------------------*/
