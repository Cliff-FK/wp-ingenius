import { defineConfig } from 'vite'
import { resolve } from 'path'
import postcssUrl from 'postcss-url'

/*---------------------------------------------------------------------------*\
  Paramètres globaux
\*---------------------------------------------------------------------------*/
const configParams = {
  paths: {
    root: __dirname,
    src: resolve(__dirname, 'assets'),
    dist: resolve(__dirname, 'dist'),
    js: 'js',
    css: 'css',
    fonts: 'fonts',
    images: 'images',
    svg: 'svg'
  },
  entries: {
    main: 'js/main.js',
    unpoly: 'js/_libs/unpoly.min.js', // Fichier déjà minifié
    admin: 'scss/admin.scss'
  },
  // Option PostCSS pour réécriture d'URLs dans les CSS
  postcssUrlOptions: {
    pattern: /^(?:(?:\.\.\/)|(?:\.\/))+/, // Pour capturer tous les préfixes relatifs
    replacement: '../'
  }
}

/*---------------------------------------------------------------------------*\
  Configuration Vite automatisé selon config ci-dessus
\*---------------------------------------------------------------------------*/
export default defineConfig({
  css: {
    postcss: {
      plugins: [
        postcssUrl({
          url: ({ url }) =>
            typeof url === 'string' && url.startsWith('.')
              ? url.replace(configParams.postcssUrlOptions.pattern, configParams.postcssUrlOptions.replacement)
              : url
        })
      ]
    }
  },
  build: {
    assetsDir: '', // On gère la répartition dans output.assetFileNames
    manifest: false,
    emptyOutDir: true,
    outDir: configParams.paths.dist,
    rollupOptions: {
      input: Object.fromEntries(
        Object.entries(configParams.entries).map(([key, relativePath]) => [
          key, resolve(configParams.paths.src, relativePath)
        ])
      ),
      output: {
        entryFileNames: `${configParams.paths.js}/[name].js`,
        chunkFileNames: `${configParams.paths.js}/[name].js`,
        assetFileNames: assetInfo => {
          if (assetInfo.name) {
            if (/woff|ttf/.test(assetInfo.name)) return `${configParams.paths.fonts}/[name].[ext]`
            if (/jpg|jpeg|png/.test(assetInfo.name)) return `${configParams.paths.images}/[name].[ext]`
            if (/svg/.test(assetInfo.name)) return `${configParams.paths.svg}/[name].[ext]`
            if (/css/.test(assetInfo.name)) return `${configParams.paths.css}/[name].[ext]`
          }
          return '[name].[ext]'
        }
      }
    }
  },
  server: {
    port: 3000,
    hmrHost: 'localhost'
  }
})
