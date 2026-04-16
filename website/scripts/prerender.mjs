#!/usr/bin/env bun
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const DOCS_DIR = path.join(__dirname, '../../docs')
const WEBSITE_DIR = path.join(__dirname, '..')

const ROUTES = [
  '/',
  '/blog',
  '/blog/detecting-n-plus-one-queries-with-laraperf',
  '/blog/using-explain-analyze-to-optimize-queries',
  '/blog/llm-coding-agents-and-performance-workflows',
  '/blog/capturing-production-performance-data',
]

async function prerender() {
  const { createServer } = await import('vite')

  const vite = await createServer({
    server: { middlewareMode: true },
    appType: 'custom',
    root: WEBSITE_DIR,
  })

  try {
    const { render } = await vite.ssrLoadModule('/src/entry-server.jsx')

    for (const route of ROUTES) {
      console.log(`Prerendering ${route}...`)

      try {
        const { html: appHtml } = await render(route)

        const templatePath = route === '/'
          ? path.join(DOCS_DIR, 'index.html')
          : null

        if (!templatePath) {
          const routeDir = path.join(DOCS_DIR, route)
          if (!fs.existsSync(routeDir)) {
            fs.mkdirSync(routeDir, { recursive: true })
          }
        }

        const indexHtmlPath = route === '/'
          ? path.join(DOCS_DIR, 'index.html')
          : path.join(DOCS_DIR, route, 'index.html')

        if (!fs.existsSync(indexHtmlPath)) {
          console.log(`  Skipping ${route} - no index.html found`)
          continue
        }

        const indexHtml = fs.readFileSync(indexHtmlPath, 'utf-8')

        let finalHtml = indexHtml.replace(
          '<div id="root"></div>',
          `<div id="root">${appHtml}</div>`,
        )

        finalHtml = finalHtml.replace(/<!--\$!-->.*?<\/template>/gs, '<!--$-->')

        fs.writeFileSync(indexHtmlPath, finalHtml)
        console.log(`  ✓ Prerendered ${route}`)
      } catch (err) {
        console.error(`  ✗ Error rendering ${route}:`, err.message)
        if (err.stack) {
          console.error(err.stack.split('\n').slice(0, 5).join('\n'))
        }
      }
    }

    console.log('\n✅ Prerendering complete')
  } finally {
    await vite.close()
  }
}

prerender().catch((err) => {
  console.error('Prerender failed:', err)
  process.exit(1)
})