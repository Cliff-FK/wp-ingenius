/*------------------------------------*/
// INIT LOCOMOTIVE SCROLL LIB
// https://scroll.locomotive.ca/docs
/*------------------------------------*/
import * as vars from './vars.js';
import '../_libs/locomotive-scroll.min.js' 

export function wbd_locoScroll() {
    if(typeof LocomotiveScroll !== 'undefined'){
        if (typeof window.myLoco !== 'undefined') { window.myLoco.destroy(); }
        if (typeof window.myLoco === 'undefined') window.myLoco ='';
        if(vars.frontDOM) window.myLoco = new LocomotiveScroll({ lenisOptions: { smoothWheel: true, lerp: 0.08 }});
        if(vars.adminDOM) window.myLoco = new LocomotiveScroll({ lenisOptions: { smoothWheel: false }});
    }}