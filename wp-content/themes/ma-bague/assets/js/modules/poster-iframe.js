/*------------------------------------*/
// INIT BREAKPOINT EVENT
/*------------------------------------*/
import * as vars from './vars.js';

	export function wbd_poster_iframe() {
		document.addEventListener('click', function(e) {
			const framed = e.target.closest('.o-framed');
			if (!framed || framed.classList.contains('active') || framed.querySelector('[wbd-mdl-id]')) return;
			setTimeout(() => framed.classList.add('active'), 200);
			const iframe = framed.querySelector('iframe');
			if (iframe){
				let src = iframe.getAttribute('wbd-src');
				src += (src.indexOf('?') !== -1 ? '&' : '?') + 'autoplay=1';
				iframe.src = src; iframe.removeAttribute('wbd-src');
			}
		});
	}
