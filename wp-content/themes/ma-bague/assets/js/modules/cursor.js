/*------------------------------------*/
// INIT CUSTOM CURSOR LIB
// https://github.com/markmead/custom-cursor
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_cursor() {
    if(typeof Cursor === 'function' && vars.frontDOM){
        vars.frontDOM.classList.add('custom-cursor')
        if(window.viewportWidth > 980) new Cursor({ targets: ['a', 'button', 'input', 'label'], count: 1, })        
    }
}