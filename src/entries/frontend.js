import { initAll } from '../frontend/menu';
import '../frontend/menu.scss';

const boot = () => initAll( document );
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
// Re-init after page builders inject markup (Elementor preview, AJAX themes).
window.dev3mmInit = ( root ) => initAll( root || document );
