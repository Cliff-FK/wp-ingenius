import sassGlobImports from 'vite-plugin-sass-glob-import';
import { defineConfig } from 'vite'
import { resolve } from 'path'

const WP_THEME_PATH = 'wp-content/themes';  
const HOST = 'localhost'; // Nom du serveur
const PORT = 3000; // Port du serveur

function getBaseUrl() {
  const currentPath = process.cwd().replace(/\\+/g, '/');
  const themePart = currentPath.split('wp-content/themes')[1];
  if(themePart) return `/${'wp-content/themes'}${themePart}`;
  // if(themePart) return `http://${HOST}:${PORT}/${WP_THEME_PATH}${themePart}`;
  return '/';
}
  
export default defineConfig({

  resolve: {
    alias: {
      '../../': '../',
    }
  },

  build: {
    assetsDir: '',
    manifest: 'manifest.json',
    sourcemap: false,
    // base: getBaseUrl(),
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'assets/js/main.js'),
        unpoly: resolve(__dirname, 'assets/js/_libs/unpoly.min.js'),  // Ce fichier sera minifié aussi
        admin: resolve(__dirname, 'assets/scss/admin.scss'),
      },
    },
  },

  plugins: [
    sassGlobImports()
  ],

  optimizeDeps: {
    exclude: [
      'jquery',
      'desandro-matches-selector',
      'ev-emitter',
      'get-size',
      'fizzy-ui-utils',
      'outlayer',
    ],
  },
  
})
