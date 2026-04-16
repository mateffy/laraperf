import { createFileRoute, Link, notFound } from '@tanstack/react-router'
import { getPostBySlug, getAllSlugs } from '@/data/posts'

// Generate static paths for all posts
export const Route = createFileRoute('/blog/$slug')({
  component: BlogPost,
  beforeLoad: ({ params }) => {
    const post = getPostBySlug(params.slug)
    if (!post) {
      throw notFound()
    }
  },
  loader: ({ params }) => {
    const post = getPostBySlug(params.slug)
    return { post }
  },
  head: ({ loaderData }) => ({
    meta: loaderData ? [
      { title: `${loaderData.post.title} — laraperf Blog` },
      { name: 'description', content: loaderData.post.description },
    ] : [],
  }),
  // Static site generation - pre-render all blog posts
  ssg: {
    getPaths: () => {
      const slugs = getAllSlugs()
      return slugs.map(slug => ({ slug }))
    },
  },
})

function BlogPost() {
  const { post } = Route.useLoaderData()

  // Simple markdown-like content rendering
  const renderContent = (content) => {
    const lines = content.trim().split('\n')
    const elements = []
    let inCodeBlock = false
    let codeContent = []
    let codeLanguage = ''

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i]

      // Code block handling
      if (line.startsWith('```')) {
        if (inCodeBlock) {
          // End code block
          elements.push(
            <pre key={`code-${i}`} className="bg-stone-950 border border-stone-800 p-4 overflow-x-auto my-6">
              <code className="text-sm font-mono text-stone-200">
                {codeContent.join('\n')}
              </code>
            </pre>
          )
          codeContent = []
          inCodeBlock = false
        } else {
          // Start code block
          codeLanguage = line.slice(3).trim()
          inCodeBlock = true
        }
        continue
      }

      if (inCodeBlock) {
        codeContent.push(line)
        continue
      }

      // Empty line
      if (!line.trim()) {
        continue
      }

      // Headings
      if (line.startsWith('## ')) {
        elements.push(
          <h2 key={`h2-${i}`} className="text-2xl font-bold text-stone-900 font-outfit mt-12 mb-4 leading-tight">
            {line.slice(3)}
          </h2>
        )
        continue
      }

      if (line.startsWith('### ')) {
        elements.push(
          <h3 key={`h3-${i}`} className="text-xl font-bold text-stone-800 font-outfit mt-8 mb-3 leading-tight">
            {line.slice(4)}
          </h3>
        )
        continue
      }

      // Unordered lists
      if (line.startsWith('- ')) {
        elements.push(
          <li key={`li-${i}`} className="flex items-start gap-2 text-stone-600 ml-4">
            <span className="text-emerald-600 mt-1.5">•</span>
            <span>{line.slice(2)}</span>
          </li>
        )
        continue
      }

      // Numbered lists
      const numberedMatch = line.match(/^(\d+)\.\s/)
      if (numberedMatch) {
        elements.push(
          <li key={`oli-${i}`} className="flex items-start gap-2 text-stone-600 ml-4">
            <span className="text-emerald-600 font-semibold min-w-[1.5rem]">{numberedMatch[1]}.</span>
            <span>{line.slice(numberedMatch[0].length)}</span>
          </li>
        )
        continue
      }

      // Bold text with **
      let processedLine = line
      processedLine = processedLine.replace(/\*\*(.*?)\*\*/g, '<strong class="text-stone-900">$1</strong>')
      processedLine = processedLine.replace(/`([^`]+)`/g, '<code class="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700 font-mono">$1</code>')

      // Regular paragraph
      elements.push(
        <p
          key={`p-${i}`}
          className="text-stone-600 leading-relaxed mb-4"
          dangerouslySetInnerHTML={{ __html: processedLine }}
        />
      )
    }

    return elements
  }

  return (
    <>
      {/* ── POST HEADER ── */}
      <section className="relative pt-12 pb-8 border-b border-stone-200 bg-stone-950/60">
        <div className="max-w-4xl mx-auto px-4 md:px-8">
          {/* Back link */}
          <Link
            to="/blog"
            className="inline-flex items-center gap-2 text-sm text-stone-400 hover:text-emerald-400 transition mb-8"
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <line x1="19" y1="12" x2="5" y2="12" />
              <polyline points="12 19 5 12 12 5" />
            </svg>
            Back to Blog
          </Link>

          {/* Tags */}
          <div className="flex flex-wrap gap-2 mb-6">
            {post.tags.map((tag) => (
              <span
                key={tag}
                className="text-xs font-medium text-emerald-400 border border-emerald-900 bg-emerald-950/50 px-2 py-1"
              >
                {tag}
              </span>
            ))}
          </div>

          {/* Title */}
          <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold text-stone-50 leading-tight font-outfit mb-6">
            {post.title}
          </h1>

          {/* Meta */}
          <div className="flex flex-wrap items-center gap-6 text-sm text-stone-400">
            <span className="flex items-center gap-2">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
              {post.author}
            </span>
            <span className="flex items-center gap-2">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <rect x="3" y="4" width="18" height="18" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
              {new Date(post.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
              })}
            </span>
            <span className="flex items-center gap-2">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
              {post.readingTime}
            </span>
          </div>
        </div>
      </section>

      {/* ── POST CONTENT ── */}
      <section className="py-16 px-4 md:px-8 border-b border-stone-200">
        <div className="max-w-3xl mx-auto">
          {/* Lead paragraph */}
          <div className="text-lg md:text-xl text-stone-700 leading-relaxed mb-12 font-medium border-b border-stone-200 pb-8">
            {post.description}
          </div>
          
          {/* Content */}
          <div className="prose-headings:font-outfit prose-headings:text-stone-900 prose-a:text-emerald-700 prose-a:no-underline hover:prose-a:underline prose-strong:text-stone-900">
            {renderContent(post.content)}
          </div>
        </div>
      </section>

      {/* ── POST FOOTER / NAV ── */}
      <section className="py-12 px-4 md:px-8 border-b border-stone-200 bg-stone-50">
        <div className="max-w-4xl mx-auto flex items-center justify-between">
          <Link
            to="/blog"
            className="inline-flex items-center gap-2 text-stone-500 hover:text-emerald-700 transition font-medium"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <line x1="19" y1="12" x2="5" y2="12" />
              <polyline points="12 19 5 12 12 5" />
            </svg>
            All articles
          </Link>
          
          <Link
            to="/"
            className="inline-flex items-center gap-2 text-stone-500 hover:text-emerald-700 transition font-medium"
          >
            Home
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </Link>
        </div>
      </section>

      {/* ── FOOTER ── */}
      <footer className="pt-16 pb-10 px-4 md:px-12">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 pb-12">
          <div className="lg:col-span-2">
            <div className="flex items-center gap-2 text-emerald-900 font-bold text-xl mb-4 font-outfit">
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="text-emerald-600"
              >
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
              </svg>
              laraperf
            </div>
            <p className="text-stone-500 text-sm max-w-xs leading-relaxed">
              A{' '}
              <strong className="text-stone-700">
                Laravel performance CLI
              </strong>{' '}
              purpose-built for LLM coding agents. MIT License.
            </p>
            <div className="flex gap-4 mt-6 text-stone-400">
              <a
                href="https://github.com/mateffy/laraperf"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-emerald-600 transition"
              >
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                </svg>
              </a>
            </div>
          </div>
          <div>
            <h4 className="font-bold text-stone-900 mb-6 font-outfit">
              Package
            </h4>
            <ul className="space-y-3 text-sm text-stone-500">
              <li>
                <a
                  href="https://github.com/mateffy/laraperf"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  GitHub
                </a>
              </li>
              <li>
                <a
                  href="https://packagist.org/packages/mateffy/laraperf"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Packagist
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/mateffy/laraperf/blob/main/CHANGELOG.md"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Changelog
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/mateffy/laraperf/blob/main/LICENSE.md"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  License
                </a>
              </li>
            </ul>
          </div>
          <div>
            <h4 className="font-bold text-stone-900 mb-6 font-outfit">
              Commands
            </h4>
            <ul className="space-y-3 text-sm text-stone-500 font-mono">
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:watch
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:query
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:explain
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:stop
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:clear
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div className="border-t border-stone-200 pt-8 text-xs text-stone-400 flex flex-col md:flex-row items-center justify-between gap-4">
          <span>© 2026 laraperf · MIT License</span>
          <a
            href="https://github.com/mateffy/laraperf"
            target="_blank"
            rel="noopener noreferrer"
            className="text-stone-400 hover:text-emerald-700 transition"
          >
            mateffy/laraperf
          </a>
        </div>
      </footer>
    </>
  )
}
