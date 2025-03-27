/*------------------------------------*/
// INIT GMAP COMPONENT
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_gmap() {
    // On attend le chargement complet de la page
    window.addEventListener('load', () => {
      // Sélectionnez tous les web components "gmp-map" présents sur la page
      document.querySelectorAll('gmp-map').forEach(gmap => {
        // Dans chaque bloc de carte, on récupère les éléments concernés
        const markers = gmap.querySelectorAll('gmp-advanced-marker');
        const listWrapper = gmap.parentElement.querySelector('[list-wrapper]');
        const listCards = gmap.parentElement.querySelectorAll('[list-card]');
        const customButton = gmap.querySelector('gmpx-icon-button');
        // On suppose que le web component expose une propriété innerMap (l'instance de la Google Map)
        const innerMap = gmap.innerMap;
        const bounds = new google.maps.LatLngBounds();

		// Fonction pour centrer la carte sur un marqueur
		function focusOnMarker(marker) {
			setTimeout(() => {
				const offsetLatitude = (marker.offsetHeight || 0) / innerMap.getDiv().offsetHeight * (360 / Math.pow(2, innerMap.getZoom()));
				innerMap.panTo(new google.maps.LatLng(marker.position.lat + offsetLatitude, marker.position.lng));
			}, 10);
			markers.forEach(m => m.classList.remove('active'));
			marker.classList.add('active');
		}

		// Gérer les clics sur les marqueurs
		markers.forEach((marker) => {
			bounds.extend(new google.maps.LatLng(marker.position.lat, marker.position.lng));
			marker.addEventListener('click', (e) => {
				// 🔒 Si clic sur le bouton .close ➔ désactiver tous les marqueurs
				if (e.target.closest('.close')) {
					markers.forEach(m => m.classList.remove('active'));
				}
				// 🎯 Sinon, centrer sur le marqueur
				else if (!e.target.closest('[href]')) {
					focusOnMarker(marker);
				}
			});
		});

		// Ajuster la vue et désactiver les contrôles inutiles
		(innerMap.fitBounds(bounds), innerMap.setOptions({
			fullscreenControl: false,
			streetViewControl: false
		}));

        google.maps.event.addListenerOnce(innerMap, 'idle', () => {
            const numMarkers = markers.length;
            // Si moins de 4 marqueurs, dézoomer de 5 niveaux, sinon de 2 niveaux
            const zoomOffset = numMarkers < 4 ? 3 : 0.5;
            const currentZoom = innerMap.getZoom();
            innerMap.setZoom(currentZoom - zoomOffset);
          });

		// Ajoute class pour les gros zoom pour faire apparaitre les bv en css pure
		innerMap.addListener('zoom_changed', () => {
			const zoomLevel = innerMap.getZoom();
			if (zoomLevel > 14) { gmap.classList.add('wbd-focused');
			} else { gmap.classList.remove('wbd-focused'); }
		});

		// Bouton de switch pour la liste
		customButton?.addEventListener('click', (e) => {
			customButton.classList.toggle('active');
			listWrapper.classList.toggle('active');
		});

		listCards.forEach((card) => {
			card.addEventListener('click', (e) => {
				const targetId = e.target.closest('[prg-target]')?.getAttribute('prg-target');
				if (targetId) {
					const targetMarker = document.querySelector(`gmp-advanced-marker[prg-id="${targetId}"]`);
					if (targetMarker) focusOnMarker(targetMarker)
				}
			});
		});

      });
    });
  }
  