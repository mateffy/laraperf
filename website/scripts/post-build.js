#!/usr/bin/env bun
/**
 * Post-build script to generate static HTML files for blog posts
 * This creates SEO-friendly static pages for each blog post
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

// Import posts data
const postsModule = await import('../src/data/posts.js')
const posts = postsModule.posts || []

const DOCS_DIR = path.join(__dirname, '../../docs')
const INDEX_HTML = path.join(DOCS_DIR, 'index.html')

console.log('🔧 Generating static blog pages...')

// Read the index.html template
const indexHtmlTemplate = fs.readFileSync(INDEX_HTML, 'utf-8')

// Helper to fix asset paths based on depth
function fixAssetPaths(html, depth) {
  const prefix = depth === 0 ? './' : '../'.repeat(depth)
  return html
    .replace(/src="\.\/assets\//g, `src="${prefix}assets/`)
    .replace(/href="\.\/assets\//g, `href="${prefix}assets/`)
}

// Generate blog list page
const blogDir = path.join(DOCS_DIR, 'blog')
if (!fs.existsSync(blogDir)) {
  fs.mkdirSync(blogDir, { recursive: true })
}

// Write blog list index (depth 1)
const blogListHtml = fixAssetPaths(
  indexHtmlTemplate.replace(
    /<title>.*<\/title>/,
    '<title>Blog — laraperf</title>'
  ).replace(
    /<meta name="description" content=".*" \/>/,
    '<meta name="description" content="Articles about Laravel performance, LLM coding agents, and database optimization." />'
  ),
  1
)

fs.writeFileSync(path.join(blogDir, 'index.html'), blogListHtml)
console.log('✓ Generated /blog/index.html')

// Generate individual blog post pages
for (const post of posts) {
  const postDir = path.join(blogDir, post.slug)
  if (!fs.existsSync(postDir)) {
    fs.mkdirSync(postDir, { recursive: true })
  }

  let postHtml = indexHtmlTemplate
    .replace(/<title>.*<\/title>/, `<title>${post.title} — laraperf Blog</title>`)
    .replace(
      /<meta name="description" content=".*" \/>/,
      `<meta name="description" content="${post.description.replace(/"/g, '&quot;')}" />`
    )
    // Add Open Graph tags
    .replace(
      /<\/head>/,
      `  <meta property="og:title" content="${post.title}" />\n  <meta property="og:description" content="${post.description.replace(/"/g, '&quot;')}" />\n  <meta property="og:type" content="article" />\n  <meta property="article:published_time" content="${post.date}" />\n  <meta property="article:author" content="${post.author}" />\n  <meta property="article:tag" content="${post.tags.join(', ')}" />\n</head>`
    )

  // Fix asset paths for depth 2 (/blog/slug/index.html -> assets are at ../../assets/)
  postHtml = fixAssetPaths(postHtml, 2)

  fs.writeFileSync(path.join(postDir, 'index.html'), postHtml)
  console.log(`✓ Generated /blog/${post.slug}/index.html`)
}

// Copy skill.md as plaintext for agents
const skillSource = path.join(__dirname, '../public/skill.md')
const skillDest = path.join(DOCS_DIR, 'skill.md')
if (fs.existsSync(skillSource)) {
  fs.copyFileSync(skillSource, skillDest)
  console.log('✓ Generated /skill.md')
}

console.log(`\n✅ Generated ${posts.length + 3} static pages`)
console.log('📁 Output directory:', DOCS_DIR)
