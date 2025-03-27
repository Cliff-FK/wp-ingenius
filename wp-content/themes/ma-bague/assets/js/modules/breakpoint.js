/*------------------------------------*/
// INIT BREAKPOINT EVENT
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_breakpoint() {
  const sizes = ["xs","sm","md","lg","xl","xxl"];
  document.body.classList.remove(...sizes); // On retire d’abord toutes les classes
  const w = window.innerWidth; // Récupère la largeur
  if (w >= 100)  document.body.classList.add("xs");
  if (w >= 576)  document.body.classList.add("sm");
  if (w >= 768)  document.body.classList.add("md");
  if (w >= 992)  document.body.classList.add("lg");
  if (w >= 1200) document.body.classList.add("xl");
  if (w >= 1400) document.body.classList.add("xxl");
}
